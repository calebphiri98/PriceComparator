<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Contract farming';
$hide_main_nav = isset($_SESSION['user_id']);
$contracts = $db->query('SELECT * FROM contracts ORDER BY id DESC LIMIT 250')->fetchAll(PDO::FETCH_ASSOC);
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">FARMING PARTNERSHIPS</span><h1>Grow with an agreement.</h1><p>Explore contract farming opportunities, crop requirements, prices and application deadlines. Contract images are supplied by the poster or shown as an illustration.</p></div>
    <div class="agri-section-head"><div><span class="agri-kicker"><?= count($contracts) ?> POSTED CONTRACTS</span><h2>Contract opportunities</h2></div><?php if (in_array($_SESSION['role'] ?? '', ['buyer','admin'], true)): ?><a href="post-contract.php">Post a contract</a><?php endif; ?></div>
    <?php if (!$contracts): ?><div class="agri-empty"><h3>No contracts posted yet</h3><p>Check back as buyers create opportunities.</p></div><?php endif; ?>
    <div class="agri-market-grid"><?php foreach ($contracts as $c): ?>
    <article class="agri-market-card"><div class="agri-market-art"><img loading="lazy" src="<?= esc(agri_crop_image($c['crop'], $c['photo_path'] ?? null)) ?>" alt="<?= esc($c['crop']) ?>"><span class="agri-source-tag"><?= esc(ucfirst($c['status'])) ?> contract</span></div><div class="agri-market-body"><div class="agri-card-top"><h3><?= esc($c['crop']) ?></h3><strong><?= esc(agri_currency($c['amount_mk'])) ?></strong></div><p class="agri-muted"><strong><?= esc($c['company']) ?></strong></p><div class="agri-meta"><span>Quantity: <?= number_format((int)$c['quantity']) ?> kg</span><span>Apply by: <?= esc(agri_datetime($c['deadline'])) ?></span></div><?php if (!empty($c['description'])): ?><p class="agri-muted"><?= esc(short_text((string)$c['description'], 160)) ?></p><?php endif; ?><div class="agri-card-footer">
        <?php if (strtolower($c['status']) === 'open' && ($_SESSION['role'] ?? '') === 'farmer'): ?><a href="apply-contract.php?id=<?= (int)$c['id'] ?>">Apply for contract →</a><?php elseif (strtolower($c['status']) === 'open' && !isset($_SESSION['user_id'])): ?><a href="login.php">Sign in to apply</a><?php else: ?><span><?= strtolower($c['status']) === 'open' ? 'Farmer applicants only' : 'Applications closed' ?></span><?php endif; ?>
        <a href="market-prices.php?area=all&amp;crop=<?= urlencode($c['crop']) ?>">Compare prices</a>
    </div></div></article>
    <?php endforeach; ?></div>
</div></div>
<?php require 'includes/footer.php'; ?>
