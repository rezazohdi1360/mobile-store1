<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>تست پنل ادمین</h1>";

try {
    require_once '../config/database.php';
    echo "<p>✅ database.php لود شد</p>";
} catch (Exception $e) {
    die("<p>❌ خطا در database.php: " . $e->getMessage() . "</p>");
}

try {
    require_once '../includes/functions.php';
    echo "<p>✅ functions.php لود شد</p>";
} catch (Exception $e) {
    die("<p>❌ خطا در functions.php: " . $e->getMessage() . "</p>");
}

echo "<p>✅ Session: " . session_status() . "</p>";
echo "<p>✅ isAdmin(): " . (isAdmin() ? 'true' : 'false') . "</p>";

try {
    $result = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<p>✅ جدول products: $result ردیف</p>";
} catch (Exception $e) {
    echo "<p>❌ خطا: " . $e->getMessage() . "</p>";
}

echo "<h2>✅ همه چیز کار می‌کنه!</h2>";
echo '<p><a href="index.php">ورود به داشبورد</a></p>';