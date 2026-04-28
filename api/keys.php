<?php
require __DIR__ . '/_bootstrap.php';
[$user, $planInfo] = require_api_user();
handle_key_post($user);
$flash = (string)($_SESSION['api_flash'] ?? ''); unset($_SESSION['api_flash']);
$createdKey = (string)($_SESSION['api_plain_key'] ?? ''); unset($_SESSION['api_plain_key']);
$keys = fetch_api_keys((int)$user['id']);
api_header('API keys', $user, $planInfo, 'keys');
?>
<?php if ($flash !== ''): ?><div class="alert alert-success"><?= e($flash) ?></div><?php endif; ?>
<div class="hero-grid mb-4">
  <section class="api-card">
    <h3 class="h5">Create API key</h3>
    <p class="muted">Existing keys are masked. Copy new keys when they are created; they are only shown once.</p>
    <?php if ($createdKey !== ''): ?>
      <div class="key-preview mb-3" id="newKeyValue"><?= e($createdKey) ?></div>
      <button type="button" class="btn btn-outline-light" data-copy-target="newKeyValue"><i class="fa-regular fa-copy me-2"></i>Copy new key</button>
    <?php endif; ?>
  </section>
  <section class="api-card">
    <form method="post" class="d-grid gap-3">
      <label class="form-label">Key name</label>
      <input class="form-control" type="text" name="key_name" placeholder="Production app, staging bot, local testing">
      <button class="btn btn-rook" type="submit" name="create_key" value="1"><i class="fa-solid fa-plus me-2"></i>Create key</button>
    </form>
  </section>
</div>
<section class="api-card">
  <div class="d-flex justify-content-between gap-3 flex-wrap mb-3">
    <div><h3 class="h5 mb-1">Keys</h3><div class="muted">Delete, disable, re-enable, and check last activity.</div></div>
  </div>
  <div class="table-responsive">
    <table class="table align-middle">
      <thead><tr><th>Name</th><th>Masked key</th><th>Status</th><th>Requests</th><th>Eval units</th><th>Last used</th><th>Created</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
      <?php foreach ($keys as $key): $enabled = empty($key['revoked_at']); $masked = masked_api_key_from_parts($key['key_prefix'] ?? '', $key['key_suffix'] ?? '', (int)$key['id']); ?>
        <tr>
          <td><strong><?= e((string)$key['name']) ?></strong><div class="muted small">Key #<?= (int)$key['id'] ?></div></td>
          <td><code class="key-inline"><?= e($masked) ?></code></td>
          <td><span class="status-dot <?= $enabled ? '' : 'off' ?>"></span><?= $enabled ? 'Enabled' : 'Disabled' ?></td>
          <td><?= number_format((int)$key['request_count']) ?></td>
          <td><?= number_format((int)$key['token_count']) ?></td>
          <td><?= !empty($key['last_request_at']) ? e(date('d M Y H:i', strtotime((string)$key['last_request_at']))) : '<span class="muted">Never</span>' ?></td>
          <td><?= e(date('d M Y', strtotime((string)$key['created_at']))) ?></td>
          <td class="text-end">
            <div class="mini-actions justify-content-end">
              <form method="post">
                <input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>">
                <input type="hidden" name="target_state" value="<?= $enabled ? 'disable' : 'enable' ?>">
                <button class="btn btn-outline-light btn-sm" type="submit" name="toggle_key" value="1"><?= $enabled ? 'Disable' : 'Enable' ?></button>
              </form>
              <form method="post" data-confirm-title="Delete API key?" data-confirm-message="This key will stop working immediately. Any apps using it will fail until they use another key." data-confirm-action="Delete key">
                <input type="hidden" name="key_id" value="<?= (int)$key['id'] ?>">
                <button class="btn btn-sm danger-btn" type="submit" name="delete_key" value="1">Delete</button>
              </form>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$keys): ?><tr><td colspan="8" class="text-center muted">No API keys yet.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
</section>
<?php api_footer(); ?>
