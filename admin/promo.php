<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_admin_role(['owner']);
$messages = [];
$errors = [];

function promo_datetime_or_null(string $value): ?string {
    $value = trim($value);
    if ($value === '') return null;
    $ts = strtotime($value);
    return $ts ? date('Y-m-d H:i:s', $ts) : null;
}
function promo_datetime_input(?string $value): string {
    if (!$value) return '';
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
}

if (is_post()) {
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $code = normalise_promo_code_admin((string) ($_POST['code'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $discountType = (string) ($_POST['discount_type'] ?? 'percent');
        $discountValue = (float) ($_POST['discount_value'] ?? 0);
        $appliesToPlan = (string) ($_POST['applies_to_plan'] ?? 'any');
        $appliesToPeriod = (string) ($_POST['applies_to_period'] ?? 'any');
        $maxRaw = trim((string) ($_POST['max_redemptions'] ?? ''));
        $maxRedemptions = $maxRaw === '' ? null : max(0, (int) $maxRaw);
        $startsAt = promo_datetime_or_null((string) ($_POST['starts_at'] ?? ''));
        $expiresAt = promo_datetime_or_null((string) ($_POST['expires_at'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;
        if ($code === '') $errors[] = 'Enter a promo code.';
        if (!in_array($discountType, ['percent','fixed'], true)) $errors[] = 'Choose a valid discount type.';
        if ($discountValue <= 0) $errors[] = 'Discount value must be greater than zero.';
        if ($discountType === 'percent' && $discountValue > 100) $errors[] = 'Percentage discounts cannot exceed 100%.';
        if ($appliesToPlan !== 'any' && !array_key_exists($appliesToPlan, rook_plan_definitions())) $errors[] = 'Choose a valid plan scope.';
        if (!in_array($appliesToPeriod, ['any','monthly','annual'], true)) $errors[] = 'Choose a valid billing scope.';
        if (!$errors) {
            try {
                if ($action === 'update' && $id > 0) {
                    db_execute('UPDATE promo_codes SET code = ?, description = ?, discount_type = ?, discount_value = ?, applies_to_plan = ?, applies_to_period = ?, max_redemptions = ?, starts_at = ?, expires_at = ?, is_active = ? WHERE id = ?', 'sssdssissii', [$code, $description, $discountType, $discountValue, $appliesToPlan, $appliesToPeriod, $maxRedemptions, $startsAt, $expiresAt, $isActive, $id]);
                    $messages[] = 'Promo code updated.';
                    log_admin_action((int)$user['id'], 'promo.update', 'promo_code', $id, 'Updated promo code ' . $code);
                } else {
                    $newId = db_insert('INSERT INTO promo_codes (code, description, discount_type, discount_value, applies_to_plan, applies_to_period, max_redemptions, starts_at, expires_at, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', 'sssdssissi', [$code, $description, $discountType, $discountValue, $appliesToPlan, $appliesToPeriod, $maxRedemptions, $startsAt, $expiresAt, $isActive]);
                    $messages[] = 'Promo code created.';
                    log_admin_action((int)$user['id'], 'promo.create', 'promo_code', $newId, 'Created promo code ' . $code);
                }
            } catch (Throwable $e) { $errors[] = 'Could not save promo code. It may already exist.'; }
        }
    } elseif ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = db_fetch_one('SELECT code, is_active FROM promo_codes WHERE id = ? LIMIT 1', 'i', [$id]);
        if ($row) { $next = (int) !$row['is_active']; db_execute('UPDATE promo_codes SET is_active = ? WHERE id = ?', 'ii', [$next, $id]); $messages[] = $next ? 'Promo code enabled.' : 'Promo code disabled.'; log_admin_action((int)$user['id'], 'promo.toggle', 'promo_code', $id, ($next ? 'Enabled ' : 'Disabled ') . (string)$row['code']); }
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $row = db_fetch_one('SELECT code FROM promo_codes WHERE id = ? LIMIT 1', 'i', [$id]);
        if ($row) { db_execute('DELETE FROM promo_codes WHERE id = ?', 'i', [$id]); $messages[] = 'Promo code deleted.'; log_admin_action((int)$user['id'], 'promo.delete', 'promo_code', $id, 'Deleted promo code ' . (string)$row['code']); }
    }
}

$page = page_num('p');
$total = (int) ((db_fetch_one('SELECT COUNT(*) AS total FROM promo_codes')['total'] ?? 0));
$codes = db_fetch_all('SELECT * FROM promo_codes ORDER BY created_at DESC LIMIT ? OFFSET ?', 'ii', [ADMIN_PAGE_SIZE, page_offset($page)]);
$editId = max(0, (int) ($_GET['edit'] ?? 0));
$editCode = $editId > 0 ? db_fetch_one('SELECT * FROM promo_codes WHERE id = ? LIMIT 1', 'i', [$editId]) : null;
$planOptions = ['any' => 'Any plan']; foreach (rook_plan_definitions() as $planSlug => $planDef) { if ($planSlug !== 'free') $planOptions[$planSlug] = (string)$planDef['label']; }
$periodOptions = ['any' => 'Any billing', 'monthly' => 'Monthly', 'annual' => 'Annual'];
admin_header('Promo codes', $user, 'promo');
?>
<div class="admin-card mb-3"><h1 class="h3 mb-1">Promo codes</h1><p class="text-white-50 mb-0">Create and manage local promo codes for the upgrade checkout.</p></div>
<?php foreach ($messages as $msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endforeach; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($editCode): ?>
<form method="post" class="admin-card mb-3 vstack gap-3">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$editCode['id'] ?>">
  <div class="d-flex justify-content-between gap-2 flex-wrap"><h2 class="h5 mb-0">Edit <?= e((string)$editCode['code']) ?></h2><a class="btn btn-outline-light btn-sm" href="promo">Cancel edit</a></div>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" value="<?= e((string)$editCode['code']) ?>" required></div>
    <div class="col-md-3"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e((string)($editCode['description'] ?? '')) ?>"></div>
    <div class="col-md-2"><label class="form-label">Discount type</label><select class="form-select" name="discount_type"><option value="percent" <?= $editCode['discount_type'] === 'percent' ? 'selected' : '' ?>>Percent</option><option value="fixed" <?= $editCode['discount_type'] === 'fixed' ? 'selected' : '' ?>>Fixed £</option></select></div>
    <div class="col-md-2"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" value="<?= e((string)$editCode['discount_value']) ?>" required></div>
    <div class="col-md-2"><label class="form-label">Max uses</label><input class="form-control" type="number" min="0" name="max_redemptions" value="<?= $editCode['max_redemptions'] !== null ? (int)$editCode['max_redemptions'] : '' ?>"></div>
    <div class="col-md-3"><label class="form-label">Plan</label><select class="form-select" name="applies_to_plan"><?php foreach ($planOptions as $key=>$label): ?><option value="<?= e($key) ?>" <?= $editCode['applies_to_plan'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Billing</label><select class="form-select" name="applies_to_period"><?php foreach ($periodOptions as $key=>$label): ?><option value="<?= e($key) ?>" <?= $editCode['applies_to_period'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Starts at</label><input class="form-control" type="datetime-local" name="starts_at" value="<?= e(promo_datetime_input($editCode['starts_at'] ?? null)) ?>"></div>
    <div class="col-md-3"><label class="form-label">Expires at</label><input class="form-control" type="datetime-local" name="expires_at" value="<?= e(promo_datetime_input($editCode['expires_at'] ?? null)) ?>"></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="edit_is_active" name="is_active" <?= (int)$editCode['is_active'] ? 'checked' : '' ?>><label class="form-check-label" for="edit_is_active">Active</label></div></div>
  </div>
  <button class="btn btn-rook align-self-start" type="submit">Save changes</button>
</form>
<?php endif; ?>

<form method="post" class="admin-card mb-3 vstack gap-3">
  <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
  <h2 class="h5 mb-0">Create promo code</h2>
  <div class="row g-3">
    <div class="col-md-3"><label class="form-label">Code</label><input class="form-control" name="code" placeholder="LAUNCH25" required></div>
    <div class="col-md-3"><label class="form-label">Description</label><input class="form-control" name="description" placeholder="Launch discount"></div>
    <div class="col-md-2"><label class="form-label">Discount type</label><select class="form-select" name="discount_type"><option value="percent">Percent</option><option value="fixed">Fixed £</option></select></div>
    <div class="col-md-2"><label class="form-label">Value</label><input class="form-control" type="number" step="0.01" min="0" name="discount_value" required></div>
    <div class="col-md-2"><label class="form-label">Max uses</label><input class="form-control" type="number" min="0" name="max_redemptions" placeholder="Unlimited"></div>
    <div class="col-md-3"><label class="form-label">Plan</label><select class="form-select" name="applies_to_plan"><?php foreach ($planOptions as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Billing</label><select class="form-select" name="applies_to_period"><?php foreach ($periodOptions as $key=>$label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div>
    <div class="col-md-3"><label class="form-label">Starts at</label><input class="form-control" type="datetime-local" name="starts_at"></div>
    <div class="col-md-3"><label class="form-label">Expires at</label><input class="form-control" type="datetime-local" name="expires_at"></div>
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="new_is_active" name="is_active" checked><label class="form-check-label" for="new_is_active">Active</label></div></div>
  </div>
  <button class="btn btn-rook align-self-start" type="submit">Create code</button>
</form>
<div class="admin-card">
  <div class="d-flex justify-content-between gap-2 flex-wrap align-items-center mb-3"><h2 class="h5 mb-0">Existing codes</h2><span class="muted"><?= (int) $total ?> total</span></div>
  <div class="admin-table-wrap"><table class="table"><thead><tr><th>Code</th><th>Discount</th><th>Scope</th><th>Uses</th><th>Dates</th><th>Status</th><th>Actions</th></tr></thead><tbody>
    <?php foreach ($codes as $code): $discountLabel = $code['discount_type'] === 'percent' ? rtrim(rtrim((string)$code['discount_value'], '0'), '.') . '%' : '£' . number_format((float)$code['discount_value'], 2); ?>
      <tr>
        <td><strong><?= e((string)$code['code']) ?></strong><span class="d-block muted small"><?= e((string)($code['description'] ?? '')) ?></span></td>
        <td><?= e($discountLabel) ?></td>
        <td><?= e(ucfirst((string)$code['applies_to_plan'])) ?> / <?= e(ucfirst((string)$code['applies_to_period'])) ?></td>
        <td><?= (int)$code['redeemed_count'] ?><?= $code['max_redemptions'] !== null ? ' / ' . (int)$code['max_redemptions'] : ' / ∞' ?></td>
        <td><span class="small muted">From <?= e((string)($code['starts_at'] ?: 'now')) ?><br>Until <?= e((string)($code['expires_at'] ?: 'never')) ?></span></td>
        <td><span class="pill"><span class="status-dot <?= (int)$code['is_active'] ? '' : 'off' ?>"></span><?= (int)$code['is_active'] ? 'Active' : 'Disabled' ?></span></td>
        <td><div class="mini-actions"><a class="btn btn-outline-light btn-sm" href="promo?edit=<?= (int)$code['id'] ?>">Edit</a><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$code['id'] ?>"><button class="btn btn-outline-light btn-sm" type="submit"><?= (int)$code['is_active'] ? 'Disable' : 'Enable' ?></button></form><form method="post" data-confirm-title="Delete promo code?" data-confirm-message="This permanently deletes <?= e((string)$code['code']) ?>." data-confirm-action="Delete"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$code['id'] ?>"><button class="danger-btn btn btn-sm" type="submit">Delete</button></form></div></td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$codes): ?><tr><td colspan="7" class="text-center muted py-4">No promo codes yet.</td></tr><?php endif; ?>
  </tbody></table></div>
  <?= pagination_links('promo', 'p', $page, $total) ?>
</div>
<?php admin_footer(); ?>
