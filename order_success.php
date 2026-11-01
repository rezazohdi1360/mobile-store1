<?php
/**
 * صفحه موفقیت پرداخت
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

$orderCode = $_SESSION['last_order_code'] ?? '';
if (empty($orderCode)) {
    header('Location: index.php');
    exit;
}

// واکشی سفارش
$stmt = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
$stmt->execute([$orderCode]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

// واکشی آیتم‌های سفارش
$itemStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
$itemStmt->execute([$order['id']]);
$orderItems = $itemStmt->fetchAll();

require_once 'includes/downloads.php';
$dlFiles = (($order['payment_status'] ?? '') === 'paid') ? dl_order_files($pdo, $order['id']) : [];
$cartCount = 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سفارش موفق | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box}
body{font-family:tahoma,sans-serif;background:#f0fdf4;margin:0;padding:20px;min-height:100vh}
a{text-decoration:none}
.container{max-width:700px;margin:0 auto}

.progress-steps{
    display:flex;justify-content:center;background:#fff;
    padding:18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);
    margin-bottom:25px;flex-wrap:wrap;gap:6px;
}
.step{display:flex;align-items:center;gap:8px;color:#16a34a;font-size:13px;font-weight:bold}
.step .circle{width:28px;height:28px;border-radius:50%;background:#16a34a;color:#fff;display:flex;align-items:center;justify-content:center;font-size:12px}
.step-line{width:30px;height:2px;background:#16a34a;margin:0 6px}

.success-card{
    background:#fff;border-radius:20px;padding:40px 30px;
    text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.08);
}

.success-icon{
    width:100px;height:100px;border-radius:50%;
    background:linear-gradient(135deg,#16a34a,#15803d);
    display:flex;align-items:center;justify-content:center;
    font-size:50px;margin:0 auto 25px;color:#fff;
    animation:pop .5s ease;
}
@keyframes pop{0%{transform:scale(0)}50%{transform:scale(1.1)}100%{transform:scale(1)}}

h1{color:#16a34a;margin:0 0 15px 0;font-size:24px}
.subtitle{color:#6b7280;margin-bottom:25px;line-height:1.8;font-size:14px}

.order-info{
    background:#f9fafb;border-radius:14px;
    padding:20px;text-align:right;margin:25px 0;
}
.info-row{
    display:flex;justify-content:space-between;
    padding:10px 0;border-bottom:1px dashed #e5e7eb;
    font-size:14px;gap:15px;
}
.info-row:last-child{border-bottom:none}
.info-row .label{color:#6b7280;white-space:nowrap}
.info-row .value{color:#111;font-weight:bold;text-align:left;direction:ltr}
.info-row .value.fa{direction:rtl;text-align:right;font-weight:normal;font-size:13px}
.info-row .value.total{color:#16a34a;font-size:18px}
.info-row .value.paid{color:#16a34a}

.items-list{margin:20px 0;text-align:right}
.item{
    display:grid;grid-template-columns:60px 1fr auto;
    gap:12px;align-items:center;padding:12px;
    background:#f9fafb;border-radius:10px;margin-bottom:10px;
}
.item img{width:60px;height:60px;object-fit:contain;background:#fff;border-radius:8px;padding:4px}
.item .name{font-size:13px;color:#374151;line-height:1.5}
.item .qty{color:#6b7280;font-size:12px}
.item .price{font-weight:bold;color:#111;white-space:nowrap;font-size:13px}

.btn-home{
    display:inline-block;background:#2563eb;color:#fff;
    padding:14px 30px;border-radius:12px;
    font-weight:bold;margin:5px;font-size:14px;
    transition:background .2s;
}
.btn-home:hover{background:#1d4ed8}

.btn-secondary{
    display:inline-block;background:#fff;color:#2563eb;
    border:2px solid #2563eb;padding:12px 28px;
    border-radius:12px;font-weight:bold;margin:5px;
    font-size:14px;transition:background .2s;
}
.btn-secondary:hover{background:#eff6ff}

@media(max-width:600px){
    .info-row{flex-direction:column;gap:5px}
    .info-row .value{text-align:right;direction:rtl}
    .item{grid-template-columns:50px 1fr;gap:10px}
    .item img{width:50px;height:50px}
    .item .price{grid-column:1/-1;text-align:left}
}
</style>
</head>
<body>
<div class="container">

<div class="progress-steps">
<div class="step"><span class="circle">✓</span> سبد</div>
<div class="step-line"></div>
<div class="step"><span class="circle">✓</span> اطلاعات</div>
<div class="step-line"></div>
<div class="step"><span class="circle">✓</span> پرداخت</div>
</div>

<div class="success-card">
<div class="success-icon">✓</div>
<h1>سفارش شما با موفقیت ثبت شد!</h1>
<div class="subtitle">
از خرید شما سپاسگزاریم 🌹<br>
همکاران ما به‌زودی با شما تماس می‌گیرند.
</div>

<div class="order-info">
<div class="info-row">
<span class="label">کد سفارش</span>
<span class="value"><?= htmlspecialchars($order['order_code']) ?></span>
</div>
<div class="info-row">
<span class="label">نام گیرنده</span>
<span class="value fa"><?= htmlspecialchars($order['customer_name']) ?></span>
</div>
<div class="info-row">
<span class="label">شماره تماس</span>
<span class="value"><?= htmlspecialchars($order['phone']) ?></span>
</div>
<?php if (!empty($order['email'])): ?>
<div class="info-row">
<span class="label">ایمیل</span>
<span class="value"><?= htmlspecialchars($order['email']) ?></span>
</div>
<?php endif; ?>
<div class="info-row">
<span class="label">آدرس</span>
<span class="value fa">
<?= htmlspecialchars(($order['province'] ?? '') . '، ' . ($order['city'] ?? '') . '، ' . $order['address']) ?>
</span>
</div>
<?php if (!empty($order['postal_code'])): ?>
<div class="info-row">
<span class="label">کد پستی</span>
<span class="value"><?= htmlspecialchars($order['postal_code']) ?></span>
</div>
<?php endif; ?>
<div class="info-row">
<span class="label">وضعیت</span>
<span class="value paid">✅ پرداخت شده</span>
</div>
<div class="info-row">
<span class="label">مبلغ کل</span>
<span class="value total"><?= number_format($order['total']) ?> تومان</span>
</div>
<div class="info-row">
<span class="label">تاریخ ثبت</span>
<span class="value fa"><?= date('Y/m/d - H:i', strtotime($order['created_at'])) ?></span>
</div>
</div>

<?php if (!empty($orderItems)): ?>
<h3 style="text-align:right;color:#374151;font-size:15px;margin-top:25px;">🛍 محصولات سفارش:</h3>
<div class="items-list">
<?php foreach ($orderItems as $it): ?>
<div class="item">
<img src="<?= htmlspecialchars($it['product_image'] ?: 'assets/no-image.png') ?>" alt="" onerror="this.src='assets/no-image.png'">
<div>
<div class="name"><?= htmlspecialchars(mb_substr($it['product_name'], 0, 60)) ?></div>
<div class="qty">تعداد: <?= $it['quantity'] ?> عدد × <?= number_format($it['unit_price']) ?> تومان</div>
</div>
<div class="price"><?= number_format($it['subtotal']) ?> تومان</div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($dlFiles): ?>
<div style="margin-top:24px;background:#ecfdf5;border:2px dashed #059669;border-radius:16px;padding:16px;text-align:right">
<div style="font-weight:bold;color:#065f46;margin-bottom:10px">📥 فایل‌های دانلودی شما</div>
<?php foreach ($dlFiles as $df): ?>
<a href="download.php?code=<?= urlencode($order['order_code']) ?>&item=<?= (int)$df['item_id'] ?>" class="dl-auto" style="display:flex;justify-content:space-between;gap:8px;background:#059669;color:#fff;padding:10px 14px;border-radius:10px;margin-bottom:6px;font-size:13px"><span>⬇ <?= htmlspecialchars($df['product_name']) ?></span><span style="direction:ltr"><?= htmlspecialchars($df['download_file']) ?> · <?= dl_size($df['size']) ?></span></a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div style="margin-top:30px;">
<a href="invoice.php?code=<?= urlencode($order['order_code']) ?>" class="btn-home" style="background:#059669">📄 دانلود فاکتور (PDF)</a>
<a href="index.php" class="btn-home">🏠 بازگشت به فروشگاه</a>
<?php if (isLoggedIn()): ?>
<a href="account.php" class="btn-secondary">👤 حساب من</a>
<?php endif; ?>
</div>
</div>

</div>
<?php if (($order['payment_status'] ?? '') === 'paid'): ?>
<script>
// دانلود خودکار فاکتور PDF (فقط یک بار برای هر سفارش)
(function(){
  var k='inv_<?= htmlspecialchars($order['order_code'], ENT_QUOTES) ?>';
  try { if (sessionStorage.getItem(k)) return; sessionStorage.setItem(k,'1'); } catch(e) {}
  var f=document.createElement('iframe'); f.style.display='none'; f.src='invoice.php?code=<?= urlencode($order['order_code']) ?>';
  document.body.appendChild(f);
  // دانلود خودکار فایل‌های محصول (با فاصله، تا مرورگر مسدود نکند)
  document.querySelectorAll('a.dl-auto').forEach(function(a,i){ setTimeout(function(){ var g=document.createElement('iframe'); g.style.display='none'; g.src=a.href; document.body.appendChild(g); }, 1500*(i+1)); });
})();
</script>
<?php endif; ?>
</body>
</html>