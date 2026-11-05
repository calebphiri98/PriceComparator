<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Market prices near you';
$hide_main_nav = isset($_SESSION['user_id']);
$area = agri_allowed_area($_GET['area'] ?? 'lunzu');
$place = substr(trim((string) ($_GET['place'] ?? '')), 0, 55);
$query = substr(trim((string) ($_GET['crop'] ?? '')), 0, 65);
$source = in_array($_GET['source'] ?? '', ['all', 'local', 'buyer', 'external'], true) ? $_GET['source'] : 'all';
require_once __DIR__ . '/includes/listing_contacts.php';
$contactMap = agri_public_listing_contacts($db);
// Farmer expected prices are NOT part of the current market list; they live on the Farmer Prices page.
$rows = agri_query_prices($db, false);
$placeFallback = false;
$applyFilters = static function (string $placeFilter) use ($rows, $query, $source, $area): array {
    $out = [];
    foreach ($rows as $r) {
        if ($query !== '' && stripos($r['crop'], $query) === false) continue;
        if ($source !== 'all' && $r['listing_type'] !== $source && !($source === 'external' && $r['listing_type'] === 'legacy')) continue;
        if (!agri_location_match((string) $r['location'], $area, $placeFilter)) continue;
        $out[] = $r;
    }
    return $out;
};
$filtered = $applyFilters($place);
$requestedPlace = $place;
if (!$filtered && $place !== '' && ($_GET['nearby'] ?? '') === '1') {
    // "Use my location" found a town with no recorded prices: fall back to the wider region.
    $filtered = $applyFilters('');
    $placeFallback = (bool) $filtered;
    if ($placeFallback) $place = '';
}
$totals = [];
foreach ($filtered as $r) $totals[strtolower($r['crop'])] = true;
usort($filtered, static function ($a, $b) {
    $d = strtotime($b['observed_at'] ?: '2000-01-01') <=> strtotime($a['observed_at'] ?: '2000-01-01');
    return $d ?: strcasecmp($a['crop'], $b['crop']);
});
$filtered = array_slice($filtered, 0, 200);
$crops = array_values(array_unique(array_column($rows, 'crop')));
sort($crops, SORT_NATURAL | SORT_FLAG_CASE);
$commentsQuery = $db->query("SELECT price_type, price_id, COUNT(*) AS total FROM price_comments WHERE status = 'approved' GROUP BY price_type, price_id")->fetchAll(PDO::FETCH_ASSOC);
$commentCounts = [];
foreach ($commentsQuery as $row) $commentCounts[$row['price_type'] . ':' . $row['price_id']] = (int) $row['total'];
require 'includes/header.php';
?>
<div class="agri-shell">
<?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?>
<div class="agri-content">
    <div class="agri-hero">
        <div>
            <span class="agri-kicker">MALAWI AGRICULTURAL MARKETPLACE</span>
            <h1>Discover crop prices <em>near you.</em></h1>
            <p>Compare local reference prices, buyer offers and dated external market observations. Every quote is labelled by source and date. Looking for what farmers are asking? See <a href="farmer-prices.php">Farmer Prices</a></p>
            <div class="agri-stats"><span><?= count($filtered) ?> matching observations</span><span><?= count($totals) ?> crops</span><span><?= esc(agri_area_label($area)) ?></span></div>
        </div>
        
    </div>
    <form method="GET" class="agri-filters" id="agri-market-form" aria-label="Filter market listings">
        <div><label for="crop">Crop</label><input name="crop" id="crop" type="search" list="agri-crops" placeholder="Search any crop" value="<?= esc($query) ?>"><datalist id="agri-crops"><?php foreach ($crops as $crop): ?><option value="<?= esc($crop) ?>"><?php endforeach; ?></datalist></div>
        <div><label for="area">Market area</label><select name="area" id="area">
            <?php foreach (['lunzu','blantyre','southern','central','northern','all'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?>
        </select></div>
        <div><label for="place">Specific town (optional)</label><input type="text" name="place" id="place" maxlength="55" placeholder="e.g. Zomba, Balaka" value="<?= esc($place) ?>"></div>
        <div><label for="source">Quote type</label><select name="source" id="source">
            <?php foreach (['all'=>'All quote types','local'=>'Platform reference','buyer'=>'Buyer offers','external'=>'External sources'] as $key => $label): ?><option value="<?= $key ?>" <?= $source === $key ? 'selected' : '' ?>><?= esc($label) ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn" type="submit">Find prices</button>
        <button class="agri-ghost" type="button" id="agri-geolocate">Use my location</button>
    </form>
    <p class="agri-helper" id="agri-location-status"><?php if ($placeFallback): ?>No prices are recorded yet for <?= esc($requestedPlace) ?>, so the wider <?= esc(agri_area_label($area)) ?> area is shown.<?php else: ?>“Use my location” finds the nearest market town from your device position, then shows prices around it. Your location stays in your browser and is never stored.<?php endif; ?></p>
    <div class="agri-section-head"><div><span class="agri-kicker">THE MARKET BOARD</span><h2><?= esc(agri_area_label($area)) ?> prices</h2></div><div class="agri-head-actions"><a href="crop-catalog.php">All crop varieties</a><a href="price-reports.php">View trends</a></div></div>
    <?php if (!$filtered): ?>
        <div class="agri-empty"><h3>No recorded prices for these filters</h3><p>Try All Malawi or another crop. This does not mean a crop is unavailable—it means there is no matching observation in the database.</p><a href="market-prices.php?area=all" class="btn">Browse Malawi prices</a></div>
    <?php else: ?>
    <div class="agri-market-grid">
        <?php foreach ($filtered as $price): ?>
        <article class="agri-market-card">
            <div class="agri-market-art"><img loading="lazy" src="<?= esc(agri_crop_image($price['crop'], $price['photo_path'])) ?>" alt="<?= $price['photo_path'] ? 'Uploaded produce photo of ' : 'Illustration of ' ?><?= esc($price['crop']) ?>"><span class="agri-source-tag"><?= esc($price['source']) ?></span></div>
            <div class="agri-market-body"><div class="agri-card-top"><h3><?= esc($price['crop']) ?></h3><strong><?= esc(agri_currency($price['price_per_kg'])) ?><small> / kg</small></strong></div>
            <div class="agri-meta"><span>⌖ <?= esc($price['location']) ?></span><span>Observed: <?= esc(agri_datetime($price['observed_at'])) ?></span></div>
            <?php $listedContact = $contactMap[$price['listing_type'] . ':' . $price['id']] ?? null; ?>
            <?php if ($listedContact && trim($listedContact['phone']) !== ''): ?>
            <p class="agri-listing-contact"><span><?= esc($listedContact['name']) ?></span><span>Call: <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $listedContact['phone'])) ?>"><?= esc($listedContact['phone']) ?></a></span></p>
            <?php endif; ?>
            <?php if (!empty($price['source_url']) && filter_var($price['source_url'], FILTER_VALIDATE_URL) && str_starts_with($price['source_url'], 'https://')): ?><a class="agri-source-link" href="<?= esc($price['source_url']) ?>" rel="noopener noreferrer" target="_blank">Visit original source</a><?php endif; ?>
            <div class="agri-card-footer">
                <?php if ($price['listing_type'] !== 'legacy'): ?>
                    <a href="price-comments.php?type=<?= esc($price['listing_type']) ?>&amp;id=<?= (int) $price['id'] ?>">Price discussion <span>(<?= $commentCounts[$price['listing_type'] . ':' . $price['id']] ?? 0 ?>)</span></a>
                <?php else: ?><span>Archived external observation</span><?php endif; ?>
                <a href="price-reports.php?crop=<?= urlencode($price['crop']) ?>">Trend ↗</a>
            </div></div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
   
</div></div>
<script src="assets/agri-geo.js" defer></script>
<?php require 'includes/footer.php'; ?>