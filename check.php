<?php
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:tahoma;direction:rtl;font-size:16px;'>";

echo "<h2>📋 ستون‌های جدول products:</h2>";
$cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
$hasExternalId = false;
foreach ($cols as $c) {
    if ($c['Field'] === 'external_id') {
        echo "✅ {$c['Field']} — {$c['Type']}\n";
        $hasExternalId = true;
    } else {
        echo "   {$c['Field']} — {$c['Type']}\n";
    }
}

echo "\n";
if ($hasExternalId) {
    echo "🎉 عالی! ستون external_id آماده است.\n";
    echo "➡️ حالا <a href='import.php'>import.php</a> را اجرا کنید.\n";
} else {
    echo "❌ هنوز ستون external_id وجود ندارد!\n";
    echo "دستور ALTER TABLE را اجرا کنید.\n";
}

echo "\n📦 تعداد محصولات فعلی: " . $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
echo "\n</pre>";