<?php
/**
 * اتصال به دیتابیس
 */
require_once __DIR__ . '/site.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'mobile_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_persian_ci");
    site_load($pdo);
    require_once __DIR__ . '/../includes/assets.php';
    require_once __DIR__ . '/../includes/theme.php';
    theme_boot($pdo);
    require_once __DIR__ . '/../includes/shipping.php';
    shipping_ensure($pdo);
} catch (PDOException $e) {
    die('<div style="font-family:tahoma;direction:rtl;padding:20px;background:#fee;border:1px solid #f00;border-radius:8px;margin:20px;">
        <h3>❌ خطا در اتصال به دیتابیس</h3>
        <p>' . htmlspecialchars($e->getMessage()) . '</p>
    </div>');
}