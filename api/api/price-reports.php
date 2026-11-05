<?php
// price-reports.php
require_once __DIR__ . '/config.php';
$db = getDbConnection();

// Fetch list of crops for the filter dropdown
$cropsStmt = $db->query("SELECT DISTINCT crop_name FROM crops ORDER BY crop_name ASC");
$cropList = $cropsStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crop Market Price Reports</title>
    <link rel="stylesheet" href="assets/pages/price-reports.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container" style="max-width: 1200px; margin: 30px auto; padding: 20px;">
        <h1>Market Price Reports & Trends</h1>
        <p>Analyze historical crop pricing, summaries, and export data directly from the PostgreSQL backend.</p>

        <!-- Filters and Actions Toolbar -->
        <div class="toolbar" style="display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; align-items: center;">
            <div>
                <label for="timeRange">Time Range:</label>
                <select id="timeRange" class="form-control" onchange="loadReportData()">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Past Year</option>
                </select>
            </div>

            <div>
                <label for="cropSelect">Crop Filter:</label>
                <select id="cropSelect" class="form-control" onchange="loadReportData()">
                    <option value="all">All Crops</option>
                    <?php foreach ($cropList as $crop): ?>
                        <option value="<?= htmlspecialchars($crop['crop_name']) ?>"><?= htmlspecialchars($crop['crop_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="margin-left: auto;">
                <a href="api/export-crop-csv.php" class="btn btn-primary" style="padding: 8px 16px; background: #2e7d32; color: #fff; text-decoration: none; border-radius: 4px;">Download Full CSV Report</a>
            </div>
        </div>

        <!-- Summary Table Section -->
        <h2>Market Summary Statistics</h2>
        <div class="table-responsive">
            <table id="summaryTable" border="1" cellpadding="10" cellspacing="0" style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background: #f5f5f5;">
                        <th>Crop Name</th>
                        <th>Category</th>
                        <th>Average Price</th>
                        <th>Min Price</th>
                        <th>Max Price</th>
                        <th>Total Listings</th>
                    </tr>
                </thead>
                <tbody id="summaryTableBody">
                    <tr><td colspan="6" style="text-align: center;">Loading data...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- JavaScript to handle async data loading -->
    <script>
        async function loadReportData() {
            const days = document.getElementById('timeRange').value;
            const crop = document.getElementById('cropSelect').value;

            try {
                const response = await fetch(`api/crop-trends-data.php?days=${days}&crop=${encodeURIComponent(crop)}`);
                const data = await response.json();

                if (!data.success) {
                    console.error('Failed to load report data');
                    return;
                }

                populateSummaryTable(data.summary);
            } catch (error) {
                console.error('Error fetching report data:', error);
            }
        }

        function populateSummaryTable(summaryData) {
            const tbody = document.getElementById('summaryTableBody');
            tbody.innerHTML = '';

            if (summaryData.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">No data available for the selected period.</td></tr>';
                return;
            }

            summaryData.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHtml(row.crop_name)}</td>
                    <td>${escapeHtml(row.category || 'N/A')}</td>
                    <td>${row.avg_price ? Number(row.avg_price).toFixed(2) : '0.00'}</td>
                    <td>${row.min_price ? Number(row.min_price).toFixed(2) : '0.00'}</td>
                    <td>${row.max_price ? Number(row.max_price).toFixed(2) : '0.00'}</td>
                    <td>${row.total_listings}</td>
                `;
                tbody.appendChild(tr);
            });
        }

        function escapeHtml(str) {
            return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        // Load data on initial page load
        document.addEventListener('DOMContentLoaded', loadReportData);
    </script>
</body>
</html>