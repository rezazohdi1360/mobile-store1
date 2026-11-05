<?php
/**
 * آیکون‌ها، لوگو، بنرها و تصاویر ثابت سایت — همه از پوشه‌ی «icon» در ریشه، به‌صورت پوشه‌بندی‌شده.
 * پوشه در هر درخواست (هنگام اجرا) خوانده می‌شود؛ پس با گذاشتن/جایگزین‌کردن فایل، بدون تغییر کد اعمال می‌شود.
 *
 *   icon/logo/         ← لوگوی فروشگاه: 01.*  (هدر، فاکتور، صفحه خوش‌آمدگویی، API)
 *   icon/enamad/       ← نماد اعتماد الکترونیک: 01.*  (فوتر) — اگر فایلی به نام custom.* باشد، به‌جای 01 نمایش داده می‌شود
 *   icon/banner/       ← بنر صفحه خوش‌آمدگویی: 01.*  (اختیاری)
 *   icon/icons/        ← آیکون‌های کوچک (آیکون برنامه، favicon، آیکون دسته‌ها و ...)
 *   icon/placeholder/  ← تصویر جایگزین «بدون عکس»: no-image.png / no-image.jpg
 *
 * هر پوشه‌ی دلخواه دیگری هم داخل icon بسازید با icon_list('نام‌پوشه') / icon_group_url() خوانده می‌شود.
 * پسوند می‌تواند jpg / jpeg / png / webp / gif / svg / ico باشد (حروف بزرگ و کوچک فرقی ندارد).
 *
 * شماره‌های قدیمی (icon_url(1) و ...) هنوز کار می‌کنند و به همین پوشه‌ها نگاشت می‌شوند؛
 * اگر فایل‌ها هنوز به‌صورت تخت در خود icon باشند (icon/01.jpg) هم همچنان پیدا می‌شوند.
 */
define('ICON_DIR', dirname(__DIR__) . '/icon');
const ICON_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'ico'];

/** شماره‌ی قدیمی ← [پوشه، نام فایل بدون پسوند] */
const ICON_SLOTS = [
    1 => ['logo',   '01'],       // لوگو
    2 => ['enamad', '01'],       // نماد اعتماد (پیش‌فرض)
    3 => ['banner', '01'],       // بنر خوش‌آمدگویی
    4 => ['enamad', 'custom'],   // نماد اعتماد دلخواه (اگر باشد جایگزین 2 می‌شود)
];

/** کدگذاری هر بخش از مسیر برای آدرس وب (فارسی و فاصله در نام فایل هم کار کند) */
function icon_rel_url($rel) {
    return implode('/', array_map('rawurlencode', explode('/', $rel)));
}

/**
 * فایل‌های تصویری یک پوشه‌ی icon (مرتب طبیعی). هر آیتم: ['file' => مسیر کامل، 'rel' => icon/...، 'url' => آدرس وب همراه ?v=زمان‌تغییر]
 * $group = '' یعنی خود icon (فقط برای سازگاری با چیدمان تخت قدیمی).
 * $recursive = true: زیرپوشه‌ها را هم بخوان (پوشه‌های نقطه‌دار مثل .cache نادیده گرفته می‌شوند).
 */
function icon_files($group, $recursive = false) {
    static $cache = [];
    $group = trim(str_replace('\\', '/', (string)$group), '/');
    if (strpos($group, '..') !== false) return [];
    $key = $group . '|' . (int)$recursive;
    if (isset($cache[$key])) return $cache[$key];

    $base = ICON_DIR . ($group === '' ? '' : '/' . $group);
    $out = [];
    if (is_dir($base)) {
        $paths = [];
        if ($recursive) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $p) {
                if ($p->isFile() && strpos(substr($p->getPathname(), strlen($base)), '/.') === false) $paths[] = $p->getPathname();
            }
        } else {
            foreach (scandir($base) as $n) {
                if ($n[0] !== '.' && is_file("$base/$n")) $paths[] = "$base/$n";
            }
        }
        natcasesort($paths);
        foreach ($paths as $f) {
            if (!in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), ICON_EXTS, true)) continue;
            $rel = 'icon' . substr($f, strlen(ICON_DIR));
            $out[] = ['file' => $f, 'rel' => $rel, 'url' => icon_rel_url($rel) . '?v=' . (int)@filemtime($f)];
        }
    }
    return $cache[$key] = $out;
}

/** تصویری با نام (بدون پسوند) مشخص داخل یک پوشه؛ یا null */
function icon_find($group, $base) {
    foreach (icon_files($group) as $f) {
        if (strcasecmp(pathinfo($f['file'], PATHINFO_FILENAME), (string)$base) === 0) return $f;
    }
    return null;
}

/** آدرس وب تصویر داخل پوشه: با $base نام فایل بدون پسوند؛ بدون $base اولین تصویر پوشه. (prefix = '../' از داخل admin) */
function icon_group_url($group, $base = '', $prefix = '') {
    $f = $base === '' ? (icon_files($group)[0] ?? null) : icon_find($group, $base);
    return $f ? $prefix . $f['url'] : null;
}

/** آدرس همه‌ی تصاویر یک پوشه (برای اسلایدر بنر، گالری آیکون‌ها و ...) */
function icon_list($group, $prefix = '', $recursive = false) {
    return array_map(fn($f) => $prefix . $f['url'], icon_files($group, $recursive));
}

/** تصویر «بدون عکس» (پوشه placeholder؛ اولویت با no-image) */
function icon_placeholder_url($prefix = '') {
    return icon_group_url('placeholder', 'no-image', $prefix) ?: icon_group_url('placeholder', '', $prefix);
}

/** مسیر تصویر شماره‌ی n (سازگاری با کد قدیمی): ['file','rel','url'] یا null */
function icon_path($n) {
    $n = str_pad((string)(int)$n, 2, '0', STR_PAD_LEFT);
    $slot = ICON_SLOTS[(int)$n] ?? null;
    if ($slot && ($f = icon_find($slot[0], $slot[1]))) return $f;
    return icon_find('', $n);                       // چیدمان تخت قدیمی: icon/01.jpg
}

/** آدرس تصویر (با پیشوند ../ اگر از پوشه‌ی admin صدا زده شود) */
function icon_url($n, $prefix = '') {
    $p = icon_path($n);
    return $p ? $prefix . $p['url'] : null;
}

/** نسخه‌ی بدون حاشیه‌ی سفید (برای لوگو در هدر) — اگر GD نبود همان اصلی */
function icon_trimmed_url($n, $prefix = '') {
    $p = icon_path($n);
    if (!$p) return null;
    if (!function_exists('imagecropauto') || preg_match('/\.(svg|gif|ico)$/i', $p['file'])) return $prefix . $p['url'];
    $dir = ICON_DIR . '/.cache';
    $tag = md5($p['rel']);                          // پوشه‌های مختلف می‌توانند فایل هم‌نام داشته باشند
    $out = $dir . '/' . $tag . '.' . (int)filemtime($p['file']) . '.png';
    if (!is_file($out)) {
        try {
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $ext = strtolower(pathinfo($p['file'], PATHINFO_EXTENSION));
            $im = $ext === 'png' ? @imagecreatefrompng($p['file']) : ($ext === 'webp' ? @imagecreatefromwebp($p['file']) : @imagecreatefromjpeg($p['file']));
            if (!$im) return $prefix . $p['url'];
            $c = @imagecropauto($im, IMG_CROP_THRESHOLD, 0.08, 0xFFFFFF);
            if ($c === false) { imagedestroy($im); return $prefix . $p['url']; }
            imagealphablending($c, false); imagesavealpha($c, true);
            if (!@imagepng($c, $out)) return $prefix . $p['url'];
            foreach (glob($dir . '/' . $tag . '.*.png') ?: [] as $old) { if ($old !== $out) @unlink($old); }   // نسخه‌های قدیمی همین تصویر
        } catch (Throwable $e) { return $prefix . $p['url']; }
    }
    return $prefix . 'icon/.cache/' . rawurlencode(basename($out));
}

/** لوگوی هدر: تصویر 01 یا (اگر نبود) ایموجی قدیمی */
function site_logo_mark($height = 46) {
    $u = icon_trimmed_url(1);
    if ($u) return '<img src="' . htmlspecialchars($u) . '" alt="' . htmlspecialchars(SITE_NAME) . '" style="height:' . (int)$height . 'px;width:auto;display:block" class="logo-img">';
    return '<span class="logo-mark">📱</span>';
}

/** تنظیمات جدید */
function welcome_enabled($pdo) { return site_get($pdo, 'welcome_enabled', '1') !== '0'; }
