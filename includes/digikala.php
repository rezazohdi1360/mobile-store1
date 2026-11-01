<?php
/**
 * به‌روزرسانی قیمت از دی‌جی‌کالا (API عمومی محصول)
 *   GET {base}/v2/product/{id}/  →  data.product.default_variant.price { selling_price, rrp_price }  (ریال)
 */

/** شناسه عددی دی‌جی‌کالا از external_id («dkp-4019035») یا آدرس منبع؛ null اگر نبود */
function dk_id(array $p) {
    foreach ([$p['external_id'] ?? '', $p['source_url'] ?? ''] as $s) {
        if (preg_match('#dkp-(\d+)#i', (string)$s, $m)) return $m[1];
    }
    return null;
}

function dk_conf(PDO $pdo) {
    return [
        'base'  => rtrim(trim(site_get($pdo, 'dk_api_base', '')) ?: 'https://api.digikala.com', '/'),
        'proxy' => trim(site_get($pdo, 'dk_proxy', '')),
    ];
}

/** @return array [httpCode, bodyOrError] */
function dk_fetch($id, array $conf) {
    $ch = curl_init($conf['base'] . '/v2/product/' . $id . '/');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_ENCODING => '',
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Accept-Language: fa-IR,fa;q=0.9'],
    ]);
    if ($conf['proxy'] !== '') curl_setopt($ch, CURLOPT_PROXY, $conf['proxy']);
    $ca = __DIR__ . '/../config/cacert.pem';
    if (is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, $body === false ? $err : $body];
}

/**
 * پاسخ API ← ['title','price','sale','pct','available'] (تومان) یا null اگر ساختار نامعتبر بود
 */
function dk_parse($json) {
    $d = json_decode((string)$json, true);
    $p = $d['data']['product'] ?? null;
    if (!is_array($p)) return null;
    $status = (string)($p['status'] ?? '');
    $v = $p['default_variant'] ?? null;
    $price = is_array($v) ? ($v['price'] ?? null) : null;
    $selling = is_array($price) ? (int)round(((float)($price['selling_price'] ?? 0)) / 10) : 0;
    $rrp     = is_array($price) ? (int)round(((float)($price['rrp_price'] ?? 0)) / 10) : 0;
    $available = ($status === 'marketplace' || $status === '') && $selling > 0;
    if ($rrp > $selling && $selling > 0) { $newPrice = $rrp; $sale = $selling; $pct = (int)round(($rrp - $selling) * 100 / $rrp); }
    else { $newPrice = $selling ?: $rrp; $sale = null; $pct = 0; }
    return ['title' => (string)($p['title_fa'] ?? ''), 'price' => $newPrice, 'sale' => $sale, 'pct' => $pct, 'available' => $available, 'status' => $status];
}

function dk_ensure(PDO $pdo) {
    try {
        $has = false;
        foreach ($pdo->query("SHOW COLUMNS FROM products")->fetchAll() as $c) if (($c['Field'] ?? '') === 'last_price_update') $has = true;
        if (!$has) $pdo->exec("ALTER TABLE products ADD COLUMN last_price_update DATETIME NULL");
    } catch (Throwable $e) { /* ستون اختیاری است */ }
}
