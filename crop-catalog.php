<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Crop directory';
$hide_main_nav = isset($_SESSION['user_id']);
$all = agri_catalog();
$databaseCrops = $db->query("SELECT crop FROM market_prices UNION SELECT crop FROM farmer_expected_prices WHERE status = 'approved' UNION SELECT crop FROM buyer_price_offers WHERE status = 'approved' UNION SELECT crop FROM contracts UNION SELECT crop FROM agri_external_quotes ORDER BY crop")->fetchAll(PDO::FETCH_COLUMN);
foreach ($databaseCrops as $crop) if (!in_array(strtolower($crop), array_map('strtolower', $all), true)) $all[] = $crop;
usort($all, 'strnatcasecmp');
$counts = [];
foreach (agri_query_prices($db) as $row) {
    $key = strtolower($row['crop']);
    $counts[$key] = ($counts[$key] ?? 0) + 1;
}
$q = substr(trim((string) ($_GET['q'] ?? '')), 0, 60);
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">DISCOVER MALAWI PRODUCE</span><h1>Explore the crop directory</h1><p>Browse familiar and emerging crops. Illustrated catalog images help with discovery; uploaded listing photos show actual produce.</p></div>
    <form method="GET" class="agri-searchline"><input type="search" name="q" placeholder="Search crop names" value="<?= esc($q) ?>"><button class="btn">Search crops</button></form>
    <div class="agri-crop-grid">
        <?php foreach ($all as $crop): if ($q !== '' && stripos($crop, $q) === false) continue; ?>
        <article class="agri-crop-card"><img loading="lazy" src="<?= esc(agri_crop_image($crop)) ?>" alt="Illustration of <?= esc($crop) ?>"><div><h2><?= esc($crop) ?></h2><p><?= ($counts[strtolower($crop)] ?? 0) ?> price observations</p><a href="market-prices.php?area=all&amp;crop=<?= urlencode($crop) ?>">Explore prices →</a></div></article>
        <?php endforeach; ?>
    </div>
</div></div>
<?php require 'includes/footer.php'; ?>
