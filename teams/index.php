<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('index', 'Teams', 'Overview, usage, and Business team workspace.');

if (!$activeTeam): ?>
  <div class="panel" style="max-width:760px;">
    <div class="eyebrow">Create team</div>
    <h2 style="font-weight:900;margin:8px 0 10px;">Spin up your Business team</h2>
    <p class="muted">Creating a team upgrades your account to Business automatically. Each account can only belong to one team at a time.</p>
    <form method="post" action="/teams/">
      <?= team_return_input('index') ?>
      <div class="field"><label for="team_name">Team name</label><input id="team_name" name="team_name" type="text" placeholder="e.g. GingerDev Ops" required></div>
      <button type="submit" name="create_team" value="1" class="btn-rook"><i class="fa-solid fa-users me-2"></i>Create team</button>
    </form>
  </div>
<?php else: ?>
  <div class="metrics">
    <div class="metric"><span>Members</span><strong><?= count($teamMembers) ?></strong></div>
    <div class="metric"><span>Conversations</span><strong><?= number_format($teamConversations) ?></strong></div>
    <div class="metric"><span>Messages</span><strong><?= number_format($teamMessages) ?></strong></div>
    <div class="metric"><span>API calls</span><strong><?= number_format($teamApiCalls) ?></strong></div>
  </div>
  <div class="grid">
    <section class="panel">
      <div class="member-head">
        <div>
          <div class="eyebrow">Current team</div>
          <h2 style="font-weight:900;margin:6px 0 0;"><?= e((string) $activeTeam['name']) ?></h2>
          <div class="muted">Team ID: <?= e((string) $activeTeam['token']) ?> · Created <?= e(date('j M Y', strtotime((string) $activeTeam['created_at']))) ?></div>
        </div>
      </div>
      <div class="metrics" style="grid-template-columns:repeat(3,minmax(0,1fr));">
        <div class="metric"><span>Your role</span><strong><?= e($isTeamOwner ? 'Owner' : ucfirst((string) ($activeMembership['role'] ?? 'Member'))) ?></strong></div>
        <div class="metric"><span>Shared chats</span><strong><?= count($teamSharedConversations) ?></strong></div>
        <div class="metric"><span>Active keys</span><strong><?= count($teamApiKeys) ?></strong></div>
      </div>
      <div class="team-subnav" style="margin-top:10px;">
        <a href="<?= e(team_page_url('members', $activeTeam)) ?>"><i class="fa-solid fa-user-group"></i>Manage members</a>
        <a href="<?= e(team_page_url('chat', $activeTeam)) ?>"><i class="fa-solid fa-message"></i>Team chat</a>
        <a href="<?= e(team_page_url('conversations', $activeTeam)) ?>"><i class="fa-solid fa-comments"></i>View conversations</a>
        <a href="<?= e(team_page_url('api-keys', $activeTeam)) ?>"><i class="fa-solid fa-key"></i>Team API keys</a>
        <a href="<?= e(team_page_url('activity', $activeTeam)) ?>"><i class="fa-solid fa-clock-rotate-left"></i>Recent changes</a>
      </div>
    </section>
    <aside>
      <?php if ($isTeamOwner): ?>
        <div class="panel">
          <div class="eyebrow">Quick invite</div>
          <p class="muted" style="margin-top:8px;">Team adds are now invites. The user must accept from their notification bell.</p>
          <form method="post" action="/teams/members">
            <?= team_return_input('members') ?>
            <div class="field"><label for="member_identifier">Username or email</label><input id="member_identifier" name="member_identifier" type="text" required></div>
            <div class="field"><label for="member_role">Role</label><select id="member_role" name="member_role"><option value="member">Member</option><option value="admin">Admin</option></select></div>
            <button class="btn-rook w-100" type="submit" name="add_team_member" value="1"><i class="fa-solid fa-user-plus me-2"></i>Send invite</button>
          </form>
        </div>
      <?php else: ?>
        <div class="panel">
          <div class="eyebrow">Team access</div>
          <p class="muted" style="margin-top:8px;">Open shared conversations from the team pages or the chat sidebar.</p>
          <a class="btn-ghost text-decoration-none w-100 text-center" href="<?= e(team_page_url('conversations', $activeTeam)) ?>">View shared conversations</a>
        </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif;
render_team_footer();
