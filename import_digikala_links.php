<?php
set_time_limit(0);
ini_set('memory_limit', '512M');
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='direction:ltr;background:#111;color:#0f0;padding:20px;'>";

$linksFile = __DIR__ . '/digikala_links.txt';
if (!file_exists($linksFile)) die("❌ digikala_links.txt پیدا نشد");

$content = file_get_contents($linksFile);
preg_match_all('/dkp-(\d+)/', $content, $matches);
$ids = array_unique($matches[1]);
echo "✅ تعداد شناسه: " . count($ids) . "\n\n";

$products = $pdo->query("SELECT id, name FROM products ORDER BY id ASC")->fetchAll();
echo "📦 محصولات در دیتابیس: " . count($products) . "\n\n";

if (count($products) === 0) die("❌ هیچ محصولی نیست. اول import.php را اجرا کنید.");

$updated = 0; $skipped = 0; $i = 0;

foreach ($ids as $digikalaId) {
    if (!isset($products[$i])) { echo "\n⚠️ محصولات تمام شد\n"; break; }

    $check = $pdo->prepare("SELECT id FROM products WHERE digikala_id = ?");
    $check->execute([$digikalaId]);
    if ($check->fetch()) { $skipped++; continue; }

    $stmt = $pdo->prepare("UPDATE products SET digikala_id = ? WHERE id = ?");
    $stmt->execute([$digikalaId, $products[$i]['id']]);
    $updated++; $i++;

    if ($updated % 100 === 0) { echo "📦 پیشرفت: $updated\n"; flush(); }
}

echo "\n════════════════════════════\n";
echo "✅ آپدیت شده: $updated\n";
echo "⏭ نادیده: $skipped\n";
echo "════════════════════════════\n";
echo "</pre>";