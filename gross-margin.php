<?php
require 'config.php';

$page_title = "Gross Margin Calculator";
$hide_main_nav = isset($_SESSION['user_id']);
require 'includes/header.php';

$is_farmer = isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'farmer';

if ($is_farmer) {
    $gov_prices = $db->query("SELECT crop, price_per_kg, location, '' AS contact, source
                             FROM market_prices ORDER BY crop")->fetchAll(PDO::FETCH_ASSOC);

    $online_prices = $db->query("SELECT crop, price_per_kg, location, '' AS contact, source_name AS source
                                 FROM online_market_prices ORDER BY crop, location")->fetchAll(PDO::FETCH_ASSOC);

    $buyer_prices = $db->query("SELECT crop, ROUND(amount_mk / quantity, 0) AS price_per_kg,
                                       location, contact, 'Buyer Offer' AS source
                                FROM outside_markets ORDER BY crop")->fetchAll(PDO::FETCH_ASSOC);

    $all_prices = array_merge($online_prices, $gov_prices, $buyer_prices);
}
?>

<h1>Gross Margin Calculator</h1>

<?php if ($is_farmer): ?>
    <div class="form-box" style="background:#f1f8e9; margin-bottom:30px; padding:20px; border-radius:8px;">
        <h3>Farmer Gross Margin Calculator</h3>
        <p style="font-size:0.9rem; color:#555;">Calculate your estimated profit per acre based on market prices and your input costs.</p>
        <form id="margin-calc" onsubmit="return false;" style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-top:15px;">
            <div>
                <label for="calc_crop">Select Crop</label>
                <select id="calc_crop" style="width:100%; padding:8px;">
                    <?php foreach ($all_prices as $p): ?>
                        <option value="<?= htmlspecialchars((string) $p['price_per_kg']) ?>"><?= htmlspecialchars($p['crop']) ?> (MK <?= number_format($p['price_per_kg'], 0) ?>/kg)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="calc_yield">Expected Yield per Acre (kg)</label>
                <input type="number" id="calc_yield" value="1000" style="width:100%; padding:8px;" min="1">
            </div>
            <div>
                <label for="calc_costs">Total Variable Costs per Acre (MK)</label>
                <input type="number" id="calc_costs" value="150000" style="width:100%; padding:8px;" min="0">
            </div>
            <div style="display:flex; align-items:flex-end;">
                <button type="button" class="btn" onclick="calculateMargin()" style="width:100%; padding:10px;">Calculate Gross Margin</button>
            </div>
        </form>
        <div id="calc_result" style="margin-top:15px; font-weight:bold; font-size:1.1rem; color:#1b5e20;" aria-live="polite"></div>
    </div>
    <script>
    function calculateMargin() {
        var price = parseFloat(document.getElementById('calc_crop').value) || 0;
        var yieldKg = parseFloat(document.getElementById('calc_yield').value) || 0;
        var costs = parseFloat(document.getElementById('calc_costs').value) || 0;
        var revenue = price * yieldKg;
        var margin = revenue - costs;
        document.getElementById('calc_result').innerHTML = 'Estimated Total Revenue: MK ' + revenue.toLocaleString() + '<br>Estimated Gross Margin (Profit): MK ' + margin.toLocaleString();
    }
    </script>
<?php else: ?>
    <p>The gross margin calculator is available to farmer accounts. Please log in with a farmer account to use it.</p>
<?php endif; ?>

<?php require 'includes/footer.php'; ?>
