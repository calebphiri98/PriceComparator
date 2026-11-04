<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';
$page_title = "Home";
require 'includes/header.php';

require_once __DIR__ . '/includes/listing_contacts.php';
$contactMap = agri_public_listing_contacts($db);
$search = trim($_GET['q'] ?? '');
$search_results = [];
$home_local = array_values(array_filter(agri_query_prices($db), static fn($r) => agri_location_match((string)$r['location'], 'lunzu')));
usort($home_local, static fn($a, $b) => strtotime($b['observed_at'] ?: '2000-01-01') <=> strtotime($a['observed_at'] ?: '2000-01-01'));
$home_local = array_slice($home_local, 0, 4);
$home_news = $db->query('SELECT * FROM agri_news ORDER BY published_at DESC LIMIT 3')->fetchAll(PDO::FETCH_ASSOC);

if ($search !== '') {
    $stmt = $db->prepare("SELECT crop, price_per_kg, location, '' AS contact, source, NULL AS source_url,
                                 'MWK' AS currency, unit, updated_at
                          FROM market_prices
                          WHERE crop LIKE ?
                          ORDER BY crop");
    $stmt->execute(['%' . $search . '%']);
    $local = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $online_stmt = $db->prepare("SELECT crop, price_per_kg, location, '' AS contact, source_name AS source, source_url,
                                        currency, unit, observed_at AS updated_at
                                 FROM online_market_prices
                                 WHERE crop LIKE ?
                                 ORDER BY crop, location");
    $online_stmt->execute(['%' . $search . '%']);
    $online = $online_stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt2 = $db->prepare("SELECT crop, ROUND(amount_mk / quantity, 0) AS price_per_kg, location, contact, 'Buyer Offer' AS source, NULL AS source_url,
                                  'MWK' AS currency, 'kg' AS unit, created_at AS updated_at
                           FROM outside_markets 
                           WHERE crop LIKE ? 
                           ORDER BY crop");
    $stmt2->execute(['%' . $search . '%']);
    $buyer = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    $farmerStmt = $db->prepare("SELECT f.crop, f.expected_price AS price_per_kg, f.location, u.phone AS contact, 'Farmer expected' AS source, NULL AS source_url, 'MWK' AS currency, 'kg' AS unit, f.created_at AS updated_at FROM farmer_expected_prices f JOIN users u ON u.id = f.farmer_id WHERE f.status = 'approved' AND f.crop LIKE ? ORDER BY f.crop");
    $farmerStmt->execute(['%' . $search . '%']);
    $farmer = $farmerStmt->fetchAll(PDO::FETCH_ASSOC);
    $search_results = array_merge($online, $local, $buyer, $farmer);
}
?>



<div class="hero">
    <h1>Agricultural Market Price Tracker</h1>
    <p>Dated crop-price observations and buyer offers for smallholder farmers in Malawi</p>
    <div class="hero-buttons">
        <a href="market-prices.php" class="btn btn-light">View Current Prices</a>
        <a href="contracts.php" class="btn btn-outline-light">See Contract Farming</a>
    </div>
</div>

<section class="agri-home-feature" aria-labelledby="agri-local-heading">
    <div class="agri-section-head"><div><span class="agri-kicker">LUNZU & BLANTYRE MARKET SNAPSHOT</span><h2 id="agri-local-heading">What the market is saying</h2></div><a href="market-prices.php?area=lunzu">Explore nearby quotes →</a></div>
    <?php if (!$home_local): ?><div class="agri-empty"><h3>No local quotes yet</h3><p>Explore prices from other Malawi markets or share a new submission for administrator review.</p><a class="btn" href="market-prices.php?area=all">All Malawi prices</a></div><?php endif; ?>
    <div class="agri-market-grid">
    <?php foreach ($home_local as $r): ?>
        <article class="agri-market-card">
            <div class="agri-market-art"><img loading="lazy" src="<?= esc(agri_crop_image($r['crop'], $r['photo_path'] ?? null)) ?>" alt="<?= esc($r['crop']) ?>"><span class="agri-source-tag"><?= esc($r['source']) ?></span></div>
            <div class="agri-market-body"><div class="agri-card-top"><h3><?= esc($r['crop']) ?></h3><strong><?= esc(agri_currency($r['price_per_kg'])) ?><small> / kg</small></strong></div><div class="agri-meta"><span>⌖ <?= esc($r['location']) ?></span><span><?= esc(agri_datetime($r['observed_at'])) ?></span></div>
            <?php $listedContact = $contactMap[$r['listing_type'] . ':' . $r['id']] ?? null; ?>
            <?php if ($listedContact && trim($listedContact['phone']) !== ''): ?>
            <p class="agri-listing-contact"><span><?= esc($listedContact['name']) ?></span><span>Call: <a href="tel:<?= esc(preg_replace('/[^0-9+]/', '', $listedContact['phone'])) ?>"><?= esc($listedContact['phone']) ?></a></span></p>
            <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</section>

<nav class="agri-home-strip" aria-label="Explore the market">
    <a href="market-prices.php?area=lunzu">⌖ Prices near Lunzu</a>
    <a href="market-prices.php?area=all">Nationwide prices</a>
    <a href="crop-catalog.php">Browse crop pictures</a>
    <a href="market-news.php">Agricultural news</a>
    <a href="price-reports.php">Interactive price trends</a>
</nav>

<div class="search-box">
    <h2>Search Crop Prices</h2>
    <form class="search-form" method="GET" action="index.php">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" 
               placeholder="Type crop name (Maize, Tobacco, Beans...)" required>
        <button type="submit">Search</button>
    </form>

    <?php if ($search !== ''): ?>
    <div class="search-results">
        <?php if (empty($search_results)): ?>
            <p>No prices found for "<?= htmlspecialchars($search) ?>".</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Crop</th>
                        <th>Price (MWK / kg)</th>
                        <th>Location</th>
                        <th>Phone Number</th>
                        <th>Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($search_results as $r): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($r['crop']) ?></strong></td>
                        <td class="price"><?= htmlspecialchars($r['currency']) ?> <?= number_format($r['price_per_kg'], 2) ?> / <?= htmlspecialchars($r['unit']) ?></td>
                        <td><?= htmlspecialchars($r['location'] ?? '-') ?></td>
                        <td><?= !empty($r['contact']) ? htmlspecialchars($r['contact']) : '-' ?></td>
                        <td>
                            <?php if (!empty($r['source_url'])): ?>
                                <a href="<?= htmlspecialchars($r['source_url']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($r['source']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($r['source']) ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<section class="agri-home-feature" aria-labelledby="agri-headlines-heading">
    <div class="agri-section-head"><div><span class="agri-kicker">AGRICULTURAL INTELLIGENCE</span><h2 id="agri-headlines-heading">Latest farm & market news</h2></div><a href="market-news.php">Read news →</a></div>
    <?php if (!$home_news): ?><div class="agri-note">News is displayed after the administrator imports external RSS headlines. <a href="market-news.php">See trusted sources</a>.</div><?php endif; ?>
    <div class="agri-news-grid">
    <?php foreach ($home_news as $article): ?>
        <article class="agri-news-card"><div class="agri-news-icon">↗</div><div class="agri-news-text"><span class="agri-kicker"><?= esc($article['publisher']) ?></span><h3><a href="<?= esc($article['article_url']) ?>" target="_blank" rel="noopener noreferrer"><?= esc($article['title']) ?></a></h3><p><?= esc(agri_datetime($article['published_at'])) ?></p></div></article>
    <?php endforeach; ?>
    </div>
</section>

<section class="quick-access">
    <h2><?= isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer' ? 'Buyer Quick Actions' : 'Quick Access' ?></h2>
    <div class="qa-grid">
        <?php if (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'buyer'): ?>
            <div class="qa-card">
                <h3>Post Price Offer</h3>
                <p>Add a buyer price offer to the market.</p>
                <a href="buyer-add-price.php" class="btn">Post Price Offer</a>
            </div>
            <div class="qa-card">
                <h3>Post Contract</h3>
                <p>Publish a crop contract for farmers.</p>
                <a href="post-contract.php" class="btn">Post Contract</a>
            </div>
            <div class="qa-card">
                <h3>View Buyer Offers</h3>
                <p>Review buyer price offers.</p>
                <a href="outside-markets.php" class="btn">View Buyer Offers</a>
            </div>
            <div class="qa-card">
                <h3>Current Market Prices</h3>
                <p>Check current crop prices.</p>
                <a href="market-prices.php" class="btn">View Prices</a>
            </div>
            <div class="qa-card">
                <h3>Price Reports</h3>
                <p>View crop prices by date.</p>
                <a href="price-reports.php" class="btn">View Graph</a>
            </div>
            <div class="qa-card">
                <h3>Browse Contracts</h3>
                <p>See contracts available to farmers.</p>
                <a href="contracts.php" class="btn">Browse Contracts</a>
            </div>
            <div class="qa-card">
                <h3>Trending Crops</h3>
                <p>See crops with current market demand.</p>
                <a href="trending.php" class="btn">View Trends</a>
            </div>
        <?php else: ?>
        <div class="qa-card">
            <h3>Market Prices</h3>
            <p>Browse dated Malawi market observations with their contributing sources and buyer offers.</p>
            <a href="market-prices.php" class="btn">View Prices</a>
        </div>
        <div class="qa-card">
            <h3>Contract Farming</h3>
            <p>Companies offering contracts. Apply and secure better prices for your produce.</p>
            <a href="contracts.php" class="btn">Browse Contracts</a>
        </div>
        <div class="qa-card">
            <h3>Outside Markets</h3>
            <p>Buyers ready to buy produce you already have harvested.</p>
            <a href="outside-markets.php" class="btn">See Buyers</a>
        </div>
        <div class="qa-card">
            <h3>Trending Crops</h3>
            <p>See which crops currently have high demand to help you plan better.</p>
            <a href="trending.php" class="btn">View Trends</a>
        </div>
        <div class="qa-card">
            <h3>Price Report</h3>
            <p>View crop prices by date.</p>
            <a href="price-reports.php" class="btn">View Report</a>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php require 'includes/footer.php'; ?>