<?php
require_once 'config/database.php';

$hash = password_hash('1328', PASSWORD_DEFAULT);

// چک کن کاربر وجود نداره
$stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
$stmt->execute(['reza']);

if ($stmt->fetch()) {
    // اگه بود، فقط پسورد رو آپدیت کن
    $stmt = $pdo->prepare("UPDATE users SET password = ?, role = 'admin' WHERE username = ?");
    $stmt->execute([$hash, 'reza']);
    echo "🔄 کاربر reza از قبل بود → پسورد به 1328 تغییر کرد";
} else {
    // کاربر جدید بساز
    $stmt = $pdo->prepare("INSERT INTO users (name, username, email, password, role) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute(['رضا', 'reza', 'reza@local', $hash, 'admin']);
    echo "✅ کاربر reza ساخته شد | پسورد: 1328";
}