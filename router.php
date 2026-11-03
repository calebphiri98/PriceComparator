<?php
// Development-router protections for php -S localhost:8000 router.php.
$path = rawurldecode((string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/'));
if (str_contains($path, '..') || str_contains($path, "\0") || str_contains($path, '\\')) {
    http_response_code(404);
    exit('Not found');
}
$filename = basename($path);
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (preg_match('~(^|/)(\.|backup|includes|security)(/|$)~i', $path) ||
    preg_match('~/(config\.php|check-db\.php|check-sms-log\.php|database\.sqlite|database\.sqlite-[^/]+)$~i', $path) ||
    in_array($extension, ['sqlite', 'db', 'sql', 'env', 'log', 'bak', 'zip', 'txt', 'md'], true)) {
    http_response_code(404);
    exit('Not found');
}
$full = __DIR__ . $path;
if ($path === '/') {
    require __DIR__ . '/index.php';
    return true;
}
if ($extension === 'php') {
    $allowed = ['index.php', 'admin.php', 'apply-contract.php', 'buyer-add-price.php',
        'contracts.php', 'farmer-add-price.php', 'farmer-prices.php', 'gross-margin.php',
        'login.php', 'logout.php', 'market-prices.php', 'outside-markets.php',
        'post-contract.php', 'price-reports.php', 'register.php', 'setup-admin.php',
        'trending.php', 'price-comments.php', 'market-news.php', 'crop-catalog.php', 'sync-market.php'];
    if (!in_array($filename, $allowed, true) || $path !== '/' . $filename || !is_file($full)) {
        http_response_code(404);
        exit('Not found');
    }
    $_SERVER['PHP_SELF'] = $path;
    $_SERVER['SCRIPT_NAME'] = $path;
    $_SERVER['SCRIPT_FILENAME'] = $full;
    require $full;
    return true;
}
if ($path !== '/' && is_file($full) && in_array($extension, ['css', 'js', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico'], true)) {
    return false;
}
http_response_code(404);
exit('Not found');
