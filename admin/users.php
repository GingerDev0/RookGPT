<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin_role(['owner']);
$flash = $_SESSION['admin_flash'] ?? '';
$error = '';
unset($_SESSION['admin_flash']);

if (is_post()) {
    try {
        $adminUserId = (int)$user['id'];
        $targetId = (int)($_POST['user_id'] ?? 0);
        if (isset($_POST['update_user'])) {
            $plan = rook_plan_slug((string)($_POST['plan'] ?? 'free'));
            if (!array_key_exists($plan, rook_plan_definitions())) $plan = 'free';
            $period = trim((string)($_POST['plan_billing_period'] ?? ''));
            $periodValue = in_array($period, ['monthly','annual','team','manual'], true) ? $period : null;
            $expiresRaw = trim((string)($_POST['plan_expires_at'] ?? ''));
            $expiresValue = $expiresRaw !== '' ? date('Y-m-d H:i:s', strtotime($expiresRaw)) : null;
            $thinking = isset($_POST['thinking_enabled']) ? 1 : 0;
            db_execute('UPDATE users SET plan = ?, plan_billing_period = ?, plan_expires_at = ?, thinking_enabled = ? WHERE id = ?', 'sssii', [$plan, $periodValue, $expiresValue, $thinking, $targetId]);
            log_admin_action($adminUserId, 'update_user_subscription', 'user', $targetId, 'Plan: ' . $plan . ', period: ' . ($periodValue ?? 'none'));
            $_SESSION['admin_flash'] = 'User subscription updated.';
            redirect_to('users?' . http_build_query($_GET));
        }
        if (isset($_POST['set_admin'])) {
            $role = (string)($_POST['admin_role'] ?? 'admin');
            if (!in_array($role, ['owner','admin','support'], true)) $role = 'admin';
            $active = isset($_POST['admin_active']) ? 1 : 0;
            db_execute('INSERT INTO admins (user_id, role, is_active, created_by_user_id) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE role = VALUES(role), is_active = VALUES(is_active), updated_at = NOW()', 'isii', [$targetId, $role, $active, $adminUserId]);
            log_admin_action($adminUserId, 'set_admin_access', 'user', $targetId, 'Role: ' . $role . ', active: ' . $active);
            $_SESSION['admin_flash'] = 'Admin access updated.';
            redirect_to('users?' . http_build_query($_GET));
        }
    } catch (Throwable $e) {
        $error = 'Admin action failed: ' . $e->getMessage();
    }
}

$userSearch = trim((string)($_GET['q'] ?? ''));
$usersPage = page_num('page');
$usersOffset = page_offset($usersPage);
$userWhere = '';
$userTypes = '';
$userParams = [];
if ($userSearch !== '') {
    $userWhere = ' WHERE u.username LIKE ? OR u.email LIKE ? OR CAST(u.id AS CHAR) = ?';
    $needle = '%' . $userSearch . '%';
    $userTypes = 'sss';
    $userParams = [$needle, $needle, $userSearch];
}
$totalUsers = (int)(db_fetch_one('SELECT COUNT(*) AS total FROM users u' . $userWhere, $userTypes, $userParams)['total'] ?? 0);
$users = db_fetch_all(
    'SELECT u.id, u.username, u.email, u.plan, u.plan_expires_at, u.plan_billing_period, u.thinking_enabled, u.two_factor_enabled_at, u.created_at,
            a.role AS admin_role, a.is_active AS admin_active,
            COUNT(DISTINCT c.id) AS conversation_count,
            COUNT(DISTINCT ak.id) AS key_count,
            COUNT(DISTINCT CASE WHEN ak.revoked_at IS NULL THEN ak.id END) AS active_key_count
     FROM users u
     LEFT JOIN admins a ON a.user_id = u.id
     LEFT JOIN conversations c ON c.user_id = u.id
     LEFT JOIN api_keys ak ON ak.user_id = u.id
     ' . $userWhere . '
     GROUP BY u.id, u.username, u.email, u.plan, u.plan_expires_at, u.plan_billing_period, u.thinking_enabled, u.two_factor_enabled_at, u.created_at, a.role, a.is_active
     ORDER BY u.id DESC LIMIT ? OFFSET ?',
    $userTypes . 'ii',
    array_merge($userParams, [ADMIN_PAGE_SIZE, $usersOffset])
);
$stats = db_fetch_one('SELECT (SELECT COUNT(*) FROM users) AS users_total, (SELECT COUNT(*) FROM users WHERE plan <> "free" AND (plan_expires_at IS NULL OR plan_expires_at >= NOW())) AS paid_users, (SELECT COUNT(*) FROM admins WHERE is_active = 1) AS admins_total, (SELECT COUNT(*) FROM teams) AS teams_total') ?? [];
admin_header('Users & plans', $user, 'users');
?>
<?php if ($flash !== ''): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<div class="metric-grid mb-4">
  <div class="admin-card metric"><span class="muted">Users</span><strong><?= number_format((int)($stats['users_total'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">Paid users</span><strong><?= number_format((int)($stats['paid_users'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">Admins</span><strong><?= number_format((int)($stats['admins_total'] ?? 0)) ?></strong></div>
  <div class="admin-card metric"><span class="muted">Teams</span><strong><?= number_format((int)($stats['teams_total'] ?? 0)) ?></strong></div>
</div>
<div class="admin-card mb-4">
  <form class="d-flex gap-2 flex-wrap" method="get" action="users">
    <input class="form-control" style="max-width:380px" type="search" name="q" value="<?= e($userSearch) ?>" placeholder="Search user, email, or ID">
    <button class="btn btn-rook" type="submit"><i class="fa-solid fa-search me-2"></i>Search users</button>
    <?php if ($userSearch !== ''): ?><a class="btn btn-outline-light" href="users">Clear</a><?php endif; ?>
  </form>
</div>
<style>
  .user-settings-summary{display:grid;gap:6px;min-width:180px}.user-settings-summary strong{font-size:.95rem}.user-settings-actions{display:flex;gap:8px;flex-wrap:wrap}.admin-modal-content{background:#0b1424;border:1px solid rgba(255,255,255,.12);color:#eaf0ff;border-radius:0;box-shadow:0 24px 80px rgba(0,0,0,.45)}.admin-modal-content .modal-header,.admin-modal-content .modal-footer{border-color:rgba(255,255,255,.08)}.settings-modal-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}.settings-modal-panel{border:1px solid rgba(255,255,255,.08);background:rgba(255,255,255,.035);border-radius:0;padding:16px}.settings-modal-panel h3{font-size:1rem;margin:0 0 12px}.settings-form-grid{display:grid;gap:10px}.settings-meta{display:grid;gap:6px;margin-top:12px;color:#8fa0bd;font-size:.9rem}.settings-modal-user{display:flex;align-items:flex-start;gap:12px}.settings-modal-avatar{width:42px;height:42px;border-radius:0;display:grid;place-items:center;background:linear-gradient(135deg,rgba(124,156,255,.35),rgba(56,211,159,.18));border:1px solid rgba(255,255,255,.1);font-weight:900}.settings-modal-user strong,.settings-modal-user span{display:block}.settings-modal-user span{color:#8fa0bd;font-size:.9rem}
</style>
<section class="admin-card">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h2 class="h5 mb-1">Users & subscriptions</h2><div class="muted">Review users quickly, then open a clean settings modal to update plans, thinking, and admin access.</div></div><span class="pill"><?= number_format($totalUsers) ?> users</span></div>
  <div class="admin-table-wrap"><table class="table align-middle"><thead><tr><th>User</th><th>Current plan</th><th>Usage</th><th>Security & access</th><th>Actions</th></tr></thead><tbody>
  <?php if ($users === []): ?><tr><td colspan="5" class="text-center muted py-4">No users found.</td></tr><?php else: foreach ($users as $row): ?>
    <?php
      $modalId = 'userSettingsModal' . (int)$row['id'];
      $expiresLabel = $row['plan_expires_at'] ? date('d M Y H:i', strtotime((string)$row['plan_expires_at'])) : 'Never';
      $periodLabel = $row['plan_billing_period'] ? ucfirst((string)$row['plan_billing_period']) : 'No period';
      $adminLabel = !empty($row['admin_role']) ? ucfirst((string)$row['admin_role']) . (!empty($row['admin_active']) ? ' active' : ' inactive') : 'No admin access';
    ?>
    <tr>
      <td class="user-cell"><strong>#<?= (int)$row['id'] ?> · <?= e((string)$row['username']) ?></strong><span><?= e((string)$row['email']) ?></span><span>Joined <?= e(date('d M Y', strtotime((string)$row['created_at']))) ?></span></td>
      <td><div class="user-settings-summary"><strong><span class="pill"><?= e(plan_label((string)$row['plan'])) ?></span></strong><span class="muted"><?= e($periodLabel) ?></span><span class="muted">Expires: <?= e($expiresLabel) ?></span></div></td>
      <td><div><?= number_format((int)$row['conversation_count']) ?> chats</div><div class="muted"><?= number_format((int)$row['active_key_count']) ?> active / <?= number_format((int)$row['key_count']) ?> keys</div></td>
      <td><div><?= !empty($row['two_factor_enabled_at']) ? '<span class="status-dot"></span>2FA enabled' : '<span class="status-dot off"></span>2FA off' ?></div><div class="muted mt-1"><?= e($adminLabel) ?></div><div class="muted mt-1">Thinking: <?= !empty($row['thinking_enabled']) ? 'On' : 'Off' ?></div></td>
      <td><div class="user-settings-actions"><button class="btn btn-rook btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#<?= e($modalId) ?>"><i class="fa-solid fa-sliders me-1"></i>Edit settings</button><a class="btn btn-outline-light btn-sm" href="api-keys?user_id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-key me-1"></i>Keys</a></div></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div>
  <?= pagination_links('users', 'page', $usersPage, $totalUsers, ['q' => $userSearch]) ?>
</section>

<?php foreach ($users as $row): ?>
  <?php
    $modalId = 'userSettingsModal' . (int)$row['id'];
    $expiresValue = $row['plan_expires_at'] ? date('Y-m-d\TH:i', strtotime((string)$row['plan_expires_at'])) : '';
  ?>
  <div class="modal fade" id="<?= e($modalId) ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content admin-modal-content">
        <div class="modal-header">
          <div class="settings-modal-user">
            <div class="settings-modal-avatar"><?= e(strtoupper(substr((string)$row['username'], 0, 1) ?: 'U')) ?></div>
            <div><h5 class="modal-title mb-0">Edit <?= e((string)$row['username']) ?></h5><span>#<?= (int)$row['id'] ?> · <?= e((string)$row['email']) ?></span></div>
          </div>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="settings-modal-grid">
            <form method="post" action="users?<?= e(http_build_query($_GET)) ?>" class="settings-modal-panel settings-form-grid">
              <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
              <h3><i class="fa-solid fa-credit-card me-2"></i>Subscription</h3>
              <label class="form-label mb-0">Plan</label>
              <select class="form-select" name="plan">
                <?php foreach (rook_plan_definitions() as $planSlug => $planDef): ?>
                  <option value="<?= e($planSlug) ?>" <?= $row['plan']===$planSlug?'selected':'' ?>><?= e((string)$planDef['label']) ?><?= empty($planDef['enabled']) ? ' (disabled)' : '' ?></option>
                <?php endforeach; ?>
              </select>
              <label class="form-label mb-0">Billing period</label>
              <select class="form-select" name="plan_billing_period">
                <option value="" <?= empty($row['plan_billing_period'])?'selected':'' ?>>No period</option>
                <option value="monthly" <?= $row['plan_billing_period']==='monthly'?'selected':'' ?>>Monthly</option>
                <option value="annual" <?= $row['plan_billing_period']==='annual'?'selected':'' ?>>Annual</option>
                <option value="team" <?= $row['plan_billing_period']==='team'?'selected':'' ?>>Team</option>
                <option value="manual" <?= $row['plan_billing_period']==='manual'?'selected':'' ?>>Manual</option>
              </select>
              <label class="form-label mb-0">Expires at</label>
              <input class="form-control" type="datetime-local" name="plan_expires_at" value="<?= e($expiresValue) ?>">
              <label class="d-flex align-items-center gap-2 m-0 mt-1"><input class="form-check-input m-0" type="checkbox" name="thinking_enabled" value="1" <?= !empty($row['thinking_enabled']) ? 'checked' : '' ?>> Thinking enabled</label>
              <button class="btn btn-rook mt-2" name="update_user" value="1" type="submit"><i class="fa-solid fa-floppy-disk me-2"></i>Save subscription</button>
            </form>

            <form method="post" action="users?<?= e(http_build_query($_GET)) ?>" class="settings-modal-panel settings-form-grid">
              <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
              <h3><i class="fa-solid fa-user-shield me-2"></i>Admin access</h3>
              <label class="form-label mb-0">Role</label>
              <select class="form-select" name="admin_role">
                <option value="support" <?= $row['admin_role']==='support'?'selected':'' ?>>Support</option>
                <option value="admin" <?= $row['admin_role']==='admin'?'selected':'' ?>>Admin</option>
                <option value="owner" <?= $row['admin_role']==='owner'?'selected':'' ?>>Owner</option>
              </select>
              <label class="d-flex align-items-center gap-2 m-0"><input class="form-check-input m-0" type="checkbox" name="admin_active" value="1" <?= !empty($row['admin_active']) ? 'checked' : '' ?>> Admin access active</label>
              <div class="settings-meta">
                <span><?= !empty($row['two_factor_enabled_at']) ? '2FA enabled' : '2FA not enabled' ?></span>
                <span><?= number_format((int)$row['conversation_count']) ?> chats</span>
                <span><?= number_format((int)$row['active_key_count']) ?> active API keys</span>
              </div>
              <button class="btn btn-outline-light mt-2" name="set_admin" value="1" type="submit"><i class="fa-solid fa-shield-halved me-2"></i>Apply admin access</button>
            </form>
          </div>
        </div>
        <div class="modal-footer">
          <a class="btn btn-outline-light me-auto" href="api-keys?user_id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-key me-1"></i>Manage API keys</a>
          <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php admin_footer(); ?>
