<?php

header('Content-Type: text/plain; charset=utf-8');

$db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

try {
    $stmt = $db->prepare("INSERT INTO sms_log (user_id, phone, message, status, response) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([null, '+265888000000', 'test', 'skipped', 'manual test']);
    echo "Insert worked.\n";
    echo "Rows now: " . $db->query('SELECT COUNT(*) FROM sms_log')->fetchColumn() . "\n";
    $db->exec("DELETE FROM sms_log WHERE message = 'test' AND response = 'manual test'");
    echo "Test row removed.\n";
} catch (Throwable $e) {
    echo "Insert failed: " . $e->getMessage() . "\n";
}