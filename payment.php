<?php
/**
 * صفحه پرداخت (شبیه‌سازی درگاه)
 */
require_once 'config/database.php';
require_once 'includes/functions.php';

// ============================================================
// ۱. بررسی سفارش در انتظار
// ============================================================
$orderId = (int)($_SESSION['pending_order_id'] ?? 0);
if ($orderId <= 0) {
    header('Location: cart.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: cart.php');
    exit;
}

// اگر قبلاً پرداخت شده
if ($order['payment_status'] === 'paid') {
    $_SESSION['last_order_code'] = $order['order_code'];
    unset($_SESSION['pending_order_id']);
    unset($_SESSION['pending_order_code']);
    unset($_SESSION['pending_order_total']);
    header('Location: order_success.php');
    exit;
}

// ============================================================
// ۲. پردازش پرداخت
// ============================================================
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'pay') {
        try {
            $pdo->beginTransaction();

            // ✅ آپدیت وضعیت سفارش — با ساختار جدید
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'paid', status = 'processing' 
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);

            // کم کردن موجودی محصولات
            $itemStmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
            $itemStmt->execute([$orderId]);
            $orderItems = $itemStmt->fetchAll();

            $updateStock = $pdo->prepare("
                UPDATE products 
                SET stock = GREATEST(stock - ?, 0) 
                WHERE id = ?
            ");
            foreach ($orderItems as $oi) {
                if ($oi['product_id']) {
                    $updateStock->execute([$oi['quantity'], $oi['product_id']]);
                }
            }

            $pdo->commit();

            // پاک کردن سبد
            $_SESSION['cart'] = [];

            // ذخیره کد سفارش برای صفحه موفقیت
            $_SESSION['last_order_code'] = $order['order_code'];

            unset($_SESSION['pending_order_id']);
            unset($_SESSION['pending_order_code']);
            unset($_SESSION['pending_order_total']);

            // گزارش فروش به کانال تلگرام (اگر در پنل مدیریت فعال شده باشد)
            require_once 'includes/telegram.php';
            telegram_defer($pdo, $orderId);

            header('Location: order_success.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $error = 'خطا در پردازش پرداخت: ' . $e->getMessage();
        }
    }

    if ($action === 'cancel') {
        try {
            $stmt = $pdo->prepare("
                UPDATE orders 
                SET payment_status = 'cancelled', status = 'cancelled' 
                WHERE id = ?
            ");
            $stmt->execute([$orderId]);
        } catch (Exception $e) {
            // نادیده بگیر
        }

        unset($_SESSION['pending_order_id']);
        unset($_SESSION['pending_order_code']);
        unset($_SESSION['pending_order_total']);

        header('Location: cart.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>پرداخت | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box}
body{
    font-family:tahoma,sans-serif;
    background:linear-gradient(135deg,#1e40af,#7c3aed);
    margin:0;padding:20px;min-height:100vh;
    display:flex;align-items:center;justify-content:center;
}
.container{width:100%;max-width:500px}

.progress-steps{
    display:flex;justify-content:center;
    margin-bottom:25px;background:rgba(255,255,255,.95);
    padding:18px;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,.2);
    flex-wrap:wrap;gap:6px;
}
.step{display:flex;align-items:center;gap:8px;color:#9ca3af;font-size:13px;font-weight:bold}
.step .circle{width:28px;height:28px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:12px}
.step.active .circle{background:#2563eb;color:#fff}
.step.active{color:#2563eb}
.step.done .circle{background:#16a34a;color:#fff}
.step.done{color:#16a34a}
.step-line{width:30px;height:2px;background:#e5e7eb;margin:0 6px}

.payment-card{
    background:#fff;border-radius:20px;padding:35px 30px;
    box-shadow:0 20px 60px rgba(0,0,0,.3);text-align:center;
}
.payment-icon{
    width:80px;height:80px;border-radius:50%;
    background:linear-gradient(135deg,#2563eb,#7c3aed);
    display:flex;align-items:center;justify-content:center;
    font-size:40px;margin:0 auto 20px;
}
h1{font-size:20px;color:#111;margin:0 0 10px 0}
.order-code{color:#6b7280;font-size:13px;margin-bottom:25px}
.order-code strong{color:#2563eb;direction:ltr;display:inline-block}

.amount-box{
    background:#f0fdf4;border:2px solid #86efac;
    border-radius:14px;padding:20px;margin:20px 0;
}
.amount-box .label{color:#6b7280;font-size:13px;margin-bottom:5px}
.amount-box .value{color:#16a34a;font-size:26px;font-weight:bold}
.amount-box .value small{font-size:14px}

.info-text{
    background:#fef3c7;color:#92400e;
    padding:12px;border-radius:10px;
    font-size:12px;margin:20px 0;
    text-align:right;line-height:1.8;
}

.btn-pay{
    width:100%;background:linear-gradient(135deg,#16a34a,#15803d);
    color:#fff;border:none;padding:16px;border-radius:12px;
    font-size:17px;font-weight:bold;cursor:pointer;
    font-family:tahoma;margin-bottom:10px;
    box-shadow:0 4px 15px rgba(22,163,74,.4);
    transition:all .2s;
}
.btn-pay:hover{transform:translateY(-2px);box-shadow:0 8px 25px rgba(22,163,74,.5)}

.btn-cancel{
    width:100%;background:transparent;color:#6b7280;
    border:1px solid #e5e7eb;padding:12px;border-radius:12px;
    font-size:14px;font-weight:bold;cursor:pointer;font-family:tahoma;
}
.btn-cancel:hover{background:#f9fafb;color:#dc2626}

.alert-error{
    background:#fef2f2;color:#dc2626;
    padding:12px;border-radius:10px;
    margin-bottom:15px;font-size:13px;
    line-height:1.7;
}
</style>
</head>
<body>
<div class="container">

<div class="progress-steps">
<div class="step done"><span class="circle">✓</span> سبد</div>
<div class="step-line"></div>
<div class="step done"><span class="circle">✓</span> اطلاعات</div>
<div class="step-line"></div>
<div class="step active"><span class="circle">3</span> پرداخت</div>
</div>

<div class="payment-card">
<div class="payment-icon">💳</div>

<h1>پرداخت آنلاین</h1>
<div class="order-code">
کد سفارش: <strong><?= htmlspecialchars($order['order_code']) ?></strong>
</div>

<div class="amount-box">
<div class="label">مبلغ قابل پرداخت</div>
<div class="value">
<?= number_format($order['total']) ?>
<small>تومان</small>
</div>
</div>

<div class="info-text">
ℹ️ این یک <strong>درگاه شبیه‌سازی</strong> است.<br>
با کلیک روی «پرداخت موفق»، سفارش شما ثبت و آماده ارسال می‌شود.
</div>

<?php if ($error): ?>
<div class="alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" style="margin:0;">
<input type="hidden" name="action" value="pay">
<button type="submit" class="btn-pay">✅ پرداخت موفق</button>
</form>

<form method="POST" style="margin:10px 0 0 0;"
      onsubmit="return confirm('از لغو پرداخت مطمئن هستید؟')">
<input type="hidden" name="action" value="cancel">
<button type="submit" class="btn-cancel">❌ لغو پرداخت</button>
</form>
</div>

</div>
</body>
</html>