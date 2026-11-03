<?php
require 'config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'admin'], true)) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
$message = '';
$error = '';

$phone_stmt = $db->prepare("SELECT phone FROM users WHERE id = ?");
$phone_stmt->execute([$user_id]);
$my_phone = (string) $phone_stmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        $error = "Your session expired. Please reload the page and try again.";
    } elseif (isset($_POST['withdraw'])) {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE buyer_price_offers SET status = 'withdrawn'
                              WHERE id = ? AND buyer_id = ? AND status = 'pending'");
        $stmt->execute([$id, $user_id]);
        if ($stmt->rowCount() === 1) {
            $message = "Your offer has been withdrawn.";
        } else {
            $error = "That offer could not be withdrawn.";
        }
    } elseif (isset($_POST['add_price'])) {
        $crop = ucfirst(strtolower(trim((string) ($_POST['crop'] ?? ''))));
        $price = (float) ($_POST['price'] ?? 0);
        $qty_raw = trim((string) ($_POST['quantity'] ?? ''));
        $quantity = $qty_raw === '' ? 1000 : (int) $qty_raw;
        $location = trim((string) ($_POST['location'] ?? ''));
        $contact = preg_replace('/\D/', '', (string) ($_POST['contact'] ?? ''));

        $pending = $db->prepare("SELECT COUNT(*) FROM buyer_price_offers WHERE buyer_id = ? AND status = 'pending'");
        $pending->execute([$user_id]);

        if ($crop === '' || strlen($crop) > 60 || $price <= 0 || $price > 1000000) {
            $error = "Crop name and a valid price are required.";
        } elseif ($quantity <= 0) {
            $error = "Quantity must be more than zero.";
        } elseif ($location === '' || strlen($location) > 80) {
            $error = "Please enter the location.";
        } elseif (strlen($contact) !== 10) {
            $error = "Phone number must be exactly 10 digits.";
        } elseif (!$is_admin && (int) $pending->fetchColumn() >= 10) {
            $error = "You already have 10 offers waiting for approval. Please wait for the admin to review them.";
        } else {
            [$photo, $photoError] = agri_upload_photo($_FILES['photo'] ?? []);
            if ($photoError) {
                $error = $photoError;
            } else {
            $stmt = $db->prepare("INSERT INTO buyer_price_offers (buyer_id, buyer_name, crop, price_per_kg, quantity, location, contact, photo_path)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $_SESSION['fullname'], $crop, $price, $quantity, $location, $contact, $photo]);
            $offer_id = (int) $db->lastInsertId();

            if ($is_admin) {
                // Admin offers are approved immediately and farmers are notified.
                [$ok, $text] = review_buyer_offer($db, $offer_id, $user_id, 'approve', '', false);
                if ($ok) {
                    $message = $text;
                } else {
                    $error = $text;
                }
            } else {
                $message = "Your price offer was submitted and is waiting for admin approval. You will receive an SMS when it is approved or rejected.";
            }
            $_POST = [];
            }
        }
    }
}

$mine = $db->prepare("SELECT * FROM buyer_price_offers WHERE buyer_id = ? ORDER BY id DESC LIMIT 30");
$mine->execute([$user_id]);
$my_offers = $mine->fetchAll(PDO::FETCH_ASSOC);

$page_title = "Buyer Portal";
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
            <h1>Post Price Offer</h1>
            <?php if (!$is_admin): ?>
                <p style="color:#555;">Your offer is reviewed by the admin before farmers see it. You get an SMS when it is approved or rejected.</p>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <label>Crop Name</label>
                <input type="text" name="crop" required maxlength="60" value="<?= esc($_POST['crop'] ?? '') ?>">

                <label>Price per kg (MK)</label>
                <input type="number" name="price" required min="1" step="any" value="<?= esc($_POST['price'] ?? '') ?>">

                <label>Quantity you want to buy (kg, default 1000)</label>
                <input type="number" name="quantity" min="1" value="<?= esc($_POST['quantity'] ?? '') ?>">

                <label>Your Location</label>
                <input type="text" name="location" required maxlength="80" value="<?= esc($_POST['location'] ?? '') ?>">

                <label>Contact Phone (10 digits)</label>
                <input type="text" name="contact" required maxlength="10" pattern="[0-9]{10}" placeholder="0888123456"
                       value="<?= esc($_POST['contact'] ?? $my_phone) ?>">

                <label for="buyer-photo">Produce reference photo (optional)</label>
                <input type="file" id="buyer-photo" name="photo" accept="image/jpeg,image/png,image/webp" data-agri-photo>
                <small class="agri-upload-note">Upload an example of the crop quality you want to buy. JPG, PNG or WEBP, up to 4 MB.</small>
                <img alt="Selected crop preview" class="agri-preview" data-agri-preview>

                <button type="submit" name="add_price" class="btn"><?= $is_admin ? 'Publish Offer' : 'Submit for Approval' ?></button>
            </form>
        </div>

        <h2>My Price Offers</h2>
        <table>
            <thead>
                <tr>
                    <th>Crop</th>
                    <th>Price (MK/kg)</th>
                    <th>Quantity</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th>Posted</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_offers)): ?>
                    <tr><td colspan="7">You have not posted any price offers yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($my_offers as $o): ?>
                    <tr>
                        <td><strong><?= esc($o['crop']) ?></strong><div><img class="agri-review-thumb" loading="lazy" src="<?= esc(agri_crop_image($o['crop'], $o['photo_path'] ?? null)) ?>" alt="<?= esc($o['crop']) ?>"></div></td>
                        <td class="price"><?= esc(mk($o['price_per_kg'])) ?></td>
                        <td><?= esc(number_format($o['quantity'])) ?> kg</td>
                        <td><?= esc($o['location']) ?></td>
                        <td>
                            <?= status_pill($o['status']) ?>
                            <?php if ($o['status'] === 'rejected' && !empty($o['rejection_reason'])): ?>
                                <div style="font-size:.8rem; color:#b71c1c; margin-top:4px;">Reason: <?= esc($o['rejection_reason']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><?= esc(date('d M Y', strtotime($o['created_at']))) ?></td>
                        <td>
                            <?php if ($o['status'] === 'pending'): ?>
                                <form method="POST" onsubmit="return confirm('Withdraw this offer?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
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