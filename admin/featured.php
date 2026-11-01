<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/featured.php';

if (!isAdmin()) redirect('../login.php');
featured_ensure($pdo);

$q = trim($_GET['q'] ?? '');
$pg = max(1, (int)($_GET['page'] ?? 1));
$per = 60;

// ───── ذخیره انتخاب‌ها (فقط محصولات همین صفحه) ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_featured'])) {
    $shown   = array_map('intval', $_POST['shown'] ?? []);
    $checked = array_flip(array_map('intval', $_POST['featured'] ?? []));
    $set = $pdo->prepare("UPDATE products SET featured = ? WHERE id = ?");
    foreach ($shown as $id) $set->execute([isset($checked[$id]) ? 1 : 0, $id]);
    setFlash('success', 'محصولات منتخب ذخیره شد و در «پیشنهاد ویژه» صفحه اصلی نمایش داده می‌شوند');
    redirect('featured.php?' . http_build_query(['q' => $q, 'page' => $pg]));
}
if (isset($_GET['clear'])) {
    $pdo->exec("UPDATE products SET featured = 0");
    setFlash('success', 'همه محصولات منتخب برداشته شدند');
    redirect('featured.php');
}

$where = $q !== '' ? "WHERE name LIKE ?" : "";
$params = $q !== '' ? ["%$q%"] : [];
$cnt = $pdo->prepare("SELECT COUNT(*) FROM products $where"); $cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = max(1, (int)ceil($total / $per));
$pg = min($pg, $pages);
$st = $pdo->prepare("SELECT id, name, main_image, price, sale_price, stock_status, status, featured FROM products $where ORDER BY featured DESC, id DESC LIMIT $per OFFSET " . (($pg - 1) * $per));
$st->execute($params);
$products = $st->fetchAll();
$featuredCount = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE featured = 1")->fetchColumn();

$pageTitle = 'محصولات منتخب';
require_once '../includes/header.php';
?>
<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
                ⭐ محصولات منتخب
            </h1>
            <p class="text-xs text-slate-500 mt-1">محصولات تیک‌خورده در بخش «پیشنهاد ویژه» صفحه اصلی نمایش داده می‌شوند. فعلاً <b><?= $featuredCount ?></b> محصول منتخب است.</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
            <a href="?clear=1" onclick="return confirm('همه محصولات منتخب برداشته شوند؟')" class="bg-red-100 hover:bg-red-200 text-red-700 font-bold px-4 py-2.5 rounded-xl text-xs">برداشتن همه</a>
        </div>
    </div>

    <?= showFlash() ?>

    <form method="get" class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 flex gap-2">
        <input type="text" name="q" value="<?= sanitize($q) ?>" placeholder="جستجوی محصول..." class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
        <button class="bg-slate-800 text-white font-bold px-5 py-2.5 rounded-xl text-xs">🔍 جستجو</button>
    </form>

    <form method="post">
        <div class="bg-white border border-slate-200 rounded-2xl p-4 mb-4 flex items-center justify-between flex-wrap gap-3">
            <label class="flex items-center gap-2 text-sm font-bold cursor-pointer"><input type="checkbox" id="checkAll" class="w-4 h-4"> انتخاب همه محصولات این صفحه</label>
            <button name="save_featured" value="1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-2.5 rounded-xl text-xs">⭐ ذخیره محصولات منتخب</button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($products as $p):
            $img = $p['main_image'] ?: 'assets/no-image.png';
            if (strpos($img, 'http') !== 0) $img = '../' . $img;
            $price = ($p['sale_price'] && $p['sale_price'] < $p['price']) ? $p['sale_price'] : $p['price']; ?>
            <label class="bg-white border <?= $p['featured'] ? 'border-amber-300 bg-amber-50' : 'border-slate-200' ?> rounded-2xl p-3 flex gap-3 items-center cursor-pointer hover:border-brand-orange">
                <input type="hidden" name="shown[]" value="<?= (int)$p['id'] ?>">
                <input type="checkbox" name="featured[]" value="<?= (int)$p['id'] ?>" class="pick w-4 h-4" <?= $p['featured'] ? 'checked' : '' ?>>
                <img src="<?= sanitize($img) ?>" class="w-14 h-14 object-contain rounded-lg bg-slate-50 border border-slate-100" loading="lazy" onerror="this.style.visibility='hidden'">
                <span class="flex-1 min-w-0">
                    <span class="block text-xs font-bold text-slate-800 leading-6"><?= sanitize(mb_substr($p['name'], 0, 70)) ?></span>
                    <span class="block text-xs font-black text-brand-orange"><?= number_format($price) ?> تومان</span>
                    <?php if (!$p['status']): ?><span class="text-[10px] text-slate-400">🙈 مخفی در سایت</span><?php endif; ?>
                    <?php if ($p['stock_status'] !== 'in_stock'): ?><span class="text-[10px] text-red-500">ناموجود</span><?php endif; ?>
                </span>
            </label>
        <?php endforeach; ?>
        <?php if (!$products): ?><div class="text-xs text-slate-400 p-6">محصولی پیدا نشد.</div><?php endif; ?>
        </div>
    </form>

    <?php if ($pages > 1): ?>
    <div class="flex gap-1 flex-wrap mt-5 items-center text-xs">
        <span class="text-slate-500">صفحه:</span>
        <?php for ($i = 1; $i <= $pages; $i++): ?>
            <a href="?<?= http_build_query(['q' => $q, 'page' => $i]) ?>" class="px-3 py-1 rounded-lg font-bold <?= $i === $pg ? 'bg-brand-orange text-white' : 'bg-white border border-slate-200 text-slate-600' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <span class="text-slate-400 mr-2">(قبل از رفتن به صفحه دیگر، ذخیره کنید)</span>
    </div>
    <?php endif; ?>
</div>
<script>
document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.pick').forEach(function (c) { c.checked = this.checked; }, this);
});
</script>
<?php require_once '../includes/footer.php'; ?>
