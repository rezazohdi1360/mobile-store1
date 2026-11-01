<?php
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>
body{font-family:tahoma;direction:rtl;background:#111;color:#0f0;padding:20px;line-height:2;}
table{border-collapse:collapse;margin:10px 0;}
td,th{border:1px solid #333;padding:8px 14px;}
th{background:#222;color:#0ff;}
.ok{color:#0f0;font-weight:bold;}
.err{color:#f55;font-weight:bold;}
.warn{color:#fb0;font-weight:bold;}
h2{color:#0ff;border-bottom:1px solid #333;padding-bottom:8px;}
</style></head><body>";

echo "<h2>🔍 تشخیص مشکل external_id</h2>";

// ۱. کدام دیتابیس متصل است؟
$db = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo "<p>📁 دیتابیس متصل: <span class='ok'>$db</span></p>";

// ۲. لیست جدول‌ها
echo "<h2>📋 جدول‌های موجود</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "<table><tr><th>#</th><th>نام جدول</th></tr>";
foreach ($tables as $i => $t) {
    $cls = ($t === 'products') ? 'ok' : '';
    echo "<tr><td>" . ($i+1) . "</td><td class='$cls'>$t</td></tr>";
}
echo "</table>";

// ۳. آیا جدول products وجود دارد؟
if (!in_array('products', $tables)) {
    echo "<p class='err'>❌ جدول products وجود ندارد! مشکل اینجاست.</p>";
    echo "<p>➡️ باید ابتدا جداول ساخته شوند. فایل database.sql را بررسی کنید.</p>";
    echo "</body></html>";
    exit;
}

// ۴. ستون‌های products
echo "<h2>📊 ستون‌های جدول products</h2>";
$cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll();
echo "<table><tr><th>#</th><th>ستون</th><th>نوع</th><th>Null</th><th>Key</th></tr>";
$hasExt = false;
foreach ($cols as $i => $c) {
    $cls = ($c['Field'] === 'external_id') ? 'ok' : '';
    if ($c['Field'] === 'external_id') $hasExt = true;
    echo "<tr class='$cls'><td>" . ($i+1) . "</td><td>{$c['Field']}</td><td>{$c['Type']}</td><td>{$c['Null']}</td><td>{$c['Key']}</td></tr>";
}
echo "</table>";

// ۵. نتیجه
echo "<h2>🎯 نتیجه</h2>";
if ($hasExt) {
    echo "<p class='ok'>✅ ستون external_id وجود دارد! اگر باز خطا می‌دهد:</p>";
    echo "<ul>";
    echo "<li>Cache مرورگر را پاک کنید (Ctrl+F5)</li>";
    echo "<li>MySQL را ری‌استارت کنید</li>";
    echo "<li>مطمئن شوید config/database.php به همین دیتابیس وصل است</li>";
    echo "</ul>";
} else {
    echo "<p class='err'>❌ ستون external_id وجود ندارد. این را اجرا کنید:</p>";
    echo "<pre style='background:#000;padding:15px;color:#0f0;direction:ltr;'>";
    echo "ALTER TABLE products ADD COLUMN external_id VARCHAR(191) NULL UNIQUE AFTER id;";
    echo "</pre>";
}

echo "</body></html>";