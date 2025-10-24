<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

$loginError = '';
$registerError = '';
$infoMessage = '';

if (isset($_SESSION['user_type'])) {
    if ($_SESSION['user_type'] === 'customer') {
        header('Location: user/dashboard.php');
        exit;
    }
    if ($_SESSION['user_type'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['login'])) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($username === '' || $password === '') {
            $loginError = 'Please provide both username and password.';
        } elseif (!loginUser($username, $password)) {
            $loginError = 'Invalid username or password.';
        } else {
            header('Location: user/dashboard.php');
            exit;
        }
    }

    if (isset($_POST['register'])) {
        $username = trim((string) ($_POST['new_username'] ?? ''));
        $password = (string) ($_POST['new_password'] ?? '');
        $fullname = trim((string) ($_POST['fullname'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $address = trim((string) ($_POST['address'] ?? ''));

        if ($username === '' || $password === '' || $fullname === '' || $phone === '' || $address === '') {
            $registerError = 'Please fill in all required fields.';
        } else {
            try {
                if (registerUser($username, $password, $fullname, $phone, $address)) {
                    loginUser($username, $password);
                    header('Location: user/dashboard.php');
                    exit;
                } else {
                    $registerError = 'Registration failed. Please try a different username.';
                }
            } catch (Throwable $e) {
                $registerError = 'Registration failed: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Cafe Management - Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <div class="admin-btn">
            <a href="admin_login.php">Admin Login</a>
        </div>

        <h1>☕ Simplified Cafe Management System</h1>

        <?php if ($infoMessage !== ''): ?>
            <p class="message text-center"><?= htmlspecialchars($infoMessage, ENT_QUOTES); ?></p>
        <?php endif; ?>

        <div class="form-container">
            <div class="card">
                <form method="POST">
                    <h2>👤 User Login</h2>
                    <?php if ($loginError !== ''): ?>
                        <p class="error"><?= htmlspecialchars($loginError, ENT_QUOTES); ?></p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter your username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" name="login">Login</button>
                </form>
            </div>

            <div class="card">
                <form method="POST">
                    <h2>📝 User Registration</h2>
                    <?php if ($registerError !== ''): ?>
                        <p class="error"><?= htmlspecialchars($registerError, ENT_QUOTES); ?></p>
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required>
                    </div>
                    <div class="form-group">
                        <label for="new_username">Username</label>
                        <input type="text" id="new_username" name="new_username" placeholder="Choose a username" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Password</label>
                        <input type="password" id="new_password" name="new_password" placeholder="Choose a password" required>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" placeholder="Enter your phone number" required>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" placeholder="Enter your address" required></textarea>
                    </div>
                    <button type="submit" name="register">Register</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
