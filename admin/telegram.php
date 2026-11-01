<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/telegram.php';

if (!isAdmin()) redirect('../login.php');

$msg = ''; $msgOk = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_tg'])) {
        $token = trim($_POST['tg_token'] ?? '');
        $chat  = trim($_POST['tg_chat'] ?? '');
        $enable = isset($_POST['tg_enabled']) ? '1' : '0';
        if ($token !== '') site_set($pdo, 'tg_token', $token);        // خالی = بدون تغییر
        site_set($pdo, 'tg_chat', $chat);
        site_set($pdo, 'tg_proxy', trim($_POST['tg_proxy'] ?? ''));
        site_set($pdo, 'tg_api', trim($_POST['tg_api'] ?? ''));
        $conf = tg_conf($pdo);
        if ($enable === '1' && ($conf['token'] === '' || $conf['chat'] === '')) {
            $enable = '0';
            $msg = 'برای فعال‌سازی باید توکن ربات و کانال هر دو وارد شوند.'; $msgOk = false;
        } else {
            $msg = 'تنظیمات ذخیره شد.';
        }
        site_set($pdo, 'tg_enabled', $enable);
    }
    if (isset($_POST['test_tg'])) {
        $conf = tg_conf($pdo);
        if ($conf['token'] === '' || $conf['chat'] === '') { $msg = 'ابتدا توکن و کانال را ذخیره کنید.'; $msgOk = false; }
        else {
            [$ok, $info] = tg_call($conf, 'sendMessage', [
                'chat_id' => $conf['chat'],
                'text' => "✅ اتصال موفق بود\nگزارش فروش «" . SITE_NAME . "» به این کانال ارسال خواهد شد.",
            ]);
            $msg = $ok ? 'پیام آزمایشی به کانال ارسال شد ✅' : $info; $msgOk = $ok;
        }
    }
}

$conf = tg_conf($pdo);
$last = site_get($pdo, 'tg_last', '');

$pageTitle = 'اتصال به تلگرام';
require_once '../includes/header.php';
?>
<div class="max-w-3xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
                ✈️ گزارش فروش در کانال تلگرام
            </h1>
            <p class="text-xs text-slate-500 mt-1">وقتی مشتری پرداخت را کامل کرد، عکس کالا و متن فروش به کانال ارسال می‌شود.</p>
        </div>
        <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
    </div>

    <?php if ($msg): ?>
        <div class="<?= $msgOk ? 'bg-emerald-50 border-emerald-200 text-emerald-700' : 'bg-red-50 border-red-200 text-red-700' ?> border rounded-2xl p-4 mb-4 text-xs leading-7"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5 mb-5">
        <label class="flex items-center gap-2 text-sm font-black mb-5 cursor-pointer">
            <input type="checkbox" name="tg_enabled" value="1" <?= $conf['enabled'] ? 'checked' : '' ?> class="w-5 h-5">
            ارسال خودکار فروش به تلگرام فعال باشد
        </label>

        <div class="grid grid-cols-1 gap-4 mb-5">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">🤖 توکن ربات تلگرام (Bot Token)</label>
                <input type="password" name="tg_token" dir="ltr" autocomplete="off"
                       placeholder="<?= $conf['token'] !== '' ? '•••••• ذخیره شده (برای تغییر، توکن جدید را بنویسید)' : '123456789:AAxxxxxxxxxxxxxxxx' ?>"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">📢 شناسه کانال</label>
                <input type="text" name="tg_chat" dir="ltr" value="<?= sanitize($conf['chat']) ?>" placeholder="@my_channel   یا   -1001234567890"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">🌐 پروکسی (اختیاری — اگر تلگرام روی اینترنت شما بسته است)</label>
                <input type="text" name="tg_proxy" dir="ltr" value="<?= sanitize($conf['proxy']) ?>" placeholder="socks5://127.0.0.1:10808   یا   http://127.0.0.1:10809"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">🔗 آدرس API تلگرام (اختیاری — فقط اگر از آدرس واسط/میرور استفاده می‌کنید)</label>
                <input type="text" name="tg_api" dir="ltr" value="<?= sanitize(site_get($pdo, 'tg_api', '')) ?>" placeholder="https://api.telegram.org"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
        </div>

        <div class="flex gap-2 flex-wrap">
            <button name="save_tg" value="1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-2.5 rounded-xl text-xs">💾 ذخیره</button>
            <button name="test_tg" value="1" class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-5 py-2.5 rounded-xl text-xs">📨 ارسال پیام آزمایشی</button>
        </div>
    </form>

    <?php if ($last): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-5 text-xs">
        <b>آخرین ارسال:</b> <?= sanitize($last) ?>
    </div>
    <?php endif; ?>

    <div class="bg-amber-50 border border-amber-200 rounded-2xl p-5 text-xs leading-8 text-amber-900">
        <div class="font-black mb-1">📘 راهنمای راه‌اندازی</div>
        ۱. در تلگرام به <b dir="ltr">@BotFather</b> پیام بدهید، دستور <b dir="ltr">/newbot</b> را بزنید و توکن ربات را بگیرید.<br>
        ۲. ربات را در کانال خود <b>ادمین</b> کنید (با دسترسی «ارسال پیام»).<br>
        ۳. شناسه کانال را بنویسید: برای کانال عمومی <b dir="ltr">@channel_name</b> و برای کانال خصوصی عدد <b dir="ltr">-100…</b>.<br>
        ۴. «ارسال پیام آزمایشی» را بزنید و بعد گزینه فعال‌سازی را روشن کنید.<br>
        ⚠️ در گزارش‌ها فقط اطلاعات کالا و فروش (نام، تعداد، قیمت، مبلغ، استان و کد سفارش) ارسال می‌شود؛ نام، تلفن و آدرس خریدار ارسال <b>نمی‌شود</b>.<br>
        ⚠️ در XAMPP افزونه <b dir="ltr">curl</b> باید فعال باشد (به‌صورت پیش‌فرض فعال است). اگر تلگرام فیلتر است، پروکسی را وارد کنید.
    </div>
</div>
<?php require_once '../includes/footer.php'; ?>
