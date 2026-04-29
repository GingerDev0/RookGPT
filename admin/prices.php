<?php
require_once __DIR__ . '/_bootstrap.php';
$user = require_admin_role(['owner']);
$messages = [];
if (!empty($_SESSION['admin_flash'])) { $messages[] = (string)$_SESSION['admin_flash']; unset($_SESSION['admin_flash']); }
$errors = [];

function admin_bool_post(string $key): bool { return isset($_POST[$key]) && (string)$_POST[$key] === '1'; }
function admin_plan_from_post(?array $existing = null): array {
    $rawSlug = (string)($_POST['slug'] ?? ($existing['slug'] ?? ''));
    $slug = rook_plan_slug($rawSlug);
    $features = preg_split('/\r\n|\r|\n/', (string)($_POST['features'] ?? '')) ?: [];
    return [
        'slug' => $slug,
        'label' => trim((string)($_POST['label'] ?? ucfirst($slug))),
        'tagline' => trim((string)($_POST['tagline'] ?? '')),
        'description' => trim((string)($_POST['description'] ?? '')),
        'price_gbp' => max(0, (float)($_POST['price_gbp'] ?? 0)),
        'enabled' => admin_bool_post('enabled') || $slug === 'free',
        'rank' => max(0, (int)($_POST['rank'] ?? 10)),
        'recommended' => admin_bool_post('recommended'),
        'max_conversations' => max(0, (int)($_POST['max_conversations'] ?? 0)),
        'max_messages_daily' => max(0, (int)($_POST['max_messages_daily'] ?? 0)),
        'max_messages_per_conversation' => max(0, (int)($_POST['max_messages_per_conversation'] ?? 0)),
        'max_messages_total' => max(0, (int)($_POST['max_messages_total'] ?? 0)),
        'thinking_available' => admin_bool_post('thinking_available'),
        'api_access' => admin_bool_post('api_access'),
        'api_call_limit' => max(0, (int)($_POST['api_call_limit'] ?? 0)),
        'custom_personality' => admin_bool_post('custom_personality'),
        'conversation_rename' => admin_bool_post('conversation_rename'),
        'share_snapshots' => admin_bool_post('share_snapshots'),
        'team_access' => admin_bool_post('team_access'),
        'team_sharing' => admin_bool_post('team_sharing'),
        'features' => array_values(array_filter(array_map('trim', $features), fn($v) => $v !== '')),
    ];
}
function admin_save_plans(array $plans, array $user, string $action): bool {
    $config = admin_config_contents(admin_current_config_values(['plan_definitions' => $plans]));
    if (file_put_contents(admin_config_file(), $config, LOCK_EX) === false) return false;
    log_admin_action((int)$user['id'], $action, 'config', null, 'Updated plan definitions');
    return true;
}

$plans = admin_plan_definitions();
if (is_post()) {
    $action = (string)($_POST['plan_action'] ?? '');
    $slug = rook_plan_slug((string)($_POST['slug'] ?? ''));
    if ($action === 'save') {
        $plan = admin_plan_from_post($plans[$slug] ?? null);
        $slug = $plan['slug'];
        if ($slug === '') $errors[] = 'Plan slug is required.';
        if ($plan['label'] === '') $errors[] = 'Plan label is required.';
        if ($slug === 'free' && (float)$plan['price_gbp'] > 0) $errors[] = 'The Free plan price must stay £0.';
        if (!$errors) {
            if (!isset($plans[$slug]) && count($plans) >= 20) $errors[] = 'You already have 20 plans. Delete one before adding another.';
            else {
                $plans[$slug] = $plan;
                if (!empty($plan['recommended'])) foreach ($plans as $k => $p) if ($k !== $slug) $plans[$k]['recommended'] = false;
                if (admin_save_plans($plans, $user, 'plans.save')) { $_SESSION['admin_flash'] = 'Plan saved.'; redirect_to('prices'); } else $errors[] = 'Could not write config/app.php. Check file permissions.';
            }
        }
    } elseif ($action === 'disable' && $slug !== 'free' && isset($plans[$slug])) {
        $plans[$slug]['enabled'] = false;
        if (admin_save_plans($plans, $user, 'plans.disable')) { $_SESSION['admin_flash'] = 'Plan disabled.'; redirect_to('prices'); } else $errors[] = 'Could not write config/app.php.';
    } elseif ($action === 'enable' && isset($plans[$slug])) {
        $plans[$slug]['enabled'] = true;
        if (admin_save_plans($plans, $user, 'plans.enable')) { $_SESSION['admin_flash'] = 'Plan enabled.'; redirect_to('prices'); } else $errors[] = 'Could not write config/app.php.';
    } elseif ($action === 'delete' && $slug !== 'free' && isset($plans[$slug])) {
        $inUse = db_fetch_one('SELECT COUNT(*) AS total FROM users WHERE plan = ? LIMIT 1', 's', [$slug]);
        $affectedUsers = (int)($inUse['total'] ?? 0);
        unset($plans[$slug]);
        if (admin_save_plans($plans, $user, 'plans.delete')) {
            if ($affectedUsers > 0) {
                db_execute("UPDATE users SET plan = 'free', plan_billing_period = NULL, plan_expires_at = NULL WHERE plan = ?", 's', [$slug]);
            }
            try {
                db_execute("UPDATE promo_codes SET is_active = 0 WHERE applies_to_plan = ?", 's', [$slug]);
            } catch (Throwable $e) {}
            $_SESSION['admin_flash'] = $affectedUsers > 0
                ? 'Plan deleted. ' . $affectedUsers . ' assigned user' . ($affectedUsers === 1 ? ' was' : 's were') . ' moved to Free.'
                : 'Plan deleted.';
            redirect_to('prices');
        } else $errors[] = 'Could not write config/app.php.';
    }
}
$annualDiscount = admin_annual_discount_months();
admin_header('Plans & pricing', $user, 'prices');
?>
<div class="admin-card mb-3"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap"><div><h1 class="h3 mb-1">Plans & pricing</h1><p class="text-white-50 mb-0">Create, edit, disable, and delete plans. Definitions are stored in <code>config/app.php</code> and power upgrade checkout plus feature-gating.</p></div><button class="btn btn-rook" data-bs-toggle="modal" data-bs-target="#planModalNew"><i class="fa-solid fa-plus me-2"></i>Add plan</button></div></div>
<?php foreach ($messages as $msg): ?><div class="alert alert-success"><?= e($msg) ?></div><?php endforeach; ?>
<?php foreach ($errors as $err): ?><div class="alert alert-danger"><?= e($err) ?></div><?php endforeach; ?>
<div class="admin-card">
  <div class="admin-table-wrap"><table class="table align-middle"><thead><tr><th>Plan</th><th>Price</th><th>Limits</th><th>Features</th><th>Status</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($plans as $slug => $plan): ?>
    <tr><td><strong><?= e((string)$plan['label']) ?></strong><div class="muted small"><code><?= e($slug) ?></code> · rank <?= (int)$plan['rank'] ?><?= !empty($plan['recommended']) ? ' · Recommended' : '' ?></div></td><td>£<?= e(number_format((float)$plan['price_gbp'], 2)) ?>/month<div class="muted small">Annual: £<?= e(number_format(max(0, (float)$plan['price_gbp'] * (12 - $annualDiscount)), 2)) ?></div></td><td><div class="small">Chats: <?= (int)$plan['max_conversations'] ?: 'Unlimited' ?></div><div class="small">Messages: <?= e(rook_message_allowance_label($plan)) ?></div><div class="small">API: <?= !empty($plan['api_access']) ? ((int)$plan['api_call_limit'] > 0 ? (int)$plan['api_call_limit'].'/day' : 'Unlimited') : 'No' ?></div></td><td><span class="pill">Thinking <?= !empty($plan['thinking_available'])?'Yes':'No' ?></span> <span class="pill">Teams <?= !empty($plan['team_access'])?'Yes':'No' ?></span> <span class="pill">Share <?= !empty($plan['share_snapshots'])?'Yes':'No' ?></span></td><td><?= !empty($plan['enabled']) ? '<span class="status-dot"></span>Enabled' : '<span class="status-dot off"></span>Disabled' ?></td><td class="mini-actions"><button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#planModal<?= e($slug) ?>">Edit</button><?php if ($slug !== 'free'): ?><form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="btn btn-outline-light btn-sm" name="plan_action" value="<?= !empty($plan['enabled']) ? 'disable' : 'enable' ?>" type="submit"><?= !empty($plan['enabled']) ? 'Disable' : 'Enable' ?></button></form><form method="post" data-confirm-title="Delete plan" data-confirm-message="Delete this plan? Any assigned users will be moved to Free, and promo codes targeting this plan will be disabled." style="display:inline"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="slug" value="<?= e($slug) ?>"><button class="danger-btn btn btn-sm" name="plan_action" value="delete" type="submit">Delete</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
<?php
function render_plan_modal(string $id, array $plan): void { $isNew = $id === 'New'; $slug = (string)($plan['slug'] ?? ''); ?>
<div class="modal fade" id="planModal<?= e($id) ?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-xl modal-dialog-centered"><form method="post" class="modal-content" style="background:#0b1424;border:1px solid rgba(255,255,255,.12);color:#eaf0ff;border-radius:0;"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="plan_action" value="save"><div class="modal-header" style="border-color:rgba(255,255,255,.08);"><h5 class="modal-title"><?= $isNew ? 'Add plan' : 'Edit ' . e((string)$plan['label']) ?></h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row g-3">
<div class="col-md-3"><label class="form-label">Slug</label><input class="form-control" name="slug" value="<?= e($slug) ?>" <?= !$isNew ? 'readonly' : '' ?> required></div>
<div class="col-md-3"><label class="form-label">Label</label><input class="form-control" name="label" value="<?= e((string)($plan['label'] ?? '')) ?>" required></div>
<div class="col-md-3"><label class="form-label">Monthly price £</label><input class="form-control" type="number" step="0.01" min="0" name="price_gbp" value="<?= e(number_format((float)($plan['price_gbp'] ?? 0), 2, '.', '')) ?>"></div>
<div class="col-md-3"><label class="form-label">Sort rank</label><input class="form-control" type="number" min="0" name="rank" value="<?= (int)($plan['rank'] ?? 10) ?>"></div>
<div class="col-md-4"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="<?= e((string)($plan['tagline'] ?? '')) ?>"></div>
<div class="col-md-8"><label class="form-label">Description</label><input class="form-control" name="description" value="<?= e((string)($plan['description'] ?? '')) ?>"></div>
<div class="col-md-3"><label class="form-label">Max conversations</label><input class="form-control" type="number" min="0" name="max_conversations" value="<?= (int)($plan['max_conversations'] ?? 0) ?>"><div class="form-text text-white-50">0 = unlimited</div></div>
<div class="col-md-3"><label class="form-label">Daily messages</label><input class="form-control" type="number" min="0" name="max_messages_daily" value="<?= (int)($plan['max_messages_daily'] ?? 0) ?>"></div>
<div class="col-md-3"><label class="form-label">Messages per chat</label><input class="form-control" type="number" min="0" name="max_messages_per_conversation" value="<?= (int)($plan['max_messages_per_conversation'] ?? 0) ?>"></div>
<div class="col-md-3"><label class="form-label">Total messages</label><input class="form-control" type="number" min="0" name="max_messages_total" value="<?= (int)($plan['max_messages_total'] ?? 0) ?>"></div>
<div class="col-md-3"><label class="form-label">API daily calls</label><input class="form-control" type="number" min="0" name="api_call_limit" value="<?= (int)($plan['api_call_limit'] ?? 0) ?>"><div class="form-text text-white-50">0 = unlimited if API enabled</div></div>
<div class="col-md-9"><label class="form-label">Marketing features, one per line</label><textarea class="form-control" rows="4" name="features"><?= e(implode("\n", (array)($plan['features'] ?? []))) ?></textarea></div>
<?php foreach (['enabled'=>'Enabled','recommended'=>'Recommended','thinking_available'=>'Thinking/reasoning','api_access'=>'API access','custom_personality'=>'AI personality controls','conversation_rename'=>'Conversation rename','share_snapshots'=>'Share snapshots','team_access'=>'Teams access','team_sharing'=>'Team sharing'] as $key=>$label): ?><div class="col-md-3"><label class="form-check"><input class="form-check-input" type="checkbox" name="<?= e($key) ?>" value="1" <?= !empty($plan[$key]) ? 'checked' : '' ?> <?= $slug==='free' && $key==='enabled' ? 'disabled checked' : '' ?>> <span class="form-check-label"><?= e($label) ?></span></label></div><?php endforeach; ?>
</div></div><div class="modal-footer" style="border-color:rgba(255,255,255,.08);"><button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Cancel</button><button class="btn btn-rook" type="submit">Save plan</button></div></form></div></div>
<?php }
$newPlan = ['slug'=>'','label'=>'','tagline'=>'','description'=>'','price_gbp'=>0,'enabled'=>true,'rank'=>40,'features'=>[]]; render_plan_modal('New', $newPlan); foreach ($plans as $slug => $plan) render_plan_modal($slug, $plan); admin_footer();
