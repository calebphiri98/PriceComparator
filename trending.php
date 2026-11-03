<?php
require 'config.php';
$page_title = 'Trending crops';
$hide_main_nav = isset($_SESSION['user_id']);
$area = agri_allowed_area($_GET['area'] ?? 'all');
$recent = strtotime('-60 days');
$activity = [];
foreach (agri_query_prices($db) as $price) {
    if (!agri_location_match($price['location'], $area) || strtotime((string)$price['observed_at']) < $recent) continue;
    $key = strtolower($price['crop']);
    if (!isset($activity[$key])) $activity[$key] = ['crop' => $price['crop'], 'observations' => 0, 'sources' => [], 'locations' => []];
    $activity[$key]['observations']++;
    $activity[$key]['sources'][$price['source']] = true;
    $activity[$key]['locations'][strtolower($price['location'])] = true;
}
usort($activity, static fn($a,$b) => ($b['observations'] <=> $a['observations']) ?: strcasecmp($a['crop'], $b['crop']));
$activity = array_slice($activity, 0, 18);
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">MARKET ACTIVITY · LAST 60 DAYS</span><h1>Crops getting attention.</h1><p>Ranked by the number of recently recorded price listings, not by unverified claims of national demand. More observations do not necessarily mean more sales.</p></div>
    <form method="GET" class="agri-filters"><div><label for="area">Area</label><select name="area" id="area"><?php foreach (['all','lunzu','blantyre','southern','central','northern'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?></select></div><button class="btn">View activity</button></form>
    <?php if (!$activity): ?><div class="agri-empty"><h3>No recent crop activity</h3><p>Try All Malawi. The system does not manufacture rankings when no recent records are available.</p></div><?php endif; ?>
    <div class="agri-crop-grid"><?php foreach ($activity as $item): ?><article class="agri-crop-card"><img loading="lazy" src="<?= esc(agri_crop_image($item['crop'])) ?>" alt="Illustration of <?= esc($item['crop']) ?>"><div><h2><?= esc($item['crop']) ?></h2><p><?= $item['observations'] ?> quotes · <?= count($item['sources']) ?> source types · <?= count($item['locations']) ?> locations</p><a href="market-prices.php?area=<?= esc($area) ?>&amp;crop=<?= urlencode($item['crop']) ?>">Explore quotes →</a></div></article><?php endforeach; ?></div>
</div></div>
<?php require 'includes/footer.php'; ?>
