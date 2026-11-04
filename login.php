<?php
require 'config.php';

$error = '';
$admin_count = (int) $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$setup_key = getenv('ADMIN_SETUP_KEY');
$show_admin_setup = $admin_count === 0 && is_string($setup_key) && strlen($setup_key) >= 32;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($phone) !== 10) {
        $error = "Phone number must be exactly 10 digits.";
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['fullname'] = $user['fullname'];
            $_SESSION['role']     = $user['role'];
            header("Location: index.php");
            exit;
        } else {
            $error = "Wrong phone number or password.";
        }
    }
}

$page_title = "Login";
require 'includes/header.php';
?>

<div class="form-box">
    <h1>Login</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="POST">
        <label for="phone">Phone Number (10 digits)</label>
        <input type="tel" id="phone" name="phone" required placeholder="0888123456"
               inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10"
               title="Enter exactly 10 digits, numbers only"
               oninput="this.value=this.value.replace(/\D/g,'')"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="Your password">

        <button type="submit" class="btn btn-primary">Login</button>
    </form>
    <p style="margin-top:18px; text-align:center;">No account yet? <a href="register.php" style="font-weight:600;">Register here</a></p>
    <?php if ($show_admin_setup): ?>
        <p style="margin-top:10px; text-align:center;"><a href="setup-admin.php">Initial admin setup</a></p>
    <?php endif; ?>
</div>

<?php require 'includes/footer.php'; ?>