<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'farmer') {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = "Your session expired. Please reload the page and try again.";
    } elseif (isset($_POST['withdraw'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE farmer_expected_prices SET status = 'withdrawn'
                              WHERE id = ? AND farmer_id = ? AND status IN ('pending', 'approved')");
        $stmt->execute([$id, $user_id]);
        if ($stmt->rowCount() === 1) {
            $message = "Your expected price has been withdrawn.";
        } else {
            $error = "That price could not be withdrawn.";
        }
    } elseif (isset($_POST['add_expected_price'])) {
        $crop = ucfirst(strtolower(trim((string) ($_POST['crop'] ?? ''))));
        $price = (float) ($_POST['price'] ?? 0);
        $qty_raw = trim((string) ($_POST['quantity'] ?? ''));
        $quantity = $qty_raw === '' ? null : (int) $qty_raw;
        $location = trim((string) ($_POST['location'] ?? ''));
        $notes = substr(trim((string) ($_POST['notes'] ?? '')), 0, 200);

        $pending = $db->prepare("SELECT COUNT(*) FROM farmer_expected_prices WHERE farmer_id = ? AND status = 'pending'");
        $pending->execute([$user_id]);

        if ($crop === '' || strlen($crop) > 60) {
            $error = "Please enter a crop name (up to 60 characters).";
        } elseif ($price <= 0 || $price > 1000000) {
            $error = "Enter a valid expected price per kg.";
        } elseif ($quantity !== null && $quantity <= 0) {
            $error = "Quantity must be more than zero, or leave it empty.";
        } elseif ($location === '' || strlen($location) > 80) {
            $error = "Please enter your location (up to 80 characters).";
        } elseif ((int) $pending->fetchColumn() >= 10) {
            $error = "You already have 10 prices waiting for approval. Please wait for the admin to review them.";
        } else {
            [$photo, $photoError] = agri_upload_photo($_FILES['photo'] ?? []);
            if ($photoError) {
                $error = $photoError;
            } else {
                $stmt = $db->prepare("INSERT INTO farmer_expected_prices (farmer_id, crop, expected_price, quantity, location, notes, photo_path)
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$user_id, $crop, $price, $quantity, $location, $notes, $photo]);
                $message = "Your expected price and optional produce photo were submitted for review. You will receive an SMS after moderation.";
                $_POST = [];
            }
        }
    }
}

$crop_options = $db->query("SELECT crop FROM market_prices UNION SELECT crop FROM online_market_prices ORDER BY crop")
                   ->fetchAll(PDO::FETCH_COLUMN);

$mine = $db->prepare("SELECT * FROM farmer_expected_prices WHERE farmer_id = ? ORDER BY id DESC LIMIT 50");
$mine->execute([$user_id]);
$my_prices = $mine->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Post Expected Price";
$hide_main_nav = true;
require 'includes/header.php';
echo pill_styles();
?>

<div class="page-layout">
    <?php require 'includes/quick-menu.php'; ?>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= esc($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= esc($error) ?></div>
        <?php endif; ?>

        <div class="form-box" style="margin-bottom: 40px;">
            <h1>Post My Expected Price</h1>
            <p style="color:#555;">Tell buyers the price you would like to get for your crop. The admin reviews it first, and you get an SMS when it is approved or rejected.</p>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <label for="crop">Crop</label>
                <input type="text" id="crop" name="crop" list="crop-list" required maxlength="60"
                       value="<?= esc($_POST['crop'] ?? '') ?>">
                <datalist id="crop-list">
                    <?php foreach ($crop_options as $c): ?>
                        <option value="<?= esc($c) ?>"></option>
                    <?php endforeach; ?>
                </datalist>

                <label for="price">Expected price per kg (MK)</label>
                <input type="number" id="price" name="price" required min="1" step="any"
                       value="<?= esc($_POST['price'] ?? '') ?>">

                <label for="quantity">Quantity available (kg, optional)</label>
                <input type="number" id="quantity" name="quantity" min="1"
                       value="<?= esc($_POST['quantity'] ?? '') ?>">

                <label for="location">Your location</label>
                <input type="text" id="location" name="location" required maxlength="80"
                       value="<?= esc($_POST['location'] ?? '') ?>">

                <label for="photo">Photo of your actual produce (optional)</label>
                <input type="file" name="photo" id="photo" accept="image/jpeg,image/png,image/webp" data-agri-photo>
                <small class="agri-upload-note">JPG, PNG or WEBP, maximum 4 MB. Show the quality and condition of produce you have.</small>
                <img alt="Selected produce preview" class="agri-preview" data-agri-preview>

                <label for="notes">Notes (optional)</label>
                <input type="text" id="notes" name="notes" maxlength="200"
                       value="<?= esc($_POST['notes'] ?? '') ?>">

                <button type="submit" name="add_expected_price" class="btn">Submit for Approval</button>
            </form>
        </div>

        <h2>My Expected Prices</h2>
        <table>
            <thead>
                <tr>
                    <th>Crop</th>
                    <th>Photo</th>
                    <th>Price (MK/kg)</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_prices)): ?>
                    <tr><td colspan="8">You have not posted any expected prices yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($my_prices as $p): ?>
                    <tr>
                        <td><strong><?= esc($p['crop']) ?></strong></td>
                        <td><img class="agri-review-thumb" src="<?= esc(agri_crop_image($p['crop'], $p['photo_path'] ?? null)) ?>" alt="<?= esc($p['crop']) ?>"></td>
                        <td class="price"><?= esc(mk($p['expected_price'])) ?></td>
                        <td><?= $p['quantity'] ? esc(number_format($p['quantity'])) . ' kg' : '-' ?></td>
                        <td><?= esc($p['location']) ?></td>
                        <td>
                            <?= status_pill($p['status']) ?>
                            <?php if ($p['status'] === 'rejected' && !empty($p['rejection_reason'])): ?>
                                <div style="font-size:.8rem; color:#b71c1c; margin-top:4px;">Reason: <?= esc($p['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc(date('d M Y', strtotime($p['created_at']))) ?></td>
                        <td>
                            <?php if (in_array($p['status'], ['pending', 'approved'], true)): ?>
                                <form method="POST" onsubmit="return confirm('Withdraw this price?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                    <button type="submit" name="withdraw" class="btn btn-danger" style="padding:5px 10px;">Withdraw</button>
                                </form>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="assets/agri-upload.js" defer></script>
<?php require 'includes/footer.php'; ?>