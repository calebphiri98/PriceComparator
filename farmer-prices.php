<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Farmers selling crops';
$hide_main_nav = isset($_SESSION['user_id']);
$role = $_SESSION['role'] ?? '';
$canContact = in_array($role, ['buyer','admin'], true);
$area = agri_allowed_area($_GET['area'] ?? 'all');
$crop = substr(trim((string) ($_GET['crop'] ?? '')), 0, 65);
$rows = $db->query("SELECT f.*, u.fullname, u.phone FROM farmer_expected_prices f JOIN users u ON u.id = f.farmer_id WHERE f.status = 'approved' ORDER BY f.reviewed_at DESC LIMIT 400")->fetchAll(PDO::FETCH_ASSOC);
$filtered = array_values(array_filter($rows, static function($row) use($area,$crop) {
    return agri_location_match($row['location'], $area) && ($crop === '' || stripos($row['crop'], $crop) !== false);
}));
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">FARMER MARKETPLACE</span><h1>Produce straight from farmers.</h1></div>
    <form class="agri-filters" method="GET"><div><label for="farmer-crop">Crop</label><input id="farmer-crop" name="crop" value="<?= esc($crop) ?>" placeholder="Search crop"></div><div><label for="farmer-area">Area</label><select id="farmer-area" name="area"><?php foreach (['all','lunzu','blantyre','southern','central','northern'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?></select></div><button class="btn" type="submit">Browse offers</button></form>
    <div class="agri-section-head"><div><span class="agri-kicker"><?= count($filtered) ?> APPROVED SUBMISSIONS</span><h2>Farmer expected prices</h2></div><a href="farmer-add-price.php">Post an expected price</a></div>
    <?php if (!$filtered): ?><div class="agri-empty"><h3>Nothing matched</h3><p>Try a wider location or a different crop.</p></div><?php endif; ?>
    <div class="agri-market-grid">
        <?php foreach ($filtered as $f): ?>
        <article class="agri-market-card"><div class="agri-market-art"><img loading="lazy" src="<?= esc(agri_crop_image($f['crop'], $f['photo_path'] ?? null)) ?>" alt="<?= !empty($f['photo_path']) ? 'Produce uploaded for ' : 'Crop illustration of ' ?><?= esc($f['crop']) ?>"><span class="agri-source-tag">Farmer asking price</span></div><div class="agri-market-body"><div class="agri-card-top"><h3><?= esc($f['crop']) ?></h3><strong><?= esc(agri_currency($f['expected_price'])) ?><small> / kg</small></strong></div>
        <div class="agri-meta"><span>⌖ <?= esc($f['location']) ?> · <?= $f['quantity'] ? number_format($f['quantity']) . ' kg' : 'Quantity on request' ?></span><span><?= esc(agri_datetime($f['reviewed_at'] ?: $f['created_at'])) ?></span></div>
        <?php if (!empty($f['notes'])): ?><p class="agri-muted"><?= esc($f['notes']) ?></p><?php endif; ?>
        <?php if (!empty($f['phone'])): ?>
        <p class="agri-listing-contact"><span>Posted by <strong><?= esc($f['fullname']) ?></strong></span> <span>Call: <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', (string)$f['phone'])) ?>"><?= esc($f['phone']) ?></a></span></p>
        <?php endif; ?>
        <div class="agri-card-footer"><a href="price-comments.php?type=farmer&amp;id=<?= (int)$f['id'] ?>">Read price discussion</a><a href="price-reports.php?crop=<?= urlencode($f['crop']) ?>">See price trend ↗</a></div></div></article>
        <?php endforeach; ?>
    </div>
</div></div>
<?php require 'includes/footer.php'; ?>
