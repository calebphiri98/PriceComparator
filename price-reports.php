<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Crop price trends';
$hide_main_nav = isset($_SESSION['user_id']);
$source = ($_GET['dataset'] ?? 'platform') === 'ace' ? 'ace' : 'platform';
$area = agri_allowed_area($_GET['area'] ?? 'all');
$days = in_array((string) ($_GET['days'] ?? '180'), ['30', '90', '180', '365', 'all'], true) ? (string) ($_GET['days'] ?? '180') : '180';
$raw = [];

if ($source === 'ace') {
    $raw = $db->query(
        "SELECT crop, price_per_kg, location, observed_at AS recorded_at
         FROM agri_external_quotes
         ORDER BY observed_at DESC LIMIT 6000"
    )->fetchAll(PDO::FETCH_ASSOC);
} else {
    $exists = $db->query(
        ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql' ? "SELECT to_regclass('public.market_price_history')" : "SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'market_price_history'")
    )->fetchColumn();

    if ($exists) {
        $columns = $db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql'
            ? $db->query("SELECT column_name FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = 'market_price_history'")->fetchAll(PDO::FETCH_COLUMN)
            : $db->query('PRAGMA table_info("market_price_history")')->fetchAll(PDO::FETCH_COLUMN, 1);

        $dateColumn = null;

        foreach (['recorded_at', 'updated_at', 'created_at', 'price_date', 'date', 'observed_at'] as $candidate) {
            if (in_array($candidate, $columns, true)) {
                $dateColumn = $candidate;
                break;
            }
        }

        $required = ['crop', 'price_per_kg', 'location'];

        if ($dateColumn !== null && !array_diff($required, $columns)) {
            $dateField = '"' . $dateColumn . '"';

            $raw = $db->query(
                "SELECT crop, price_per_kg, location,
                 $dateField AS recorded_at
                 FROM market_price_history
                 ORDER BY $dateField DESC LIMIT 6000"
            )->fetchAll(PDO::FETCH_ASSOC);
        }
    }
}
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
$compare = trim((string) ($_GET['compare'] ?? ''));

if ($selected !== '' && !in_array($selected, $crops, true)) {
    $selected = '';
}
if ($compare !== '' && (!in_array($compare, $crops, true) || $compare === $selected)) {
    $compare = '';
}

$chartGroups = [];
foreach ($filtered as $point) {
    if ($selected !== '' && $point['crop'] !== $selected && $point['crop'] !== $compare) continue;
    if ($selected === '' && $compare !== '' && $point['crop'] !== $compare) continue;

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

require 'includes/header.php';
?>
<style>
.agri-chart-toolbar{display:flex;gap:6px;flex-wrap:wrap;margin:0 0 14px}
.agri-chart-toolbar button{border:2px solid #12b76a;background:#fff;color:#0b7a46;font-weight:700;font-size:.88rem;padding:7px 18px;border-radius:999px;cursor:pointer;transition:background .15s,color .15s,transform .1s}
.agri-chart-toolbar button:hover{background:#e7f8ef}
.agri-chart-toolbar button.is-active{background:linear-gradient(135deg,#12b76a,#0ea5e9);border-color:transparent;color:#fff;box-shadow:0 3px 10px rgba(18,183,106,.35)}
.agri-chart-toolbar button:focus-visible{outline:3px solid #2e90fa;outline-offset:2px}

.agri-trend-panel {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
    margin-bottom: 24px;
}
.agri-chart-wrap {
    position: relative;
    background: #f8fafc;
    border-radius: 8px;
    padding: 12px;
    border: 1px solid #f1f5f9;
}
</style>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">PRICE INTELLIGENCE</span><h1>See the market move.</h1></div>
    <form method="GET" class="agri-filters" aria-label="Price chart filters">
        <div><label for="dataset">Dataset</label><select name="dataset" id="dataset"><option value="platform" <?= $source === 'platform' ? 'selected' : '' ?>>Platform price history</option><option value="ace" <?= $source === 'ace' ? 'selected' : '' ?>>ACE imported reference prices</option></select></div>
        <div><label for="crop">Crop</label><select name="crop" id="crop"><option value="">-- All Crops --</option><?php foreach ($crops as $crop): ?><option value="<?= esc($crop) ?>" <?= $crop === $selected ? 'selected' : '' ?>><?= esc($crop) ?></option><?php endforeach; ?></select></div>
        <div><label for="compare">Compare with</label><select name="compare" id="compare"><option value="">No comparison</option><?php foreach ($crops as $crop): ?><option value="<?= esc($crop) ?>" <?= $crop === $compare ? 'selected' : '' ?>><?= esc($crop) ?></option><?php endforeach; ?></select></div>
        <div><label for="area">Region</label><select name="area" id="area"><?php foreach (['all','lunzu','blantyre','southern','central','northern'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?></select></div>
        <div><label for="days">Period</label><select name="days" id="days"><?php foreach (['30'=>'30 days','90'=>'90 days','180'=>'6 months','365'=>'12 months','all'=>'All available'] as $key => $label): ?><option value="<?= $key ?>" <?= $days === $key ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></div>
        <button class="btn" type="submit">Update chart</button>
    </form>
    <div class="agri-trend-panel">
        <div class="agri-section-head"><div><span class="agri-kicker">HISTORICAL OBSERVATIONS · MWK PER KG</span><h2><?= $selected ? esc($selected) . ($compare ? ' vs ' . esc($compare) : '') : 'All Crops Market Prices' ?></h2></div><span class="agri-pill"><?= $source === 'ace' ? 'ACE observations' : 'Platform history' ?></span></div>
        <?php if (!$series): ?>
            <div class="agri-empty"><h3>Not enough data to draw a price trend</h3><p>Try another region or period. Import ACE observations through the admin feed updater if external data is not yet available.</p></div>
        <?php else: ?>
            <div class="agri-chart-toolbar" id="agri-chart-type" role="group" aria-label="Chart type">
                <button type="button" data-type="line" class="is-active" aria-pressed="true">Line</button>
                <button type="button" data-type="bar" aria-pressed="false">Bar</button>
                <button type="button" data-type="area" aria-pressed="false">Area</button>
                <button type="button" data-type="pie" aria-pressed="false">Pie</button>
            </div>
            <div class="agri-chart-wrap"><canvas id="agri-trend-chart" width="1100" height="420" role="img" aria-label="Chart of crop prices in Malawi kwacha per kilogram"></canvas><div id="agri-chart-tooltip" class="agri-chart-tooltip" hidden></div></div>
            <div class="agri-chart-legend" id="agri-chart-legend"></div>
        <?php endif; ?>
    </div>
    
</div></div>
<?php if ($series): ?><script>window.agriTrendData = <?= json_encode($series, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_NUMERIC_CHECK) ?>;</script><script src="assets/agri-trends.js" defer></script><?php endif; ?>
<?php require 'includes/footer.php'; ?>