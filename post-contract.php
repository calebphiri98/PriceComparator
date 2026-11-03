<?php
require 'config.php';
require_once __DIR__ . '/includes/features.php';
require_once __DIR__ . '/includes/market_plus.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['buyer', 'admin'], true)) {
    header("Location: login.php");
    exit;
}

$message = '';
$error = '';

if (isset($_POST['add_contract'])) {
    $crop = trim($_POST['crop'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $amount_mk = (float)($_POST['amount_mk'] ?? 0);
    $deadline = trim($_POST['deadline'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please reload and retry.';
    } elseif ($crop === '' || strlen($crop) > 80 || $quantity <= 0 || $amount_mk <= 0 || $deadline === '') {
        $error = "All contract fields are required.";
    } elseif (!DateTimeImmutable::createFromFormat('!Y-m-d', $deadline) || $deadline < date('Y-m-d')) {
        $error = 'Choose a deadline that is today or later.';
    } else {
        [$photo, $photoError] = agri_upload_photo($_FILES['photo'] ?? []);
        if ($photoError) {
            $error = $photoError;
        } else {
            $company = $_SESSION['fullname'];
            $stmt = $db->prepare("
                INSERT INTO contracts (company, crop, quantity, amount_mk, deadline, description, status, photo_path)
                VALUES (?, ?, ?, ?, ?, ?, 'open', ?)
            ");
            $stmt->execute([$company, $crop, $quantity, $amount_mk, $deadline, $description, $photo]);
            $message = "Contract created and posted successfully!";
            $_POST = [];
        }
    }
}

$page_title = "Post Contract";
$hide_main_nav = true;
require 'includes/header.php';
?>

<div class="page-layout">
    <?php require 'includes/quick-menu.php'; ?>

    <div class="main-content">
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="form-box">
            <h1>Post Contract</h1>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <label for="crop">Crop Name</label>
                <input type="text" id="crop" name="crop" required value="<?= htmlspecialchars($_POST['crop'] ?? '') ?>">

                <label for="quantity">Quantity Needed (kg)</label>
                <input type="number" id="quantity" name="quantity" required min="1" value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">

                <label for="amount_mk">Total Amount (MK)</label>
                <input type="number" id="amount_mk" name="amount_mk" required min="1" value="<?= htmlspecialchars($_POST['amount_mk'] ?? '') ?>">

                <label for="deadline">Deadline</label>
                <input type="date" id="deadline" name="deadline" required value="<?= htmlspecialchars($_POST['deadline'] ?? '') ?>">

                <label for="description">Description</label>
                <textarea id="description" name="description" rows="3"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>

                <label for="contract-photo">Crop photo (optional)</label>
                <input id="contract-photo" type="file" name="photo" accept="image/jpeg,image/png,image/webp" data-agri-photo>
                <small class="agri-upload-note">Illustrate the crop required or expected quality. JPG, PNG or WEBP, 4 MB maximum.</small>
                <img alt="Selected crop preview" class="agri-preview" data-agri-preview>

                <button type="submit" name="add_contract" class="btn" style="margin-top:15px;">Post Contract</button>
            </form>
        </div>
    </div>
</div>

<script src="assets/agri-upload.js" defer></script>
<?php require 'includes/footer.php'; ?>
