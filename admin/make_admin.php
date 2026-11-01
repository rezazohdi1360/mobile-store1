<?php
require_once 'config/database.php';

$email = 'admin@mobile-store.ir';
$password = 'admin123';
$name = 'مدیر سیستم';

$hashed = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare("DELETE FROM users WHERE email = ?")->execute([$email]);

$stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')");
$stmt->execute([$name, $email, $hashed]);

echo "✅ کاربر ادمین ساخته شد:<br>";
echo "📧 ایمیل: <strong>$email</strong><br>";
echo "🔑 رمز: <strong>$password</strong><br>";
echo "<br><a href='login.php'>ورود به پنل</a>";
echo "<br><br>⚠️ <strong style='color:red;'>بعد از ورود، این فایل را حذف کنید!</strong>";