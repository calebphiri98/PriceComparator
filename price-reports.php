<?php
require 'config.php';
$page_title = 'Crop price trends';
$hide_main_nav = isset($_SESSION['user_id']);
$source = ($_GET['dataset'] ?? 'platform') === 'ace' ? 'ace' : 'platform';
$area = agri_allowed_area($_GET['area'] ?? 'all');
$days = in_array((string) ($_GET['days'] ?? '180'), ['30', '90', '180', '365', 'all'], true) ? (string) ($_GET['days'] ?? '180') : '180';
$raw = $source === 'ace'
    ? $db->query("SELECT crop, price_per_kg, location, observed_at AS recorded_at FROM agri_external_quotes ORDER BY observed_at DESC LIMIT 6000")->fetchAll(PDO::FETCH_ASSOC)
    : $db->query("SELECT crop, price_per_kg, location, recorded_at FROM market_price_history ORDER BY recorded_at DESC LIMIT 6000")->fetchAll(PDO::FETCH_ASSOC);
$cutoff = $days === 'all' ? 0 : strtotime('-' . $days . ' days');
$available = [];
$filtered = [];
foreach ($raw as $point) {
    if ($cutoff && strtotime($point['recorded_at']) < $cutoff) continue;
    if (!agri_location_match((string) $point['location'], $area)) continue;
    $filtered[] = $point;
    $available[$point['crop']] = ($available[$point['crop']] ?? 0) + 1;
}
arsort($available);
$crops = array_keys($available);
$selected = trim((string) ($_GET['crop'] ?? ''));
if ($selected === '') $selected = $crops[0] ?? '';
$compare = trim((string) ($_GET['compare'] ?? ''));
if (!in_array($selected, $crops, true)) $selected = '';
if (!in_array($compare, $crops, true) || $compare === $selected) $compare = '';
$chartGroups = [];
foreach ($filtered as $point) {
    if ($point['crop'] !== $selected && $point['crop'] !== $compare) continue;
    $day = date('Y-m-d', strtotime($point['recorded_at']));
    $key = $point['crop'] . '|' . $day;
    if (!isset($chartGroups[$key])) $chartGroups[$key] = ['crop' => $point['crop'], 'date' => $day, 'total' => 0, 'count' => 0];
    $chartGroups[$key]['total'] += (float) $point['price_per_kg'];
    $chartGroups[$key]['count']++;
}
$series = [];
foreach ($chartGroups as $group) {
    $series[$group['crop']][] = ['date' => $group['date'], 'price' => round($group['total'] / $group['count'], 2)];
}
foreach ($series as &$points) usort($points, static fn($a, $b) => strcmp($a['date'], $b['date']));
unset($points);
$summary = [];
foreach ($series as $crop => $points) {
    $first = $points[0];
    $last = $points[count($points) - 1];
    $delta = count($points) > 1 && $first['price'] > 0 ? (($last['price'] - $first['price']) / $first['price']) * 100 : null;
    $summary[] = ['crop' => $crop, 'first' => $first, 'last' => $last, 'change' => $delta, 'observations' => count($points)];
}
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">PRICE INTELLIGENCE</span><h1>See the market move.</h1><p>Interactive price trends built from actual dated observations, with crop comparisons and regional filters. No illustrative or predicted price values.</p></div>
    <form method="GET" class="agri-filters" aria-label="Price chart filters">
        <div><label for="dataset">Dataset</label><select name="dataset" id="dataset"><option value="platform" <?= $source === 'platform' ? 'selected' : '' ?>>Platform price history</option><option value="ace" <?= $source === 'ace' ? 'selected' : '' ?>>ACE imported reference prices</option></select></div>
        <div><label for="crop">Crop</label><select name="crop" id="crop"><option value="">Choose a crop</option><?php foreach ($crops as $crop): ?><option value="<?= esc($crop) ?>" <?= $crop === $selected ? 'selected' : '' ?>><?= esc($crop) ?></option><?php endforeach; ?></select></div>
        <div><label for="compare">Compare with</label><select name="compare" id="compare"><option value="">No comparison</option><?php foreach ($crops as $crop): ?><option value="<?= esc($crop) ?>" <?= $crop === $compare ? 'selected' : '' ?>><?= esc($crop) ?></option><?php endforeach; ?></select></div>
        <div><label for="area">Region</label><select name="area" id="area"><?php foreach (['all','lunzu','blantyre','southern','central','northern'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?></select></div>
        <div><label for="days">Period</label><select name="days" id="days"><?php foreach (['30'=>'30 days','90'=>'90 days','180'=>'6 months','365'=>'12 months','all'=>'All available'] as $key => $label): ?><option value="<?= $key ?>" <?= $days === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <button class="btn" type="submit">Update chart</button>
    </form>
    <div class="agri-trend-panel">
        <div class="agri-section-head"><div><span class="agri-kicker">HISTORICAL OBSERVATIONS · MWK PER KG</span><h2><?= $selected ? esc($selected) . ($compare ? ' vs ' . esc($compare) : '') : 'Crop prices' ?></h2></div><span class="agri-pill"><?= $source === 'ace' ? 'ACE observations' : 'Platform history' ?></span></div>
        <?php if (!$series): ?>
            <div class="agri-empty"><h3>Not enough data to draw a price trend</h3><p>Try another region or period. Import ACE observations through the admin feed updater if external data is not yet available.</p></div>
        <?php else: ?>
            <div class="agri-chart-wrap"><canvas id="agri-trend-chart" width="1100" height="420" role="img" aria-label="Line chart showing daily average crop prices over time"></canvas><div id="agri-chart-tooltip" class="agri-chart-tooltip" hidden></div></div>
            <div class="agri-chart-legend" id="agri-chart-legend"></div>
        <?php endif; ?>
        <p class="agri-helper">Each point is the arithmetic mean of observations recorded for a crop on that day in the chosen dataset and region. Comparing different crops does not account for differences in crop quality, market type, sample size or inflation.</p>
    </div>
    <?php if ($summary): ?><div class="agri-summary-grid"><?php foreach ($summary as $row): ?>
        <div class="agri-summary-card"><span><?= esc($row['crop']) ?></span><strong><?= esc(agri_currency($row['last']['price'])) ?><small>/kg</small></strong>
            <?php if ($row['change'] !== null): ?><p class="<?= $row['change'] >= 0 ? 'agri-up' : 'agri-down' ?>"><?= $row['change'] >= 0 ? '+' : '' ?><?= number_format($row['change'], 1) ?>% from first plotted point</p>
            <?php else: ?><p>Only one dated point — no trend available</p><?php endif; ?>
            <small><?= $row['observations'] ?> daily points · <?= esc($row['first']['date']) ?> to <?= esc($row['last']['date']) ?></small></div>
    <?php endforeach; ?></div><?php endif; ?>
    <div class="agri-note">These charts describe historical prices, not forecasts or personalised financial advice. <a href="market-prices.php">Compare current listings and their exact observation dates</a>.</div>
</div></div>
<?php if ($series): ?><script>window.agriTrendData = <?= json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_NUMERIC_CHECK) ?>;</script><script src="assets/agri-trends.js" defer></script><?php endif; ?>
<?php require 'includes/footer.php'; ?>
