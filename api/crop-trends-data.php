<?php
// api/crop-trends-data.php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$crop_filter = isset($_GET['crop']) ? $_GET['crop'] : 'all';

$db = getDbConnection(); // Your PostgreSQL PDO connection

// 1. Summary statistics per crop (PostgreSQL syntax)
$summarySql = "
    SELECT 
        c.crop_name,
        c.category,
        AVG(p.price) AS avg_price,
        MIN(p.price) AS min_price,
        MAX(p.price) AS max_price,
        COUNT(p.id) AS total_listings
    FROM crops c
    LEFT JOIN market_prices p ON c.id = p.crop_id AND p.created_at >= NOW() - INTERVAL '1 day' * :days
    GROUP BY c.id, c.crop_name, c.category
";

$stmt = $db->prepare($summarySql);
$stmt->execute([':days' => $days]);
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Daily price trends for charting (PostgreSQL syntax)
$trendSql = "
    SELECT 
        c.crop_name,
        TO_CHAR(p.created_at, 'YYYY-MM-DD') AS report_date,
        AVG(p.price) AS daily_avg_price
    FROM market_prices p
    JOIN crops c ON p.crop_id = c.id
    WHERE p.created_at >= NOW() - INTERVAL '1 day' * :days
";

if ($crop_filter !== 'all') {
    $trendSql .= " AND c.crop_name = :crop";
}

$trendSql .= " GROUP BY c.crop_name, TO_CHAR(p.created_at, 'YYYY-MM-DD') ORDER BY report_date ASC";

$stmtTrend = $db->prepare($trendSql);
$params = [':days' => $days];
if ($crop_filter !== 'all') {
    $params[':crop'] = $crop_filter;
}
$stmtTrend->execute($params);
$trends = $stmtTrend->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'summary' => $summary,
    'trends'  => $trends
]);