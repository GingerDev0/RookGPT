<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('members', 'Team members', 'Invite people and manage their permissions.');

if (!$activeTeam): ?>
  <div class="notice">Create a team before managing members.</div>
<?php else: ?>
  <div class="grid">
    <section class="panel">
      <div class="eyebrow">Members</div>
      <h2 style="font-weight:900;margin:6px 0 12px;"><?= e((string) $activeTeam['name']) ?></h2>
      <?php foreach ($teamMembers as $member): ?>
        <div class="member">
          <div class="member-head">
            <div>
              <strong><?= e((string) $member['username']) ?></strong>
              <div class="muted"><?= e((string) $member['email']) ?> · <?= e(ucfirst((string) $member['role'])) ?> · <?= e(plan_label((string) $member['plan'])) ?></div>
            </div>
            <?php if ($isTeamOwner && (($member['role'] ?? '') !== 'owner')): ?>
              <form method="post" action="/teams/members" data-confirm-title="Remove member?" data-confirm-message="This removes the user from the team and restores their pre-team plan." data-confirm-action="Remove member">
                <?= team_return_input('members') ?>
                <input type="hidden" name="team_member_id" value="<?= (int) $member['id'] ?>">
                <button class="btn-danger-soft" type="submit" name="remove_team_member" value="1"><i class="fa-solid fa-user-minus me-2"></i>Remove</button>
              </form>
            <?php endif; ?>
          </div>
          <form method="post" action="/teams/members" style="margin-top:12px;">
            <?= team_return_input('members') ?>
            <input type="hidden" name="team_member_id" value="<?= (int) $member['id'] ?>">
            <div class="field" style="max-width:220px;"><label>Role</label><select name="member_role" <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>><option value="admin" <?= (($member['role'] ?? '') === 'admin') ? 'selected' : '' ?>>Admin</option><option value="member" <?= (($member['role'] ?? '') === 'member') ? 'selected' : '' ?>>Member</option></select></div>
            <div class="checks">
              <label><input type="checkbox" name="can_read" value="1" <?= !empty($member['can_read']) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> Read-only access</label>
              <label><input type="checkbox" name="can_send_messages" value="1" <?= !empty($member['can_send_messages']) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> Message sending</label>
              <label><input type="checkbox" name="can_create_conversations" value="1" <?= !empty($member['can_create_conversations']) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> New conversations</label>
              <label><input type="checkbox" name="can_view_api_keys" value="1" <?= !empty($member['can_view_api_keys']) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> View/use keys</label>
              <label><input type="checkbox" name="can_manage_api_keys" value="1" <?= !empty($member['can_manage_api_keys']) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> Manage keys</label>
              <label><input type="checkbox" name="can_interact_with_bot" value="1" <?= ((int) ($member['can_interact_with_bot'] ?? 1) === 1) ? 'checked' : '' ?> <?= (!$isTeamOwner || (($member['role'] ?? '') === 'owner')) ? 'disabled' : '' ?>> Interact with bot</label>
            </div>
            <?php if ($isTeamOwner && (($member['role'] ?? '') !== 'owner')): ?><button class="btn-ghost" type="submit" name="update_team_member" value="1">Save member</button><?php endif; ?>
          </form>
        </div>
      <?php endforeach; ?>
    </section>
    <aside>
      <?php if ($isTeamOwner): ?>
        <div class="panel">
          <div class="eyebrow">Invite member</div>
          <p class="muted" style="margin-top:8px;">An invite notification is sent to the user. They must accept before joining.</p>
          <form method="post" action="/teams/members">
            <?= team_return_input('members') ?>
            <div class="field"><label for="member_identifier">Username or email</label><input id="member_identifier" name="member_identifier" type="text" required></div>
            <div class="field"><label for="member_role">Role</label><select id="member_role" name="member_role"><option value="member">Member</option><option value="admin">Admin</option></select></div>
            <button class="btn-rook w-100" type="submit" name="add_team_member" value="1"><i class="fa-solid fa-user-plus me-2"></i>Send invite</button>
          </form>
        </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif;
render_team_footer();
