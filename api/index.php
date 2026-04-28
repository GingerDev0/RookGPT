<?php
require __DIR__ . '/_bootstrap.php';
[$user, $planInfo] = require_api_user();
$stats = api_stats($user);
$keys = $stats['keys'];
$usageTotals = $stats['usageTotals'];
$usageDaily = $stats['usageDaily'];
$todayApiCalls = $stats['todayApiCalls'];
$activeKeyCount = $stats['activeKeyCount'];
$chartKeyLabels = $stats['chartKeyLabels'];
$chartKeyValues = $stats['chartKeyValues'];
$chartLabels = array_map(static fn(array $row): string => (string)$row['label'], $usageDaily);
$chartRequests = array_map(static fn(array $row): int => (int)$row['requests'], $usageDaily);
api_header('API overview', $user, $planInfo, 'overview', true);
?>
<div class="hero-grid mb-4">
  <section class="api-card">
    <div class="d-flex justify-content-between gap-3 flex-wrap align-items-start mb-3">
      <div><div class="muted text-uppercase fw-bold small">Developer access</div><h3 class="mb-2">API workspace</h3></div>
      <span class="badge-soft">Bearer auth · <?= e((string)$planInfo['label']) ?> plan</span>
    </div>
    <p class="muted mb-0">Manage keys, inspect usage, test requests, and copy implementation examples from a cleaner tabbed workspace that matches the admin pages.</p>
  </section>
  <section class="api-card">
    <h3 class="h5 mb-2">Create API key</h3>
    <p class="muted">Name it after the app, environment, or integration.</p>
    <form method="post" action="/api/keys" class="d-grid gap-3">
      <input class="form-control" type="text" name="key_name" placeholder="Production app, staging bot, local testing">
      <button class="btn btn-rook" type="submit" name="create_key" value="1"><i class="fa-solid fa-plus me-2"></i>Create key</button>
    </form>
  </section>
</div>
<div class="metric-grid mb-4">
  <div class="api-card metric"><span class="muted">Today</span><strong><?= number_format((int)$todayApiCalls) ?></strong></div>
  <div class="api-card metric"><span class="muted">Active keys</span><strong><?= number_format((int)$activeKeyCount) ?></strong></div>
  <div class="api-card metric"><span class="muted">Total requests</span><strong><?= number_format((int)$usageTotals['requests']) ?></strong></div>
  <div class="api-card metric"><span class="muted">Daily limit</span><strong><?= (int)($planInfo['api_call_limit'] ?? 0) > 0 ? number_format((int)$planInfo['api_call_limit']) : 'Unlimited' ?></strong></div>
</div>
<div class="row g-4">
  <div class="col-lg-7"><section class="api-card"><h3 class="h5 mb-3">Requests this week</h3><div class="chart-shell"><canvas id="requestsChart"></canvas></div></section></div>
  <div class="col-lg-5"><section class="api-card"><h3 class="h5 mb-3">Recent keys</h3><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Name</th><th>Status</th><th>Requests</th></tr></thead><tbody><?php foreach (array_slice($keys,0,6) as $key): $enabled = empty($key['revoked_at']); ?><tr><td><?= e((string)$key['name']) ?></td><td><span class="status-dot <?= $enabled ? '' : 'off' ?>"></span><?= $enabled ? 'Enabled' : 'Disabled' ?></td><td><?= number_format((int)$key['request_count']) ?></td></tr><?php endforeach; ?><?php if (!$keys): ?><tr><td colspan="3" class="muted">No keys yet.</td></tr><?php endif; ?></tbody></table></div><a class="btn btn-outline-light btn-sm" href="/api/keys">Manage keys</a></section></div>
</div>
<script>
new Chart(document.getElementById('requestsChart'), {type:'line', data:{labels:<?= json_encode($chartLabels) ?>, datasets:[{label:'Requests', data:<?= json_encode($chartRequests) ?>, tension:.35}]}, options:{responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{color:'#dfe8ff'}}}, scales:{x:{ticks:{color:'#8fa0bd'}, grid:{color:'rgba(255,255,255,.06)'}}, y:{ticks:{color:'#8fa0bd'}, grid:{color:'rgba(255,255,255,.06)'}}}}});
</script>
<?php api_footer(); ?>
