<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isLoggedIn()) redirect('login.php');
if (isAdmin()) redirect('admin/index.php');

$userId = $_SESSION['user_id'];

// واکشی اطلاعات کاربر
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// واکشی سفارشات کاربر
$stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20");
$stmt->execute([$userId]);
$orders = $stmt->fetchAll();

// واکشی شماره‌های تماس با کاربر (سفارشات مهمان با همان شماره)
$stmt = $pdo->prepare("SELECT * FROM orders WHERE phone = ? AND user_id IS NULL ORDER BY id DESC LIMIT 20");
$stmt->execute([$user['phone'] ?? '']);
$guestOrders = $stmt->fetchAll();

$allOrders = array_merge($orders, $guestOrders);
usort($allOrders, fn($a, $b) => $b['id'] - $a['id']);

$statusLabels = [
    'pending' => ['در انتظار', '#f59e0b'],
    'processing' => ['در حال پردازش', '#2563eb'],
    'shipped' => ['ارسال شده', '#6366f1'],
    'delivered' => ['تحویل شده', '#16a34a'],
    'cancelled' => ['لغو شده', '#dc2626'],
];

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>حساب کاربری | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box}
body{font-family:tahoma,sans-serif;background:#f8fafc;margin:0;padding:0;color:#111}
a{text-decoration:none}
.container{max-width:1100px;margin:0 auto;padding:20px}

.site-header{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.06);position:sticky;top:0;z-index:100}
.header-inner{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;gap:20px;max-width:1100px;margin:0 auto}
.logo{font-size:20px;font-weight:bold;color:#2563eb;display:flex;align-items:center;gap:8px}
.header-actions{display:flex;align-items:center;gap:12px}
.header-actions a{color:#374151;font-size:13px;font-weight:bold;padding:8px 12px;border-radius:8px}
.header-actions a:hover{background:#eff6ff;color:#2563eb}
.cart-badge{background:#ef4444;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;font-weight:bold}

.breadcrumb{color:#6b7280;font-size:13px;margin:20px 0}
.breadcrumb a{color:#2563eb}

.profile-card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:25px}
.profile-header{display:flex;align-items:center;gap:20px;flex-wrap:wrap}
.avatar{width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#7c3aed);color:#fff;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:bold}
.profile-info h1{font-size:20px;margin:0 0 5px 0}
.profile-info p{color:#6b7280;font-size:13px;margin:0;direction:ltr;text-align:right}

.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:15px;margin:20px 0}
.stat{background:#f9fafb;border-radius:12px;padding:15px;text-align:center}
.stat .num{font-size:22px;font-weight:bold;color:#2563eb}
.stat .label{font-size:12px;color:#6b7280;margin-top:5px}

.section{background:#fff;border-radius:16px;padding:25px;box-shadow:0 2px 12px rgba(0,0,0,.06);margin-bottom:25px}
.section h2{font-size:17px;margin:0 0 20px 0;padding-bottom:12px;border-bottom:2px solid #eff6ff;display:flex;align-items:center;gap:8px}
.section h2::before{content:'';width:4px;height:22px;background:#2563eb;border-radius:3px}

.order-item{display:grid;grid-template-columns:auto 1fr auto auto;gap:15px;padding:15px;border:1px solid #e5e7eb;border-radius:12px;margin-bottom:12px;align-items:center;transition:all .2s}
.order-item:hover{border-color:#2563eb;background:#f9fafb}
.order-code{font-weight:bold;color:#2563eb;font-size:14px;direction:ltr}
.order-info{font-size:12px;color:#6b7280;line-height:1.8}
.order-total{font-weight:bold;color:#16a34a;font-size:15px;white-space:nowrap}
.order-status{padding:5px 12px;border-radius:20px;font-size:11px;font-weight:bold;color:#fff;white-space:nowrap}

.empty-state{text-align:center;padding:50px 20px;color:#9ca3af}
.empty-state .icon{font-size:48px;margin-bottom:12px}
.empty-state p{font-size:13px;margin:0}
.empty-state a{color:#2563eb;font-weight:bold}

.logout-btn{display:inline-block;background:#fef2f2;color:#dc2626;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:bold;margin-top:15px}
.logout-btn:hover{background:#fecaca}

@media (max-width:600px){
    .order-item{grid-template-columns:1fr;gap:8px}
    .header-inner{flex-wrap:wrap}
}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

<div class="breadcrumb">
<a href="index.php">🏠 خانه</a> › <span>حساب کاربری</span>
</div>

<!-- پروفایل -->
<div class="profile-card">
<div class="profile-header">
<div class="avatar"><?= mb_substr($user['name'], 0, 1) ?></div>
<div class="profile-info" style="flex:1">
<h1><?= htmlspecialchars($user['name']) ?></h1>
<p>📧 <?= htmlspecialchars($user['email']) ?></p>
<?php if ($user['phone']): ?>
<p>📞 <?= htmlspecialchars($user['phone']) ?></p>
<?php endif; ?>
</div>
<a href="logout.php" class="logout-btn">🚪 خروج از حساب</a>
</div>

<div class="stats">
<div class="stat">
<div class="num"><?= count($allOrders) ?></div>
<div class="label">کل سفارشات</div>
</div>
<div class="stat">
<div class="num"><?= count(array_filter($allOrders, fn($o) => $o['status'] === 'delivered')) ?></div>
<div class="label">تحویل شده</div>
</div>
<div class="stat">
<div class="num"><?= count(array_filter($allOrders, fn($o) => in_array($o['status'], ['pending','processing','shipped']))) ?></div>
<div class="label">در جریان</div>
</div>
</div>
</div>

<!-- سفارشات -->
<div class="section">
<h2>📦 سفارشات من</h2>

<?php if (empty($allOrders)): ?>
<div class="empty-state">
<div class="icon">📭</div>
<p>هنوز سفارشی ثبت نکرده‌اید</p>
<p style="margin-top:12px"><a href="index.php">شروع خرید ←</a></p>
</div>
<?php else: ?>
<?php foreach ($allOrders as $o): 
    $st = $statusLabels[$o['status']] ?? ['نامعلوم', '#6b7280'];
?>
<div class="order-item">
<div class="order-code">#<?= htmlspecialchars($o['order_code'] ?? $o['id']) ?></div>
<div class="order-info">
📅 <?= date('Y/m/d H:i', strtotime($o['created_at'])) ?><br>
📍 <?= htmlspecialchars(($o['province'] ?? '') . '، ' . ($o['city'] ?? '')) ?>
</div>
<div class="order-total"><?= number_format($o['total']) ?> تومان</div>
<div class="order-status" style="background:<?= $st[1] ?>"><?= $st[0] ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>

</div>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>