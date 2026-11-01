<?php
header('Content-Type: text/html; charset=utf-8');
echo "<pre style='direction:ltr;background:#111;color:#0f0;padding:20px;font-family:monospace;'>";

$file = __DIR__ . '/products.json';
if (!file_exists($file)) die("❌ products.json پیدا نشد");

$content = file_get_contents($file);
echo "حجم فایل: " . number_format(strlen($content) / 1024 / 1024, 2) . " مگابایت\n\n";

$data = json_decode($content, true);
if ($data === null) {
    echo "❌ خطای JSON: " . json_last_error_msg() . "\n";
    echo "کد خطا: " . json_last_error() . "\n\n";
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        echo "⚠️ فایل BOM دارد (UTF-8 BOM) - این باعث خطا می‌شود\n";
    }
} else {
    echo "✅ JSON معتبر است!\n";
    $products = $data['products'] ?? $data;
    echo "تعداد آیتم‌ها: " . count($products) . "\n";
    if (isset($data['last_updated'])) echo "آخرین بروزرسانی: " . $data['last_updated'] . "\n";
}
echo "</pre>";