<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$error = '';

if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_login'])) {
    $username = trim((string) ($_POST['admin_username'] ?? ''));
    $password = (string) ($_POST['admin_password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Provide both admin username and password.';
    } elseif (adminLogin($username, $password)) {
        header('Location: admin/dashboard.php');
        exit;
    } else {
        $error = 'Incorrect admin credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <a href="index.php">&larr; Back to Home</a>

        <div class="card single-form">
            <form method="POST">
                <h2>🔐 Admin Login</h2>
                <?php if ($error !== ''): ?>
                    <p class="error"><?= htmlspecialchars($error, ENT_QUOTES); ?></p>
                <?php endif; ?>
                <div class="form-group">
                    <label for="admin_username">Admin Username</label>
                    <input type="text" id="admin_username" name="admin_username" placeholder="Enter admin username" required>
                </div>
                <div class="form-group">
                    <label for="admin_password">Admin Password</label>
                    <input type="password" id="admin_password" name="admin_password" placeholder="Enter admin password" required>
                </div>
                <button type="submit" name="admin_login">Admin Login</button>
            </form>
        </div>

        <p class="note">💡 Default credentials: <strong>admin / adminadmin</strong></p>
    </div>
</body>
</html>
