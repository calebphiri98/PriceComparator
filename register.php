<?php
require 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = preg_replace('/\D/', '', $_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'farmer';

    if ($fullname === '' || $phone === '' || $password === '') {
        $error = "All fields are required.";
    } elseif (strlen($phone) !== 10) {
        $error = "Phone number must be exactly 10 digits (for example 0888123456).";
    } elseif (!in_array($role, ['farmer', 'buyer'], true)) {
        $error = "Please choose a valid account type.";
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        try {
            $stmt = $db->prepare("INSERT INTO users (fullname, phone, password, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$fullname, $phone, $hashed, $role]);
            $success = "Registration successful! You can now login.";
        } catch (PDOException $e) {
            $error = "Phone number already registered.";
        }
    }
}

$page_title = "Register";
require 'includes/header.php';
?>

<div class="form-box">
    <h1>Create Account</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <form method="POST">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" required placeholder="Enter your full name"
               value="<?= htmlspecialchars($_POST['fullname'] ?? '') ?>">

        <label for="phone">Phone Number (10 digits)</label>
        <input type="tel" id="phone" name="phone" required placeholder="0888123456"
               inputmode="numeric" pattern="[0-9]{10}" maxlength="10" minlength="10"
               title="Enter exactly 10 digits, numbers only"
               oninput="this.value=this.value.replace(/\D/g,'')"
               value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">

        <label for="role">I am a:</label>
        <select id="role" name="role" required>
            <option value="farmer">Farmer</option>
            <option value="buyer"<?= ($_POST['role'] ?? '') === 'buyer' ? ' selected' : '' ?>>Buyer</option>
        </select>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required placeholder="Create a password">

        <button type="submit" class="btn btn-primary">Register</button>
    </form>
    <p style="margin-top:18px; text-align:center;">
        Already have an account? <a href="login.php" style="font-weight:600;">Login here</a>
    </p>
</div>

<?php require 'includes/footer.php'; ?>