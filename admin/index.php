<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$metrics = [
  'Users' => (int) (db_fetch_one('SELECT COUNT(*) AS total FROM users')['total'] ?? 0),
  'Team-plan users' => (int) (db_fetch_one('SELECT COUNT(*) AS total FROM users WHERE plan <> "free" AND plan_billing_period = "team"')['total'] ?? 0),
  'Teams' => (int) (db_fetch_one('SELECT COUNT(*) AS total FROM teams')['total'] ?? 0),
  'Unread notifications' => (int) (db_fetch_one('SELECT COUNT(*) AS total FROM notifications WHERE read_at IS NULL')['total'] ?? 0),
];
$latest = db_fetch_all('SELECT n.id, n.title, n.type, n.created_at, u.username FROM notifications n INNER JOIN users u ON u.id = n.user_id ORDER BY n.created_at DESC LIMIT 8');
admin_header('Dashboard', $user, 'dashboard');
?>
<div class="metric-grid mb-4"><?php foreach ($metrics as $label => $value): ?><div class="admin-card metric"><span class="muted"><?= e($label) ?></span><strong><?= (int)$value ?></strong></div><?php endforeach; ?></div>
<div class="row g-4"><div class="col-lg-7"><div class="admin-card"><h2 class="h5 mb-3">Latest notifications</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Title</th><th>User</th><th>Type</th><th>Created</th></tr></thead><tbody><?php foreach ($latest as $row): ?><tr><td><?= e((string)$row['title']) ?></td><td><?= e((string)$row['username']) ?></td><td><?= e((string)$row['type']) ?></td><td><?= e(date('d M H:i', strtotime((string)$row['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$latest): ?><tr><td colspan="4" class="muted">No notifications yet.</td></tr><?php endif; ?></tbody></table></div></div></div><div class="col-lg-5"><div class="admin-card"><h2 class="h5 mb-3">Admin sections</h2><p class="muted">Create announcements, manage user plans/admins, API keys, notifications, and activity from separate pages.</p><div class="d-grid gap-2"><a class="btn btn-rook" href="notifications">Create notification</a><a class="btn btn-outline-light" href="users">Open users & plans</a><a class="btn btn-outline-light" href="api-keys">Manage API keys</a><a class="btn btn-outline-light" href="activity">View activity</a></div></div></div></div>
<?php admin_footer(); ?>
