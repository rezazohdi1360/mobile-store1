<?php
$host = 'localhost';
$dbname = 'mobile_db';
$username = 'rezazohdi';
$password = '1328';

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>تست</title>";
echo "<style>body{font-family:tahoma;background:#f8fafc;padding:30px;}.box{max-width:700px;margin:auto;background:#fff;padding:30px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}.ok{color:#16a34a;background:#dcfce7;padding:10px;border-radius:8px;margin:5px 0;}.err{color:#dc2626;background:#fee;padding:10px;border-radius:8px;margin:5px 0;}pre{background:#0f172a;color:#0f0;padding:15px;border-radius:8px;direction:ltr;}</style></head><body><div class='box'>";
echo "<h1>🔌 تست اتصال</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    echo "<div class='ok'>✅ اتصال موفق</div>";
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "<div class='ok'>✅ تعداد جداول: " . count($tables) . "</div>";
    echo "<pre>";
    foreach ($tables as $t) echo "✓ $t\n";
    echo "</pre>";
    $count = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
    echo "<div class='ok'>✅ تعداد محصولات: $count</div>";
} catch (PDOException $e) {
    echo "<div class='err'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div></body></html>";