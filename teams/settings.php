<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('settings', 'Team settings', 'Manage team access and team lifecycle actions.');
?>

<?php if (!$activeTeam): ?>
  <div class="notice">Create or join a team before changing team settings.</div>
<?php else: ?>
  <div class="panel" style="max-width:760px;">
    <div class="eyebrow">Team settings</div>
    <h2 style="font-weight:900;margin:8px 0 10px;">Manage <?= e((string) $activeTeam['name']) ?></h2>
    <p class="muted">Use this page for team lifecycle actions. Bot name, prompt, mention trigger, and response behaviour now live in <a href="<?= e(team_page_url('bot-settings', $activeTeam)) ?>">Bot Settings</a>.</p>
  </div>

  <?php if ($isTeamOwner): ?>
    <div class="panel danger-zone" style="max-width:720px;margin-top:16px;">
      <div class="eyebrow" style="color:var(--danger);">Danger zone</div>
      <h2 style="font-weight:900;margin:8px 0 10px;">Delete <?= e((string) $activeTeam['name']) ?></h2>
      <p class="muted">Deleting the team removes every membership and restores each user's pre-team plan. This cannot be undone.</p>
      <form method="post" action="/teams/settings" data-confirm-title="Delete team?" data-confirm-message="This removes every team membership and restores each user's pre-team plan. This cannot be undone." data-confirm-action="Delete team">
        <?= team_return_input('settings') ?>
        <button class="btn-danger-soft" type="submit" name="delete_team" value="1"><i class="fa-solid fa-trash me-2"></i>Delete team</button>
      </form>
    </div>
  <?php else: ?>
    <div class="panel danger-zone" style="max-width:720px;margin-top:16px;">
      <div class="eyebrow" style="color:var(--danger);">Leave team</div>
      <h2 style="font-weight:900;margin:8px 0 10px;">Leave <?= e((string) $activeTeam['name']) ?></h2>
      <p class="muted">You will lose access to team conversations and team API keys. Your pre-team plan will be restored.</p>
      <form method="post" action="/teams/settings" data-confirm-title="Leave team?" data-confirm-message="You will lose access to team conversations and team API keys. Your pre-team plan will be restored." data-confirm-action="Leave team">
        <?= team_return_input('settings') ?>
        <button class="btn-danger-soft" type="submit" name="leave_team" value="1"><i class="fa-solid fa-right-from-bracket me-2"></i>Leave team</button>
      </form>
    </div>
  <?php endif; ?>
<?php endif; ?>
<?php render_team_footer(); ?>
