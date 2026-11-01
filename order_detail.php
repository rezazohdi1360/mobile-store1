<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('login.php');

$orderId = (int)($_GET['id'] ?? 0);
$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id=? AND user_id=?");
$stmt->execute([$orderId, $userId]);
$order = $stmt->fetch();

if (!$order) { setFlash('error', 'سفارش یافت نشد'); redirect('profile.php'); }

$stmt = $pdo->prepare("
    SELECT oi.*, p.name, p.main_image 
    FROM order_items oi 
    LEFT JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->execute([$orderId]);
$items = $stmt->fetchAll();

require_once 'includes/downloads.php';
$dlFiles = (($order['payment_status'] ?? '') === 'paid') ? dl_order_files($pdo, $orderId) : [];

$statusLabels = [
    'pending'    => ['در انتظار پرداخت', 'bg-amber-100 text-amber-700'],
    'processing' => ['در حال پردازش', 'bg-blue-100 text-blue-700'],
    'shipped'    => ['ارسال شده', 'bg-indigo-100 text-indigo-700'],
    'delivered'  => ['تحویل داده شده', 'bg-emerald-100 text-emerald-700'],
    'cancelled'  => ['لغو شده', 'bg-red-100 text-red-700'],
];

$pageTitle = 'سفارش #' . $orderId;
require_once 'includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <nav class="text-xs text-slate-500 mb-6 flex items-center gap-2">
        <a href="profile.php" class="hover:text-blue-600">پنل کاربری</a>
        <i class="fa-solid fa-chevron-left text-[10px]"></i>
        <a href="profile.php?tab=orders" class="hover:text-blue-600">سفارشات</a>
        <i class="fa-solid fa-chevron-left text-[10px]"></i>
        <span class="text-slate-700">سفارش #<?= faNumber($orderId) ?></span>
    </nav>

    <?php if ($dlFiles): ?>
    <div class="bg-emerald-50 border-2 border-dashed border-emerald-500 rounded-2xl p-5 mb-5">
        <div class="font-black text-emerald-800 mb-3">📥 فایل‌های دانلودی</div>
        <?php foreach ($dlFiles as $df): ?>
        <a href="download.php?code=<?= urlencode($order['order_code']) ?>&item=<?= (int)$df['item_id'] ?>" style="display:flex;justify-content:space-between;gap:8px;background:#059669;color:#fff;padding:10px 14px;border-radius:10px;margin-bottom:6px;font-size:13px"><span>⬇ <?= htmlspecialchars($df['product_name']) ?></span><span style="direction:ltr"><?= htmlspecialchars($df['download_file']) ?> · <?= dl_size($df['size']) ?></span></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="bg-white border rounded-2xl p-6 mb-5">
        <div class="flex justify-between items-start flex-wrap gap-3 mb-5">
            <div>
                <h1 class="text-xl font-black">سفارش #<?= faNumber($order['id']) ?></h1>
                <p class="text-xs text-slate-500 mt-1"><?= date('Y/m/d - H:i', strtotime($order['created_at'])) ?></p>
            </div>
            <a href="invoice.php?code=<?= urlencode($order['order_code']) ?>" class="text-xs px-3 py-1.5 rounded-full font-bold bg-emerald-600 text-white">📄 دانلود فاکتور PDF</a>
            <span class="text-xs px-3 py-1.5 rounded-full font-bold <?= $statusLabels[$order['status']][1] ?? 'bg-slate-100' ?>">
                <?= $statusLabels[$order['status']][0] ?? $order['status'] ?>
            </span>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-slate-50 rounded-xl p-3">
                <div class="text-[10px] text-slate-500 mb-1">مبلغ کل</div>
                <div class="text-xs font-bold text-blue-600"><?= faNumber($order['total']) ?> تومان</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
                <div class="text-[10px] text-slate-500 mb-1">تعداد اقلام</div>
                <div class="text-xs font-bold"><?= faNumber(count($items)) ?> قلم</div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
                <div class="text-[10px] text-slate-500 mb-1">تلفن</div>
                <div class="text-xs font-bold" style="direction:ltr;"><?= sanitize($order['phone']) ?></div>
            </div>
            <div class="bg-slate-50 rounded-xl p-3">
                <div class="text-[10px] text-slate-500 mb-1">روش پرداخت</div>
                <div class="text-xs font-bold"><?= $order['payment_method'] === 'cod' ? 'پرداخت در محل' : 'آنلاین' ?></div>
            </div>
        </div>
    </div>

    <div class="bg-white border rounded-2xl p-6 mb-5">
        <h2 class="font-bold text-sm mb-4">📍 آدرس ارسال</h2>
        <div class="bg-slate-50 rounded-xl p-4 text-xs leading-7">
            <div><strong>استان:</strong> <?= sanitize($order['province'] ?? '-') ?></div>
            <div><strong>شهر:</strong> <?= sanitize($order['city'] ?? '-') ?></div>
            <div><strong>آدرس:</strong> <?= sanitize($order['address']) ?></div>
        </div>
    </div>

    <div class="bg-white border rounded-2xl p-6">
        <h2 class="font-bold text-sm mb-4">🛍 اقلام سفارش</h2>
        <div class="space-y-3">
            <?php foreach ($items as $item): ?>
                <div class="flex items-center gap-4 bg-slate-50 rounded-xl p-3">
                    <img src="<?= sanitize($item['main_image']) ?>" 
                         class="w-16 h-16 object-contain bg-white rounded-lg p-1 border"
                         onerror="this.src='https://placehold.co/100x100/f1f5f9/1e293b?text=P'">
                    <div class="flex-1 min-w-0">
                        <div class="text-xs font-bold text-slate-800 line-clamp-1"><?= sanitize($item['name'] ?? 'محصول حذف شده') ?></div>
                        <div class="text-[10px] text-slate-500 mt-1">
                            تعداد: <?= faNumber($item['quantity']) ?> × <?= faNumber($item['price']) ?> تومان
                        </div>
                    </div>
                    <div class="text-xs font-bold text-blue-600"><?= faNumber($item['price'] * $item['quantity']) ?> تومان</div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>