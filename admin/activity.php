<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$page = page_num('page');
$offset = page_offset($page);
$totalLogs = (int)(db_fetch_one('SELECT COUNT(*) AS total FROM admin_activity_logs')['total'] ?? 0);
$logs = db_fetch_all('SELECT l.*, u.username FROM admin_activity_logs l LEFT JOIN users u ON u.id = l.admin_user_id ORDER BY l.created_at DESC LIMIT ? OFFSET ?', 'ii', [ADMIN_PAGE_SIZE, $offset]);
admin_header('Activity', $user, 'activity');
?>
<div class="admin-card"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3"><div><h2 class="h5 mb-1">Admin activity</h2><div class="muted">Recent admin actions now live on their own page instead of inside Users.</div></div><span class="pill"><?= number_format($totalLogs) ?> events</span></div><div class="admin-table-wrap"><table class="table align-middle"><thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Target</th><th>Details</th></tr></thead><tbody><?php foreach ($logs as $log): ?><tr><td><?= e(date('d M Y H:i', strtotime((string)$log['created_at']))) ?></td><td><?= e((string)($log['username'] ?? 'System')) ?></td><td><span class="pill"><?= e((string)$log['action']) ?></span></td><td><?= e((string)$log['target_type']) ?> #<?= e((string)($log['target_id'] ?? '')) ?></td><td class="muted"><?= e((string)($log['details'] ?? '')) ?></td></tr><?php endforeach; ?><?php if (!$logs): ?><tr><td colspan="5" class="muted text-center py-4">No activity yet.</td></tr><?php endif; ?></tbody></table></div><?= pagination_links('activity', 'page', $page, $totalLogs) ?></div>
<?php admin_footer(); ?>
