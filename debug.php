<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$jsonFile = __DIR__ . '/products.json';
$data = json_decode(file_get_contents($jsonFile), true);
$products = $data['products'] ?? $data;

// شمارش دقیق
$stats = [
    'total' => count($products),
    'no_name' => 0,
    'no_price' => 0,
    'no_ext_id' => 0,
    'ok' => 0,
];

$bad_indices = [];
$good_indices = [];

foreach ($products as $i => $p) {
    $name = trim($p['نام محصول'] ?? '');
    $price = trim($p['قیمت (تومان)'] ?? '');
    $extId = trim($p['شناسه'] ?? '');
    
    // تبدیل اعداد فارسی
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    $priceEn = str_replace($fa, $en, $price);
    $priceNum = (int)preg_replace('/[^\d]/', '', $priceEn);
    
    if (empty($name)) {
        $stats['no_name']++;
        if (count($bad_indices) < 5) $bad_indices[] = $i;
    } else if ($priceNum <= 0) {
        $stats['no_price']++;
        if (count($bad_indices) < 5) $bad_indices[] = $i;
    } else {
        $stats['ok']++;
        if (count($good_indices) < 3) $good_indices[] = $i;
    }
    if (empty($extId)) $stats['no_ext_id']++;
}

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='font-family:tahoma;direction:rtl;font-size:15px;background:#111;color:#0f0;padding:20px;'>";

echo "📊 آمار JSON:\n";
echo "───────────────────────\n";
echo "کل: {$stats['total']}\n";
echo "✅ قابل ایمپورت: {$stats['ok']}\n";
echo "❌ بدون نام: {$stats['no_name']}\n";
echo "❌ بدون قیمت: {$stats['no_price']}\n";
echo "⚠️ بدون شناسه: {$stats['no_ext_id']}\n";

// نمایش محصولات معیوب
echo "\n\n🔴 نمونه محصولات معیوب (ایندکس: " . implode(', ', $bad_indices) . "):\n";
foreach ($bad_indices as $idx) {
    $p = $products[$idx];
    echo "\n─── محصول #$idx ───\n";
    echo "کلیدهای موجود: " . implode(' | ', array_keys($p)) . "\n\n";
    
    // نمایش همه مقادیر
    foreach ($p as $k => $v) {
        $short = is_array($v) ? '[آرایه]' : mb_substr((string)$v, 0, 60);
        echo "  '$k' => '$short'\n";
    }
    break; // فقط اولین معیوب را کامل نشان بده
}

// نمایش محصولات سالم
echo "\n\n🟢 نمونه محصولات سالم (ایندکس: " . implode(', ', $good_indices) . "):\n";
foreach ($good_indices as $idx) {
    $p = $products[$idx];
    echo "\n─── محصول #$idx ───\n";
    echo "نام: '" . ($p['نام محصول'] ?? '❌ نیست') . "'\n";
    echo "قیمت: '" . ($p['قیمت (تومان)'] ?? '❌ نیست') . "'\n";
    echo "شناسه: '" . ($p['شناسه'] ?? '❌ نیست') . "'\n";
}

echo "</pre>";