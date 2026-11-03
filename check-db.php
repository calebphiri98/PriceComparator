<?php

header('Content-Type: text/plain; charset=utf-8');

$path = __DIR__ . '/database.sqlite';

if (!is_file($path)) {
    exit("File not found: $path\n");
}

echo "File size: " . filesize($path) . " bytes\n\n";

try {
    $db = new PDO('sqlite:' . $path);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = $db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
                 ->fetchAll(PDO::FETCH_COLUMN);

    if (!$tables) {
        exit("No tables found. The database is empty.\n");
    }

    foreach ($tables as $table) {
        $count = $db->query('SELECT COUNT(*) FROM "' . $table . '"')->fetchColumn();
        echo str_pad($table, 25) . $count . " rows\n";
    }

    echo "\nLatest 5 SMS log entries:\n";
    if (in_array('sms_log', $tables, true)) {
        $rows = $db->query('SELECT * FROM sms_log ORDER BY rowid DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
        print_r($rows);
    } else {
        echo "sms_log table does not exist yet.\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// farmers should be commenting on the price maybe coz of high agricultura inputs or anything like a comment
// another option is web crawling and we would like the user to be able to view the latest market prices and news
// based on geographical location so to make it dynamic we can say all crops close to Ludzu or the user caan choose outside
// on the charts it should should how the trend is and be more professional and competitive
// all crops should listed with their and even those posting like the buyer or farmer they should posting the m=imagesso that we should be seeing what we are buying
// we would like this to be a high profile system and professional and competitive so that we can be able to compete with other platforms and also be able to attract more users
// address these features and give it as a zipped file and me the commands that can be used to extract it and apply 
// the changes