<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

if (($_SESSION['role'] ?? '') !== 'farmer') {
    http_response_code(403);
    $page_title = "Farmers Only";
    require 'includes/header.php';
    ?>
    <div class="alert alert-error">Only farmer accounts can apply for contracts.</div>
    <p><a href="contracts.php">Back to contracts</a></p>
    <?php
    require 'includes/footer.php';
    exit;
}

$contract_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$message = '';
$error = '';

$stmt = $db->prepare("SELECT * FROM contracts WHERE id = ?");
$stmt->execute([$contract_id]);
$contract = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$contract) {
    header("Location: contracts.php");
    exit;
}

if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'pgsql') {
$db->exec("CREATE TABLE IF NOT EXISTS applications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    contract_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    full_name TEXT NOT NULL,
    national_id TEXT NOT NULL,
    phone TEXT NOT NULL,
    location TEXT NOT NULL,
    quantity INTEGER,
    notes TEXT,
    status TEXT DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");
}

if (isset($_POST['apply'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $national_id = strtoupper(trim($_POST['national_id'] ?? ''));
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $quantity = (int)($_POST['quantity'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    if (!csrf_valid()) {
        $error = 'Your session expired. Please refresh and submit the application again.';
    } elseif ($full_name === '') {
        $error = "Full name is required.";
    } elseif ($national_id === '') {
        $error = "Malawian National ID number is required.";
    } elseif (!preg_match('/^[A-Z0-9]{8,13}$/', $national_id)) {
        $error = "Invalid National ID format. Use 8 to 13 letters and numbers only.";
    } elseif (strlen($phone) !== 10) {
        $error = "Phone number must be exactly 10 digits.";
    } elseif ($location === '') {
        $error = "Your location or district is required.";
    } elseif ($quantity > 0 && $quantity > $contract['quantity']) {
        $error = "Quantity cannot be more than the contract needs (" . number_format($contract['quantity']) . " kg).";
    } else {
        $check = $db->prepare("SELECT id FROM applications WHERE contract_id = ? AND national_id = ?");
        $check->execute([$contract_id, $national_id]);
        if ($check->fetch()) {
            $error = "This National ID has already been used to apply for this contract.";
        } else {
            $insert = $db->prepare("INSERT INTO applications 
                (contract_id, user_id, full_name, national_id, phone, location, quantity, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $contract_id,
                $_SESSION['user_id'],
                $full_name,
                $national_id,
                $phone,
                $location,
                $quantity > 0 ? $quantity : null,
                $notes
            ]);
            $message = "Your application has been submitted successfully. The company will contact you using the phone number you provided.";
        }
    }
}

$page_title = "Apply for Contract";
$hide_main_nav = false;
require 'includes/header.php';
?>



<div class="page-wrap">
    <div class="apply-card">
        <div class="apply-header">
            <h1>Apply for Contract</h1>
            <p>Complete the form below carefully. Your National ID will be verified.</p>
        </div>

        <div class="contract-banner">
            <div class="item">
                <label>Company</label>
                <span><?= htmlspecialchars($contract['company']) ?></span>
            </div>
            <div class="item">
                <label>Crop</label>
                <span><?= htmlspecialchars($contract['crop']) ?></span>
            </div>
            <div class="item">
                <label>Quantity Needed</label>
                <span><?= number_format($contract['quantity']) ?> kg</span>
            </div>
            <div class="item">
                <label>Total Amount</label>
                <span>MK <?= number_format($contract['amount_mk'], 0) ?></span>
            </div>
            <div class="item">
                <label>Deadline</label>
                <span><?= htmlspecialchars($contract['deadline']) ?></span>
            </div>
            <div class="item">
                <label>Status</label>
                <span><?= ucfirst($contract['status']) ?></span>
            </div>
        </div>

        <div class="apply-body">
            <?php if ($message): ?>
                <div class="alert-success"><?= htmlspecialchars($message) ?></div>
            <?php else: ?>

                <?php if ($error): ?>
                    <div class="alert-error"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <label>Full Name</label>
                        <input type="text" name="full_name" 
                               value="<?= htmlspecialchars($_POST['full_name'] ?? $_SESSION['fullname'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <label>Malawian National ID Number</label>
                        <input type="text" name="national_id" 
                               value="<?= htmlspecialchars($_POST['national_id'] ?? '') ?>" 
                               maxlength="13" required>
                    </div>

                    <div class="form-row">
                        <label>Phone Number (10 digits)</label>
                        <input type="text" name="phone" 
                               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" 
                               maxlength="10" pattern="[0-9]{10}" placeholder="0888123456" required>
                    </div>

                    <div class="form-row">
                        <label>Location / District</label>
                        <input type="text" name="location" 
                               value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" required>
                    </div>

                    <div class="form-row">
                        <label>Quantity you can supply (kg)</label>
                        <input type="number" name="quantity" min="1" 
                               value="<?= htmlspecialchars($_POST['quantity'] ?? '') ?>">
                    </div>

                    <div class="form-row">
                        <label>Additional notes</label>
                        <textarea name="notes"><?= htmlspecialchars($_POST['notes'] ?? '') ?></textarea>
                    </div>

                    <button type="submit" name="apply" class="btn-submit">Submit Application</button>
                </form>
            <?php endif; ?>

            <div class="form-footer">
                <a href="contracts.php" class="btn-back">Back to Contracts</a>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>