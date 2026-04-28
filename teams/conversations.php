<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('conversations', 'Team conversations', 'Open, unshare, or delete conversations shared with this team.');

if (!$activeTeam): ?>
  <div class="notice">Create or join a team before viewing conversations.</div>
<?php else: ?>
  <section class="panel">
    <div class="eyebrow">Shared conversations</div>
    <?php if ($teamSharedConversations === []): ?>
      <div class="notice" style="margin-top:12px;">No team conversations have been shared yet.</div>
    <?php else: ?>
      <?php foreach ($teamSharedConversations as $chat): ?>
        <div class="member">
          <div class="member-head">
            <div>
              <strong><?= e((string) $chat['title']) ?></strong>
              <div class="muted">Owner: <?= e((string) $chat['owner_username']) ?> · <?= e(date('d M · H:i', strtotime((string) $chat['updated_at']))) ?></div>
            </div>
            <div class="key-actions">
              <a class="btn-ghost text-decoration-none" href="/?c=<?= urlencode((string) $chat['token']) ?>">Open</a>
              <?php if ($isTeamOwner): ?>
                <form method="post" action="/teams/conversations" data-confirm-title="Disable team sharing?" data-confirm-message="This conversation will move back to the owner's private conversations. Team members will no longer see it." data-confirm-action="Disable sharing">
                  <?= team_return_input('conversations') ?>
                  <input type="hidden" name="conversation_id" value="<?= (int) $chat['id'] ?>">
                  <button class="btn-ghost" type="submit" name="disable_shared_team_conversation" value="1"><i class="fa-solid fa-users-slash me-2"></i>Disable</button>
                </form>
                <form method="post" action="/teams/conversations" data-confirm-title="Delete shared conversation?" data-confirm-message="This permanently deletes the conversation and every message inside it. This cannot be undone." data-confirm-action="Delete conversation">
                  <?= team_return_input('conversations') ?>
                  <input type="hidden" name="conversation_id" value="<?= (int) $chat['id'] ?>">
                  <button class="btn-danger-soft" type="submit" name="delete_shared_team_conversation" value="1"><i class="fa-solid fa-trash me-2"></i>Delete</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
          <div class="muted" style="margin-top:8px;"><?= e((string) ($chat['last_message'] ?: 'No messages yet.')) ?></div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </section>
<?php endif;
render_team_footer();
