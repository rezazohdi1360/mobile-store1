<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/downloads.php';
dl_ensure($pdo);

if (!isAdmin()) redirect('../login.php');

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

    [$dlName, $dlErr] = dl_handle_form('');
    if ($dlErr) setFlash('error', '⚠ ' . $dlErr);

    $discount = 0;
    if ($sale_price && $price > 0 && $sale_price < $price) {
        $discount = round((($price - $sale_price) / $price) * 100);
    } else {
        $sale_price = null;
    }

    $stock_status = $stock > 0 ? 'in_stock' : 'out_of_stock';
    $slug = makeSlug($name) . '-' . time();

    $stmt = $pdo->prepare("
        INSERT INTO products (name, name_en, slug, brand, price, sale_price, discount_percent, stock, stock_status, category_id, main_image, short_desc, description, warranty, external_id, status, download_file)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([$name, $name_en, $slug, $brand, $price, $sale_price, $discount, $stock, $stock_status, $category_id, $main_image, $short_desc, $description, $warranty, $external_id, $status, ($dlName ?: null)]);

    setFlash('success', '✅ محصول با موفقیت اضافه شد');
    redirect('products.php');
}

$pageTitle = 'افزودن محصول';
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            ➕ افزودن محصول جدید
        </h1>
        <a href="products.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← بازگشت</a>
    </div>

    <form method="post" enctype="multipart/form-data" class="bg-white border border-slate-200 rounded-2xl p-6 space-y-5">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">نام محصول *</label>
            <input type="text" name="name" required class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">نام لاتین</label>
                <input type="text" name="name_en" style="direction:ltr;" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">برند</label>
                <input type="text" name="brand" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">قیمت اصلی (تومان) *</label>
                <input type="number" name="price" required min="0" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">قیمت با تخفیف</label>
                <input type="number" name="sale_price" min="0" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">موجودی *</label>
                <input type="number" name="stock" value="10" required min="0" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">دسته‌بندی</label>
                <select name="category_id" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
                    <option value="">— انتخاب کنید —</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= $c['id'] ?>"><?= sanitize($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">آدرس تصویر (URL)</label>
            <input type="url" name="main_image" style="direction:ltr;" placeholder="https://..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">شناسه خارجی</label>
            <input type="text" name="external_id" style="direction:ltr;" placeholder="dkp-4019035" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">توضیح کوتاه</label>
            <input type="text" name="short_desc" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">توضیحات کامل</label>
            <textarea name="description" rows="5" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange resize-none"></textarea>
        </div>

        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">گارانتی</label>
            <input type="text" name="warranty" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        </div>

        <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-xl">
            <input type="checkbox" name="status" id="status" checked class="w-4 h-4">
            <label for="status" class="text-sm font-bold text-slate-700 cursor-pointer">✅ فعال (نمایش در سایت)</label>
        </div>

        <div class="flex gap-3 pt-3">
            <?= dl_form_html('') ?>
        <button type="submit" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-8 py-3 rounded-xl text-sm shadow-md">💾 ذخیره محصول</button>
            <a href="products.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-6 py-3 rounded-xl text-sm">لغو</a>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>