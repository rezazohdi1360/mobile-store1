<?php
/**
 * خواندن محصولات از پوشه MP
 *
 *   MP/
 *     <نام محصول>/              ← هر محصول یک پوشه به اسم خودش
 *        <نام محصول>.json       ← فایل اطلاعات محصول
 *        01.jpg … 05.jpg        ← عکس‌های محصول (01 = عکس اصلی)
 *
 * فایل JSON و عکس‌های هر محصول کنار هم، داخل همان پوشه‌اند؛ پس عکس هر محصول
 * مستقیم از پوشه‌ی خودش خوانده می‌شود و نیازی به تطبیق نام نیست.
 */
define('PF_MP_DIR', realpath(__DIR__ . '/..') . '/MP');
const PF_MP_WEB = 'MP';                           // پیشوند مسیر وب عکس‌ها (حروف بزرگ؛ روی لینوکس حساس به حروف است)
const PF_MAX_IMAGES = 5;
const PF_EXTS = ['jpg', 'jpeg', 'png', 'webp'];   // jpg اولویت دارد

/** نام پوشه‌های محصول داخل MP (مرتب‌شده) */
function pf_product_dirs() {
    $out = [];
    if (!is_dir(PF_MP_DIR)) return $out;
    foreach (scandir(PF_MP_DIR) as $d) {
        if ($d === '.' || $d === '..' || !is_dir(PF_MP_DIR . '/' . $d)) continue;
        $out[] = $d;
    }
    sort($out, SORT_NATURAL);
    return $out;
}

/** عکس‌های 01 تا 05 یک پوشه‌ی محصول؛ خروجی: مسیرهای نسبی کدگذاری‌شده برای وب (MP/<نام محصول>/01.jpg) */
function pf_images($dirName) {
    $out = [];
    $base = PF_MP_DIR . '/' . $dirName;
    // فقط نویسه‌هایی که در آدرس وب مشکل می‌سازند کدگذاری می‌شوند (فارسی و فاصله همان‌طور می‌مانند تا ستون دیتابیس کوتاه بماند)
    $enc = strtr($dirName, ['%' => '%25', '#' => '%23', '?' => '%3F', ',' => '%2C']);
    for ($i = 1; $i <= PF_MAX_IMAGES; $i++) {
        $num = sprintf('%02d', $i);
        foreach (PF_EXTS as $ext) {
            foreach ([$ext, strtoupper($ext)] as $e) {
                if (is_file("$base/$num.$e")) { $out[] = PF_MP_WEB . '/' . $enc . "/$num.$e"; continue 3; }
            }
        }
    }
    return $out;
}

/** فایل‌های .json داخل پوشه‌ی یک محصول (بدون glob تا نویسه‌هایی مثل [ ] در نام پوشه مشکل نسازند) */
function pf_json_files($dirName) {
    $out = [];
    $base = PF_MP_DIR . '/' . $dirName;
    foreach (scandir($base) as $f) {
        if (strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'json' && is_file("$base/$f")) $out[] = $f;
    }
    sort($out, SORT_NATURAL);
    return $out;
}

function pf_parse_json($raw) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', trim($raw));
    $raw = preg_replace('/,\s*([\]}])/', '$1', $raw);
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    if (isset($d['نام محصول'])) $d = [$d];
    return array_values(array_filter($d, fn($x) => is_array($x) && trim($x['نام محصول'] ?? '') !== ''));
}

function pf_key(array $x) { return trim($x['شناسه'] ?? '') ?: 'json-' . substr(md5($x['نام محصول']), 0, 10); }

/**
 * همهٔ محصولات پوشه MP (هر پوشه = یک محصول، به‌همراه عکس‌های همان پوشه).
 * هر آیتم: همان کلیدهای فارسی فایل + _file (نام فایل JSON), _dir (نام پوشه محصول), _images (لیست مسیرها؛ خالی اگر عکسی نباشد)
 */
function pf_load_products(&$errors = []) {
    $errors = [];
    $items = [];
    foreach (pf_product_dirs() as $dir) {
        $images = pf_images($dir);                       // عکس‌ها از همان پوشه‌ی محصول
        $files = pf_json_files($dir);
        if (!$files) { $errors[] = "$dir: فایل JSON در پوشه محصول پیدا نشد"; continue; }
        foreach ($files as $fname) {
            $list = pf_parse_json((string)file_get_contents(PF_MP_DIR . '/' . $dir . '/' . $fname));
            if ($list === null) { $errors[] = "$dir/$fname: فایل JSON معتبر نیست"; continue; }
            foreach ($list as $x) {
                if (trim($x['شناسه'] ?? '') === '') $x['شناسه'] = pathinfo($fname, PATHINFO_FILENAME);   // نام فایل = شناسه
                $x['_file']   = $fname;
                $x['_dir']    = $dir;
                $x['_images'] = $images;
                $items[] = $x;
            }
        }
    }
    return $items;
}

function pf_price($v) { return (int)preg_replace('/[^\d]/', '', strtr((string)$v, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9'])); }

/** تبدیل یک آیتم (فایل JSON) به ردیف نرمال‌شدهٔ جدول products */
function pf_row(array $x) {
    $now  = pf_price($x['قیمت (تومان)'] ?? '');
    $orig = pf_price($x['قیمت قبل از تخفیف (تومان)'] ?? '');
    if ($orig > $now && $now > 0) { $price = $orig; $sale = $now; $pct = (int)round(($orig - $now) * 100 / $orig); }
    else { $price = $now ?: $orig; $sale = null; $pct = 0; }
    $avail = $price > 0 && trim($x['وضعیت موجودی'] ?? '') !== 'out_of_stock';

    if (!empty($x['_images'])) {                      // عکس‌های پوشه محصول در MP
        $main = $x['_images'][0];
        $all  = implode(',', $x['_images']);
    } else {                                          // آدرس اینترنتی داخل فایل (اگر باشد)
        $main = trim($x['آدرس عکس اصلی'] ?? '');
        $all  = trim($x['همه تصاویر'] ?? '') ?: $main;
    }
    $desc = trim($x['بررسی تخصصی'] ?? '');

    // مشخصات فنی: «مشخصه: کلید» یا متن «• کلید: مقدار»
    $specs = [];
    foreach ($x as $k => $v) {
        if (strpos($k, 'مشخصه: ') === 0 && trim((string)$v) !== '') $specs[] = [trim(substr($k, strlen('مشخصه: '))), (string)$v];
    }
    if (!$specs && !empty($x['مشخصات فنی'])) {
        foreach (preg_split('/\R/u', (string)$x['مشخصات فنی']) as $line) {
            if (preg_match('/^\s*[•\-\*]\s*([^:：]+)[:：]\s*(.+)$/u', $line, $m)) $specs[] = [trim($m[1]), trim($m[2])];
        }
    }
    return [
        'name' => trim($x['نام محصول']), 'name_en' => trim($x['نام لاتین'] ?? ''), 'brand' => trim($x['برند'] ?? ''),
        'path' => trim($x['دسته‌بندی'] ?? ''), 'price' => $price, 'sale' => $sale, 'pct' => $pct, 'avail' => $avail,
        'rating' => (float)($x['امتیاز'] ?? 0), 'reviews' => (int)($x['تعداد نظرات'] ?? 0),
        'warranty' => trim($x['گارانتی'] ?? ''), 'seller' => trim($x['فروشنده'] ?? ''), 'url' => trim($x['آدرس وب'] ?? ''),
        'img' => $main, 'all' => $all, 'desc' => $desc, 'short' => mb_substr(strip_tags($desc), 0, 490), 'specs' => $specs,
    ];
}
