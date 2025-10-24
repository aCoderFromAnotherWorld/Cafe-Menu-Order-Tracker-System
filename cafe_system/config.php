<?php
declare(strict_types=1);

session_start();

$host = '127.0.0.1';
$user = 'root';
$pass = '';
$db   = 'cafe_management';
$port = 3306;

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die('Failed to connect to database: ' . htmlspecialchars($conn->connect_error));
}

$conn->set_charset('utf8mb4');
