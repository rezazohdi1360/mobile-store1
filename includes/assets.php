<?php
/**
 * آیکون‌ها و تصاویر ثابت سایت — همه از پوشه‌ی «icon» در ریشه خوانده می‌شوند.
 *   icon/01.jpg  ← لوگوی فروشگاه
 *   icon/02.jpg  ← نماد اعتماد الکترونیک (اینماد) — در فوتر
 *   icon/03.*    ← بنر صفحه اصلی و خوش‌آمدگویی (اختیاری)
 *   icon/04.*    ← تصویر دلخواه نماد اعتماد (اگر وجود داشته باشد به‌جای 02 در فوتر نمایش داده می‌شود)
 * پسوند می‌تواند jpg / jpeg / png / webp / gif / svg باشد.
 */
define('ICON_DIR', dirname(__DIR__) . '/icon');

/** مسیر نسبی (برای src) تصویر شماره‌ی n یا null اگر وجود نداشت */
function icon_path($n) {
    $n = str_pad((string)(int)$n, 2, '0', STR_PAD_LEFT);
    foreach (['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'JPG', 'JPEG', 'PNG'] as $ext) {
        $f = ICON_DIR . "/$n.$ext";
        if (is_file($f)) return ['file' => $f, 'url' => "icon/$n.$ext?v=" . filemtime($f)];
    }
    return null;
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
    if (!function_exists('imagecropauto') || preg_match('/\.(svg|gif)/i', $p['file'])) return $prefix . $p['url'];
    $dir = ICON_DIR . '/.cache';
    $out = $dir . '/' . basename($p['file']) . '.' . filemtime($p['file']) . '.png';
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
