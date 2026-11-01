<?php
/**
 * کد تخفیف زمان‌دار + توابع تقویم شمسی
 */
date_default_timezone_set('Asia/Tehran');

// ───────────── تقویم شمسی ─────────────
function g2j($gy, $gm, $gd) {
    $g_d_m = [0,31,59,90,120,151,181,212,243,273,304,334];
    $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
    $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
    $jy = -1595 + (33 * intdiv($days, 12053));
    $days %= 12053;
    $jy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) { $jy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    if ($days < 186) { $jm = 1 + intdiv($days, 31); $jd = 1 + ($days % 31); }
    else { $jm = 7 + intdiv($days - 186, 30); $jd = 1 + (($days - 186) % 30); }
    return [$jy, $jm, $jd];
}
function j2g($jy, $jm, $jd) {
    $jy += 1595;
    $days = -355668 + (365 * $jy) + (intdiv($jy, 33) * 8) + intdiv(($jy % 33) + 3, 4) + $jd + (($jm < 7) ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
    $gy = 400 * intdiv($days, 146097);
    $days %= 146097;
    if ($days > 36524) {
        $gy += 100 * intdiv(--$days, 36524);
        $days %= 36524;
        if ($days >= 365) $days++;
    }
    $gy += 4 * intdiv($days, 1461);
    $days %= 1461;
    if ($days > 365) { $gy += intdiv($days - 1, 365); $days = ($days - 1) % 365; }
    $gd = $days + 1;
    $sal = [0,31,((($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0)) ? 29 : 28,31,30,31,30,31,31,30,31,30,31];
    for ($gm = 0; $gm < 13 && $gd > $sal[$gm]; $gm++) $gd -= $sal[$gm];
    return [$gy, $gm, $gd];
}
function fa_digits($s) {
    return strtr((string)$s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}
function en_digits($s) {
    return strtr((string)$s, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9','٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
}
/** رشته میلادی (Y-m-d [H:i:s]) → رشته شمسی 1405/07/21 */
function jalali_str($dt, $persian = true) {
    if (!$dt) return '';
    $ts = strtotime($dt);
    [$y, $m, $d] = g2j((int)date('Y', $ts), (int)date('n', $ts), (int)date('j', $ts));
    $s = sprintf('%04d/%02d/%02d', $y, $m, $d);
    return $persian ? fa_digits($s) : $s;
}
/** رشته شمسی 1405/07/21 → Y-m-d میلادی (یا null اگر نامعتبر) */
function jalali_to_date($str) {
    $str = en_digits(trim($str));
    if (!preg_match('/^(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})$/', $str, $m)) return null;
    [$jy, $jm, $jd] = [(int)$m[1], (int)$m[2], (int)$m[3]];
    if ($jm < 1 || $jm > 12 || $jd < 1 || $jd > ($jm <= 6 ? 31 : ($jm <= 11 ? 30 : 30))) return null;
    [$gy, $gm, $gd] = j2g($jy, $jm, $jd);
    if (g2j($gy, $gm, $gd) !== [$jy, $jm, $jd]) return null;   // مثلاً ۳۰ اسفند در سال کبیسه نبودن
    return sprintf('%04d-%02d-%02d', $gy, $gm, $gd);
}

// ───────────── ساخت جدول‌ها (اگر وجود نداشته باشند) ─────────────
function coupons_ensure(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            code VARCHAR(50) NOT NULL UNIQUE,
            title VARCHAR(200) DEFAULT NULL,
            type ENUM('percent','amount') NOT NULL DEFAULT 'percent',
            value DECIMAL(15,0) NOT NULL DEFAULT 0,
            starts_at DATETIME NOT NULL,
            ends_at DATETIME NOT NULL,
            max_uses INT NULL DEFAULT NULL,
            used_count INT NOT NULL DEFAULT 0,
            status TINYINT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        $pdo->exec("CREATE TABLE IF NOT EXISTS coupon_products (
            coupon_id INT NOT NULL,
            product_id INT NOT NULL,
            PRIMARY KEY (coupon_id, product_id),
            KEY idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
        $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('coupon_code', $cols))     $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_code VARCHAR(50) NULL");
        if (!in_array('coupon_discount', $cols)) $pdo->exec("ALTER TABLE orders ADD COLUMN coupon_discount DECIMAL(15,0) NOT NULL DEFAULT 0");
    } catch (Exception $e) { /* بی‌صدا */ }
}

function coupon_state(array $c) {
    $now = date('Y-m-d H:i:s');
    if (!$c['status']) return 'inactive';
    if ($now < $c['starts_at']) return 'upcoming';
    if ($now > $c['ends_at']) return 'expired';
    if ($c['max_uses'] !== null && $c['used_count'] >= $c['max_uses']) return 'used_up';
    return 'active';
}

/**
 * ارزیابی کد تخفیف روی اقلام سبد. $items: هر آیتم دارای id و subtotal
 * خروجی: ['ok'=>bool, 'error'=>string, 'discount'=>int, 'coupon'=>array|null]
 */
function coupon_evaluate(PDO $pdo, $code, array $items) {
    $fail = fn($m) => ['ok' => false, 'error' => $m, 'discount' => 0, 'coupon' => null];
    $code = strtoupper(trim(en_digits($code)));
    if ($code === '') return $fail('کد تخفیف را وارد کنید');
    coupons_ensure($pdo);
    $st = $pdo->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $st->execute([$code]);
    $c = $st->fetch();
    if (!$c) return $fail('کد تخفیف نامعتبر است');
    switch (coupon_state($c)) {
        case 'inactive': return $fail('این کد تخفیف غیرفعال است');
        case 'upcoming': return $fail('زمان استفاده از این کد هنوز شروع نشده است (از ' . jalali_str($c['starts_at']) . ')');
        case 'expired':  return $fail('مهلت استفاده از این کد تمام شده است');
        case 'used_up':  return $fail('ظرفیت استفاده از این کد تکمیل شده است');
    }
    $st = $pdo->prepare("SELECT product_id FROM coupon_products WHERE coupon_id = ?");
    $st->execute([$c['id']]);
    $allowed = array_flip($st->fetchAll(PDO::FETCH_COLUMN));
    $base = 0;
    foreach ($items as $it) if (isset($allowed[$it['id']])) $base += (int)$it['subtotal'];
    if ($base <= 0) return $fail('این کد تخفیف برای هیچ‌کدام از کالاهای سبد شما قابل استفاده نیست');
    $disc = $c['type'] === 'percent' ? (int)round($base * min(100, (int)$c['value']) / 100) : min((int)$c['value'], $base);
    return ['ok' => true, 'error' => '', 'discount' => $disc, 'coupon' => $c];
}
