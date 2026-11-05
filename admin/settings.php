<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isAdmin()) redirect('../login.php');

$fields = [
    'site_name'     => ['نام فروشگاه', 'text'],
    'owner'         => ['نام مالک / مدیر', 'text'],
    'phone'         => ['تلفن تماس (موبایل)', 'tel'],
    'phone2'        => ['تلفن دوم (ثابت)', 'tel'],
    'email'         => ['ایمیل', 'email'],
    'address'       => ['آدرس', 'text'],
    'hours'         => ['ساعات کاری', 'text'],
    'instagram'     => ['لینک اینستاگرام', 'url'],
    'telegram_link' => ['لینک تلگرام (کانال یا آیدی)', 'url'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $defaults = site_defaults();
    foreach ($fields as $k => $_) {
        $v = trim($_POST[$k] ?? '');
        if ($k === 'site_name' && $v === '') $v = $defaults['site_name'];
        site_set($pdo, $k, $v);
    }
    site_set($pdo, 'enamad_html', trim($_POST['enamad_html'] ?? ''));
    $eu = trim($_POST['enamad_url'] ?? '');
    if ($eu !== '' && !preg_match('#^https?://#i', $eu)) $eu = 'https://' . $eu;
    site_set($pdo, 'enamad_url', filter_var($eu, FILTER_VALIDATE_URL) ? $eu : '');
    site_set($pdo, 'welcome_enabled', isset($_POST['welcome_enabled']) ? '1' : '0');
    $th = $_POST['theme_default'] ?? 'tools';
    site_set($pdo, 'theme_default', isset(theme_list()[$th]) ? $th : 'tools');
    site_set($pdo, 'site_tagline', trim($_POST['site_tagline'] ?? ''));
    $hh = []; foreach (array_keys($_POST['hero'] ?? []) as $hk) $hh[$hk] = trim((string)$_POST['hero'][$hk]);
    site_set($pdo, 'home_hero', json_encode($hh, JSON_UNESCAPED_UNICODE));
    site_set($pdo, 'theme_switcher', isset($_POST['theme_switcher']) ? '1' : '0');
    site_set($pdo, 'welcome_message', trim($_POST['welcome_message'] ?? ''));
    setFlash('success', 'اطلاعات تماس و تنظیمات سایت ذخیره شد و در همه بخش‌ها اعمال می‌شود');
    redirect('settings.php');
}

$d = site_defaults();
$pageTitle = 'تنظیمات سایت';
require_once '../includes/header.php';
?>
<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
                ⚙ تنظیمات سایت و اطلاعات تماس
            </h1>
            <p class="text-xs text-slate-500 mt-1">تغییرات در عنوان صفحات، هدر، فوتر، صفحه «تماس با ما» و صفحات متنی سایت اعمال می‌شود. فیلد خالی در سایت نمایش داده نمی‌شود.</p>
        </div>
        <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
    </div>

    <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <?php foreach ($fields as $k => [$label, $type]): ?>
            <div class="<?= in_array($k, ['address', 'hours']) ? 'md:col-span-2' : '' ?>">
                <label class="block text-xs font-bold text-slate-600 mb-2"><?= $label ?></label>
                <input type="<?= $type ?>" name="<?= $k ?>" value="<?= sanitize(site_get($pdo, $k, $d[$k])) ?>"
                       <?= in_array($type, ['tel', 'email', 'url']) ? 'dir="ltr"' : '' ?>
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mb-5 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <label class="block text-xs font-bold text-slate-600 mb-2">🔗 آدرس صفحه نماد اعتماد (اینماد) — با کلیک روی نماد فوتر به این آدرس می‌رود</label>
            <input type="text" name="enamad_url" dir="ltr" placeholder="https://trustseal.enamad.ir/?id=...&Code=..."
                   value="<?= sanitize(site_get($pdo, 'enamad_url', '')) ?>"
                   class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm">
            <p class="text-[11px] text-slate-500 mt-2">تصویر نماد از پوشه <code>icon/enamad/</code> خوانده می‌شود: <code>01.*</code> پیش‌فرض است و اگر فایلی به نام <code>custom.*</code> بگذارید به‌جای آن نمایش داده می‌شود. برای تغییر تصویر، فایل را در همان پوشه جایگزین کنید.</p>
        </div>

        <div class="mb-5 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <div class="text-xs font-bold text-slate-700 mb-3">🎨 تم رنگی سایت (پیش‌فرض)</div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2 mb-3">
                <?php $curTheme = site_get($pdo, 'theme_default', 'tools'); foreach (theme_list() as $tk => $tv): $v = $tv['v']; ?>
                <label class="flex items-center gap-3 bg-white border <?= $tk === $curTheme ? 'border-orange-400' : 'border-slate-200' ?> rounded-xl px-3 py-2 cursor-pointer text-xs">
                    <input type="radio" name="theme_default" value="<?= $tk ?>" <?= $tk === $curTheme ? 'checked' : '' ?>>
                    <span style="display:inline-block;width:46px;height:24px;border-radius:6px;background:linear-gradient(90deg,<?= $v['navy'] ?> 0 40%,<?= $v['gold'] ?> 40% 70%,<?= $v['cream'] ?> 70%);border:1px solid #cbd5e1"></span>
                    <b><?= sanitize($tv['name']) ?></b>
                </label>
                <?php endforeach; ?>
            </div>
            <label class="flex items-center gap-2 text-xs text-slate-700">
                <input type="checkbox" name="theme_switcher" value="1" <?= site_get($pdo, 'theme_switcher', '1') !== '0' ? 'checked' : '' ?>>
                نمایش دکمه‌ی 🎨 برای بازدیدکنندگان تا تم دلخواه خود را انتخاب کنند
            </label>
        </div>

        <div class="mb-5 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <div class="text-xs font-bold text-slate-700 mb-3">🖼 بنر صفحه اصلی و عنوان هدر (فیلد خالی = متن پیش‌فرض)</div>
            <?php
            $hv = json_decode(site_get($pdo, 'home_hero', ''), true) ?: [];
            $hf = ['badge' => 'برچسب بنر', 't1' => 'عنوان (خط اول)', 't2' => 'عنوان (خط دوم، رنگی)', 'sub' => 'توضیح زیر عنوان', 'b1' => 'دکمه اصلی', 'b1u' => 'لینک دکمه اصلی', 'b2' => 'دکمه دوم', 'b2u' => 'لینک دکمه دوم',
                   'c1tag' => 'کارت نارنجی: برچسب', 'c1t' => 'کارت نارنجی: عنوان', 'c1s' => 'کارت نارنجی: توضیح', 'c1l' => 'کارت نارنجی: متن لینک', 'c1u' => 'کارت نارنجی: آدرس لینک',
                   'c2tag' => 'کارت تیره: برچسب', 'c2t' => 'کارت تیره: عنوان', 'c2s' => 'کارت تیره: توضیح', 'c2l' => 'کارت تیره: متن لینک', 'c2u' => 'کارت تیره: آدرس لینک'];
            ?>
            <label class="block text-xs text-slate-600 mb-3">شعار زیر نام فروشگاه در هدر
                <input type="text" name="site_tagline" value="<?= sanitize(site_get($pdo, 'site_tagline', '')) ?>" placeholder="تخصصی‌ترین مرجع ابزارآلات" class="w-full mt-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm"></label>
            <div class="grid md:grid-cols-2 gap-3">
                <?php foreach ($hf as $hk => $hl): ?>
                <label class="block text-xs text-slate-600"><?= $hl ?>
                    <input type="text" name="hero[<?= $hk ?>]" value="<?= sanitize($hv[$hk] ?? '') ?>" <?= substr($hk, -1) === 'u' ? 'dir="ltr"' : '' ?> class="w-full mt-1 px-3 py-2 bg-white border border-slate-200 rounded-xl text-sm"></label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="mb-5 p-4 bg-slate-50 border border-slate-200 rounded-xl">
            <label class="flex items-center gap-2 text-xs font-bold text-slate-700 mb-3">
                <input type="checkbox" name="welcome_enabled" value="1" <?= site_get($pdo, 'welcome_enabled', '1') !== '0' ? 'checked' : '' ?>>
                👋 نمایش صفحه خوش‌آمدگویی هنگام ورود به سایت
            </label>
            <label class="block text-xs font-bold text-slate-600 mb-2">متن خوش‌آمدگویی (اگر خالی باشد متن پیش‌فرض نمایش داده می‌شود)</label>
            <textarea name="welcome_message" rows="3" class="w-full px-3 py-2.5 bg-white border border-slate-200 rounded-xl text-sm"><?= sanitize(site_get($pdo, 'welcome_message', '')) ?></textarea>
        </div>

        <div class="mb-5">
            <label class="block text-xs font-bold text-slate-600 mb-2">کد نماد اعتماد الکترونیک (enamad) — اختیاری</label>
            <textarea name="enamad_html" rows="3" dir="ltr" placeholder="کد HTML نماد را اینجا قرار دهید"
                      class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs font-mono"><?= sanitize(site_get($pdo, 'enamad_html', '')) ?></textarea>
        </div>

        <button name="save_settings" value="1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-2.5 rounded-xl text-xs">💾 ذخیره تغییرات</button>
    </form>
</div>
<?php require_once '../includes/footer.php'; ?>
