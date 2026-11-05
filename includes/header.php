<?php
if (!isset($page_title)) $page_title = "Agri Market Price Tracker";
$hide_main_nav = $hide_main_nav ?? false;
$current_page = basename($_SERVER['PHP_SELF']);

// Helper: adds class="active" to the link of the page being viewed
function nav_active($file, $current) {
    return $file === $current ? ' class="active" aria-current="page"' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#1e5631">
    <title><?= htmlspecialchars($page_title) ?> | Malawi Farmers</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Source+Sans+3:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="assets/agri-plus.css">
    <link rel="stylesheet" href="assets/agri-navbar.css">
    <script src="assets/agri-responsive-fixes.js" defer></script>
    <?php
    $pageCss = pathinfo($current_page, PATHINFO_FILENAME);
    if (preg_match('/^[a-z0-9-]+$/', $pageCss)):
        foreach ([$pageCss . '-legacy.css', $pageCss . '.css'] as $sheet):
            if (is_file(__DIR__ . '/../assets/pages/' . $sheet)):
    ?>
    <link rel="stylesheet" href="assets/pages/<?= htmlspecialchars($sheet, ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; endforeach; endif; ?>
    <?php if (in_array($current_page, ['admin.php','index.php','buyer-add-price.php','farmer-add-price.php','contracts.php','price-reports.php','post-contract.php','apply-contract.php'], true)): ?>
    <script src="assets/pages/tables.js" defer></script>
    <?php endif; ?>
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>

<?php if (!$hide_main_nav): ?>
    <header class="site-header">
        <div class="container nav">
            <div class="logo">
                <a href="index.php" aria-label="AgriPrice Malawi home"><span class="agri-brand-icon" aria-hidden="true">🌾</span><span class="agri-brand-text">AgriPrice <span>Malawi</span></span></a>
            </div>

            <button type="button" class="nav-toggle" id="navToggle"
                    aria-label="Toggle navigation" aria-expanded="false" aria-controls="navPanel">&#9776;</button>

            <div class="nav-panel" id="navPanel">
                <nav aria-label="Main navigation">
                    <a href="index.php"<?= nav_active('index.php', $current_page) ?>>Home</a>
                    <a href="market-prices.php"<?= nav_active('market-prices.php', $current_page) ?>>Market Prices</a>
                    <a href="crop-catalog.php"<?= nav_active('crop-catalog.php', $current_page) ?>>Crops</a>
                    <a href="market-news.php"<?= nav_active('market-news.php', $current_page) ?>>News</a>
                    <a href="contracts.php"<?= nav_active('contracts.php', $current_page) ?>>Contracts</a>
                    <details class="agri-explore">
                        <summary<?= in_array($current_page, ['farmer-prices.php', 'outside-markets.php', 'trending.php'], true) ? ' class="active"' : '' ?>>Explore <span aria-hidden="true" class="agri-chevron"></span></summary>
                        <div class="agri-explore-menu">
                            <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $current_page) ?>>Farmer Prices</a>
                            <a href="outside-markets.php"<?= nav_active('outside-markets.php', $current_page) ?>>Outside Markets</a>
                            <a href="trending.php"<?= nav_active('trending.php', $current_page) ?>>Trending Crops</a>
                        </div>
                    </details>
                </nav>
                <div class="auth">
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="login.php" class="btn">Login</a>
                        <a href="register.php" class="btn btn-outline">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>
<?php endif; ?>

<?php if (isset($_SESSION['user_id'])): ?>
<div class="user-bar">
    <div class="container user-bar-inner">
        <div class="menu-dropdown" id="userMenu">
            <button type="button" class="hamburger-btn" id="userMenuBtn"
                    aria-label="Quick actions menu" aria-expanded="false">&#9776;</button>
            <div class="dropdown-content">
                <div class="dropdown-header">Quick Actions</div>

                <?php if (($_SESSION['role'] ?? '') === 'buyer'): ?>
                    <div class="dropdown-section">
                        <div class="dropdown-label">Buyer Actions</div>
                        <a href="index.php"<?= nav_active('index.php', $current_page) ?>>Home</a>
                        <a href="buyer-add-price.php">Post Price Offer</a>
                        <a href="post-contract.php"<?= nav_active('post-contract.php', $current_page) ?>>Post Contract</a>
                        <a href="outside-markets.php">View Buyer Offers</a>
                    </div>
                    <div class="dropdown-section">
                        <div class="dropdown-label">Market</div>
                        <a href="market-prices.php"<?= nav_active('market-prices.php', $current_page) ?>>Current Market Prices</a>
                        <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $current_page) ?>>Farmer Prices</a>
                        <a href="price-reports.php"<?= nav_active('price-reports.php', $current_page) ?>>Price Reports</a>
                        <a href="contracts.php"<?= nav_active('contracts.php', $current_page) ?>>Browse Contracts</a>
                        <a href="trending.php"<?= nav_active('trending.php', $current_page) ?>>Trending Crops</a>
                    </div>
                    <div class="dropdown-section dropdown-account">
                        <a href="logout.php" class="logout-link">Log Out</a>
                    </div>
                <?php else: ?>
                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="index.php">Admin Dashboard</a>
                    <a href="admin.php?section=review">Review Submissions</a>
                    <a href="admin.php?section=prices">Manage Prices</a>
                    <a href="admin.php?section=contracts">View All Contracts</a>
                    <a href="admin.php?section=sms">SMS Log</a>
                <?php endif; ?>

                <?php if (in_array($_SESSION['role'] ?? '', ['farmer', 'admin'], true)): ?>
                    <a href="price-reports.php">Price Reports</a>
                <?php endif; ?>

                <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
                    <a href="buyer-add-price.php">Post Price Offer</a>
                    <a href="post-contract.php">Post Contract</a>
                    <a href="outside-markets.php">View Buyer Offers</a>
                <?php endif; ?>

                <?php if (($_SESSION['role'] ?? '') === 'farmer'): ?>
                    <a href="farmer-add-price.php"<?= nav_active('farmer-add-price.php', $current_page) ?>>Post Expected Price</a>
                    <a href="gross-margin.php">Gross Margin Calculator</a>
                <?php endif; ?>

                <a href="market-prices.php">Current Market Prices</a>
                <a href="crop-catalog.php">Crop Directory</a>
                <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $current_page) ?>>Farmer Prices</a>
                <a href="contracts.php">Browse Contracts</a>
                <a href="trending.php">Trending Crops</a>
                <a href="logout.php" class="logout-link">Logout</a>
                <?php endif; ?>
            </div>
        </div>

        <span class="user-name">
            <?= htmlspecialchars($_SESSION['fullname']) ?>
            (<?= ucfirst($_SESSION['role'] ?? 'farmer') ?>)
        </span>
    </div>
</div>
<?php endif; ?>

<main class="container" id="main-content">

<script>
(function () {
    // Main site navigation (mobile)
    var navToggle = document.getElementById('navToggle');
    var navPanel  = document.getElementById('navPanel');
    if (navToggle && navPanel) {
        navToggle.addEventListener('click', function () {
            var open = navPanel.classList.toggle('open');
            navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    var exploreMenu = document.querySelector('.agri-explore');
    if (exploreMenu) {
        document.addEventListener('click', function (event) {
            if (!exploreMenu.contains(event.target)) exploreMenu.removeAttribute('open');
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && exploreMenu.open) {
                exploreMenu.removeAttribute('open');
                var summary = exploreMenu.querySelector('summary');
                if (summary) summary.focus();
            }
        });
    }

    // Logged-in quick actions dropdown
    var userMenu = document.getElementById('userMenu');
    var userBtn  = document.getElementById('userMenuBtn');
    if (userMenu && userBtn) {
        userBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = userMenu.classList.toggle('show');
            userBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
        document.addEventListener('click', function (e) {
            if (!userMenu.contains(e.target)) {
                userMenu.classList.remove('show');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                userMenu.classList.remove('show');
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });
    }
})();
</script>