<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$_POST['status'], (int)$_POST['order_id']]);
    setFlash('success', 'وضعیت بروزرسانی شد');
    redirect('orders.php');
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->beginTransaction();
    try {
        $pdo->prepare("DELETE FROM order_items WHERE order_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$id]);
        $pdo->commit();
        setFlash('success', 'سفارش حذف شد');
    } catch (Exception $e) { $pdo->rollBack(); setFlash('error', 'خطا در حذف'); }
    redirect('orders.php');
}

$filter = $_GET['status'] ?? '';
$search = sanitize($_GET['q'] ?? '');
$where = "WHERE 1=1";
$params = [];

if ($filter && in_array($filter, ['pending','processing','shipped','delivered','cancelled'])) {
    $where .= " AND o.status=?";
    $params[] = $filter;
}
if ($search) {
    $where .= " AND (o.id LIKE ? OR o.customer_name LIKE ? OR o.email LIKE ? OR o.phone LIKE ?)";
    $like = "%$search%";
    array_push($params, $like, $like, $like, $like);
}

$orders = $pdo->prepare("
    SELECT o.*, 
    (SELECT COUNT(*) FROM order_items WHERE order_id=o.id) AS items_count
    FROM orders o
    $where ORDER BY o.id DESC LIMIT 100
");
$orders->execute($params);
$orders = $orders->fetchAll();

$statusLabels = [
    'pending' => ['در انتظار', 'bg-amber-100 text-amber-700'],
    'processing' => ['در حال پردازش', 'bg-blue-100 text-blue-700'],
    'shipped' => ['ارسال شده', 'bg-indigo-100 text-indigo-700'],
    'delivered' => ['تحویل شده', 'bg-emerald-100 text-emerald-700'],
    'cancelled' => ['لغو شده', 'bg-red-100 text-red-700'],
];

$pageTitle = 'مدیریت سفارشات';
require_once '../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            🛒 مدیریت سفارشات
        </h1>
        <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
    </div>

    <div class="flex flex-wrap gap-2 mb-5">
        <a href="orders.php" class="px-4 py-2 rounded-xl text-xs font-bold <?= !$filter ? 'bg-brand-orange text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">همه</a>
        <?php foreach ($statusLabels as $k => $v): ?>
            <a href="?status=<?= $k ?>" class="px-4 py-2 rounded-xl text-xs font-bold <?= $filter === $k ? 'bg-brand-orange text-white' : 'bg-white border border-slate-200 text-slate-700' ?>"><?= $v[0] ?></a>
        <?php endforeach; ?>
    </div>

    <form method="get" class="bg-white border border-slate-200 rounded-2xl p-4 mb-5 flex gap-2">
        <?php if ($filter): ?><input type="hidden" name="status" value="<?= $filter ?>"><?php endif; ?>
        <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="جستجو..." class="flex-1 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
        <button class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-2.5 rounded-xl text-xs">🔍</button>
    </form>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-xs text-slate-500">
                        <th class="px-4 py-3 text-right font-bold">#</th>
                        <th class="px-4 py-3 text-right font-bold">کد</th>
                        <th class="px-4 py-3 text-right font-bold">مشتری</th>
                        <th class="px-4 py-3 text-right font-bold">تلفن</th>
                        <th class="px-4 py-3 text-right font-bold">اقلام</th>
                        <th class="px-4 py-3 text-right font-bold">مبلغ</th>
                        <th class="px-4 py-3 text-right font-bold">وضعیت</th>
                        <th class="px-4 py-3 text-right font-bold">تاریخ</th>
                        <th class="px-4 py-3 text-right font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="9" class="px-4 py-12 text-center text-slate-400 text-xs">سفارشی یافت نشد</td></tr>
                    <?php else: foreach ($orders as $o): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-xs font-bold">#<?= $o['id'] ?></td>
                            <td class="px-4 py-3 text-[10px]" style="direction:ltr;"><?= sanitize($o['order_code'] ?? '—') ?></td>
                            <td class="px-4 py-3">
                                <div class="text-xs font-bold text-slate-800"><?= sanitize($o['customer_name']) ?></div>
                                <div class="text-[10px] text-slate-400" style="direction:ltr;"><?= sanitize($o['email'] ?? '') ?></div>
                            </td>
                            <td class="px-4 py-3 text-xs" style="direction:ltr;"><?= sanitize($o['phone']) ?></td>
                            <td class="px-4 py-3 text-xs"><?= $o['items_count'] ?> قلم</td>
                            <td class="px-4 py-3 text-xs font-bold text-brand-orange"><?= number_format($o['total']) ?></td>
                            <td class="px-4 py-3">
                                <form method="post" class="inline">
                                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="text-[10px] border border-slate-200 rounded-lg px-2 py-1 font-bold">
                                        <?php foreach ($statusLabels as $k => $v): ?>
                                            <option value="<?= $k ?>" <?= $o['status'] === $k ? 'selected' : '' ?>><?= $v[0] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td class="px-4 py-3 text-[10px] text-slate-500"><?= date('Y/m/d', strtotime($o['created_at'])) ?></td>
                            <td class="px-4 py-3">
                                <a href="?delete=<?= $o['id'] ?>" onclick="return confirm('حذف شود؟')" class="text-[10px] bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded-lg font-bold">🗑</a>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>