<?php
require __DIR__ . '/_bootstrap.php';
$user = require_admin();
$flash = '';
if (is_post()) {
    $title = trim((string)($_POST['title'] ?? ''));
    $body = trim((string)($_POST['body'] ?? ''));
    $target = (string)($_POST['target'] ?? 'all');
    $identifier = trim((string)($_POST['identifier'] ?? ''));
    if ($title === '' || $body === '') {
        $flash = 'Title and message are required.';
    } elseif ($target === 'specific' && $identifier === '') {
        $flash = 'Enter a username or email for a specific user.';
    } else {
        if ($target === 'specific') {
            $targetUser = db_fetch_one('SELECT id, username FROM users WHERE username = ? OR email = ? LIMIT 1', 'ss', [$identifier, $identifier]);
            if (!$targetUser) {
                $flash = 'No matching user found.';
            } else {
                create_notification((int)$targetUser['id'], $title, $body, (int)$user['id']);
                log_admin_action((int)$user['id'], 'create_notification', 'user', (int)$targetUser['id'], $title);
                $flash = 'Notification sent to ' . (string)$targetUser['username'] . '.';
            }
        } else {
            $users = db_fetch_all('SELECT id FROM users ORDER BY id ASC');
            foreach ($users as $targetUser) create_notification((int)$targetUser['id'], $title, $body, (int)$user['id']);
            log_admin_action((int)$user['id'], 'broadcast_notification', 'notification', null, $title . ' · sent to ' . count($users) . ' users');
            $flash = 'Notification sent to ' . count($users) . ' users.';
        }
    }
}
$notifications = db_fetch_all('SELECT n.id, n.title, n.type, n.read_at, n.created_at, u.username, creator.username AS creator_username FROM notifications n INNER JOIN users u ON u.id = n.user_id LEFT JOIN users creator ON creator.id = n.created_by_user_id ORDER BY n.created_at DESC LIMIT 50');
admin_header('Notifications', $user, 'notifications');
?>
<?php if ($flash !== ''): ?><div class="alert alert-info"><?= e($flash) ?></div><?php endif; ?>
<div class="row g-4"><div class="col-lg-5"><form method="post" class="admin-card"><h2 class="h5 mb-3">Create system message</h2><div class="mb-3"><label class="form-label">Target</label><select name="target" class="form-select" id="targetSelect"><option value="all">Everybody</option><option value="specific">Specific user</option></select></div><div class="mb-3"><label class="form-label">Username or email</label><input class="form-control" name="identifier" placeholder="Only needed for a specific user"></div><div class="mb-3"><label class="form-label">Title</label><input class="form-control" name="title" maxlength="180" required></div><div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="body" rows="7" required></textarea></div><button class="btn btn-rook w-100" type="submit"><i class="fa-solid fa-paper-plane me-2"></i>Send notification</button></form></div><div class="col-lg-7"><div class="admin-card"><h2 class="h5 mb-3">Recent notifications</h2><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Title</th><th>User</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach ($notifications as $row): ?><tr><td><?= e((string)$row['title']) ?><div class="muted small">By <?= e((string)($row['creator_username'] ?? 'system')) ?></div></td><td><?= e((string)$row['username']) ?></td><td><?= empty($row['read_at']) ? 'Unread' : 'Read' ?></td><td><?= e(date('d M H:i', strtotime((string)$row['created_at']))) ?></td></tr><?php endforeach; ?><?php if (!$notifications): ?><tr><td colspan="4" class="muted">No notifications yet.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
<?php admin_footer(); ?>
