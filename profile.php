<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$tab = $_GET['tab'] ?? 'orders';

// آپدیت پروفایل
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = sanitize($_POST['name']);
    $phone = sanitize($_POST['phone']);
    $address = sanitize($_POST['address']);
    $province = sanitize($_POST['province']);
    $city = sanitize($_POST['city']);

    $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=?, province=?, city=? WHERE id=?");
    $stmt->execute([$name, $phone, $address, $province, $city, $userId]);
    $_SESSION['name'] = $name;
    setFlash('success', 'اطلاعات با موفقیت بروزرسانی شد');
    redirect('profile.php?tab=profile');
}

// اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// سفارشات کاربر
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id=? ORDER BY id DESC");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

$statusLabels = [
    'pending'    => ['در انتظار پرداخت', 'bg-amber-100 text-amber-700'],
    'processing' => ['در حال پردازش', 'bg-blue-100 text-blue-700'],
    'shipped'    => ['ارسال شده', 'bg-indigo-100 text-indigo-700'],
    'delivered'  => ['تحویل داده شده', 'bg-emerald-100 text-emerald-700'],
    'cancelled'  => ['لغو شده', 'bg-red-100 text-red-700'],
];

$pageTitle = 'پنل کاربری';
require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-2">
        <span class="w-2 h-7 bg-blue-600 rounded-full"></span>
        پنل کاربری
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Sidebar -->
        <aside class="lg:col-span-1">
            <div class="bg-white rounded-2xl border p-5 sticky top-32">
                <div class="text-center pb-4 border-b mb-4">
                    <div class="w-16 h-16 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-2xl mx-auto mb-2">
                        <i class="fa-regular fa-user"></i>
                    </div>
                    <div class="font-bold text-sm"><?= sanitize($user['name']) ?></div>
                    <div class="text-xs text-slate-400" style="direction:ltr;"><?= sanitize($user['email']) ?></div>
                </div>
                <nav class="space-y-1 text-sm">
                    <a href="?tab=orders" class="block px-3 py-2 rounded-lg <?= $tab === 'orders' ? 'bg-blue-50 text-blue-600 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-bag-shopping ml-2"></i>سفارشات من
                    </a>
                    <a href="?tab=profile" class="block px-3 py-2 rounded-lg <?= $tab === 'profile' ? 'bg-blue-50 text-blue-600 font-bold' : 'text-slate-600 hover:bg-slate-50' ?>">
                        <i class="fa-solid fa-user-gear ml-2"></i>اطلاعات حساب
                    </a>
                    <a href="logout.php" class="block px-3 py-2 rounded-lg text-red-600 hover:bg-red-50">
                        <i class="fa-solid fa-sign-out-alt ml-2"></i>خروج
                    </a>
                </nav>
            </div>
        </aside>

        <!-- Content -->
        <div class="lg:col-span-3">

            <?php if ($tab === 'orders'): ?>
                <div class="bg-white rounded-2xl border p-6">
                    <h2 class="font-bold text-lg mb-5 pb-3 border-b">سفارشات من</h2>
                    <?php if (empty($orders)): ?>
                        <div class="text-center py-12">
                            <i class="fa-solid fa-bag-shopping text-5xl text-slate-200 mb-4"></i>
                            <p class="text-sm text-slate-500">هنوز سفارشی ثبت نکرده‌اید</p>
                            <a href="products.php" class="inline-block mt-4 bg-blue-600 text-white font-bold px-5 py-2.5 rounded-xl text-xs">شروع خرید</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($orders as $o): ?>
                                <a href="order_detail.php?id=<?= $o['id'] ?>" class="block bg-slate-50 hover:bg-slate-100 rounded-xl p-4 border">
                                    <div class="flex items-center justify-between flex-wrap gap-2">
                                        <div>
                                            <div class="font-bold text-sm">سفارش #<?= faNumber($o['id']) ?></div>
                                            <div class="text-xs text-slate-500 mt-1"><?= date('Y/m/d - H:i', strtotime($o['created_at'])) ?></div>
                                        </div>
                                        <span class="text-xs px-3 py-1.5 rounded-full font-bold <?= $statusLabels[$o['status']][1] ?? 'bg-slate-100' ?>">
                                            <?= $statusLabels[$o['status']][0] ?? $o['status'] ?>
                                        </span>
                                        <div class="font-bold text-blue-600 text-sm"><?= faNumber($o['total']) ?> تومان</div>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-2xl border p-6">
                    <h2 class="font-bold text-lg mb-5 pb-3 border-b">اطلاعات حساب</h2>
                    <form method="post" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">نام کامل</label>
                            <input type="text" name="name" value="<?= sanitize($user['name']) ?>" required 
                                   class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">ایمیل</label>
                            <input type="email" value="<?= sanitize($user['email']) ?>" disabled 
                                   class="w-full px-4 py-3 bg-slate-100 border rounded-xl text-sm text-slate-500" style="direction:ltr;">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">تلفن</label>
                            <input type="text" name="phone" value="<?= sanitize($user['phone']) ?>" style="direction:ltr;"
                                   class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm focus:outline-none focus:border-blue-500">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">استان</label>
                            <select name="province" class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm focus:outline-none focus:border-blue-500">
                                <option value="">انتخاب کنید</option>
                                <?php foreach (array_keys(getIranProvinces()) as $p): ?>
                                    <option value="<?= $p ?>" <?= $user['province'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-600 mb-2">شهر</label>
                            <input type="text" name="city" value="<?= sanitize($user['city']) ?>" 
                                   class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm focus:outline-none focus:border-blue-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-bold text-slate-600 mb-2">آدرس</label>
                            <textarea name="address" rows="3" 
                                      class="w-full px-4 py-3 bg-slate-50 border rounded-xl text-sm focus:outline-none focus:border-blue-500 resize-none"><?= sanitize($user['address']) ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <button type="submit" name="update_profile" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl text-sm">
                                <i class="fa-solid fa-save"></i> ذخیره تغییرات
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>