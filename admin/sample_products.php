<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/coupons.php';          // fa_digits() و توابع تاریخ شمسی
require_once '../includes/product_files.php';    // خواندن محصولات از پوشه‌های products و images

if (!isAdmin()) redirect('../login.php');

// ───── منبع ۱: کاتالوگ داخلی [نام فارسی، نام انگلیسی، برند، slug دسته، قیمت (تومان)] ─────
$samples = [
    ['گوشی موبایل اپل مدل iPhone 15 Pro Max ظرفیت 256 گیگابایت','iPhone 15 Pro Max 256GB','اپل','mobile',68500000],
    ['گوشی موبایل اپل مدل iPhone 14 ظرفیت 128 گیگابایت','iPhone 14 128GB','اپل','mobile',41000000],
    ['گوشی موبایل سامسونگ مدل Galaxy S24 Ultra ظرفیت 256 گیگابایت','Galaxy S24 Ultra 256GB','سامسونگ','mobile',62000000],
    ['گوشی موبایل سامسونگ مدل Galaxy A55 ظرفیت 256 گیگابایت','Galaxy A55 256GB','سامسونگ','mobile',21500000],
    ['گوشی موبایل سامسونگ مدل Galaxy A15 ظرفیت 128 گیگابایت','Galaxy A15 128GB','سامسونگ','mobile',9800000],
    ['گوشی موبایل شیائومی مدل Redmi Note 13 Pro ظرفیت 256 گیگابایت','Redmi Note 13 Pro 256GB','شیائومی','mobile',18900000],
    ['گوشی موبایل شیائومی مدل Poco X6 Pro ظرفیت 256 گیگابایت','Poco X6 Pro 256GB','شیائومی','mobile',19800000],
    ['گوشی موبایل شیائومی مدل Redmi 13C ظرفیت 128 گیگابایت','Redmi 13C 128GB','شیائومی','mobile',6900000],
    ['گوشی موبایل هوآوی مدل Nova 12i ظرفیت 128 گیگابایت','Nova 12i 128GB','هوآوی','mobile',8700000],
    ['گوشی موبایل آنر مدل X8b ظرفیت 256 گیگابایت','Honor X8b 256GB','آنر','mobile',13400000],
    ['هدفون بی‌سیم اپل مدل AirPods Pro 2','AirPods Pro 2','اپل','accessories',11200000],
    ['هدفون بی‌سیم سامسونگ مدل Galaxy Buds2 Pro','Galaxy Buds2 Pro','سامسونگ','accessories',7200000],
    ['شارژر دیواری 25 وات سامسونگ مدل EP-T2510','Samsung 25W Charger','سامسونگ','accessories',1250000],
    ['پاوربانک شیائومی ظرفیت 20000 میلی‌آمپر ساعت مدل PB2022ZM','Xiaomi Power Bank 20000','شیائومی','accessories',2100000],
    ['کابل شارژ USB-C به USB-C اپل طول 1 متر','Apple USB-C Cable 1m','اپل','accessories',1450000],
    ['ساعت هوشمند اپل مدل Watch Series 9 سایز 45 میلی‌متر','Apple Watch Series 9 45mm','اپل','watch',24500000],
    ['ساعت هوشمند شیائومی مدل Redmi Watch 4','Redmi Watch 4','شیائومی','watch',4900000],
    ['ساعت هوشمند سامسونگ مدل Galaxy Watch6 44mm','Galaxy Watch6 44mm','سامسونگ','watch',13800000],
    ['تبلت سامسونگ مدل Galaxy Tab A9 Plus ظرفیت 64 گیگابایت','Galaxy Tab A9+ 64GB','سامسونگ','tablet',12900000],
    ['تبلت اپل مدل iPad 10th Generation 64GB','iPad 10th Gen 64GB','اپل','tablet',23800000]
];
$catNames = ['mobile'=>'گوشی موبایل','accessories'=>'لوازم جانبی','watch'=>'ساعت هوشمند','tablet'=>'تبلت'];

// ───── منبع ۲: فایل JSON محصولات نمونه (همان قالب products.json) ─────
const SAMPLE_JSON = __DIR__ . '/data/sample_products.json';

function sj_load() {
    $map = [];
    foreach (pf_load_products($GLOBALS['pfErrors']) as $x) $map[sj_key($x)] = $x;         // منبع اصلی: پوشه MP
    if (is_file(SAMPLE_JSON)) foreach (sj_parse(file_get_contents(SAMPLE_JSON)) ?: [] as $x) {
        $k = sj_key($x);
        if (!isset($map[$k])) $map[$k] = $x;                                                // فایل JSON قدیمی (آپلودی)
    }
    return array_values($map);
}
$pfErrors = [];
function sj_parse($raw) {
    $raw = preg_replace('/^\xEF\xBB\xBF/', '', trim($raw));
    $raw = preg_replace('/,\s*([\]}])/', '$1', $raw);   // حذف ویرگول اضافه آخر آرایه/آبجکت
    $d = json_decode($raw, true);
    if (!is_array($d)) return null;
    if (isset($d['نام محصول'])) $d = [$d];
    return array_values(array_filter($d, fn($x) => is_array($x) && trim($x['نام محصول'] ?? '') !== ''));
}
function sj_price($v) { return (int)preg_replace('/[^\d]/', '', faToEn((string)$v)); }
function sj_key(array $x) { return trim($x['شناسه'] ?? '') ?: 'json-' . substr(md5($x['نام محصول']), 0, 10); }

function sample_category_id(PDO $pdo, $slug, $name) {
    $st = $pdo->prepare("SELECT id FROM categories WHERE slug = ? OR name = ? LIMIT 1");
    $st->execute([$slug, $name]);
    $id = $st->fetchColumn();
    if ($id) return (int)$id;
    $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$name, $slug]);
    return (int)$pdo->lastInsertId();
}

$jsonItems = sj_load();

// ───── آپلود فایل JSON جدید و ادغام با لیست نمونه‌ها ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_json'])) {
    @set_time_limit(0);
    @ini_set('memory_limit', '2048M');
    $f = $_FILES['json_file'] ?? null;
    $errMsg = [
        UPLOAD_ERR_INI_SIZE  => 'حجم فایل از حد مجاز PHP بیشتر است. در php.ini مقدار upload_max_filesize و post_max_size را بالا ببرید (مثلاً 512M) و Apache را ری‌استارت کنید.',
        UPLOAD_ERR_FORM_SIZE => 'حجم فایل زیاد است.',
        UPLOAD_ERR_PARTIAL   => 'آپلود ناقص انجام شد؛ دوباره تلاش کنید.',
        UPLOAD_ERR_NO_FILE   => 'فایلی انتخاب نشده است.',
    ];
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        setFlash('error', $errMsg[$f['error'] ?? UPLOAD_ERR_NO_FILE] ?? 'آپلود فایل ناموفق بود.');
    } else {
        $new = sj_parse(file_get_contents($f['tmp_name']));
        if ($new === null) {
            setFlash('error', 'فایل JSON معتبر نیست');
        } else {
            $map = [];
            foreach ((is_file(SAMPLE_JSON) ? sj_parse(file_get_contents(SAMPLE_JSON)) : []) ?: [] as $x) $map[sj_key($x)] = $x;
            $before = count($map);
            foreach ($new as $x) $map[sj_key($x)] = $x;
            file_put_contents(SAMPLE_JSON, json_encode(array_values($map), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            setFlash('success', count($new) . ' محصول از فایل خوانده شد (' . (count($map) - $before) . ' مورد جدید به لیست نمونه‌ها اضافه شد)');
        }
    }
    redirect('sample_products.php');
}

// ───── محصولاتی که قبلاً به فروشگاه اضافه شده‌اند ─────
$existing = [];
foreach ($pdo->query("SELECT external_id FROM products WHERE external_id IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $k) $existing[$k] = true;

// ───── به‌روزرسانی محصولات موجود از پوشه‌ها (عکس، قیمت، اطلاعات) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refresh_existing'])) {
    @set_time_limit(0);
    $upd = 0;
    $pdo->beginTransaction();
    $find = $pdo->prepare("SELECT id FROM products WHERE external_id = ? LIMIT 1");
    $up = $pdo->prepare("UPDATE products SET name=?, name_en=?, brand=?, category_path=?, price=?, sale_price=?, discount_percent=?,
        stock_status=?, rating=?, review_count=?, warranty=?, seller=?, source_url=?, main_image=?, all_images=?, description=?, short_desc=?, updated_at=NOW() WHERE id=?");
    $delSpec = $pdo->prepare("DELETE FROM product_specs WHERE product_id = ?");
    $specIns = $pdo->prepare("INSERT INTO product_specs (product_id, group_name, spec_key, spec_value, sort_order) VALUES (?,?,?,?,?)");
    foreach ($jsonItems as $x) {
        $key = sj_key($x);
        $find->execute([$key]);
        $pid = (int)$find->fetchColumn();
        if (!$pid) continue;
        $r = pf_row($x);
        $up->execute([$r['name'], $r['name_en'], $r['brand'], $r['path'], $r['price'], $r['sale'], $r['pct'],
            $r['avail'] ? 'in_stock' : 'out_of_stock', $r['rating'], $r['reviews'], $r['warranty'], $r['seller'], $r['url'],
            $r['img'], $r['all'], $r['desc'], $r['short'], $pid]);
        if ($r['specs']) {
            $delSpec->execute([$pid]);
            foreach ($r['specs'] as $n => [$sk, $sv]) $specIns->execute([$pid, 'مشخصات', $sk, $sv, $n]);
        }
        $upd++;
    }
    $pdo->commit();
    setFlash($upd ? 'success' : 'error', $upd ? "$upd محصول از روی پوشه‌ها به‌روزرسانی شد" : 'محصولی برای به‌روزرسانی پیدا نشد (ابتدا محصولات را اضافه کنید)');
    redirect('sample_products.php');
}

// ───── افزودن موارد انتخاب‌شده ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_samples']) || isset($_POST['add_all_json']))) {
    @set_time_limit(0);
    @ini_set('memory_limit', '2048M');
    $added = 0;
    $pdo->beginTransaction();

    $ins = $pdo->prepare("
        INSERT INTO products (name, name_en, slug, external_id, brand, category_id, category_path, price, sale_price, discount_percent,
                              stock, stock_status, rating, review_count, warranty, seller, source_url, main_image, all_images,
                              description, short_desc, status)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
    ");

    // الف) کاتالوگ داخلی
    foreach (array_map('intval', post_list('pick')) as $i) {
        if (!isset($samples[$i])) continue;
        $key = 'sample-' . ($i + 1);
        if (isset($existing[$key])) continue;
        [$name, $en, $brand, $cat, $price] = $samples[$i];
        $catId = sample_category_id($pdo, $cat, $catNames[$cat] ?? $cat);
        $img = 'https://placehold.co/500x500/f1f5f9/1e293b?text=' . rawurlencode($en);
        $ins->execute([$name, $en, makeSlug($en) . '-' . ($i + 1), $key, $brand, $catId, '', $price, null, 0,
                       10, 'in_stock', 0, 0, 'گارانتی ۱۸ ماهه', '', '', $img, $img, '', 'محصول نمونه - ' . $brand]);
        $existing[$key] = true;
        $added++;
    }

    // ب) محصولات فایل JSON
    $byKey = [];
    foreach ($jsonItems as $x) $byKey[sj_key($x)] = $x;
    $specIns = $pdo->prepare("INSERT INTO product_specs (product_id, group_name, spec_key, spec_value, sort_order) VALUES (?,?,?,?,?)");

    $jsonKeys = isset($_POST['add_all_json']) ? array_keys($byKey) : post_list('pick_json');
    foreach ($jsonKeys as $key) {
        if (!isset($byKey[$key]) || isset($existing[$key])) continue;
        $x = $byKey[$key];
        $r = pf_row($x);
        $mainCat = trim(explode('<', $r['path'])[0]) ?: 'سایر';
        $catId = sample_category_id($pdo, makeSlug($mainCat), $mainCat);
        $ins->execute([
            $r['name'], $r['name_en'], makeSlug(mb_substr($r['name'], 0, 60)) . '-' . preg_replace('/\D/', '', $key) . '-' . substr(md5($key), 0, 4), $key,
            $r['brand'], $catId, $r['path'], $r['price'], $r['sale'], $r['pct'],
            $r['avail'] ? 10 : 0, $r['avail'] ? 'in_stock' : 'out_of_stock',
            $r['rating'], $r['reviews'], $r['warranty'], $r['seller'], $r['url'], $r['img'], $r['all'], $r['desc'], $r['short'],
        ]);
        $pid = (int)$pdo->lastInsertId();
        foreach ($r['specs'] as $n => [$sk, $sv]) $specIns->execute([$pid, 'مشخصات', $sk, $sv, $n]);
        $existing[$key] = true;
        $added++;
    }

    $pdo->commit();
    setFlash($added ? 'success' : 'error', $added ? "$added محصول نمونه به فروشگاه اضافه شد" : 'محصولی انتخاب نشد یا قبلاً اضافه شده بود');
    redirect('sample_products.php');
}

$perPage = 60;
$totalJson = count($jsonItems);
$pages = max(1, (int)ceil($totalJson / $perPage));
$pg = max(1, min($pages, (int)($_GET['page'] ?? 1)));
$jsonPage = array_slice($jsonItems, ($pg - 1) * $perPage, $perPage);

$pageTitle = 'محصولات نمونه';
require_once '../includes/header.php';
?>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
                🧪 محصولات نمونه
            </h1>
            <p class="text-xs text-slate-500 mt-1">محصولات مورد نظر را تیک بزنید و روی «افزودن به محصولات» کلیک کنید.</p>
        </div>
        <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
    </div>

    <?php
        $folderItems = array_filter($jsonItems, fn($x) => isset($x['_file']));
        $noImg = array_filter($folderItems, fn($x) => empty($x['_images']));
    ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 text-xs leading-7">
        <div class="font-black text-sm mb-1">📂 منبع اصلی: پوشه <span dir="ltr">MP</span></div>
        <div>هر محصول یک پوشه به اسم خودش دارد: <b dir="ltr">MP/«نام محصول»/</b> ← فایل JSON محصول + عکس‌ها <b dir="ltr">01.jpg … 05.jpg</b> (۰۱ = عکس اصلی)</div>
        <div class="mt-1">
            محصول در پوشه: <b><?= fa_digits(count($folderItems)) ?></b>
            • بدون پوشه عکس: <b class="<?= $noImg ? 'text-amber-600' : '' ?>"><?= fa_digits(count($noImg)) ?></b>
            <?php if (!is_dir(PF_MP_DIR)): ?> • <span class="text-red-600 font-bold">پوشه MP پیدا نشد</span><?php endif; ?>
        </div>
        <?php foreach ($pfErrors as $e): ?><div class="text-red-600">• <?= sanitize($e) ?></div><?php endforeach; ?>
        <form method="post" class="mt-2" onsubmit="return confirm('اطلاعات و عکس محصولات موجود از روی پوشه‌ها به‌روزرسانی شود؟')">
            <button name="refresh_existing" value="1" class="bg-sky-600 hover:bg-sky-700 text-white font-bold px-4 py-2 rounded-xl text-xs">🔄 به‌روزرسانی محصولات اضافه‌شده از روی پوشه‌ها</button>
        </form>
    </div>

    <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 flex items-center gap-3 flex-wrap">
        <div class="text-xs font-bold text-slate-700">📁 افزودن محصولات نمونه از فایل JSON (قالب products.json):</div>
        <input type="file" name="json_file" accept=".json,application/json" required class="text-xs">
        <button name="upload_json" value="1" class="bg-slate-800 hover:bg-slate-900 text-white font-bold px-4 py-2 rounded-xl text-xs">⬆ بارگذاری</button>
    </form>

    <form method="post">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 flex items-center justify-between flex-wrap gap-3">
            <label class="flex items-center gap-2 text-sm font-bold cursor-pointer">
                <input type="checkbox" id="checkAll" class="w-4 h-4"> انتخاب همه
            </label>
            <div class="flex gap-2 flex-wrap">
                <?php if ($totalJson): ?>
                <button name="add_all_json" value="1" onclick="return confirm('همه <?= $totalJson ?> محصول فایل JSON (که قبلاً اضافه نشده‌اند) به فروشگاه اضافه شوند؟')" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs">⚡ افزودن همه محصولات فایل JSON</button>
                <?php endif; ?>
                <button name="add_samples" value="1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-5 py-2.5 rounded-xl text-xs">➕ افزودن موارد تیک‌خورده</button>
            </div>
        </div>

        <?php if ($jsonItems): ?>
        <h2 class="text-sm font-black text-slate-800 mb-2">📄 از پوشه MP / فایل JSON (<?= $totalJson ?> محصول — صفحه <?= $pg ?> از <?= $pages ?>)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-6">
        <?php foreach ($jsonPage as $x):
            $key = sj_key($x); $done = isset($existing[$key]);
            $now = sj_price($x['قیمت (تومان)'] ?? ''); ?>
            <label class="bg-white border <?= $done ? 'border-emerald-200 opacity-70' : 'border-slate-200 hover:border-brand-orange cursor-pointer' ?> rounded-2xl p-4 flex gap-3 items-start">
                <input type="checkbox" data-compact="pick_json" name="pick_json[]" value="<?= sanitize($key) ?>" class="pick w-4 h-4 mt-1" <?= $done ? 'disabled' : '' ?>>
                <?php $thumb = !empty($x['_images']) ? '../' . $x['_images'][0] : ($x['آدرس عکس اصلی'] ?? ''); ?>
                <?php if ($thumb): ?>
                    <img src="<?= sanitize($thumb) ?>" class="w-14 h-14 object-contain rounded-lg bg-slate-50 border border-slate-100" loading="lazy" onerror="this.style.visibility='hidden'">
                <?php endif; ?>
                <span class="flex-1">
                    <span class="block text-xs font-bold text-slate-800 leading-6"><?= sanitize($x['نام محصول']) ?></span>
                    <span class="block text-[10px] text-slate-400 mt-1"><?= sanitize(trim(explode('<', $x['دسته‌بندی'] ?? '')[0])) ?></span>
                    <span class="block text-xs font-black text-brand-orange mt-1"><?= $now ? number_format($now) . ' تومان' : 'بدون قیمت' ?></span>
                    <?php if (isset($x['_file'])): ?>
                        <span class="inline-block mt-1 text-[10px] <?= $x['_images'] ? 'bg-sky-100 text-sky-700' : 'bg-amber-100 text-amber-700' ?> px-2 py-0.5 rounded-full font-bold">
                            <?= $x['_images'] ? '🖼 ' . fa_digits(count($x['_images'])) . ' عکس' : '⚠ عکسی در پوشه محصول پیدا نشد' ?></span>
                    <?php endif; ?>
                    <?php if ($done): ?><span class="inline-block mt-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">✅ اضافه شده</span><?php endif; ?>
                </span>
            </label>
        <?php endforeach; ?>
        </div>
        <?php if ($pages > 1): ?>
        <div class="flex gap-1 flex-wrap mb-6 -mt-3 items-center text-xs">
            <span class="text-slate-500">صفحه:</span>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="?page=<?= $i ?>" class="px-3 py-1 rounded-lg font-bold <?= $i === $pg ? 'bg-brand-orange text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <h2 class="text-sm font-black text-slate-800 mb-2">📱 کاتالوگ داخلی (<?= count($samples) ?> محصول)</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($samples as $i => $s):
            $done = isset($existing['sample-' . ($i + 1)]); ?>
            <label class="bg-white border <?= $done ? 'border-emerald-200 opacity-70' : 'border-slate-200 hover:border-brand-orange cursor-pointer' ?> rounded-2xl p-4 flex gap-3 items-start">
                <input type="checkbox" data-compact="pick" name="pick[]" value="<?= $i ?>" class="pick w-4 h-4 mt-1" <?= $done ? 'disabled' : '' ?>>
                <span class="flex-1">
                    <span class="block text-xs font-bold text-slate-800 leading-6"><?= sanitize($s[0]) ?></span>
                    <span class="block text-[10px] text-slate-400 mt-1"><?= sanitize($s[2]) ?> • <?= sanitize($catNames[$s[3]] ?? '') ?></span>
                    <span class="block text-xs font-black text-brand-orange mt-1"><?= number_format($s[4]) ?> تومان</span>
                    <?php if ($done): ?><span class="inline-block mt-1 text-[10px] bg-emerald-100 text-emerald-700 px-2 py-0.5 rounded-full font-bold">✅ اضافه شده</span><?php endif; ?>
                </span>
            </label>
        <?php endforeach; ?>
        </div>
    </form>
</div>
<script>
document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.pick:not(:disabled)').forEach(function (c) { c.checked = this.checked; }, this);
});
</script>
<script src="../assets/compact-form.js"></script>
<?php require_once '../includes/footer.php'; ?>
