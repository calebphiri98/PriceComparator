<?php
require 'config.php';

$page_title = "Initial Admin Setup";
$setup_key = getenv('ADMIN_SETUP_KEY');
$setup_enabled = is_string($setup_key) && strlen($setup_key) >= 32;
$admin_count = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$error = '';
$success = false;

if (!isset($_SESSION['admin_setup_token'])) {
    $_SESSION['admin_setup_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $admin_count === 0 && $setup_enabled) {
    $fullname_input = $_POST['fullname'] ?? '';
    $phone_input = $_POST['phone'] ?? '';
    $password = $_POST['password'] ?? '';
    $provided_key = $_POST['setup_key'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!is_string($csrf_token) || !hash_equals($_SESSION['admin_setup_token'], $csrf_token)) {
        $error = 'Setup session expired. Reload this page and try again.';
    } elseif (!is_string($provided_key) || !hash_equals($setup_key, $provided_key)) {
        $error = 'Invalid setup key.';
    } elseif (!is_string($fullname_input) || !is_string($phone_input) || !is_string($password)) {
        $error = 'Enter valid account details.';
    } else {
        $fullname = trim($fullname_input);
        $phone = preg_replace('/\D/', '', $phone_input);

        if ($fullname === '' || strlen($phone) !== 10 || strlen($password) < 12) {
            $error = 'Enter a name, a 10-digit phone number, and a password of at least 12 characters.';
        } else {
            try {
                $stmt = $db->prepare("
                    INSERT INTO users (fullname, phone, password, role)
                    SELECT ?, ?, ?, 'admin'
                    WHERE NOT EXISTS (SELECT 1 FROM users WHERE role = 'admin')
                ");
                $stmt->execute([$fullname, $phone, password_hash($password, PASSWORD_DEFAULT)]);

                if ($stmt->rowCount() === 1) {
                    unset($_SESSION['admin_setup_token']);
                    $success = true;
                    $admin_count = 1;
                } else {
                    $admin_count = 1;
                    $error = 'Admin setup is already complete.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $error = 'That phone number is already registered.';
                } else {
                    throw $e;
                }
            }
        }
    }
}

require 'includes/header.php';
?>

<div class="form-box">
    <h1>Initial Admin Setup</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">Admin account created. <a href="login.php">Log in</a>.</div>
    <?php elseif ($admin_count > 0): ?>
        <p>Admin setup is complete. Ask an admin to create additional admin accounts.</p>
    <?php elseif (!$setup_enabled): ?>
        <p>Set the <code>ADMIN_SETUP_KEY</code> environment variable to a secret of at least 32 characters, then restart the web server.</p>
    <?php else: ?>
        <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['admin_setup_token']) ?>">

            <label for="fullname">Full Name</label>
            <input type="text" id="fullname" name="fullname" required autocomplete="name">

            <label for="phone">Phone Number (10 digits)</label>
            <input type="tel" id="phone" name="phone" required inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10" autocomplete="tel">

            <label for="password">Password (at least 12 characters)</label>
            <input type="password" id="password" name="password" required minlength="12" autocomplete="new-password">

            <label for="setup_key">Setup Key</label>
            <input type="password" id="setup_key" name="setup_key" required autocomplete="off">

            <button type="submit" class="btn btn-primary">Create Admin</button>
        </form>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>
