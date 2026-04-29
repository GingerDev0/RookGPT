<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$flash = $_SESSION['admin_flash'] ?? '';
$newAdminKey = $_SESSION['admin_new_key'] ?? '';
$error = '';
unset($_SESSION['admin_flash'], $_SESSION['admin_new_key']);

if (is_post()) {
    try {
        $adminUserId = (int)$user['id'];
        if (isset($_POST['create_user_key'])) {
            $targetId = (int)($_POST['user_id'] ?? 0);
            $name = trim((string)($_POST['key_name'] ?? 'Admin-created key'));
            $plain = generate_api_key_plaintext();
            ensure_api_key_preview_schema(); db_insert('INSERT INTO api_keys (user_id, name, key_hash, key_prefix, key_suffix, created_at) VALUES (?, ?, ?, ?, ?, NOW())', 'issss', [$targetId, mb_substr($name !== '' ? $name : 'Admin-created key', 0, 100), hash('sha256', $plain), api_key_prefix($plain), api_key_suffix($plain)]); $_SESSION['admin_flash'] = 'API key created. Full key shown once: ' . $plain;
            log_admin_action($adminUserId, 'create_api_key', 'user', $targetId, 'Created key named ' . $name);
            $_SESSION['admin_new_key'] = $plain;
            $_SESSION['admin_flash'] = 'API key created. Copy it now.';
            redirect_to('api-keys?' . http_build_query($_GET));
        }
        if (isset($_POST['toggle_key'])) {
            $keyId = (int)($_POST['key_id'] ?? 0);
            $enabled = ((string)($_POST['target_state'] ?? 'enable')) === 'enable';
            db_execute('UPDATE api_keys SET revoked_at = ' . ($enabled ? 'NULL' : 'NOW()') . ' WHERE id = ?', 'i', [$keyId]);
            log_admin_action($adminUserId, $enabled ? 'enable_api_key' : 'disable_api_key', 'api_key', $keyId);
            $_SESSION['admin_flash'] = 'API key state updated.';
            redirect_to('api-keys?' . http_build_query($_GET));
        }
        if (isset($_POST['delete_key'])) {
            $keyId = (int)($_POST['key_id'] ?? 0);
            db_execute('DELETE FROM api_keys WHERE id = ?', 'i', [$keyId]);
            log_admin_action($adminUserId, 'delete_api_key', 'api_key', $keyId);
            $_SESSION['admin_flash'] = 'API key deleted.';
            redirect_to('api-keys?' . http_build_query($_GET));
        }
    } catch (Throwable $e) {
        $error = 'API key action failed: ' . $e->getMessage();
    }
}

$page = page_num('page');
$offset = page_offset($page);
$filterUserId = max(0, (int)($_GET['user_id'] ?? 0));
$where = '';
$types = '';
$params = [];
if ($filterUserId > 0) {
    $where = ' WHERE ak.user_id = ?';
    $types = 'i';
    $params = [$filterUserId];
}
$totalKeys = (int)(db_fetch_one('SELECT COUNT(*) AS total FROM api_keys ak' . $where, $types, $params)['total'] ?? 0);
$keys = db_fetch_all(
    'SELECT ak.id, ak.user_id, ak.team_id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at,
            u.username, u.email, t.name AS team_name,
            COUNT(al.id) AS request_count,
            COALESCE(SUM(al.prompt_eval_count + al.eval_count), 0) AS token_count
     FROM api_keys ak
     INNER JOIN users u ON u.id = ak.user_id
     LEFT JOIN teams t ON t.id = ak.team_id
     LEFT JOIN api_logs al ON al.api_key_id = ak.id
     ' . $where . '
     GROUP BY ak.id, ak.user_id, ak.team_id, ak.name, ak.key_prefix, ak.key_suffix, ak.last_used_at, ak.revoked_at, ak.created_at, u.username, u.email, t.name
     ORDER BY ak.id DESC LIMIT ? OFFSET ?',
    $types . 'ii',
    array_merge($params, [ADMIN_PAGE_SIZE, $offset])
);
$selectedUser = $filterUserId > 0 ? db_fetch_one('SELECT id, username, email FROM users WHERE id = ? LIMIT 1', 'i', [$filterUserId]) : null;
$usersForSelect = db_fetch_all('SELECT id, username, email FROM users ORDER BY id DESC LIMIT 500');
$stats = db_fetch_one('SELECT (SELECT COUNT(*) FROM api_keys) AS keys_total, (SELECT COUNT(*) FROM api_keys WHERE revoked_at IS NULL) AS active_keys, (SELECT COUNT(*) FROM api_logs WHERE DATE(created_at) = CURDATE()) AS api_calls_today, COALESCE((SELECT SUM(prompt_eval_count + eval_count) FROM api_logs WHERE DATE(created_at) = CURDATE()), 0) AS eval_today') ?? [];
admin_header('API keys', $user, 'api-keys');
?>
<?php if ($flash !== ''): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($newAdminKey !== ''): ?><div class="alert alert-warning"><strong>New key:</strong> <code><?= e($newAdminKey) ?></code><div class="small">Copy this now. It may not be shown again.</div></div><?php endif; ?>
<div class="metric-grid mb-4">
  <div class="admin-card metric"><span class="muted">Total keys</span><strong><?= number_format((int)($stats['keys_total'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">Active keys</span><strong><?= number_format((int)($stats['active_keys'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">API calls today</span><strong><?= number_format((int)($stats['api_calls_today'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">Eval units today</span><strong><?= number_format((int)($stats['eval_today'] ?? 0)) ?></strong></div>
</div>
<div class="row g-4 mb-4">
  <div class="col-lg-5"><form method="post" action="api-keys?<?= e(http_build_query($_GET)) ?>" class="admin-card"><h2 class="h5 mb-3">Create user API key</h2><div class="mb-3"><label class="form-label">User</label><select class="form-select" name="user_id" required><?php foreach ($usersForSelect as $option): ?><option value="<?= (int)$option['id'] ?>" <?= $filterUserId === (int)$option['id'] ? 'selected' : '' ?>>#<?= (int)$option['id'] ?> · <?= e((string)$option['username']) ?> · <?= e((string)$option['email']) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label">Key name</label><input class="form-control" name="key_name" value="Admin-created key"></div><button class="btn btn-rook w-100" name="create_user_key" value="1" type="submit"><i class="fa-solid fa-plus me-2"></i>Create key</button></form></div>
  <div class="col-lg-7"><div class="admin-card"><h2 class="h5 mb-3">Filter</h2><form class="d-flex gap-2 flex-wrap" method="get" action="api-keys"><input class="form-control" style="max-width:220px" type="number" min="1" name="user_id" value="<?= $filterUserId > 0 ? (int)$filterUserId : '' ?>" placeholder="User ID"><button class="btn btn-outline-light" type="submit">Filter by user</button><?php if ($filterUserId > 0): ?><a class="btn btn-outline-light" href="api-keys">Clear</a><?php endif; ?></form><?php if ($selectedUser): ?><p class="muted mt-3 mb-0">Showing keys for <?= e((string)$selectedUser['username']) ?> / <?= e((string)$selectedUser['email']) ?>.</p><?php endif; ?></div></div>
</div>
<section class="admin-card">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h2 class="h5 mb-1">API keys</h2><div class="muted">Disable, re-enable, or delete keys across users and teams.</div></div><span class="pill"><?= number_format($totalKeys) ?> keys</span></div>
  <div class="admin-table-wrap"><table class="table align-middle"><thead><tr><th>Key</th><th>Owner</th><th>Status</th><th>Requests</th><th>Last used</th><th>Created</th><th class="text-end">Actions</th></tr></thead><tbody>
  <?php if ($keys === []): ?><tr><td colspan="7" class="text-center muted py-4">No API keys found.</td></tr><?php else: foreach ($keys as $key): $enabled = empty($key['revoked_at']); ?>
    <tr>
      <td><strong><?= e((string)$key['name']) ?></strong><div class="muted"><?= e(masked_api_key_from_parts($key['key_prefix'] ?? '', $key['key_suffix'] ?? '', (int)$key['id'])) ?></div></td>
      <td><strong><?= e((string)$key['username']) ?></strong><div class="muted"><?= e((string)$key['email']) ?><?= $key['team_name'] ? ' · Team: ' . e((string)$key['team_name']) : '' ?></div></td>
      <td><span><span class="status-dot <?= $enabled ? '' : 'off' ?>"></span><?= $enabled ? 'Enabled' : 'Disabled' ?></span></td>
      <td><?= number_format((int)$key['request_count']) ?><div class="muted"><?= number_format((int)$key['token_count']) ?> eval units</div></td>
      <td><?= $key['last_used_at'] ? e(date('d M Y H:i', strtotime((string)$key['last_used_at']))) : '<span class="muted">Never</span>' ?></td>
      <td><?= e(date('d M Y', strtotime((string)$key['created_at']))) ?></td>
      <td class="text-end"><div class="d-inline-flex gap-2 flex-wrap justify-content-end"><form method="post" action="api-keys?<?= e(http_build_query($_GET)) ?>"><input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>"><input type="hidden" name="target_state" value="<?= $enabled ? 'disable' : 'enable' ?>"><button class="btn btn-outline-light btn-sm" name="toggle_key" value="1" type="submit"><?= $enabled ? 'Disable' : 'Enable' ?></button></form><form method="post" action="api-keys?<?= e(http_build_query($_GET)) ?>" data-confirm-title="Delete API key?" data-confirm-message="This key will stop working immediately. Existing API logs will stay available for reporting." data-confirm-action="Delete key"><input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>"><button class="danger-btn btn btn-sm" name="delete_key" value="1" type="submit">Delete</button></form></div></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div>
  <?= pagination_links('api-keys', 'page', $page, $totalKeys, ['user_id' => $filterUserId ?: '']) ?>
</section>
<?php admin_footer(); ?>
