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
 * پوشه‌ی MP بدون حساسیت به حروف پیدا می‌شود، پوشه‌ها می‌توانند تو در تو باشند، و عکس‌ها اگر 01..05 نبودند با هر اسمی خوانده می‌شوند.
 */
/** پوشه‌ی MP را بدون حساسیت به حروف پیدا می‌کند (MP / mp / Mp ...) چون روی هاست لینوکس این‌ها سه پوشه‌ی جدا هستند */
function pf_resolve_mp() {
    $root = realpath(__DIR__ . '/..');
    if (is_dir("$root/MP")) return ['MP', "$root/MP"];
    foreach (scandir($root) as $d) {
        if ($d[0] !== '.' && strcasecmp($d, 'MP') === 0 && is_dir("$root/$d")) return [$d, "$root/$d"];
    }
    return ['MP', "$root/MP"];                         // وجود ندارد؛ نام پیش‌فرض
}
[$__mpName, $__mpPath] = pf_resolve_mp();
define('PF_MP_DIR', $__mpPath);
define('PF_MP_WEB', $__mpName);                        // پیشوند مسیر وب عکس‌ها = نام واقعی پوشه روی سرور
unset($__mpName, $__mpPath);
const PF_MAX_IMAGES = 5;
const PF_EXTS = ['jpg', 'jpeg', 'png', 'webp'];       // jpg اولویت دارد

/**
 * پوشه‌های محصول داخل MP، در هر عمقی: هر پوشه‌ای که خودش فایل .json داشته باشد یک محصول است.
 * خروجی: مسیر نسبی نسبت به MP (مثلاً «نام محصول» یا «دسته/نام محصول»). '' یعنی خود MP (فایل JSON مستقیم داخل MP).
 */
function pf_product_dirs() {
    $out = [];
    if (!is_dir(PF_MP_DIR)) return $out;
    $walk = function ($rel) use (&$walk, &$out) {
        $abs = PF_MP_DIR . ($rel === '' ? '' : '/' . $rel);
        $names = @scandir($abs) ?: [];
        $hasJson = false; $sub = [];
        foreach ($names as $n) {
            if ($n === '.' || $n === '..' || $n[0] === '.') continue;
            if (is_dir("$abs/$n")) $sub[] = $n;
            elseif (strtolower(pathinfo($n, PATHINFO_EXTENSION)) === 'json') $hasJson = true;
        }
        if ($hasJson) $out[] = $rel;
        natcasesort($sub);
        foreach ($sub as $n) $walk($rel === '' ? $n : "$rel/$n");
    };
    $walk('');
    return $out;
}

/** مسیر وب هر بخش کدگذاری می‌شود؛ فقط نویسه‌هایی که در آدرس مشکل می‌سازند (فارسی و فاصله همان‌طور می‌مانند تا ستون دیتابیس کوتاه بماند) */
function pf_web_path($rel, $file) {
    $enc = implode('/', array_map(fn($s) => strtr($s, ['%' => '%25', '#' => '%23', '?' => '%3F', ',' => '%2C']), explode('/', $rel)));
    return PF_MP_WEB . '/' . ($rel === '' ? '' : $enc . '/') . strtr($file, ['%' => '%25', '#' => '%23', '?' => '%3F', ',' => '%2C']);
}

/**
 * عکس‌های یک پوشه‌ی محصول (حداکثر ۵): اول 01 تا 05 (jpg/jpeg/png/webp)؛
 * اگر هیچ‌کدام نبود، هر عکس دیگری که با هر اسمی در پوشه باشد (مرتب‌شده).
 */
function pf_images($rel) {
    $out = [];
    $base = PF_MP_DIR . ($rel === '' ? '' : '/' . $rel);
    for ($i = 1; $i <= PF_MAX_IMAGES; $i++) {
        $num = sprintf('%02d', $i);
        foreach (PF_EXTS as $ext) {
            foreach ([$ext, strtoupper($ext)] as $e) {
                if (is_file("$base/$num.$e")) { $out[] = pf_web_path($rel, "$num.$e"); continue 3; }
            }
        }
    }
    if (!$out && is_dir($base)) {
        $all = [];
        foreach (scandir($base) as $f) {
            if ($f[0] !== '.' && is_file("$base/$f") && in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), PF_EXTS, true)) $all[] = $f;
        }
        natcasesort($all);
        foreach (array_slice(array_values($all), 0, PF_MAX_IMAGES) as $f) $out[] = pf_web_path($rel, $f);
    }
    return $out;
}

/** فایل‌های .json داخل پوشه‌ی یک محصول (بدون glob تا نویسه‌هایی مثل [ ] در نام پوشه مشکل نسازند) */
function pf_json_files($rel) {
    $out = [];
    $base = PF_MP_DIR . ($rel === '' ? '' : '/' . $rel);
    foreach (scandir($base) as $f) {
        if ($f[0] !== '.' && strtolower(pathinfo($f, PATHINFO_EXTENSION)) === 'json' && is_file("$base/$f")) $out[] = $f;
    }
    natcasesort($out);
    return array_values($out);
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
        $images = $dir === '' ? null : pf_images($dir);   // عکس‌ها از همان پوشه‌ی محصول
        foreach (pf_json_files($dir) as $fname) {
            $label = ($dir === '' ? '' : $dir . '/') . $fname;
            $list = pf_parse_json((string)file_get_contents(PF_MP_DIR . '/' . $label));
            if ($list === null) { $errors[] = "$label: فایل JSON معتبر نیست یا کلید «نام محصول» ندارد"; continue; }
            $stem = pathinfo($fname, PATHINFO_FILENAME);
            // JSON مستقیم داخل MP: عکس‌ها فقط از پوشه‌ای هم‌نام فایل خوانده می‌شود (اگر باشد)
            $imgs = $images ?? (is_dir(PF_MP_DIR . '/' . $stem) ? pf_images($stem) : []);
            foreach ($list as $x) {
                if (trim($x['شناسه'] ?? '') === '') $x['شناسه'] = $stem;   // نام فایل = شناسه
                $x['_file']   = $fname;
                $x['_dir']    = $dir;
                $x['_images'] = $imgs;
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
