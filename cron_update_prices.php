<?php
/**
 * آپدیت قیمت‌ها از دیجی‌کالا
 * اجرا با cron یا مرورگر
 */
set_time_limit(0);
ini_set('memory_limit', '512M');
require_once 'config/database.php';

$logFile = __DIR__ . '/price_update_log.txt';
$batchSize = 5;

function logMsg($msg) {
    global $logFile;
    $line = date('[Y-m-d H:i:s] ') . $msg . "\n";
    file_put_contents($logFile, $line, FILE_APPEND);
    echo nl2br($line);
    flush();
}

function fetchProductPage($digikalaId) {
    $url = "https://www.digikala.com/product/dkp-{$digikalaId}/";
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_ENCODING => 'gzip, deflate, br',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: fa-IR,fa;q=0.9,en;q=0.8',
            'Sec-Ch-Ua-Mobile: ?0',
            'Sec-Ch-Ua-Platform: "Windows"',
        ],
    ]);
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['html' => $html, 'httpCode' => $httpCode, 'size' => strlen($html)];
}

function extractPrice($html) {
    if (preg_match('/<script id="__NEXT_DATA__" type="application\/json">(.+?)<\/script>/s', $html, $m)) {
        $json = json_decode($m[1], true);
        if ($json && isset($json['props']['pageProps']['product'])) {
            $product = $json['props']['pageProps']['product'];
            if (isset($product['default_variant']['price'])) {
                $price = $product['default_variant']['price'];
                $selling = isset($price['selling_price']) ? (int)($price['selling_price'] / 10) : null;
                $rrp = isset($price['rrp_price']) ? (int)($price['rrp_price'] / 10) : null;
                return [
                    'title' => $product['title_fa'] ?? null,
                    'price' => $rrp ?: $selling,
                    'sale_price' => ($rrp && $selling && $selling < $rrp) ? $selling : null,
                ];
            }
        }
    }
    return null;
}

header('Content-Type: text/html; charset=utf-8');
echo "<pre style='direction:ltr;background:#111;color:#0f0;padding:20px;'>";

logMsg("=== شروع آپدیت ===");

$stmt = $pdo->prepare("
    SELECT id, digikala_id, name, price, sale_price 
    FROM products 
    WHERE digikala_id IS NOT NULL AND digikala_id != '' AND status = 1
    ORDER BY RAND() LIMIT $batchSize
");
$stmt->execute();
$products = $stmt->fetchAll();

logMsg("📦 تعداد: " . count($products));

$updated = 0; $errors = 0; $noChange = 0;

foreach ($products as $p) {
    logMsg("→ درخواست: {$p['digikala_id']}");
    $result = fetchProductPage($p['digikala_id']);
    logMsg("  کد: {$result['httpCode']}، حجم: " . number_format($result['size']) . " بایت");

    if ($result['httpCode'] !== 200 || $result['size'] < 5000) {
        $errors++; logMsg("  ❌ خطا"); sleep(3); continue;
    }

    $data = extractPrice($result['html']);
    if (!$data || !$data['price']) {
        $errors++; logMsg("  ⚠️ قیمت پیدا نشد"); sleep(3); continue;
    }

    $newPrice = $data['price'];
    $newSalePrice = $data['sale_price'];
    $discount = 0;
    if ($newSalePrice && $newPrice > 0) {
        $discount = round((($newPrice - $newSalePrice) / $newPrice) * 100);
    }

    if ($p['price'] != $newPrice || $p['sale_price'] != $newSalePrice) {
        $pdo->prepare("UPDATE products SET price=?, sale_price=?, discount_percent=?, last_price_update=NOW() WHERE id=?")
            ->execute([$newPrice, $newSalePrice, $discount, $p['id']]);
        $updated++;
        logMsg("  ✅ " . number_format($newPrice));
    } else {
        $noChange++;
        $pdo->prepare("UPDATE products SET last_price_update=NOW() WHERE id=?")->execute([$p['id']]);
        logMsg("  ➖ بدون تغییر");
    }
    sleep(5);
}

logMsg("=== پایان: آپدیت $updated، بدون تغییر $noChange، خطا $errors ===");
echo "</pre>";