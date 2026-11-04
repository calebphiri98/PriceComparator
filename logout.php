<?php
require_once __DIR__ . '/includes/persistent_session.php';
session_destroy();
header("Location: index.php");
exit;
