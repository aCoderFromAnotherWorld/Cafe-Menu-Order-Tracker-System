<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$admin_username = 'admin';
$admin_password = 'adminadmin';

function registerUser(string $username, string $password, string $fullname, string $phone, string $address): bool
{
    global $conn;

    $username = trim($username);
    $fullname = trim($fullname);
    $phone = trim($phone);
    $address = trim($address);

    if ($username === '' || $password === '' || $fullname === '' || $phone === '' || $address === '') {
        return false;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $sql = 'INSERT INTO users (username, password, fullname, phone, address) VALUES (?, ?, ?, ?, ?)';
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('sssss', $username, $hashed, $fullname, $phone, $address);
    $result = $stmt->execute();
    $stmt->close();

    return $result;
}

function loginUser(string $username, string $password): bool
{
    global $conn;

    $sql = 'SELECT id, username, password, fullname, phone, address FROM users WHERE username = ?';
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param('s', $username);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['fullname'] = $user['fullname'];
        $_SESSION['phone'] = $user['phone'];
        $_SESSION['address'] = $user['address'];
        $_SESSION['user_type'] = 'customer';
        return true;
    }

    return false;
}

function adminLogin(string $username, string $password): bool
{
    global $admin_username, $admin_password;

    if ($username === $admin_username && $password === $admin_password) {
        $_SESSION['user_type'] = 'admin';
        $_SESSION['username'] = $admin_username;
        return true;
    }

    return false;
}

function requireCustomer(): void
{
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'customer') {
        header('Location: ../index.php');
        exit;
    }
}

function requireAdmin(): void
{
    if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        header('Location: ../index.php');
        exit;
    }
}
