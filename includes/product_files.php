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

/** آرایه‌ی لیستی (کلیدهای 0..n-1)؛ array_is_list فقط از PHP 8.1 هست */
function pf_is_list($a) { return is_array($a) && ($a === [] || array_keys($a) === range(0, count($a) - 1)); }

/** نرمال‌سازی نام کلید برای مقایسه: فاصله/نیم‌فاصله/NBSP، ی و ک عربی، حروف بزرگ، «_» و «-» */
function pf_nk($k) {
    $k = strtr((string)$k, ['ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه']);
    return preg_replace('/[\s\x{a0}\x{200c}\x{200d}\x{200e}\x{200f}_\-]+/u', '', mb_strtolower($k, 'UTF-8'));
}

/** کلیدهای استاندارد فایل محصول (همان‌هایی که pf_row می‌خواند) */
function pf_canonical_keys() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    foreach (['شناسه', 'نام محصول', 'نام لاتین', 'برند', 'دسته‌بندی', 'قیمت (تومان)', 'قیمت قبل از تخفیف (تومان)', 'درصد تخفیف',
              'وضعیت موجودی', 'امتیاز', 'تعداد نظرات', 'گارانتی', 'فروشنده', 'آدرس وب', 'آدرس عکس اصلی', 'همه تصاویر',
              'بررسی تخصصی', 'مشخصات فنی'] as $c) $map[pf_nk($c)] = $c;
    return $map;
}

/** نام‌های جایگزین برای «نام محصول» (به ترتیب اولویت) */
const PF_NAME_ALIASES = ['نام', 'عنوان', 'نام کالا', 'عنوان محصول', 'عنوان کالا', 'نام فارسی', 'title', 'name', 'product_name', 'product name', 'product_title'];

/**
 * فایل‌های محصول با کلیدهای انگلیسی (id, title, price_toman, rrp_toman, available, specs ...) را
 * به کلیدهای فارسی استاندارد تبدیل می‌کند تا pf_row یکسان کار کند. فقط جاهای خالی پر می‌شوند.
 */
function pf_from_english(array $x, array $out) {
    $g = [];
    foreach ($x as $k => $v) $g[pf_nk($k)] = $v;
    $put = function ($pk, $v) use (&$out) {
        if (is_array($v) || is_bool($v) || $v === null) return;
        if (trim((string)($out[$pk] ?? '')) === '' && trim((string)$v) !== '') $out[$pk] = is_string($v) ? trim($v) : $v;
    };
    foreach (['id' => 'شناسه', 'title' => 'نام محصول', 'titleen' => 'نام لاتین', 'brand' => 'برند', 'category' => 'دسته‌بندی',
              'pricetoman' => 'قیمت (تومان)', 'rrptoman' => 'قیمت قبل از تخفیف (تومان)', 'discountpercent' => 'درصد تخفیف',
              'rating' => 'امتیاز', 'ratingcount' => 'تعداد نظرات', 'seller' => 'فروشنده', 'url' => 'آدرس وب',
              'warranty' => 'گارانتی', 'description' => 'بررسی تخصصی', 'desc' => 'بررسی تخصصی'] as $ek => $pk) {
        if (isset($g[$ek])) $put($pk, $g[$ek]);
    }
    // موجودی: available = true/false
    if (array_key_exists('available', $g) && trim((string)($out['وضعیت موجودی'] ?? '')) === '') {
        $b = filter_var($g['available'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $out['وضعیت موجودی'] = $b === false ? 'out_of_stock' : 'in_stock';
    }
    // آدرس عکس‌های اینترنتی (فقط وقتی عکسی در پوشه محصول نباشد استفاده می‌شود)
    if (isset($g['imageurls']) && is_array($g['imageurls'])) {
        $urls = array_values(array_filter(array_map(fn($u) => is_string($u) ? trim($u) : '', $g['imageurls'])));
        if ($urls) {
            if (trim((string)($out['آدرس عکس اصلی'] ?? '')) === '') $out['آدرس عکس اصلی'] = $urls[0];
            if (trim((string)($out['همه تصاویر'] ?? '')) === '') $out['همه تصاویر'] = implode(',', $urls);
        }
    }
    // مشخصات: شیء {"کلید": "مقدار"} ← «مشخصه: کلید»؛ «سایر توضیحات» اگر توضیحی نباشد به‌عنوان توضیحات محصول
    if (isset($g['specs']) && is_array($g['specs'])) {
        foreach ($g['specs'] as $k => $v) {
            if (!is_scalar($v) || trim((string)$v) === '') continue;
            $k = trim((string)$k);
            if (pf_nk($k) === pf_nk('سایر توضیحات') && trim((string)($out['بررسی تخصصی'] ?? '')) === '') { $out['بررسی تخصصی'] = trim((string)$v); continue; }
            $out['مشخصه: ' . $k] = (string)$v;
        }
    }
    return $out;
}

/** یک آیتم را به کلیدهای استاندارد برمی‌گرداند؛ اگر نام محصول نداشته باشد null */
function pf_canon_item($x) {
    if (!is_array($x) || pf_is_list($x)) return null;
    $map = pf_canonical_keys();
    $out = $x;
    foreach ($x as $k => $v) {
        $n = pf_nk($k);
        if (isset($map[$n]) && !array_key_exists($map[$n], $out)) $out[$map[$n]] = $v;
    }
    $out = pf_from_english($x, $out);
    if (trim((string)($out['نام محصول'] ?? '')) === '') {
        $names = [];
        foreach ($x as $k => $v) $names[pf_nk($k)] = $v;
        foreach (PF_NAME_ALIASES as $al) {
            $v = $names[pf_nk($al)] ?? null;
            if (is_scalar($v) && trim((string)$v) !== '') { $out['نام محصول'] = trim((string)$v); break; }
        }
    }
    return trim((string)($out['نام محصول'] ?? '')) === '' ? null : $out;
}

/** آیتم‌های محصول را از هر ساختاری پیدا می‌کند: یک شیء، لیست، یا پوشش‌هایی مثل {"products":[...]} (تا ۳ سطح) */
function pf_extract_items($d, $depth = 0) {
    if (!is_array($d) || $depth > 3) return [];
    if (!pf_is_list($d)) {
        if (($c = pf_canon_item($d)) !== null) return [$c];
    } else {
        $items = [];
        foreach ($d as $x) if (($c = pf_canon_item($x)) !== null) $items[] = $c;
        if ($items) return $items;
    }
    foreach ($d as $v) {
        if (is_array($v) && ($r = pf_extract_items($v, $depth + 1))) return $r;
    }
    return [];
}

/** متن خام فایل ← UTF-8 (BOM، UTF-16 ویندوز، یا CP1256) */
function pf_decode_text($raw) {
    if (strncmp($raw, "\xFF\xFE", 2) === 0) return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16LE');
    if (strncmp($raw, "\xFE\xFF", 2) === 0) return mb_convert_encoding(substr($raw, 2), 'UTF-8', 'UTF-16BE');
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
    if (!mb_check_encoding($raw, 'UTF-8')) $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
    return $raw;
}

/** null = JSON معتبر نیست؛ [] = معتبر ولی محصولی در آن پیدا نشد؛ وگرنه فهرست محصولات */
function pf_parse_json($raw) {
    $raw = trim(pf_decode_text((string)$raw));
    $raw = preg_replace('/,\s*([\]}])/', '$1', $raw);
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    return pf_extract_items($d);
}

/** برای پیام خطا: کلیدهای سطح اول فایل (چند تای اول) */
function pf_keys_hint($raw) {
    $d = json_decode(preg_replace('/,\s*([\]}])/', '$1', trim(pf_decode_text((string)$raw))), true);
    if (is_array($d) && pf_is_list($d)) $d = $d[0] ?? [];
    if (!is_array($d)) return '(ساختار ناشناخته)';
    $ks = array_slice(array_keys($d), 0, 12);
    return implode(' ، ', array_map(fn($k) => '«' . $k . '»', $ks)) . (count($d) > 12 ? ' …' : '');
}

function pf_key(array $x) { return trim($x['شناسه'] ?? '') ?: 'json-' . substr(md5($x['نام محصول']), 0, 10); }

/**
 * همهٔ محصولات پوشه MP (هر پوشه = یک محصول، به‌همراه عکس‌های همان پوشه).
 * هر آیتم: همان کلیدهای فارسی فایل + _file (نام فایل JSON), _dir (نام پوشه محصول), _images (لیست مسیرها؛ خالی اگر عکسی نباشد)
 */
function pf_load_products(&$errors = []) {
    $errors = [];
    $items = [];
    $hints = 0;
    foreach (pf_product_dirs() as $dir) {
        $images = $dir === '' ? null : pf_images($dir);   // عکس‌ها از همان پوشه‌ی محصول
        foreach (pf_json_files($dir) as $fname) {
            $label = ($dir === '' ? '' : $dir . '/') . $fname;
            $raw  = (string)file_get_contents(PF_MP_DIR . '/' . $label);
            $list = pf_parse_json($raw);
            if ($list === null) { $errors[] = "$label: فایل JSON معتبر نیست"; continue; }
            if (!$list) {
                if ($hints++ < 3) $errors[] = "$label: JSON خوانده شد ولی «نام محصول» پیدا نشد. کلیدهای فایل: " . pf_keys_hint($raw);
                else $errors['more'] = "… و فایل‌های مشابه دیگر (فقط ۳ نمونه نشان داده می‌شود)";
                continue;
            }
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
