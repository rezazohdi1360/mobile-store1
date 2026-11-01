<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/downloads.php';
dl_ensure($pdo);

if (!isAdmin()) redirect('../login.php');

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM products WHERE id=?");
$stmt->execute([$id]);
$p = $stmt->fetch();
if (!$p) { setFlash('error', 'محصول یافت نشد'); redirect('products.php'); }

$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $name_en = sanitize($_POST['name_en'] ?? '');
    $brand = sanitize($_POST['brand'] ?? '');
    $price = (int)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (int)$_POST['sale_price'] : null;
    $stock = (int)$_POST['stock'];
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $main_image = sanitize($_POST['main_image'] ?? '');
    $short_desc = sanitize($_POST['short_desc'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $warranty = sanitize($_POST['warranty'] ?? '');
    $external_id = sanitize($_POST['external_id'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    [$dlName, $dlErr] = dl_handle_form($p['download_file'] ?? '');
    if ($dlErr) setFlash('error', '⚠ ' . $dlErr);
    if ($dlName === null) $dlName = $p['download_file'] ?? '';

    $discount = 0;
    if ($sale_price && $price > 0 && $sale_price < $price) {
        $discount = round((($price - $sale_price) / $price) * 100);
    } else {
        $sale_price = null;
    }

    $stock_status = $stock > 0 ? 'in_stock' : 'out_of_stock';

    $stmt = $pdo->prepare("
        UPDATE products SET 
            name=?, name_en=?, brand=?, price=?, sale_price=?, discount_percent=?,
            stock=?, stock_status=?, category_id=?, main_image=?, short_desc=?, description=?, warranty=?, external_id=?, status=?, download_file=?
        WHERE id=?
    ");
    $stmt->execute([$name, $name_en, $brand, $price, $sale_price, $discount, $stock, $stock_status, $category_id, $main_image, $short_desc, $description, $warranty, $external_id, $status, ($dlName === '' ? null : $dlName), $id]);

    setFlash('success', '✅ محصول ویرایش شد');
    redirect('products.php');
}

$pageTitle = 'ویرایش محصول';
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            ✏ ویرایش محصول
        </h1>
        <a href="products.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← بازگشت</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <?php 
        // پیش‌نمایش عکس فعلی با اصلاح مسیر
        if ($p['main_image']): 
            $imgSrc = $p['main_image'];
            if (strpos($imgSrc, 'http') !== 0) {
                $imgSrc = '../' . $imgSrc;
            }
        ?>
            <div class="flex justify-center flex-col items-center gap-2">
                <img src="<?= sanitize($imgSrc) ?>" 
                     class="w-32 h-32 rounded-xl object-contain bg-slate-50 p-2 border border-slate-200" 
                     onerror="this.src='https://placehold.co/200x200/f1f5f9/1e293b?text=No+Image'">
                <div class="text-[10px] text-slate-400" style="direction:ltr"><?= sanitize($p['main_image']) ?></div>
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">نام محصول *</label>
            <input type="text" name="name" required value="<?= sanitize($p['name']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">نام لاتین</label>
                <input type="text" name="name_en" style="direction:ltr;" value="<?= sanitize($p['name_en']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">برند</label>
                <input type="text" name="brand" value="<?= sanitize($p['brand']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">قیمت اصلی (تومان) *</label>
                <input type="number" name="price" required min="0" value="<?= $p['price'] ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">قیمت با تخفیف</label>
                <input type="number" name="sale_price" min="0" value="<?= $p['sale_price'] ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">موجودی *</label>
                <input type="number" name="stock" required min="0" value="<?= $p['stock'] ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
                <p class="text-[10px] text-slate-400 mt-1">0 = ناموجود</p>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">دسته‌بندی</label>
                <select name="category_id" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $p['category_id'] == $c['id'] ? 'selected' : '' ?>><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">آدرس تصویر</label>
            <input type="text" name="main_image" style="direction:ltr;" value="<?= sanitize($p['main_image']) ?>" placeholder="uploads/pictuer/dkp-4019035.jpg یا https://..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            <p class="text-[10px] text-slate-400 mt-1">مسیر نسبی (uploads/...) یا URL کامل (https://...)</p>
        </div>

        <?= dl_form_html($p['download_file'] ?? '') ?>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">شناسه خارجی</label>
            <input type="text" name="external_id" style="direction:ltr;" value="<?= sanitize($p['external_id']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">توضیح کوتاه</label>
            <input type="text" name="short_desc" value="<?= sanitize($p['short_desc']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">توضیحات کامل</label>
            <textarea name="description" rows="5" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange resize-none"><?= sanitize($p['description']) ?></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">گارانتی</label>
            <input type="text" name="warranty" value="<?= sanitize($p['warranty']) ?>" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-xl">
            <input type="checkbox" name="status" id="status" <?= $p['status'] ? 'checked' : '' ?> class="w-4 h-4">
            <label for="status" class="text-sm font-bold text-slate-700 cursor-pointer">✅ فعال (نمایش در سایت)</label>
        </div>

        <div class="flex gap-3 pt-3">
            <button type="submit" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-8 py-3 rounded-xl text-sm shadow-md">💾 ذخیره تغییرات</button>
            <a href="products.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-6 py-3 rounded-xl text-sm">لغو</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>