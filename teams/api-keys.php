<?php
require_once __DIR__ . '/_bootstrap.php';
render_team_header('api-keys', 'Team API keys', 'Create, copy, rotate, and revoke global team keys.');

if (!$activeTeam): ?>
  <div class="notice">Create or join a team before using team API keys.</div>
<?php elseif (!$canViewTeamApiKeys): ?>
  <div class="panel"><div class="eyebrow">No key access</div><p class="muted" style="margin-top:8px;">You do not currently have permission to view or use team API keys.</p></div>
<?php else: ?>
  <div class="grid">
    <section class="panel">
      <div class="eyebrow">Active team keys</div>
      <?php if ($teamApiKeys === []): ?>
        <div class="notice" style="margin-top:12px;">No global team API keys yet.</div>
      <?php else: ?>
        <?php foreach ($teamApiKeys as $key): ?>
          <?php $plainKey = (string) ($key['plain_key'] ?? ''); $maskedKey = masked_api_key_from_parts($key['key_prefix'] ?? '', $key['key_suffix'] ?? '', (int)$key['id']); ?>
          <div class="member">
            <div class="member-head">
              <div>
                <strong><?= e((string) $key['name']) ?></strong>
                <div class="muted">Active · <?= number_format((int) $key['request_count']) ?> requests · <?= number_format((int) $key['token_count']) ?> tokens</div>
              </div>
              <?php if ($canManageTeamApiKeys): ?>
                <div class="key-actions">
                  <form method="post" action="/teams/api-keys" data-confirm-title="Rotate team API key?" data-confirm-message="The old key will stop working immediately. Any apps using it will need the new key." data-confirm-action="Rotate key">
                    <?= team_return_input('api-keys') ?>
                    <input type="hidden" name="team_api_key_id" value="<?= (int) $key['id'] ?>">
                    <button class="btn-ghost" type="submit" name="rotate_team_api_key" value="1"><i class="fa-solid fa-arrows-rotate me-2"></i>Rotate</button>
                  </form>
                  <form method="post" action="/teams/api-keys" data-confirm-title="Delete team API key?" data-confirm-message="This key will stop working immediately. Any apps using it will fail until they use another key." data-confirm-action="Delete key">
                    <?= team_return_input('api-keys') ?>
                    <input type="hidden" name="team_api_key_id" value="<?= (int) $key['id'] ?>">
                    <button class="btn-danger-soft" type="submit" name="delete_team_api_key" value="1"><i class="fa-solid fa-trash me-2"></i>Delete</button>
                  </form>
                </div>
              <?php endif; ?>
            </div>
            <div class="key-row">
              <code class="key-preview"><?= e($maskedKey) ?></code>
              <?php if ($canManageTeamApiKeys && $plainKey !== ''): ?><button class="btn-ghost copy-team-key" type="button" data-key="<?= e($plainKey) ?>"><i class="fa-regular fa-copy me-2"></i>Copy</button><?php elseif ($canManageTeamApiKeys): ?><button class="btn-ghost" type="button" disabled title="This key was created before encrypted key copying was available. Rotate it to generate a copyable key."><i class="fa-regular fa-copy me-2"></i>Rotate to copy</button><?php else: ?><button class="btn-ghost" type="button" disabled title="Only members with manage access can copy the full secret."><i class="fa-solid fa-eye-slash me-2"></i>Masked only</button><?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>
    <aside>
      <?php if ($canManageTeamApiKeys): ?>
        <div class="panel">
          <div class="eyebrow">Create key</div>
          <p class="muted" style="margin-top:8px;">Members with manage access can create, rotate, and delete keys.</p>
          <form method="post" action="/teams/api-keys">
            <?= team_return_input('api-keys') ?>
            <div class="field"><label for="team_api_key_name">Key name</label><input id="team_api_key_name" name="team_api_key_name" type="text" placeholder="Production team key"></div>
            <button class="btn-rook w-100" type="submit" name="create_team_api_key" value="1"><i class="fa-solid fa-key me-2"></i>Create team key</button>
          </form>
        </div>
      <?php endif; ?>
    </aside>
  </div>
<?php endif;
render_team_footer();
