<?php
/**
 * خواندن محصولات از پوشه‌ها
 *
 *   products/            ← فایل اطلاعات هر محصول (.json)   مثل: products/dkp-4019035.json
 *   images/<نام محصول>/  ← عکس‌های محصول: 01.jpg (عکس اصلی) تا 05.jpg
 *
 * نام پوشه عکس = «نام محصول» (فارسی) همان‌طور که در فایل اطلاعات نوشته شده است.
 */
define('PF_PRODUCTS_DIR', realpath(__DIR__ . '/..') . '/products');
define('PF_IMAGES_DIR',   realpath(__DIR__ . '/..') . '/images');
const PF_MAX_IMAGES = 5;
const PF_EXTS = ['jpg', 'jpeg', 'png', 'webp'];   // jpg اولویت دارد

/** نرمال‌سازی نام برای مقایسه (فاصله، نیم‌فاصله، ی/ک عربی، ارقام، حروف کوچک، کاراکترهای ممنوعه ویندوز) */
function pf_norm($s) {
    $s = strtr((string)$s, ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه',
        '۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9',
        '٠'=>'0','١'=>'1','٢'=>'2','٣'=>'3','٤'=>'4','٥'=>'5','٦'=>'6','٧'=>'7','٨'=>'8','٩'=>'9']);
    $s = mb_strtolower($s, 'UTF-8');
    return preg_replace('/[\s\x{200c}\x{200d}\x{200f}\x{200e}\\\\\/:*?"<>|\-_.,،()\[\]]+/u', '', $s);
}

/** فهرست پوشه‌های عکس: [نام نرمال‌شده => نام واقعی پوشه] */
function pf_image_dirs() {
    static $idx = null;
    if ($idx !== null) return $idx;
    $idx = [];
    if (is_dir(PF_IMAGES_DIR)) {
        foreach (scandir(PF_IMAGES_DIR) as $d) {
            if ($d === '.' || $d === '..' || !is_dir(PF_IMAGES_DIR . '/' . $d)) continue;
            $idx[pf_norm($d)] = $d;
        }
    }
    return $idx;
}

/** پیدا کردن پوشه عکس یک محصول بر اساس نام فارسی (و در صورت نبودن، شناسه) */
function pf_find_dir($name, $externalId = '') {
    $idx = pf_image_dirs();
    if (!$idx) return null;
    if (is_dir(PF_IMAGES_DIR . '/' . $name)) return $name;               // دقیقاً هم‌نام
    $n = pf_norm($name);
    if ($n !== '' && isset($idx[$n])) return $idx[$n];                    // با نرمال‌سازی
    // ویندوز نام‌های طولانی را کوتاه می‌کند؛ تطبیق پیشوندی (حداقل ۱۵ حرف)
    foreach ($idx as $dn => $real) {
        if (mb_strlen($dn) >= 15 && $n !== '' && (strpos($n, $dn) === 0 || strpos($dn, $n) === 0)) return $real;
    }
    if ($externalId !== '' && isset($idx[pf_norm($externalId)])) return $idx[pf_norm($externalId)];
    return null;
}

/** عکس‌های 01 تا 05 یک پوشه؛ خروجی: مسیرهای نسبی کدگذاری‌شده برای وب */
function pf_images($dirName) {
    $out = [];
    $base = PF_IMAGES_DIR . '/' . $dirName;
    // فقط نویسه‌هایی که در آدرس وب مشکل می‌سازند کدگذاری می‌شوند (فارسی و فاصله همان‌طور می‌مانند تا ستون دیتابیس کوتاه بماند)
    $enc = strtr($dirName, ['%' => '%25', '#' => '%23', '?' => '%3F', ',' => '%2C']);
    for ($i = 1; $i <= PF_MAX_IMAGES; $i++) {
        $num = sprintf('%02d', $i);
        foreach (PF_EXTS as $ext) {
            foreach ([$ext, strtoupper($ext)] as $e) {
                if (is_file("$base/$num.$e")) { $out[] = 'images/' . $enc . "/$num.$e"; continue 3; }
            }
        }
    }
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
 * همهٔ محصولات پوشه products (به‌همراه عکس‌های پوشه images).
 * هر آیتم: همان کلیدهای فارسی فایل + _file, _dir (پوشه عکس یا null), _images (لیست مسیرها)
 */
function pf_load_products(&$errors = []) {
    $errors = [];
    $items = [];
    if (!is_dir(PF_PRODUCTS_DIR)) return $items;
    $files = glob(PF_PRODUCTS_DIR . '/*.json') ?: [];
    sort($files, SORT_NATURAL);
    foreach ($files as $f) {
        $list = pf_parse_json((string)file_get_contents($f));
        if ($list === null) { $errors[] = basename($f) . ': فایل JSON معتبر نیست'; continue; }
        foreach ($list as $x) {
            if (trim($x['شناسه'] ?? '') === '') $x['شناسه'] = pathinfo($f, PATHINFO_FILENAME);   // نام فایل = شناسه
            $x['_file'] = basename($f);
            $dir = pf_find_dir(trim($x['نام محصول']), trim($x['شناسه']));
            $x['_dir'] = $dir;
            $x['_images'] = $dir ? pf_images($dir) : [];
            $items[] = $x;
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

    if (!empty($x['_images'])) {                      // عکس‌های پوشه images
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
