<?php
require 'config.php';
$page_title = 'Buyer marketplace';
$hide_main_nav = isset($_SESSION['user_id']);
$area = agri_allowed_area($_GET['area'] ?? 'all');
$crop = substr(trim((string) ($_GET['crop'] ?? '')), 0, 65);
$rows = $db->query('SELECT * FROM outside_markets WHERE quantity > 0 ORDER BY created_at DESC LIMIT 400')->fetchAll(PDO::FETCH_ASSOC);
$rows = array_values(array_filter($rows, static fn($r) => agri_location_match($r['location'], $area) && ($crop === '' || stripos($r['crop'], $crop) !== false)));
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">BUYERS READY TO TRADE</span><h1>Find a buyer for your harvest.</h1><p>Approved purchase offers with buyer contact details, requirements and example produce photos. Confirm availability and specifications directly.</p></div>
    <form method="GET" class="agri-filters"><div><label for="buyer-crop">Crop</label><input id="buyer-crop" name="crop" placeholder="Search crop" value="<?= esc($crop) ?>"></div><div><label for="buyer-area">Area</label><select id="buyer-area" name="area"><?php foreach (['all','lunzu','blantyre','southern','central','northern'] as $a): ?><option value="<?= $a ?>" <?= $a === $area ? 'selected' : '' ?>><?= esc(agri_area_label($a)) ?></option><?php endforeach; ?></select></div><button class="btn" type="submit">Find buyers</button></form>
    <div class="agri-section-head"><div><span class="agri-kicker"><?= count($rows) ?> BUYER OFFERS</span><h2>Available opportunities</h2></div><a href="buyer-add-price.php">Post a buyer offer</a></div>
    <?php if (!$rows): ?><div class="agri-empty"><h3>No offers for this selection</h3><p>Try a broader region or different crop.</p></div><?php endif; ?>
    <div class="agri-market-grid"><?php foreach ($rows as $r): ?>
    <article class="agri-market-card"><div class="agri-market-art"><img loading="lazy" src="<?= esc(agri_crop_image($r['crop'], $r['photo_path'] ?? null)) ?>" alt="<?= esc($r['crop']) ?> crop"><span class="agri-source-tag">Buyer offer</span></div><div class="agri-market-body"><div class="agri-card-top"><h3><?= esc($r['crop']) ?></h3><strong><?= esc(agri_currency($r['amount_mk'] / $r['quantity'])) ?><small> / kg</small></strong></div><p class="agri-muted"><strong><?= esc($r['company']) ?></strong> · <?= number_format((int)$r['quantity']) ?> kg required</p><div class="agri-meta"><span>⌖ <?= esc($r['location']) ?></span><span>Contact: <?= esc($r['contact']) ?></span><span>Posted: <?= esc(agri_datetime($r['created_at'])) ?></span></div><div class="agri-card-footer"><a href="price-comments.php?type=buyer&amp;id=<?= (int)$r['id'] ?>">Discuss price</a><a href="price-reports.php?crop=<?= urlencode($r['crop']) ?>">Price trend ↗</a></div></div></article>
    <?php endforeach; ?></div>
</div></div>
<?php require 'includes/footer.php'; ?>
