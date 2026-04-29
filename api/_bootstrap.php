<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../lib/install_guard.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/plans.php';
csrf_bootstrap_web();
date_default_timezone_set('Europe/London');

defined('DEFAULT_API_SYSTEM_PROMPT') || define('DEFAULT_API_SYSTEM_PROMPT', '');
defined('DEFAULT_API_TEMPERATURE') || define('DEFAULT_API_TEMPERATURE', 1.0);
defined('DEFAULT_API_TOP_P') || define('DEFAULT_API_TOP_P', 0.95);
defined('DEFAULT_API_TOP_K') || define('DEFAULT_API_TOP_K', 64);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli { static $db = null; if ($db instanceof mysqli) return $db; $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); $db->set_charset('utf8mb4'); return $db; }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function db_fetch_one(string $sql, string $types = '', array $params = []): ?array { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $row = $result ? $result->fetch_assoc() : null; $stmt->close(); return is_array($row) ? $row : null; }
function db_fetch_all(string $sql, string $types = '', array $params = []): array { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $rows = []; if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row; $stmt->close(); return $rows; }
function db_execute(string $sql, string $types = '', array $params = []): int { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); return $affected; }
function db_insert(string $sql, string $types = '', array $params = []): int { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $id = (int) db()->insert_id; $stmt->close(); return $id; }
function redirect_to(string $path): never { header('Location: ' . $path); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function api_teams_require_2fa(): bool { return defined('TEAMS_REQUIRE_2FA') ? (bool) TEAMS_REQUIRE_2FA : true; }

function db_column_exists_auth(string $table, string $column): bool
{
    try {
        $stmt = db()->prepare('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1');
        $stmt->bind_param('ss', $table, $column); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc(); $stmt->close(); return (int) ($row['total'] ?? 0) > 0;
    } catch (Throwable $e) { return false; }
}

function api_key_prefix(string $plain): string { if (str_starts_with($plain, 'rgpt_team_')) return 'rgpt_team_'; if (str_starts_with($plain, 'rgpt_')) return 'rgpt_'; if (str_starts_with($plain, 'rk_live_')) return 'rk_live_'; return substr($plain, 0, min(8, strlen($plain))); }
function api_key_suffix(string $plain): string { return substr($plain, -4); }
function masked_api_key_from_parts(?string $prefix, ?string $suffix, ?int $id = null): string { $prefix=trim((string)$prefix); $suffix=trim((string)$suffix); return ($prefix!=='' && $suffix!=='') ? $prefix.'****'.$suffix : 'Key #'.(int)$id; }
function ensure_api_key_preview_schema(): void { try { if (!db_column_exists_auth('api_keys','key_prefix')) db()->query("ALTER TABLE api_keys ADD COLUMN key_prefix VARCHAR(32) NULL AFTER key_hash"); if (!db_column_exists_auth('api_keys','key_suffix')) db()->query("ALTER TABLE api_keys ADD COLUMN key_suffix VARCHAR(16) NULL AFTER key_prefix"); if (db_column_exists_auth('api_keys','plain_key')) { db()->query("UPDATE api_keys SET key_prefix = CASE WHEN plain_key LIKE 'rgpt_team_%' THEN 'rgpt_team_' WHEN plain_key LIKE 'rgpt_%' THEN 'rgpt_' WHEN plain_key LIKE 'rk_live_%' THEN 'rk_live_' ELSE LEFT(plain_key, 8) END, key_suffix = RIGHT(plain_key, 4) WHERE plain_key IS NOT NULL AND plain_key != '' AND (key_prefix IS NULL OR key_suffix IS NULL)"); db()->query("UPDATE api_keys SET plain_key = NULL WHERE plain_key IS NOT NULL AND plain_key != ''"); } } catch (Throwable $e) {} }
function ensure_auth_security_schema(): void
{
    try {
        if (!db_column_exists_auth('users', 'current_session_token')) db()->query("ALTER TABLE users ADD COLUMN current_session_token VARCHAR(128) NULL AFTER custom_prompt");
        if (!db_column_exists_auth('users', 'session_rotated_at')) db()->query("ALTER TABLE users ADD COLUMN session_rotated_at DATETIME NULL AFTER current_session_token");
        if (!db_column_exists_auth('users', 'two_factor_secret')) db()->query("ALTER TABLE users ADD COLUMN two_factor_secret VARCHAR(64) NULL AFTER session_rotated_at");
        if (!db_column_exists_auth('users', 'two_factor_enabled_at')) db()->query("ALTER TABLE users ADD COLUMN two_factor_enabled_at DATETIME NULL AFTER two_factor_secret");
    } catch (Throwable $e) {}
}
function clear_local_session(?string $flash = null): void { $_SESSION=[]; session_destroy(); session_start(); if($flash) $_SESSION['flash']=$flash; }
function current_user(): ?array
{
    ensure_auth_security_schema();
    if (!isset($_SESSION['user_id'])) return null;
    $row = db_fetch_one('SELECT id, username, email, CASE WHEN plan_expires_at IS NOT NULL AND plan_expires_at < NOW() THEN "free" ELSE plan END AS plan, plan_expires_at, plan_billing_period, thinking_enabled, current_session_token, session_rotated_at, two_factor_secret, two_factor_enabled_at, created_at FROM users WHERE id = ? LIMIT 1', 'i', [(int) $_SESSION['user_id']]);
    if (!$row) { clear_local_session(); return null; }
    $sessionToken = (string) ($_SESSION['session_token'] ?? '');
    $currentToken = (string) ($row['current_session_token'] ?? '');
    if ($sessionToken === '' || $currentToken === '' || !hash_equals($currentToken, $sessionToken)) { clear_local_session('You were signed out because this account was used on another device.'); return null; }
    return $row;
}
function plan_limits(string $plan): array { return rook_plan_limits($plan); }
function app_base_url(): string { $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')); $scheme = ($https || $forwardedProto === 'https' || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443') ? 'https' : 'http'; $host = trim((string) ($_SERVER['HTTP_HOST'] ?? 'localhost')); return $scheme . '://' . $host; }
function generate_api_key_plaintext(): string { return 'rgpt_' . bin2hex(random_bytes(24)); }
function mask_api_key(?string $plain): string { $plain = trim((string)$plain); if ($plain === '') return 'Unavailable'; $tail = strlen($plain) >= 4 ? substr($plain, -4) : $plain; $prefix = str_starts_with($plain, 'rgpt_team_') ? 'rgpt_team_' : (str_starts_with($plain, 'rk_live_') ? 'rk_live_' : (str_starts_with($plain, 'rgpt_') ? 'rgpt_' : substr($plain, 0, min(8, strlen($plain))))); return $prefix . '****' . $tail; }
function create_api_key(int $userId, string $name): array { ensure_api_key_preview_schema(); $plain = generate_api_key_plaintext(); $hash = hash('sha256', $plain); $prefix = api_key_prefix($plain); $suffix = api_key_suffix($plain); $id = db_insert('INSERT INTO api_keys (user_id, name, key_hash, key_prefix, key_suffix, created_at) VALUES (?, ?, ?, ?, ?, NOW())', 'issss', [$userId, $name, $hash, $prefix, $suffix]); return ['id'=>$id, 'plain'=>$plain]; }
function fetch_api_keys(int $userId): array { return db_fetch_all('SELECT ak.id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at, COUNT(al.id) AS request_count, COALESCE(SUM(al.prompt_eval_count + al.eval_count), 0) AS token_count, MAX(al.created_at) AS last_request_at FROM api_keys ak LEFT JOIN api_logs al ON al.api_key_id = ak.id WHERE ak.user_id = ? AND ak.team_id IS NULL GROUP BY ak.id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at ORDER BY ak.id DESC', 'i', [$userId]); }
function fetch_playground_key_options(int $userId, string $createdKey = ''): array {
    ensure_api_key_preview_schema();
    $options = [];
    foreach (fetch_api_keys($userId) as $key) {
        if (!empty($key['revoked_at'])) continue;
        $options[] = [
            'label' => (string)($key['name'] ?? ('Key #' . (int)$key['id'])),
            'masked' => masked_api_key_from_parts($key['key_prefix'] ?? '', $key['key_suffix'] ?? '', (int)$key['id']),
            'value' => 'user:' . (int)$key['id'],
            'type' => 'member',
            'available' => true,
        ];
    }
    try {
        $teamKeys = db_fetch_all(
            'SELECT ak.id, ak.name, ak.key_prefix, ak.key_suffix, t.name AS team_name
             FROM api_keys ak
             INNER JOIN teams t ON t.id = ak.team_id
             INNER JOIN team_members tm ON tm.team_id = t.id AND tm.user_id = ?
             WHERE ak.team_id IS NOT NULL
               AND ak.revoked_at IS NULL
               AND (t.owner_user_id = ? OR tm.can_view_api_keys = 1 OR tm.can_manage_api_keys = 1)
             ORDER BY ak.id DESC',
            'ii',
            [$userId, $userId]
        );
    } catch (Throwable $e) {
        $teamKeys = [];
    }
    foreach ($teamKeys as $key) {
        $teamName = trim((string)($key['team_name'] ?? 'Team'));
        $keyName = trim((string)($key['name'] ?? ('Key #' . (int)$key['id'])));
        $options[] = [
            'label' => $teamName . ' · ' . $keyName,
            'masked' => masked_api_key_from_parts($key['key_prefix'] ?? '', $key['key_suffix'] ?? '', (int)$key['id']),
            'value' => 'team:' . (int)$key['id'],
            'type' => 'team',
            'available' => true,
        ];
    }
    return $options;
}
function update_api_key_state(int $userId, int $keyId, bool $enabled): void { db_execute('UPDATE api_keys SET revoked_at = ' . ($enabled ? 'NULL' : 'NOW()') . ' WHERE id = ? AND user_id = ?', 'ii', [$keyId, $userId]); }
function delete_api_key(int $userId, int $keyId): void { if ($keyId <= 0) return; db_execute('DELETE FROM api_logs WHERE api_key_id = ? AND user_id = ?', 'ii', [$keyId, $userId]); db_execute('DELETE FROM api_keys WHERE id = ? AND user_id = ? AND team_id IS NULL', 'ii', [$keyId, $userId]); }
function fetch_daily_usage(int $userId, int $days = 7): array { $rows = db_fetch_all('SELECT DATE(created_at) AS day, COUNT(*) AS requests, COALESCE(SUM(prompt_eval_count + eval_count), 0) AS tokens FROM api_logs WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY) GROUP BY DATE(created_at) ORDER BY day ASC', 'ii', [$userId, $days - 1]); $indexed = []; foreach ($rows as $row) $indexed[(string)$row['day']] = $row; $out = []; $start = new DateTimeImmutable('-' . ($days - 1) . ' days'); for ($i=0; $i<$days; $i++) { $day = $start->modify('+' . $i . ' days')->format('Y-m-d'); $row = $indexed[$day] ?? ['requests'=>0,'tokens'=>0]; $out[] = ['label'=>(new DateTimeImmutable($day))->format('d M'), 'requests'=>(int)($row['requests'] ?? 0), 'tokens'=>(int)($row['tokens'] ?? 0)]; } return $out; }
function fetch_usage_totals(int $userId): array { $row = db_fetch_one('SELECT COUNT(*) AS requests, COALESCE(SUM(prompt_eval_count + eval_count), 0) AS tokens, COALESCE(SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END), 0) AS failures FROM api_logs WHERE user_id = ?', 'i', [$userId]) ?? []; return ['requests'=>(int)($row['requests'] ?? 0), 'tokens'=>(int)($row['tokens'] ?? 0), 'failures'=>(int)($row['failures'] ?? 0)]; }
function fetch_today_api_call_count(int $userId): int { $row = db_fetch_one('SELECT COUNT(*) AS total FROM api_logs WHERE user_id = ? AND DATE(created_at) = CURDATE()', 'i', [$userId]); return (int)($row['total'] ?? 0); }
function is_admin_user(int $userId): bool { try { $row = db_fetch_one('SELECT id FROM admins WHERE user_id = ? AND is_active = 1 LIMIT 1', 'i', [$userId]); return (bool)$row; } catch (Throwable $e) { return false; } }
function require_api_user(): array { $user = current_user(); if (!$user) redirect_to('/'); $planInfo = plan_limits((string)($user['plan'] ?? 'free')); if (empty($planInfo['api_access'])) { $_SESSION['flash'] = 'API access is not available on your current plan.'; redirect_to('/'); } return [$user, $planInfo]; }
function api_stats(array $user): array { $keys = fetch_api_keys((int)$user['id']); $usageDaily = fetch_daily_usage((int)$user['id']); $usageTotals = fetch_usage_totals((int)$user['id']); $todayApiCalls = fetch_today_api_call_count((int)$user['id']); $activeKeyCount = 0; foreach ($keys as $key) if (empty($key['revoked_at'])) $activeKeyCount++; $chartKeyLabels = []; $chartKeyValues = []; foreach (array_slice($keys, 0, 6) as $key) { $chartKeyLabels[] = (string)$key['name']; $chartKeyValues[] = (int)$key['request_count']; } return compact('keys','usageDaily','usageTotals','todayApiCalls','activeKeyCount','chartKeyLabels','chartKeyValues'); }

function api_header(string $title, array $user, array $planInfo, string $active = 'overview', bool $charts = false): void { $tabs = [['/api/','overview','fa-chart-line','Overview'],['/api/keys','keys','fa-key','Keys'],['/api/playground','playground','fa-flask','Playground'],['/api/docs','docs','fa-book-open','Docs'],['/api/usage','usage','fa-chart-simple','Usage']]; ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · <?= APP_NAME ?></title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"><?php if ($charts): ?><script src="https://cdn.jsdelivr.net/npm/chart.js"></script><?php endif; ?><link rel="stylesheet" href="../rook.css"><style>body{font-family:Inter,system-ui,sans-serif}.api-main{padding:24px}.api-card{padding:20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);box-shadow:0 18px 40px rgba(0,0,0,.28)}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.metric strong{display:block;font-size:2rem;letter-spacing:-.06em}.table{--bs-table-bg:transparent;--bs-table-color:#eaf0ff;--bs-table-border-color:rgba(255,255,255,.08)}.form-control,.form-select{background:#08101d;border-color:rgba(255,255,255,.1);color:#fff}.form-control:focus,.form-select:focus{background:#08101d;color:#fff}.muted{color:#8fa0bd}.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#dbe6ff;font-size:.82rem;font-weight:800}.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:#38d39f;margin-right:6px}.status-dot.off{background:#ff6b81}.mini-actions{display:flex;gap:8px;flex-wrap:wrap}.api-subnav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.api-subnav a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:#8fa0bd;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.035);padding:10px 12px;font-weight:800}.api-subnav a.active,.api-subnav a:hover{color:#fff;border-color:rgba(124,156,255,.55);background:rgba(124,156,255,.14)}.key-preview,.code-box,pre.play-output{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;background:#07101e;border:1px solid rgba(255,255,255,.09);padding:14px;color:#dfe8ff;white-space:pre-wrap;overflow:auto}.chart-shell{height:320px}.danger-btn{background:rgba(255,107,129,.14);border:1px solid rgba(255,107,129,.26);color:#ffd4dc}.danger-btn:hover{background:rgba(255,107,129,.24);color:#fff}.docs-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px}.badge-soft{display:inline-flex;align-items:center;padding:6px 10px;border:1px solid rgba(124,156,255,.35);background:rgba(124,156,255,.12);color:#dfe8ff;font-weight:800;font-size:.82rem}.hero-grid{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.8fr);gap:16px}.playground-grid{display:grid;grid-template-columns:minmax(0,.9fr) minmax(0,1.1fr);gap:16px}@media(max-width:960px){.hero-grid,.playground-grid{grid-template-columns:1fr}}</style><link rel="stylesheet" href="/api/api.css"></head><body class="rook-body rook-app is-authenticated"><div class="app"><aside class="sidebar"><div class="sidebar-top"><a href="/" class="brand"><span class="brand-mark"><i class="fa-solid fa-chess-rook"></i></span><span><h1><?= APP_NAME ?></h1><p>Developer workspace</p></span></a><div class="workspace-label">Workspace</div></div><div class="sidebar-body"><a class="sidebar-link" href="/"><i class="fa-solid fa-message"></i> Chat workspace</a><a class="sidebar-link active" href="/api/"><i class="fa-solid fa-key"></i> API keys</a><a class="sidebar-link" href="/teams/"><i class="fa-solid fa-users"></i> Teams</a><?php if (is_admin_user((int)$user['id'])): ?><a class="sidebar-link" href="/admin/"><i class="fa-solid fa-shield-halved"></i> Admin</a><?php endif; ?><a class="sidebar-link" href="/upgrade"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade</a><div class="page-panel p-3 mt-auto"><div class="muted small">Signed in as</div><strong><?= e((string)$user['username']) ?></strong><div class="muted small"><?= e((string)$planInfo['label']) ?> plan</div></div></div></aside><main class="main-panel"><header class="topbar"><div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-key"></i></div><div class="topbar-title"><h2><?= e($title) ?></h2><p>Keys, usage, playground, and docs in one structured API workspace.</p></div></div><div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div></header><div class="page-content api-main"><nav class="api-subnav" aria-label="API sections"><?php foreach ($tabs as [$href,$key,$icon,$label]): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?></a><?php endforeach; ?></nav><?php }
function api_footer(): void { ?></div></main></div>
<div class="modal fade" id="apiMessageModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content api-modal-content"><div class="modal-header"><h5 class="modal-title" id="apiMessageTitle">Notice</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="apiMessageBody"></div><div class="modal-footer"><button type="button" class="btn btn-rook" data-bs-dismiss="modal">Close</button></div></div></div></div>
<div class="modal fade" id="apiConfirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content api-modal-content"><div class="modal-header"><h5 class="modal-title" id="apiConfirmTitle">Confirm action</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="apiConfirmBody"></div><div class="modal-footer"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn danger-btn" id="apiConfirmSubmit">Confirm</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  function modalInstance(id){ const el = document.getElementById(id); return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null; }
  window.showApiModal = function(title, message){ const titleEl = document.getElementById('apiMessageTitle'); const bodyEl = document.getElementById('apiMessageBody'); if (titleEl) titleEl.textContent = title || 'Notice'; if (bodyEl) bodyEl.textContent = message || ''; const modal = modalInstance('apiMessageModal'); if (modal) modal.show(); };
  async function copyText(text){
    if (!text) { window.showApiModal('Nothing to copy', 'There is no value available to copy.'); return false; }
    try { if (navigator.clipboard && window.isSecureContext) { await navigator.clipboard.writeText(text); return true; } } catch(e) {}
    const ta = document.createElement('textarea'); ta.value = text; ta.setAttribute('readonly',''); ta.style.position = 'fixed'; ta.style.left = '-9999px'; document.body.appendChild(ta); ta.select();
    try { const ok = document.execCommand('copy'); document.body.removeChild(ta); return ok; } catch(e) { document.body.removeChild(ta); return false; }
  }
  window.copyApiText = async function(text, button){ const ok = await copyText(text); if (ok) { const old = button ? button.innerHTML : ''; if (button) { button.innerHTML = '<i class="fa-solid fa-check me-2"></i>Copied'; setTimeout(() => { button.innerHTML = old; }, 1200); } } else { window.showApiModal('Copy failed', 'Your browser blocked clipboard access. Select the text manually and copy it.'); } };
  document.addEventListener('click', function(event){
    const copyBtn = event.target.closest('[data-copy-target], [data-copy-text], [data-copy-source]');
    if (copyBtn) {
      event.preventDefault();
      let text = copyBtn.dataset.copyText || '';
      if (!text && copyBtn.dataset.copyTarget) { const target = document.getElementById(copyBtn.dataset.copyTarget); text = target ? (target.value || target.innerText || target.textContent || '') : ''; }
      if (!text && copyBtn.dataset.copySource) { const source = document.getElementById(copyBtn.dataset.copySource); text = source ? (source.value || '') : ''; }
      window.copyApiText(text.trim(), copyBtn);
    }
  });
  let pendingForm = null;
  document.addEventListener('submit', function(event){
    const form = event.target.closest('form[data-confirm-title], form[data-confirm-message]');
    if (!form || form.dataset.confirmed === '1') return;
    event.preventDefault(); pendingForm = form; pendingForm._rookSubmitter = event.submitter || document.activeElement;
    const title = form.dataset.confirmTitle || 'Confirm action';
    const message = form.dataset.confirmMessage || 'Are you sure you want to continue?';
    const action = form.dataset.confirmAction || 'Confirm';
    const titleEl = document.getElementById('apiConfirmTitle'); const bodyEl = document.getElementById('apiConfirmBody'); const submit = document.getElementById('apiConfirmSubmit');
    if (titleEl) titleEl.textContent = title; if (bodyEl) bodyEl.textContent = message; if (submit) submit.textContent = action;
    const modal = modalInstance('apiConfirmModal'); if (modal) modal.show();
  }, true);
  document.getElementById('apiConfirmSubmit')?.addEventListener('click', function(){
    if (!pendingForm) return;
    const submitter = pendingForm._rookSubmitter || pendingForm.querySelector('button[type=submit], input[type=submit]');
    if (submitter && submitter.name) {
      let hidden = pendingForm.querySelector('input[type=hidden][data-confirm-submitter="1"]');
      if (!hidden) {
        hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.dataset.confirmSubmitter = '1';
        pendingForm.appendChild(hidden);
      }
      hidden.name = submitter.name;
      hidden.value = submitter.value || '1';
    }
    pendingForm.dataset.confirmed = '1';
    const modal = modalInstance('apiConfirmModal');
    if (modal) modal.hide();
    if (pendingForm.requestSubmit) pendingForm.requestSubmit(); else pendingForm.submit();
  });
})();
</script></body></html><?php }
function handle_key_post(array $user): void { if (!is_post()) return; if (isset($_POST['create_key'])) { $name = trim((string)($_POST['key_name'] ?? '')); if ($name === '') $name = 'Production key'; $newKey = create_api_key((int)$user['id'], mb_substr($name, 0, 100)); $_SESSION['api_plain_key'] = (string)$newKey['plain']; redirect_to('/api/keys'); } if (isset($_POST['toggle_key'])) { update_api_key_state((int)$user['id'], (int)($_POST['key_id'] ?? 0), ((string)($_POST['target_state'] ?? 'enable')) === 'enable'); $_SESSION['api_flash'] = 'API key state updated.'; redirect_to('/api/keys'); } if (isset($_POST['delete_key'])) { delete_api_key((int)$user['id'], (int)($_POST['key_id'] ?? 0)); $_SESSION['api_flash'] = 'API key deleted.'; redirect_to('/api/keys'); } }
