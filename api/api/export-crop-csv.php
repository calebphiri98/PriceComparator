<?php
// api/export-crop-csv.php
require_once __DIR__ . '/../config.php';

$db = getDbConnection();

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="crop_market_trend_report_' . date('Y-m-d') . '.csv"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Crop Name', 'Category', 'Market Location', 'Reported Price (MWK)', 'Date Recorded']);

$query = "
    SELECT 
        c.crop_name,
        c.category,
        p.market_location,
        p.price,
        p.created_at
    FROM market_prices p
    JOIN crops c ON p.crop_id = c.id
    ORDER BY p.created_at DESC
";

$stmt = $db->query($query);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, $row);
}

fclose($output);
exit;