<?php
$menu_role = $_SESSION['role'] ?? '';
$menu_page = basename($_SERVER['PHP_SELF']);
?>
<div class="side-menu">
    <div class="side-header">Quick Menu</div>

    <?php if ($menu_role === 'buyer'): ?>
        <div class="side-label">Buyer Actions</div>
        <a href="index.php"<?= nav_active('index.php', $menu_page) ?>>Home</a>
        <a href="buyer-add-price.php"<?= nav_active('buyer-add-price.php', $menu_page) ?>>Post Price Offer</a>
        <a href="post-contract.php"<?= nav_active('post-contract.php', $menu_page) ?>>Post Contract</a>
        <a href="outside-markets.php"<?= nav_active('outside-markets.php', $menu_page) ?>>View Buyer Offers</a>
        <div class="side-label">Market</div>
        <a href="market-prices.php"<?= nav_active('market-prices.php', $menu_page) ?>>Current Market Prices</a>
        <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $menu_page) ?>>Farmer Prices</a>
        <a href="price-reports.php"<?= nav_active('price-reports.php', $menu_page) ?>>Price Reports</a>
        <a href="contracts.php"<?= nav_active('contracts.php', $menu_page) ?>>Browse Contracts</a>
        <a href="trending.php"<?= nav_active('trending.php', $menu_page) ?>>Trending Crops</a>
    <?php elseif ($menu_role === 'farmer'): ?>
        <a href="index.php"<?= nav_active('index.php', $menu_page) ?>>Home</a>
        <a href="farmer-add-price.php"<?= nav_active('farmer-add-price.php', $menu_page) ?>>Post Expected Price</a>
        <a href="market-prices.php"<?= nav_active('market-prices.php', $menu_page) ?>>Current Market Prices</a>
        <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $menu_page) ?>>Farmer Prices</a>
        <a href="price-reports.php"<?= nav_active('price-reports.php', $menu_page) ?>>Price Reports</a>
        <a href="gross-margin.php"<?= nav_active('gross-margin.php', $menu_page) ?>>Gross Margin Calculator</a>
        <a href="contracts.php"<?= nav_active('contracts.php', $menu_page) ?>>Browse Contracts</a>
        <a href="trending.php"<?= nav_active('trending.php', $menu_page) ?>>Trending Crops</a>
    <?php elseif ($menu_role === 'admin'): ?>
        <div class="side-label">Administration</div>
        <a href="index.php"<?= nav_active('index.php', $menu_page) ?>>Admin Dashboard</a>
        <a href="admin.php?section=review">Review Submissions</a>
        <a href="admin.php?section=prices">Manage Prices</a>
        <a href="admin.php?section=contracts">Manage Contracts</a>
        <a href="admin.php?section=admins">Manage Admins</a>
        <a href="admin.php?section=sms">SMS Log</a>
        <div class="side-label">Buyer Actions</div>
        <a href="buyer-add-price.php"<?= nav_active('buyer-add-price.php', $menu_page) ?>>Post Price Offer</a>
        <a href="post-contract.php"<?= nav_active('post-contract.php', $menu_page) ?>>Post Contract</a>
        <a href="outside-markets.php"<?= nav_active('outside-markets.php', $menu_page) ?>>View Buyer Offers</a>
        <div class="side-label">Market</div>
        <a href="market-prices.php"<?= nav_active('market-prices.php', $menu_page) ?>>Current Market Prices</a>
        <a href="farmer-prices.php"<?= nav_active('farmer-prices.php', $menu_page) ?>>Farmer Prices</a>
        <a href="price-reports.php"<?= nav_active('price-reports.php', $menu_page) ?>>Price Reports</a>
        <a href="contracts.php"<?= nav_active('contracts.php', $menu_page) ?>>Browse Contracts</a>
        <a href="trending.php"<?= nav_active('trending.php', $menu_page) ?>>Trending Crops</a>
    <?php endif; ?>

    <a href="crop-catalog.php"<?= nav_active('crop-catalog.php', $menu_page) ?>>Crop Directory</a>
    <a href="market-news.php"<?= nav_active('market-news.php', $menu_page) ?>>Agricultural News</a>
    <a href="logout.php" class="logout-link">Logout</a>
</div>