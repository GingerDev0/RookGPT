<?php
require __DIR__ . '/_bootstrap.php';
[$user, $planInfo] = require_api_user();
$stats = api_stats($user);
$usageDaily = $stats['usageDaily'];
$usageTotals = $stats['usageTotals'];
$chartLabels = array_map(static fn(array $row): string => (string)$row['label'], $usageDaily);
$chartRequests = array_map(static fn(array $row): int => (int)$row['requests'], $usageDaily);
$chartTokens = array_map(static fn(array $row): int => (int)$row['tokens'], $usageDaily);
api_header('API usage', $user, $planInfo, 'usage', true);
?>
<div class="metric-grid mb-4"><div class="api-card metric"><span class="muted">Total requests</span><strong><?= number_format((int)$usageTotals['requests']) ?></strong></div><div class="api-card metric"><span class="muted">Total eval units</span><strong><?= number_format((int)$usageTotals['tokens']) ?></strong></div><div class="api-card metric"><span class="muted">Failures logged</span><strong><?= number_format((int)$usageTotals['failures']) ?></strong></div><div class="api-card metric"><span class="muted">Daily limit</span><strong><?= (int)($planInfo['api_call_limit'] ?? 0) > 0 ? number_format((int)$planInfo['api_call_limit']) : 'Unlimited' ?></strong></div></div>
<section class="api-card mb-4"><h3 class="h5 mb-3">Requests</h3><div class="chart-shell"><canvas id="requestsChart"></canvas></div></section>
<section class="api-card"><h3 class="h5 mb-3">Eval units</h3><div class="chart-shell"><canvas id="tokensChart"></canvas></div></section>
<script>
const labels = <?= json_encode($chartLabels) ?>;
const opts = {responsive:true, maintainAspectRatio:false, plugins:{legend:{labels:{color:'#dfe8ff'}}}, scales:{x:{ticks:{color:'#8fa0bd'},grid:{color:'rgba(255,255,255,.06)'}},y:{ticks:{color:'#8fa0bd'},grid:{color:'rgba(255,255,255,.06)'}}}};
new Chart(document.getElementById('requestsChart'), {type:'line', data:{labels, datasets:[{label:'Requests', data:<?= json_encode($chartRequests) ?>, tension:.35}]}, options:opts});
new Chart(document.getElementById('tokensChart'), {type:'bar', data:{labels, datasets:[{label:'Eval units', data:<?= json_encode($chartTokens) ?>}]}, options:opts});
</script>
<?php api_footer(); ?>
