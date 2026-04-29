<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../lib/install_guard.php';
require_once __DIR__ . '/../lib/security.php';
require_once __DIR__ . '/../lib/plans.php';
csrf_bootstrap_web();
date_default_timezone_set('Europe/London');

defined('ADMIN_PAGE_SIZE') || define('ADMIN_PAGE_SIZE', 10);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function db(): mysqli { static $db = null; if ($db instanceof mysqli) return $db; $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME); $db->set_charset('utf8mb4'); return $db; }
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }
function db_fetch_one(string $sql, string $types = '', array $params = []): ?array { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $row = $result ? $result->fetch_assoc() : null; $stmt->close(); return is_array($row) ? $row : null; }
function db_fetch_all(string $sql, string $types = '', array $params = []): array { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $result = $stmt->get_result(); $rows = []; if ($result) while ($row = $result->fetch_assoc()) $rows[] = $row; $stmt->close(); return $rows; }
function db_execute(string $sql, string $types = '', array $params = []): int { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $affected = $stmt->affected_rows; $stmt->close(); return $affected; }
function db_insert(string $sql, string $types = '', array $params = []): int { $stmt = db()->prepare($sql); if ($types !== '' && $params !== []) $stmt->bind_param($types, ...$params); $stmt->execute(); $id = (int) db()->insert_id; $stmt->close(); return $id; }
function redirect_to(string $path): never { header('Location: ' . $path); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }

function db_column_exists(string $table, string $column): bool { $row = db_fetch_one('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1', 'ss', [$table, $column]); return (int)($row['total'] ?? 0) > 0; }

function ensure_admin_schema(): void
{
    try {
        db()->query("CREATE TABLE IF NOT EXISTS admins (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          role ENUM('owner','admin','support') NOT NULL DEFAULT 'admin',
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_by_user_id INT UNSIGNED NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id), UNIQUE KEY uniq_admins_user_id (user_id), KEY idx_admins_active (is_active),
          CONSTRAINT fk_admins_user_dash FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->query("CREATE TABLE IF NOT EXISTS admin_activity_logs (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          admin_user_id INT UNSIGNED NULL,
          action VARCHAR(100) NOT NULL,
          target_type VARCHAR(50) NOT NULL,
          target_id BIGINT UNSIGNED NULL,
          details TEXT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id), KEY idx_admin_activity_admin_user_id (admin_user_id), KEY idx_admin_activity_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function ensure_user_admin_columns(): void
{
    try {
        try { db()->query("ALTER TABLE users MODIFY COLUMN plan VARCHAR(64) NOT NULL DEFAULT 'free'"); } catch (Throwable $e) {}
        if (!db_column_exists('users', 'plan_expires_at')) db()->query('ALTER TABLE users ADD COLUMN plan_expires_at DATETIME NULL AFTER plan');
        if (!db_column_exists('users', 'plan_billing_period')) db()->query("ALTER TABLE users ADD COLUMN plan_billing_period ENUM('monthly','annual','team','manual') NULL AFTER plan_expires_at");
        if (!db_column_exists('users', 'thinking_enabled')) db()->query('ALTER TABLE users ADD COLUMN thinking_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER plan_billing_period');
    } catch (Throwable $e) {}
}

function ensure_notifications_schema(): void
{
    try {
        db()->query("CREATE TABLE IF NOT EXISTS team_invites (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          team_id INT UNSIGNED NOT NULL,
          invited_user_id INT UNSIGNED NOT NULL,
          invited_by_user_id INT UNSIGNED NOT NULL,
          role ENUM('admin','member') NOT NULL DEFAULT 'member',
          can_read TINYINT(1) NOT NULL DEFAULT 1,
          can_send_messages TINYINT(1) NOT NULL DEFAULT 1,
          can_create_conversations TINYINT(1) NOT NULL DEFAULT 0,
          status ENUM('pending','accepted','declined','cancelled') NOT NULL DEFAULT 'pending',
          responded_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id), KEY idx_team_invites_user_status (invited_user_id, status), KEY idx_team_invites_team_user_status (team_id, invited_user_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        db()->query("CREATE TABLE IF NOT EXISTS notifications (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          user_id INT UNSIGNED NOT NULL,
          created_by_user_id INT UNSIGNED NULL,
          type VARCHAR(40) NOT NULL DEFAULT 'system',
          title VARCHAR(180) NOT NULL,
          body MEDIUMTEXT NOT NULL,
          action_url VARCHAR(255) NULL,
          related_team_invite_id BIGINT UNSIGNED NULL,
          read_at DATETIME NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id), KEY idx_notifications_user_read (user_id, read_at), KEY idx_notifications_created_at (created_at), KEY idx_notifications_invite (related_team_invite_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}


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
        try { db()->query("ALTER TABLE users MODIFY COLUMN plan VARCHAR(64) NOT NULL DEFAULT 'free'"); } catch (Throwable $e) {}
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
function is_admin_user(int $userId): bool { $row = db_fetch_one('SELECT id FROM admins WHERE user_id = ? AND is_active = 1 LIMIT 1', 'i', [$userId]); return (bool) $row; }
function bootstrap_first_admin(array $user): void { $row = db_fetch_one('SELECT COUNT(*) AS total FROM admins WHERE is_active = 1'); if ((int)($row['total'] ?? 0) === 0) db_insert('INSERT INTO admins (user_id, role, is_active, created_by_user_id) VALUES (?, "owner", 1, ?)', 'ii', [(int)$user['id'], (int)$user['id']]); }
function log_admin_action(int $adminUserId, string $action, string $targetType, ?int $targetId = null, string $details = ''): void { try { db_insert('INSERT INTO admin_activity_logs (admin_user_id, action, target_type, target_id, details) VALUES (?, ?, ?, ?, ?)', 'issis', [$adminUserId, $action, $targetType, $targetId, $details]); } catch (Throwable $e) {} }
function require_admin(): array { ensure_admin_schema(); ensure_notifications_schema(); ensure_user_admin_columns(); ensure_billing_schema(); $user = current_user(); if (!$user) redirect_to('../'); bootstrap_first_admin($user); if (!is_admin_user((int)$user['id'])) redirect_to('../'); return $user; }
function create_notification(int $userId, string $title, string $body, int $adminUserId): int { ensure_notifications_schema(); return db_insert('INSERT INTO notifications (user_id, created_by_user_id, type, title, body, created_at) VALUES (?, ?, "system", ?, ?, NOW())', 'iiss', [$userId, $adminUserId, $title, $body]); }
function page_num(string $key): int { return max(1, (int)($_GET[$key] ?? 1)); }
function page_offset(int $page): int { return max(0, ($page - 1) * ADMIN_PAGE_SIZE); }
function pagination_links(string $baseUrl, string $pageKey, int $page, int $total, array $extra = []): string { $pages = max(1, (int)ceil($total / ADMIN_PAGE_SIZE)); if ($pages <= 1) return ''; $start = max(1, $page - 2); $end = min($pages, $start + 4); $start = max(1, $end - 4); $html = '<nav class="d-flex gap-2 flex-wrap mt-3" aria-label="Pagination">'; for ($i = $start; $i <= $end; $i++) { $params = array_merge($extra, [$pageKey => $i]); $href = $baseUrl . ($params ? '?' . http_build_query($params) : ''); $class = $i === $page ? 'btn btn-rook btn-sm' : 'btn btn-outline-light btn-sm'; $html .= '<a class="' . $class . '" href="' . e($href) . '">' . $i . '</a>'; } return $html . '</nav>'; }
function plan_label(string $plan): string { return rook_plan_label($plan); }
function masked_key(?string $plain, int $id): string { $plain = trim((string)$plain); return ($plain !== '' && strlen($plain) >= 12) ? substr($plain, 0, 8) . '****' . substr($plain, -4) : 'key #' . $id; }
function generate_api_key_plaintext(): string { return 'rgpt_' . bin2hex(random_bytes(24)); }

function db_index_exists(string $table, string $index): bool { $row = db_fetch_one('SELECT COUNT(*) AS total FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1', 'ss', [$table, $index]); return (int)($row['total'] ?? 0) > 0; }

function ensure_billing_schema(): void
{
    try {
        db()->query("CREATE TABLE IF NOT EXISTS promo_codes (
          id INT UNSIGNED NOT NULL AUTO_INCREMENT,
          code VARCHAR(64) NOT NULL,
          description VARCHAR(255) NULL,
          discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
          discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          applies_to_plan VARCHAR(64) NOT NULL DEFAULT 'any',
          applies_to_period ENUM('any','monthly','annual') NOT NULL DEFAULT 'any',
          max_redemptions INT UNSIGNED NULL,
          redeemed_count INT UNSIGNED NOT NULL DEFAULT 0,
          starts_at DATETIME NULL,
          expires_at DATETIME NULL,
          is_active TINYINT(1) NOT NULL DEFAULT 1,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (id), UNIQUE KEY uniq_promo_codes_code (code), KEY idx_promo_codes_active (is_active), KEY idx_promo_codes_dates (starts_at, expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $columns = [
            'description' => "ALTER TABLE promo_codes ADD COLUMN description VARCHAR(255) NULL AFTER code",
            'discount_type' => "ALTER TABLE promo_codes ADD COLUMN discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent' AFTER description",
            'discount_value' => "ALTER TABLE promo_codes ADD COLUMN discount_value DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER discount_type",
            'applies_to_plan' => "ALTER TABLE promo_codes ADD COLUMN applies_to_plan VARCHAR(64) NOT NULL DEFAULT 'any' AFTER discount_value",
            'applies_to_period' => "ALTER TABLE promo_codes ADD COLUMN applies_to_period ENUM('any','monthly','annual') NOT NULL DEFAULT 'any' AFTER applies_to_plan",
            'max_redemptions' => "ALTER TABLE promo_codes ADD COLUMN max_redemptions INT UNSIGNED NULL AFTER applies_to_period",
            'redeemed_count' => "ALTER TABLE promo_codes ADD COLUMN redeemed_count INT UNSIGNED NOT NULL DEFAULT 0 AFTER max_redemptions",
            'starts_at' => "ALTER TABLE promo_codes ADD COLUMN starts_at DATETIME NULL AFTER redeemed_count",
            'expires_at' => "ALTER TABLE promo_codes ADD COLUMN expires_at DATETIME NULL AFTER starts_at",
            'is_active' => "ALTER TABLE promo_codes ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER expires_at",
            'created_at' => "ALTER TABLE promo_codes ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER is_active",
            'updated_at' => "ALTER TABLE promo_codes ADD COLUMN updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];
        foreach ($columns as $column => $sql) if (!db_column_exists('promo_codes', $column)) db()->query($sql);
        try { db()->query("ALTER TABLE promo_codes MODIFY COLUMN applies_to_plan VARCHAR(64) NOT NULL DEFAULT 'any'"); } catch (Throwable $e) {}
        if (!db_index_exists('promo_codes', 'uniq_promo_codes_code')) db()->query('ALTER TABLE promo_codes ADD UNIQUE KEY uniq_promo_codes_code (code)');
        db()->query("CREATE TABLE IF NOT EXISTS promo_code_redemptions (
          id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          promo_code_id INT UNSIGNED NOT NULL,
          user_id INT UNSIGNED NOT NULL,
          stripe_session_id VARCHAR(255) NOT NULL,
          created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (id), UNIQUE KEY uniq_promo_redemptions_session (stripe_session_id), KEY idx_promo_redemptions_promo_code_id (promo_code_id), KEY idx_promo_redemptions_user_id (user_id),
          CONSTRAINT fk_promo_redemptions_code FOREIGN KEY (promo_code_id) REFERENCES promo_codes (id) ON DELETE CASCADE,
          CONSTRAINT fk_promo_redemptions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

function admin_config_file(): string { return dirname(__DIR__) . '/config/app.php'; }
function admin_plan_price_defaults(): array { return array_map(fn($p) => (float)($p['price_gbp'] ?? 0), rook_default_plan_definitions()); }
function admin_plan_price(string $plan): float { return rook_plan_price_gbp($plan, 0); }
function admin_plan_definitions(): array { return rook_plan_definitions(); }
function admin_annual_discount_months(): int { return defined('ANNUAL_DISCOUNT_MONTHS') ? max(0, min(11, (int) ANNUAL_DISCOUNT_MONTHS)) : 2; }
function normalise_promo_code_admin(string $rawCode): string { return strtoupper(preg_replace('/[^A-Z0-9_-]/i', '', trim($rawCode)) ?? ''); }
function admin_current_config_values(array $overrides = []): array {
    return array_merge([
        'db_host' => DB_HOST, 'db_name' => DB_NAME, 'db_user' => DB_USER, 'db_pass' => DB_PASS,
        'ai_provider' => defined('AI_PROVIDER') ? AI_PROVIDER : 'ollama', 'ai_base_url' => defined('AI_BASE_URL') ? AI_BASE_URL : '', 'ai_model' => defined('AI_MODEL') ? AI_MODEL : '', 'ai_api_key' => defined('AI_API_KEY') ? AI_API_KEY : '',
        'stripe_secret_key' => defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '',
        'team_chat_encryption_key' => defined('TEAM_CHAT_ENCRYPTION_KEY') ? TEAM_CHAT_ENCRYPTION_KEY : bin2hex(random_bytes(32)),
        'teams_require_2fa' => defined('TEAMS_REQUIRE_2FA') && TEAMS_REQUIRE_2FA ? 1 : 0,
        'app_name' => APP_NAME, 'app_tagline' => defined('APP_TAGLINE') ? APP_TAGLINE : 'Professional AI assistant',
        'plan_definitions' => admin_plan_definitions(),
        'plan_plus_price_gbp' => admin_plan_price('plus'), 'plan_pro_price_gbp' => admin_plan_price('pro'), 'plan_business_price_gbp' => admin_plan_price('business'),
        'annual_discount_months' => admin_annual_discount_months(),
    ], $overrides);
}
function admin_config_contents(array $values): string {
    $export = fn($v) => var_export((string)$v, true);
    $exportBool = fn($v) => ((int)$v === 1 ? 'true' : 'false');
    $price = fn($v) => number_format(max(0, (float)$v), 2, '.', '');
    return "<?php\n" .
        "// Generated by RookGPT admin on " . date('c') . ".\n" .
        "defined('DB_HOST') || define('DB_HOST', " . $export($values['db_host'] ?? DB_HOST) . ");\n" .
        "defined('DB_NAME') || define('DB_NAME', " . $export($values['db_name'] ?? DB_NAME) . ");\n" .
        "defined('DB_USER') || define('DB_USER', " . $export($values['db_user'] ?? DB_USER) . ");\n" .
        "defined('DB_PASS') || define('DB_PASS', " . $export($values['db_pass'] ?? DB_PASS) . ");\n" .
        "defined('AI_PROVIDER') || define('AI_PROVIDER', " . $export($values['ai_provider'] ?? (defined('AI_PROVIDER') ? AI_PROVIDER : 'ollama')) . ");\n" .
        "defined('AI_BASE_URL') || define('AI_BASE_URL', " . $export($values['ai_base_url'] ?? (defined('AI_BASE_URL') ? AI_BASE_URL : '')) . ");\n" .
        "defined('AI_MODEL') || define('AI_MODEL', " . $export($values['ai_model'] ?? (defined('AI_MODEL') ? AI_MODEL : '')) . ");\n" .
        "defined('AI_API_KEY') || define('AI_API_KEY', " . $export($values['ai_api_key'] ?? (defined('AI_API_KEY') ? AI_API_KEY : '')) . ");\n" .
        "defined('OLLAMA_URL') || define('OLLAMA_URL', AI_BASE_URL);\n" .
        "defined('OLLAMA_MODEL') || define('OLLAMA_MODEL', AI_MODEL);\n" .
        "defined('STRIPE_SECRET_KEY') || define('STRIPE_SECRET_KEY', " . $export($values['stripe_secret_key'] ?? (defined('STRIPE_SECRET_KEY') ? STRIPE_SECRET_KEY : '')) . ");\n" .
        "defined('TEAM_CHAT_ENCRYPTION_KEY') || define('TEAM_CHAT_ENCRYPTION_KEY', " . $export($values['team_chat_encryption_key'] ?? (defined('TEAM_CHAT_ENCRYPTION_KEY') ? TEAM_CHAT_ENCRYPTION_KEY : bin2hex(random_bytes(32)))) . ");\n" .
        "defined('TEAMS_REQUIRE_2FA') || define('TEAMS_REQUIRE_2FA', " . $exportBool($values['teams_require_2fa'] ?? 1) . ");\n" .
        "defined('APP_NAME') || define('APP_NAME', " . $export($values['app_name'] ?? APP_NAME) . ");\n" .
        "defined('APP_TAGLINE') || define('APP_TAGLINE', " . $export($values['app_tagline'] ?? (defined('APP_TAGLINE') ? APP_TAGLINE : 'Professional AI assistant')) . ");\n" .
        "defined('PLAN_PLUS_PRICE_GBP') || define('PLAN_PLUS_PRICE_GBP', " . $price($values['plan_plus_price_gbp'] ?? admin_plan_price('plus')) . ");\n" .
        "defined('PLAN_PRO_PRICE_GBP') || define('PLAN_PRO_PRICE_GBP', " . $price($values['plan_pro_price_gbp'] ?? admin_plan_price('pro')) . ");\n" .
        "defined('PLAN_BUSINESS_PRICE_GBP') || define('PLAN_BUSINESS_PRICE_GBP', " . $price($values['plan_business_price_gbp'] ?? admin_plan_price('business')) . ");\n" .
        "defined('ANNUAL_DISCOUNT_MONTHS') || define('ANNUAL_DISCOUNT_MONTHS', " . (int)($values['annual_discount_months'] ?? admin_annual_discount_months()) . ");\n" .
        "defined('ROOK_PLAN_DEFINITIONS') || define('ROOK_PLAN_DEFINITIONS', " . var_export($values['plan_definitions'] ?? admin_plan_definitions(), true) . ");\n";
}
function admin_header(string $title, array $user, string $active = ''): void {
    $tabs = [
        ['./', 'dashboard', 'fa-chart-line', 'Dashboard'],
        ['notifications', 'notifications', 'fa-bell', 'Notifications'],
        ['users', 'users', 'fa-users-gear', 'Users & plans'],
        ['prices', 'prices', 'fa-sterling-sign', 'Prices'],
        ['promo', 'promo', 'fa-ticket', 'Promo codes'],
        ['api-keys', 'api-keys', 'fa-key', 'API keys'],
        ['activity', 'activity', 'fa-clock-rotate-left', 'Activity'],
        ['settings', 'settings', 'fa-gear', 'Settings'],
    ];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($title) ?> · <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="../rook.css">
  <style>
    body{font-family:Inter,system-ui,sans-serif}.admin-main{padding:24px}.admin-card{padding:20px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08)}.metric-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px}.metric strong{display:block;font-size:2rem;letter-spacing:-.06em}.table{--bs-table-bg:transparent;--bs-table-color:#eaf0ff;--bs-table-border-color:rgba(255,255,255,.08)}.form-control,.form-select{background:#08101d;border-color:rgba(255,255,255,.1);color:#fff}.form-control:focus,.form-select:focus{background:#08101d;color:#fff}.muted{color:#8fa0bd}.pill{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border:1px solid rgba(255,255,255,.1);background:rgba(255,255,255,.05);color:#dbe6ff;font-size:.82rem;font-weight:800}.status-dot{width:8px;height:8px;border-radius:50%;display:inline-block;background:#38d39f;margin-right:6px}.status-dot.off{background:#ff6b81}.inline-grid{display:grid;gap:8px;min-width:220px}.mini-actions{display:flex;gap:8px;flex-wrap:wrap}.user-cell strong{display:block}.user-cell span{display:block;color:#8fa0bd;font-size:.86rem}.admin-table-wrap{overflow-x:auto}.table td,.table th{vertical-align:middle}.danger-btn{background:rgba(255,107,129,.14);border:1px solid rgba(255,107,129,.26);color:#ffd4dc}.danger-btn:hover{background:rgba(255,107,129,.24);color:#fff}.team-subnav{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}.team-subnav a{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:#8fa0bd;border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.035);padding:10px 12px;font-weight:800}.team-subnav a.active,.team-subnav a:hover{color:#fff;border-color:rgba(124,156,255,.55);background:rgba(124,156,255,.14)}
  </style>
</head>
<body class="rook-body rook-app is-authenticated">
<div class="app">
  <aside class="sidebar">
    <div class="sidebar-top"><a href="/" class="brand"><span class="brand-mark"><i class="fa-solid fa-chess-rook"></i></span><span><h1><?= APP_NAME ?></h1><p>Admin workspace</p></span></a><div class="workspace-label">Workspace</div></div>
    <div class="sidebar-body">
      <a class="sidebar-link" href="/"><i class="fa-solid fa-message"></i> Chat workspace</a>
      <a class="sidebar-link" href="/api/"><i class="fa-solid fa-key"></i> API keys</a>
      <a class="sidebar-link" href="/teams/"><i class="fa-solid fa-users"></i> Teams</a>
      <a class="sidebar-link active" href="./"><i class="fa-solid fa-shield-halved"></i> Admin</a>
      <a class="sidebar-link" href="/upgrade"><i class="fa-solid fa-arrow-up-right-dots"></i> Upgrade</a>
      <div class="page-panel p-3 mt-auto"><div class="muted small">Signed in as</div><strong><?= e((string)$user['username']) ?></strong><div class="muted small"><?= e(plan_label((string)($user['plan'] ?? 'free'))) ?> plan</div></div>
    </div>
  </aside>
  <main class="main-panel">
    <header class="topbar">
      <div class="topbar-main"><div class="topbar-icon"><i class="fa-solid fa-shield-halved"></i></div><div class="topbar-title"><h2><?= e($title) ?></h2><p>Structured SaaS control room for RookGPT.</p></div></div>
      <div class="topbar-actions"><a class="ghost-btn" href="/"><i class="fa-solid fa-arrow-left"></i> Back to chat</a></div>
    </header>
    <div class="page-content admin-main">
      <nav class="team-subnav" aria-label="Admin sections"><?php foreach ($tabs as [$href, $key, $icon, $label]): ?><a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($href) ?>"><i class="fa-solid <?= e($icon) ?>"></i><?= e($label) ?></a><?php endforeach; ?></nav>
<?php }
function admin_footer(): void { ?></div></main></div>
<div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content" style="background:#0b1424;border:1px solid rgba(255,255,255,.12);color:#eaf0ff;border-radius:0;"><div class="modal-header" style="border-color:rgba(255,255,255,.08);"><h5 class="modal-title" id="adminConfirmTitle">Confirm action</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div><div class="modal-body" id="adminConfirmBody"></div><div class="modal-footer" style="border-color:rgba(255,255,255,.08);"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button type="button" class="danger-btn btn" id="adminConfirmSubmit">Confirm</button></div></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function(){
  let pendingForm = null;
  function modalInstance(){ const el = document.getElementById('adminConfirmModal'); return el && window.bootstrap ? bootstrap.Modal.getOrCreateInstance(el) : null; }
  document.addEventListener('submit', function(event){
    const form = event.target.closest('form[data-confirm-title], form[data-confirm-message]');
    if (!form || form.dataset.confirmed === '1') return;
    event.preventDefault();
    pendingForm = form;
    pendingForm._rookSubmitter = event.submitter || document.activeElement;
    const titleEl = document.getElementById('adminConfirmTitle');
    const bodyEl = document.getElementById('adminConfirmBody');
    const submit = document.getElementById('adminConfirmSubmit');
    if (titleEl) titleEl.textContent = form.dataset.confirmTitle || 'Confirm action';
    if (bodyEl) bodyEl.textContent = form.dataset.confirmMessage || 'Are you sure you want to continue?';
    if (submit) submit.textContent = form.dataset.confirmAction || 'Confirm';
    const modal = modalInstance();
    if (modal) modal.show();
  }, true);
  document.getElementById('adminConfirmSubmit')?.addEventListener('click', function(){
    if (!pendingForm) return;
    const submitter = pendingForm._rookSubmitter || pendingForm.querySelector('button[type=submit], input[type=submit]');
    if (submitter && submitter.name) {
      let hidden = pendingForm.querySelector('input[type=hidden][data-confirm-submitter="1"]');
      if (!hidden) { hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.dataset.confirmSubmitter = '1'; pendingForm.appendChild(hidden); }
      hidden.name = submitter.name;
      hidden.value = submitter.value || '1';
    }
    pendingForm.dataset.confirmed = '1';
    const modal = modalInstance();
    if (modal) modal.hide();
    if (pendingForm.requestSubmit) pendingForm.requestSubmit(); else pendingForm.submit();
  });
})();
</script></body></html><?php }


