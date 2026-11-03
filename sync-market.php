<?php
require 'config.php';
require_once __DIR__ . '/includes/market_feed.php';
$cli = PHP_SAPI === 'cli';
if (!$cli) {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SESSION['role'] ?? '') !== 'admin' || !csrf_valid()) {
        http_response_code(403);
        exit('Access denied. Admin POST with CSRF token required.');
    }
}
$last = $db->query("SELECT feed, last_attempt FROM agri_sync_status WHERE feed IN ('ace','news')")->fetchAll(PDO::FETCH_KEY_PAIR);
if (!$cli && $last) {
    $recent = false;
    foreach ($last as $time) if ($time && strtotime($time) > time() - 900) $recent = true;
    if ($recent) {
        $_SESSION['flash'] = ['type' => 'error', 'text' => 'Feeds were checked recently. Please allow 15 minutes between refreshes.'];
        header('Location: admin.php?section=feeds');
        exit;
    }
}
$messages = [];
foreach (['agri_sync_ace', 'agri_sync_news'] as $job) {
    try { [$ok, $message] = $job($db); }
    catch (Throwable $e) { $ok = false; $message = $job . ': source or parser error. See server logs.'; error_log($e->getMessage()); }
    $messages[] = $message;
}
if ($cli) {
    echo implode(PHP_EOL, $messages) . PHP_EOL;
} else {
    $_SESSION['flash'] = ['type' => 'success', 'text' => implode(' ', $messages)];
    header('Location: admin.php?section=feeds');
}
