<?php
// Load config.php first: it starts the session (PostgreSQL-backed on Render, plain PHP session on localhost).
require_once __DIR__ . '/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/includes/persistent_session.php';
}
$_SESSION = [];
session_destroy();
header("Location: index.php");
exit;