<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    $name = sanitize($_POST['name']);
    $slug = makeSlug($name);
    $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?,?)")->execute([$name, $slug]);
    setFlash('success', 'دسته‌بندی اضافه شد');
    redirect('categories.php');
}

// حذف همه‌ی دسته‌بندی‌ها (فقط با POST). محصولات حذف نمی‌شوند؛ دسته‌ی آن‌ها خالی می‌شود (ON DELETE SET NULL)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
    $n = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
    $pdo->exec("DELETE FROM categories");
    setFlash('success', $n ? "همه‌ی دسته‌بندی‌ها حذف شد ($n مورد)" : 'دسته‌بندی‌ای برای حذف وجود نداشت');
    redirect('categories.php');
}

if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success', 'دسته‌بندی حذف شد');
    redirect('categories.php');
}

$cats = $pdo->query("
    SELECT c.*, (SELECT COUNT(*) FROM products WHERE category_id=c.id) AS product_count 
    FROM categories c ORDER BY c.id DESC
")->fetchAll();

$pageTitle = 'دسته‌بندی‌ها';
require_once '../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            📁 مدیریت دسته‌بندی‌ها
        </h1>
        <div class="flex items-center gap-2">
            <?php if ($cats): ?>
            <form method="post" class="m-0" onsubmit="return confirm('همه‌ی <?= count($cats) ?> دسته‌بندی حذف شود؟\nمحصولات حذف نمی‌شوند، فقط بدون دسته‌بندی می‌شوند.\nاین کار قابل بازگشت نیست.')">
                <button name="delete_all" value="1" class="bg-red-600 hover:bg-red-700 text-white font-bold px-4 py-2.5 rounded-xl text-xs">🗑 حذف همه دسته‌بندی‌ها</button>
            </form>
            <?php endif; ?>
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
        </div>
    </div>

    <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5 mb-5 flex gap-2">
        <input type="text" name="name" placeholder="نام دسته‌بندی جدید..." required class="flex-1 px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        <button name="add" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-3 rounded-xl text-sm">➕ افزودن</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs text-slate-500">
                    <th class="px-4 py-3 text-right font-bold">#</th>
                    <th class="px-4 py-3 text-right font-bold">نام</th>
                    <th class="px-4 py-3 text-right font-bold">تعداد محصول</th>
                    <th class="px-4 py-3 text-right font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($cats as $c): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-xs font-bold"><?= $c['id'] ?></td>
                        <td class="px-4 py-3 text-xs font-bold text-slate-800"><?= sanitize($c['name']) ?></td>
                        <td class="px-4 py-3 text-xs">
                            <a href="products.php?cat=<?= $c['id'] ?>" class="text-brand-orange font-bold"><?= $c['product_count'] ?> محصول</a>
                        </td>
                        <td class="px-4 py-3">
                            <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('حذف شود؟')" class="text-[10px] bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded-lg font-bold">🗑 حذف</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>