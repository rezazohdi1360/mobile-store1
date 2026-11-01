<?php
/**
 * تنظیمات سایت (قابل ویرایش از پنل مدیریت ← تنظیمات سایت)
 * مقدارها در جدول site_settings ذخیره می‌شوند؛ مقدار پیش‌فرض اینجاست.
 */
function site_defaults() {
    return [
        'site_name'     => 'فروشگاه جامع موبایل 72412',
        'owner'         => 'رضا زهدی',
        'phone'         => '09156481181',
        'phone2'        => '',
        'email'         => '',
        'address'       => '',
        'hours'         => 'شنبه تا پنجشنبه، ۹ صبح تا ۶ عصر',
        'instagram'     => '',
        'telegram_link' => '',
        'enamad_html'   => '',
    ];
}

function site_settings_ensure(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        skey VARCHAR(80) NOT NULL PRIMARY KEY,
        svalue TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
}

function site_get(PDO $pdo, $key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        try { $cache = $pdo->query("SELECT skey, svalue FROM site_settings")->fetchAll(PDO::FETCH_KEY_PAIR); }
        catch (Exception $e) { site_settings_ensure($pdo); $cache = []; }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== null ? $cache[$key] : $default;
}

function site_set(PDO $pdo, $key, $value) {
    site_settings_ensure($pdo);
    $pdo->prepare("INSERT INTO site_settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)")
        ->execute([$key, $value]);
}

/** تبدیل ارقام به فارسی برای نمایش */
function site_fa($s) {
    return strtr((string)$s, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}

/** تعریف ثابت‌های سایت (یک‌بار، بعد از اتصال به دیتابیس) */
function site_load(PDO $pdo) {
    if (defined('SITE_NAME')) return;
    $d = site_defaults();
    define('SITE_NAME',      site_get($pdo, 'site_name',     $d['site_name']));
    define('SITE_OWNER',     site_get($pdo, 'owner',         $d['owner']));
    define('SITE_PHONE',     site_get($pdo, 'phone',         $d['phone']));
    define('SITE_PHONE2',    site_get($pdo, 'phone2',        $d['phone2']));
    define('SITE_EMAIL',     site_get($pdo, 'email',         $d['email']));
    define('SITE_ADDRESS',   site_get($pdo, 'address',       $d['address']));
    define('SITE_HOURS',     site_get($pdo, 'hours',         $d['hours']));
    define('SITE_INSTAGRAM', site_get($pdo, 'instagram',     $d['instagram']));
    define('SITE_TELEGRAM',  site_get($pdo, 'telegram_link', $d['telegram_link']));
    define('SITE_ENAMAD_HTML', site_get($pdo, 'enamad_html', $d['enamad_html']));
}
