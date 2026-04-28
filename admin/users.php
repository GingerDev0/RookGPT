<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$flash = $_SESSION['admin_flash'] ?? '';
$error = '';
unset($_SESSION['admin_flash']);

if (is_post()) {
    try {
        $adminUserId = (int)$user['id'];
        $targetId = (int)($_POST['user_id'] ?? 0);
        if (isset($_POST['update_user'])) {
            $plan = (string)($_POST['plan'] ?? 'free');
            if (!in_array($plan, ['free','plus','pro','business'], true)) $plan = 'free';
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
<section class="admin-card">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h2 class="h5 mb-1">Users & subscriptions</h2><div class="muted">Update plans, billing period, expiry, thinking, and admin access.</div></div><span class="pill"><?= number_format($totalUsers) ?> users</span></div>
  <div class="admin-table-wrap"><table class="table align-middle"><thead><tr><th>User</th><th>Current</th><th>Usage</th><th>Subscription</th><th>Admin</th><th>API keys</th></tr></thead><tbody>
  <?php if ($users === []): ?><tr><td colspan="6" class="text-center muted py-4">No users found.</td></tr><?php else: foreach ($users as $row): ?>
    <tr>
      <td class="user-cell"><strong>#<?= (int)$row['id'] ?> · <?= e((string)$row['username']) ?></strong><span><?= e((string)$row['email']) ?></span><span>Joined <?= e(date('d M Y', strtotime((string)$row['created_at']))) ?></span><span><?= !empty($row['two_factor_enabled_at']) ? '2FA enabled' : '2FA off' ?></span></td>
      <td><span class="pill"><?= e(plan_label((string)$row['plan'])) ?></span><div class="muted mt-2">Expires: <?= $row['plan_expires_at'] ? e(date('d M Y H:i', strtotime((string)$row['plan_expires_at']))) : 'Never' ?></div></td>
      <td><div><?= number_format((int)$row['conversation_count']) ?> chats</div><div class="muted"><?= number_format((int)$row['active_key_count']) ?> active / <?= number_format((int)$row['key_count']) ?> keys</div></td>
      <td><form method="post" action="users?<?= e(http_build_query($_GET)) ?>" class="inline-grid"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm" name="plan"><option value="free" <?= $row['plan']==='free'?'selected':'' ?>>Free</option><option value="plus" <?= $row['plan']==='plus'?'selected':'' ?>>Plus</option><option value="pro" <?= $row['plan']==='pro'?'selected':'' ?>>Pro</option><option value="business" <?= $row['plan']==='business'?'selected':'' ?>>Business</option></select><select class="form-select form-select-sm" name="plan_billing_period"><option value="" <?= empty($row['plan_billing_period'])?'selected':'' ?>>No period</option><option value="monthly" <?= $row['plan_billing_period']==='monthly'?'selected':'' ?>>Monthly</option><option value="annual" <?= $row['plan_billing_period']==='annual'?'selected':'' ?>>Annual</option><option value="team" <?= $row['plan_billing_period']==='team'?'selected':'' ?>>Team</option><option value="manual" <?= $row['plan_billing_period']==='manual'?'selected':'' ?>>Manual</option></select><input class="form-control form-control-sm" type="datetime-local" name="plan_expires_at" value="<?= $row['plan_expires_at'] ? e(date('Y-m-d\TH:i', strtotime((string)$row['plan_expires_at']))) : '' ?>"><label class="d-flex align-items-center gap-2 m-0"><input class="form-check-input m-0" type="checkbox" name="thinking_enabled" value="1" <?= !empty($row['thinking_enabled']) ? 'checked' : '' ?>> Think</label><button class="btn btn-rook btn-sm" name="update_user" value="1" type="submit">Save</button></form></td>
      <td><form method="post" action="users?<?= e(http_build_query($_GET)) ?>" class="inline-grid"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><select class="form-select form-select-sm" name="admin_role"><option value="support" <?= $row['admin_role']==='support'?'selected':'' ?>>Support</option><option value="admin" <?= $row['admin_role']==='admin'?'selected':'' ?>>Admin</option><option value="owner" <?= $row['admin_role']==='owner'?'selected':'' ?>>Owner</option></select><label class="d-flex align-items-center gap-2 m-0"><input class="form-check-input m-0" type="checkbox" name="admin_active" value="1" <?= !empty($row['admin_active']) ? 'checked' : '' ?>> Active</label><button class="btn btn-outline-light btn-sm" name="set_admin" value="1" type="submit">Apply</button></form></td>
      <td><a class="btn btn-outline-light btn-sm" href="api-keys?user_id=<?= (int)$row['id'] ?>"><i class="fa-solid fa-key me-1"></i> Manage keys</a></td>
    </tr>
  <?php endforeach; endif; ?>
  </tbody></table></div>
  <?= pagination_links('users', 'page', $usersPage, $totalUsers, ['q' => $userSearch]) ?>
</section>
<?php admin_footer(); ?>
