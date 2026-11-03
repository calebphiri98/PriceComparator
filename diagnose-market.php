<?php
/** CLI-only diagnostics: public market/news hosts. No application config or credentials are loaded. */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/includes/market_feed.php';

function agri_diagnostic_setting(string $name): string
{
    $value = trim((string) ini_get($name));
    if ($value === '') return 'not set';
    if (str_ends_with($name, 'cafile') || $name === 'curl.cainfo') {
        return is_file($value) && is_readable($value) ? 'configured and readable' : 'configured but missing/unreadable';
    }
    return $value;
}

echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'cURL: ' . (function_exists('curl_init') ? 'enabled' : 'not enabled') . PHP_EOL;
echo 'OpenSSL: ' . (extension_loaded('openssl') ? 'enabled' : 'not enabled') . PHP_EOL;
echo 'DOM: ' . (class_exists('DOMDocument') ? 'enabled' : 'not enabled') . PHP_EOL;
echo 'SimpleXML: ' . (function_exists('simplexml_load_string') ? 'enabled' : 'not enabled') . PHP_EOL;
echo 'allow_url_fopen: ' . agri_diagnostic_setting('allow_url_fopen') . PHP_EOL;
echo 'curl.cainfo: ' . agri_diagnostic_setting('curl.cainfo') . PHP_EOL;
echo 'openssl.cafile: ' . agri_diagnostic_setting('openssl.cafile') . PHP_EOL;

$checks = [
    ['ACE prices', 'https://www.aceafricatrust.org/resources/market-information', 'www.aceafricatrust.org'],
    ['Google News RSS', 'https://news.google.com/rss/search?q=Malawi%20agriculture&hl=en-US&gl=US&ceid=US%3Aen', 'news.google.com'],
];
foreach ($checks as [$name, $url, $host]) {
    $error = null;
    $body = agri_remote_get($url, $host, 2097152, $error);
    if ($body === null) {
        echo $name . ': FAILED (' . ($error ?? 'unknown reason') . ')' . PHP_EOL;
    } else {
        echo $name . ': HTTP 200, received ' . strlen($body) . ' bytes' . PHP_EOL;
    }
}
