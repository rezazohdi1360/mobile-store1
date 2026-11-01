<?php
/**
 * توابع محاسبه هزینه ارسال
 */

const SHIPPING_DEFAULT_COST = 200000;   // هزینه ارسال پیش‌فرض همه مناطق (تومان) — از پنل «تنظیمات ارسال» قابل تغییر است

function shipping_provinces() {
    return ['تهران','البرز','اصفهان','فارس','خراسان رضوی','آذربایجان شرقی','آذربایجان غربی','گیلان','مازندران','خوزستان','کرمان','یزد','قم','مرکزی','همدان','کرمانشاه','گلستان','سیستان و بلوچستان','هرمزگان','بوشهر','اردبیل','زنجان','قزوین','لرستان','چهارمحال و بختیاری','کهگیلویه و بویراحمد','سمنان','خراسان شمالی','خراسان جنوبی','ایلام','کردستان'];
}

/** ساخت جدول و مقداردهی هزینه ارسال ۲۰۰,۰۰۰ تومان برای همه (یک‌بار برای سایت‌های موجود) */
function shipping_ensure(PDO $pdo) {
    try {
        site_settings_ensure($pdo);
        $pdo->exec("CREATE TABLE IF NOT EXISTS shipping_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value VARCHAR(100) NOT NULL,
            setting_label VARCHAR(200),
            setting_group VARCHAR(50),
            sort_order INT DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        if (site_get($pdo, 'shipping_v2', '') === '1') return;

        $rows = [
            ['base_post_express', '200000', 'پست پیشتاز', 'base_cost', 1],
            ['base_post_normal',  '200000', 'پست عادی', 'base_cost', 2],
            ['base_freight',      '200000', 'باربری', 'base_cost', 3],
            ['base_cod',          '200000', 'پرداخت در محل', 'base_cost', 4],
            ['free_shipping_threshold', '0', 'ارسال رایگان برای خرید بالاتر از (تومان) — ۰ یعنی غیرفعال', 'free_shipping', 5],
        ];
        foreach (shipping_provinces() as $n => $prov) $rows[] = ['factor_' . $prov, '1.0', 'ضریب ' . $prov, 'province_factor', 10 + $n];
        $st = $pdo->prepare("INSERT INTO shipping_settings (setting_key, setting_value, setting_label, setting_group, sort_order)
                             VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), setting_label = VALUES(setting_label)");
        foreach ($rows as $r) $st->execute($r);
        site_set($pdo, 'shipping_v2', '1');
    } catch (Exception $e) { /* بی‌صدا */ }
}

/** هزینه ارسال یک سفارش: $payable = مبلغ کالاها پس از تخفیف */
function shipping_quote($province, $payable) {
    if (isFreeShipping($payable)) return 0;
    return calculateShippingCost($province ?: 'تهران', 'post_express');
}

/** کمترین و بیشترین هزینه ارسال بین استان‌ها (برای نمایش در سبد خرید که هنوز استان معلوم نیست) */
function shipping_range($payable) {
    $c = array_map(fn($p) => shipping_quote($p, $payable), shipping_provinces());
    return [min($c), max($c)];
}

function getShippingSetting($key, $default = 0) {
    global $pdo;
    static $cache = [];
    
    if (isset($cache[$key])) return $cache[$key];
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM shipping_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        $cache[$key] = ($value === false) ? $default : $value;
        return $cache[$key];
    } catch (Exception $e) {
        return $default;
    }
}

function calculateShippingCost($province, $method = 'post_express') {
    $baseKeys = [
        'post_express' => 'base_post_express',
        'post_normal'  => 'base_post_normal',
        'freight'      => 'base_freight',
        'cod'          => 'base_cod',
    ];
    
    $baseKey = $baseKeys[$method] ?? 'base_post_express';
    $base = (int)getShippingSetting($baseKey, SHIPPING_DEFAULT_COST);
    if ($base === 0) return 0;
    
    $factorKey = 'factor_' . $province;
    $factor = (float)getShippingSetting($factorKey, 1.0);
    $cost = $base * $factor;
    
    return (int)(round($cost / 1000) * 1000);
}

function isFreeShipping($cartTotal) {
    $threshold = (int)getShippingSetting('free_shipping_threshold', 0);
    return $threshold > 0 && $cartTotal >= $threshold;
}