<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = 'Agricultural news';
$hide_main_nav = isset($_SESSION['user_id']);
$area = agri_allowed_area($_GET['area'] ?? 'southern');
$region = in_array($area, ['lunzu', 'blantyre', 'southern'], true) ? 'southern' : ($area === 'all' ? 'all' : $area);
if ($region === 'all') {
    $stmt = $db->query("SELECT * FROM agri_news ORDER BY published_at DESC LIMIT 60");
} else {
    $stmt = $db->prepare("SELECT * FROM agri_news WHERE region IN ('national', ?) ORDER BY published_at DESC LIMIT 60");
    $stmt->execute([$region]);
}
$news = $stmt->fetchAll(PDO::FETCH_ASSOC);
$lastSync = $db->query("SELECT last_success, last_attempt, detail FROM agri_sync_status WHERE feed = 'news'")->fetch(PDO::FETCH_ASSOC);
require 'includes/header.php';
?>
<div class="agri-shell"><?php if (isset($_SESSION['user_id'])) require 'includes/quick-menu.php'; ?><div class="agri-content">
    <div class="agri-page-intro"><span class="agri-kicker">AGRICULTURE NEWS & MARKET INTELLIGENCE</span><h1>News for the fields and the markets.</h1><p>A dated, source-linked feed of agriculture headlines from external publishers. We link to their reporting; we do not rewrite or invent it.</p></div>
    <form class="agri-filters agri-news-filter" method="GET"><div><label for="area">View stories from</label><select name="area" id="area"><?php foreach (['lunzu','southern','central','northern','all'] as $key): ?><option value="<?= $key ?>" <?= $area === $key ? 'selected' : '' ?>><?= esc(agri_area_label($key)) ?></option><?php endforeach; ?></select></div><button class="btn" type="submit">Update view</button></form>
    <p class="agri-helper">Last successful feed import: <?= esc(agri_datetime($lastSync['last_success'] ?? null)) ?>. Publication times below are from publishers, not live price updates. Feed refresh requires a scheduled command or administrator action.</p>
    <div class="agri-section-head"><div><span class="agri-kicker">FROM PUBLIC NEWS SOURCES</span><h2>Latest imported headlines</h2></div><a href="https://fews.net/southern-africa/malawi" target="_blank" rel="noopener noreferrer">FEWS NET Malawi ↗</a></div>
    <?php if (!$news): ?>
        <div class="agri-empty"><h3>No imported news yet</h3><p>Your admin can refresh the news feed from the administration panel, or schedule <code>php sync-market.php</code>. A working internet connection and PHP XML extension are required.</p><p>Until then, browse <a href="https://massp.ifpri.info/publications/maize-market-reports/" target="_blank" rel="noopener noreferrer">IFPRI Malawi maize reports</a> and <a href="https://fews.net/southern-africa/malawi" target="_blank" rel="noopener noreferrer">FEWS NET Malawi</a>.</p></div>
    <?php else: ?>
        <div class="agri-news-grid">
        <?php foreach ($news as $article): ?>
            <article class="agri-news-card"><div class="agri-news-icon">↗</div><div class="agri-news-text"><span class="agri-kicker"><?= esc($article['region']) ?> · <?= esc($article['publisher']) ?></span><h3><a href="<?= esc($article['article_url']) ?>" rel="noopener noreferrer" target="_blank"><?= esc($article['title']) ?></a></h3><p><?= esc(agri_datetime($article['published_at'])) ?> · Source link opens a separate publisher page</p></div></article>
        <?php endforeach; ?></div>
    <?php endif; ?>
    <div class="agri-note"><strong>Price sources:</strong> Read <a href="https://www.aceafricatrust.org/resources/market-information" rel="noopener noreferrer" target="_blank">ACE Africa Trust</a> for dated market quotes, or explore <a href="market-prices.php?area=all&amp;source=external">imported source prices</a>.</div>
</div></div>
<?php require 'includes/footer.php'; ?>
