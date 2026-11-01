<?php
/**
 * صفحه تسویه حساب
 */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/coupons.php';
coupons_ensure($pdo);

// ============================================================
// ۱. بررسی سبد خالی
// ============================================================
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: cart.php');
    exit;
}

// ============================================================
// ۲. واکشی محصولات سبد
// ============================================================
$ids = array_keys($cart);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $pdo->prepare("
    SELECT id, name, main_image, price, sale_price, stock, stock_status 
    FROM products 
    WHERE id IN ($placeholders) AND status = 1
");
$stmt->execute($ids);
$dbProducts = $stmt->fetchAll();

$items = [];
$subtotal = 0;
$totalOriginal = 0;
$discount = 0;
$hasUnavailable = false;

foreach ($dbProducts as $p) {
    $qty = (int)$cart[$p['id']];
    $unitPrice = ($p['sale_price'] && $p['sale_price'] < $p['price']) 
        ? $p['sale_price'] 
        : $p['price'];
    $lineTotal = $unitPrice * $qty;
    $lineOriginal = $p['price'] * $qty;

    $isAvailable = ($p['stock_status'] === 'in_stock' && $p['stock'] > 0);
    if (!$isAvailable) $hasUnavailable = true;

    $items[] = [
        'id' => $p['id'],
        'name' => $p['name'],
        'image' => $p['main_image'] ?: 'assets/no-image.png',
        'unit_price' => $unitPrice,
        'qty' => $qty,
        'subtotal' => $lineTotal,
        'is_available' => $isAvailable,
    ];

    $subtotal += $lineTotal;
    $totalOriginal += $lineOriginal;
}

$discount = $totalOriginal - $subtotal;
$shipping = 0;

// ───── کد تخفیف ─────
$couponDiscount = 0; $couponCode = null; $couponId = 0;
if (!empty($_SESSION['coupon_code'])) {
    $ev = coupon_evaluate($pdo, $_SESSION['coupon_code'], $items);
    if ($ev['ok']) { $couponDiscount = $ev['discount']; $couponCode = $ev['coupon']['code']; $couponId = (int)$ev['coupon']['id']; }
    else unset($_SESSION['coupon_code']);
}
$payableGoods = $subtotal - $couponDiscount;
[$shipMin, $shipMax] = shipping_range($payableGoods);
$shipping = $shipMin;
$total = $payableGoods + $shipping;
$shipMap = [];
foreach (shipping_provinces() as $__p) $shipMap[$__p] = shipping_quote($__p, $payableGoods);

// ============================================================
// ۳. پردازش فرم
// ============================================================
$errors = [];
$old = $_SESSION['checkout_old'] ?? [];
unset($_SESSION['checkout_old']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $province = trim($_POST['province'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    $shipping = shipping_quote($province, $payableGoods);
    $total = $payableGoods + $shipping;

    if (mb_strlen($name) < 3) $errors['name'] = 'نام باید حداقل ۳ حرف باشد';
    
    $cleanPhone = str_replace([' ', '-', '(', ')'], '', $phone);
    if (!preg_match('/^09[0-9]{9}$/', $cleanPhone)) {
        $errors['phone'] = 'شماره موبایل باید به شکل 09xxxxxxxxx باشد';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'ایمیل معتبر نیست';
    }
    
    if (empty($province)) $errors['province'] = 'استان را انتخاب کنید';
    if (empty($city)) $errors['city'] = 'شهر را وارد کنید';
    if (mb_strlen($address) < 10) $errors['address'] = 'آدرس کامل را وارد کنید';
    if ($hasUnavailable) $errors['cart'] = 'سبد شما شامل محصول ناموجود است';

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // کد سفارش یکتا
            do {
                $orderCode = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
                $check = $pdo->prepare("SELECT id FROM orders WHERE order_code = ?");
                $check->execute([$orderCode]);
            } while ($check->fetch());

            // درج سفارش — با ساختار جدید
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    order_code, user_id, customer_name, phone, email,
                    province, city, address, postal_code, notes,
                    subtotal, discount, shipping, total,
                    payment_method, payment_status, status, coupon_code, coupon_discount
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $orderCode,
                $_SESSION['user_id'] ?? null,
                $name,
                $cleanPhone,
                $email,
                $province, $city, $address, $postalCode, $notes,
                $subtotal, $discount + $couponDiscount, $shipping, $total,
                'online', 'pending', 'pending', $couponCode, $couponDiscount
            ]);
            $orderId = $pdo->lastInsertId();

            // درج آیتم‌ها
            $itemStmt = $pdo->prepare("
                INSERT INTO order_items 
                    (order_id, product_id, product_name, product_image, unit_price, quantity, subtotal) 
                VALUES (?,?,?,?,?,?,?)
            ");
            foreach ($items as $it) {
                $itemStmt->execute([
                    $orderId, $it['id'], $it['name'], $it['image'],
                    $it['unit_price'], $it['qty'], $it['subtotal']
                ]);
            }

            if ($couponId) {
                $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
                unset($_SESSION['coupon_code']);
            }

            $pdo->commit();

            // ذخیره برای صفحه پرداخت
            $_SESSION['pending_order_id'] = $orderId;
            $_SESSION['pending_order_code'] = $orderCode;
            $_SESSION['pending_order_total'] = $total;

            header('Location: payment.php');
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'خطا در ثبت سفارش: ' . $e->getMessage();
        }
    }

    $_SESSION['checkout_old'] = $_POST;
    $old = $_POST;
}

$cartCount = array_sum($cart);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>تسویه حساب | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box}
body{font-family:tahoma,sans-serif;background:#f8fafc;margin:0;padding:0;color:#111}
a{text-decoration:none}
.container{max-width:1200px;margin:0 auto;padding:20px}

.site-header{background:#fff;box-shadow:0 2px 12px rgba(0,0,0,.06);position:sticky;top:0;z-index:100}
.header-inner{display:flex;align-items:center;justify-content:space-between;padding:15px 20px;gap:20px;max-width:1200px;margin:0 auto}
.logo{font-size:20px;font-weight:bold;color:#2563eb;display:flex;align-items:center;gap:8px}
.header-actions{display:flex;align-items:center;gap:12px}
.header-actions a{color:#374151;font-size:13px;font-weight:bold;padding:8px 12px;border-radius:8px}
.header-actions a:hover{background:#eff6ff;color:#2563eb}
.cart-badge{background:#ef4444;color:#fff;font-size:11px;padding:2px 7px;border-radius:10px;font-weight:bold}

.breadcrumb{color:#6b7280;font-size:13px;margin:20px 0}
.breadcrumb a{color:#2563eb}

h1.page-title{font-size:22px;margin:0 0 20px 0}

.progress-steps{display:flex;justify-content:center;margin-bottom:25px;background:#fff;padding:18px;border-radius:16px;box-shadow:0 2px 12px rgba(0,0,0,.06);flex-wrap:wrap;gap:8px}
.step{display:flex;align-items:center;gap:8px;color:#9ca3af;font-size:13px;font-weight:bold}
.step .circle{width:28px;height:28px;border-radius:50%;background:#e5e7eb;display:flex;align-items:center;justify-content:center;font-size:13px}
.step.active .circle{background:#2563eb;color:#fff}
.step.active{color:#2563eb}
.step.done .circle{background:#16a34a;color:#fff}
.step.done{color:#16a34a}
.step-line{width:35px;height:2px;background:#e5e7eb;margin:0 6px}

.layout{display:grid;grid-template-columns:1fr 360px;gap:25px;align-items:start}

.form-card{background:#fff;border-radius:16px;padding:25px;box-shadow:0 2px 12px rgba(0,0,0,.06)}
.form-card h2{font-size:17px;margin:0 0 20px 0;border-bottom:2px solid #eff6ff;padding-bottom:12px;color:#111}

.form-row{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.form-group{margin-bottom:18px}
.form-group label{display:block;margin-bottom:6px;font-size:13px;font-weight:bold;color:#374151}
.form-group label .req{color:#ef4444}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px;font-family:tahoma;font-size:14px;transition:border .2s;background:#fff}
.form-group input:focus,.form-group select:focus,.form-group textarea:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.1)}
.form-group textarea{min-height:90px;resize:vertical}
.form-group .error{color:#dc2626;font-size:12px;margin-top:4px;display:block}
.form-group.has-error input,.form-group.has-error select,.form-group.has-error textarea{border-color:#ef4444}

.alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;padding:14px;border-radius:10px;margin-bottom:20px;font-size:14px;line-height:1.7}

.summary{background:#fff;border-radius:16px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.06);position:sticky;top:90px}
.summary h3{margin:0 0 18px 0;font-size:16px;border-bottom:2px solid #eff6ff;padding-bottom:12px}
.summary-items{max-height:280px;overflow-y:auto;margin-bottom:15px}
.summary-item{display:grid;grid-template-columns:50px 1fr auto;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #f3f4f6;font-size:12px}
.summary-item:last-child{border-bottom:none}
.summary-item img{width:50px;height:50px;object-fit:contain;background:#f9fafb;border-radius:8px;padding:4px}
.summary-item .name{color:#374151;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.summary-item .price{font-weight:bold;color:#111;white-space:nowrap;font-size:13px}
.summary-item .qty{color:#6b7280;font-size:11px}
.summary-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13px;color:#374151}
.summary-row.discount{color:#16a34a}
.summary-row.total{border-top:2px dashed #e5e7eb;margin-top:10px;padding-top:15px;font-size:16px;font-weight:bold}
.summary-row.total .amount{color:#16a34a;font-size:18px}
.btn-submit{display:block;width:100%;background:#16a34a;color:#fff;border:none;padding:15px;border-radius:12px;font-size:16px;font-weight:bold;cursor:pointer;margin-top:18px;font-family:tahoma}
.btn-submit:hover{background:#15803d}
.btn-submit:disabled{background:#9ca3af;cursor:not-allowed}
.back-link{display:block;text-align:center;margin-top:12px;font-size:13px;color:#6b7280}
.warning-box{background:#fef3c7;color:#92400e;padding:12px;border-radius:10px;font-size:12px;margin-top:12px;line-height:1.7}

@media(max-width:800px){
.header-inner{flex-wrap:wrap}
.layout{grid-template-columns:1fr}
.form-row{grid-template-columns:1fr}
.summary{position:static}
}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

<div class="breadcrumb">
<a href="index.php">🏠 خانه</a> › 
<a href="cart.php">سبد خرید</a> › 
<span>تسویه حساب</span>
</div>

<h1 class="page-title">📝 تسویه حساب</h1>

<div class="progress-steps">
<div class="step done"><span class="circle">✓</span> سبد خرید</div>
<div class="step-line"></div>
<div class="step active"><span class="circle">2</span> اطلاعات ارسال</div>
<div class="step-line"></div>
<div class="step"><span class="circle">3</span> پرداخت</div>
</div>

<?php if (!empty($errors['general'])): ?>
<div class="alert-error">❌ <?= htmlspecialchars($errors['general']) ?></div>
<?php endif; ?>
<?php if (!empty($errors['cart'])): ?>
<div class="alert-error">⚠️ <?= htmlspecialchars($errors['cart']) ?></div>
<?php endif; ?>

<div class="layout">

<form method="POST" class="form-card" id="checkoutForm">
<h2>📍 اطلاعات گیرنده</h2>

<div class="form-row">
<div class="form-group <?= isset($errors['name']) ? 'has-error' : '' ?>">
<label>نام و نام خانوادگی <span class="req">*</span></label>
<input type="text" name="name" required value="<?= htmlspecialchars($old['name'] ?? $_SESSION['name'] ?? '') ?>">
<?php if (isset($errors['name'])): ?><span class="error"><?= $errors['name'] ?></span><?php endif; ?>
</div>

<div class="form-group <?= isset($errors['phone']) ? 'has-error' : '' ?>">
<label>شماره موبایل <span class="req">*</span></label>
<input type="tel" name="phone" required placeholder="09xxxxxxxxx" value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
<?php if (isset($errors['phone'])): ?><span class="error"><?= $errors['phone'] ?></span><?php endif; ?>
</div>
</div>

<div class="form-group <?= isset($errors['email']) ? 'has-error' : '' ?>">
<label>ایمیل (اختیاری)</label>
<input type="email" name="email" placeholder="example@email.com" value="<?= htmlspecialchars($old['email'] ?? $_SESSION['email'] ?? '') ?>">
<?php if (isset($errors['email'])): ?><span class="error"><?= $errors['email'] ?></span><?php endif; ?>
</div>

<div class="form-row">
<div class="form-group <?= isset($errors['province']) ? 'has-error' : '' ?>">
<label>استان <span class="req">*</span></label>
<select name="province" required>
<option value="">انتخاب کنید...</option>
<?php 
$provinces = ['تهران','البرز','اصفهان','فارس','خراسان رضوی','آذربایجان شرقی','آذربایجان غربی','گیلان','مازندران','خوزستان','کرمان','یزد','قم','مرکزی','همدان','کرمانشاه','گلستان','سیستان و بلوچستان','هرمزگان','بوشهر','اردبیل','زنجان','قزوین','لرستان','چهارمحال و بختیاری','کهگیلویه و بویراحمد','سمنان','خراسان شمالی','خراسان جنوبی','ایلام','کردستان'];
$selectedProv = $old['province'] ?? '';
foreach ($provinces as $prov):
?>
<option value="<?= $prov ?>" <?= $selectedProv === $prov ? 'selected' : '' ?>><?= $prov ?></option>
<?php endforeach; ?>
</select>
<?php if (isset($errors['province'])): ?><span class="error"><?= $errors['province'] ?></span><?php endif; ?>
</div>

<div class="form-group <?= isset($errors['city']) ? 'has-error' : '' ?>">
<label>شهر <span class="req">*</span></label>
<input type="text" name="city" required value="<?= htmlspecialchars($old['city'] ?? '') ?>">
<?php if (isset($errors['city'])): ?><span class="error"><?= $errors['city'] ?></span><?php endif; ?>
</div>
</div>

<div class="form-group <?= isset($errors['address']) ? 'has-error' : '' ?>">
<label>آدرس کامل پستی <span class="req">*</span></label>
<textarea name="address" required placeholder="خیابان، کوچه، پلاک، واحد..."><?= htmlspecialchars($old['address'] ?? '') ?></textarea>
<?php if (isset($errors['address'])): ?><span class="error"><?= $errors['address'] ?></span><?php endif; ?>
</div>

<div class="form-group">
<label>کد پستی (اختیاری)</label>
<input type="text" name="postal_code" maxlength="10" placeholder="1234567890" value="<?= htmlspecialchars($old['postal_code'] ?? '') ?>">
</div>

<div class="form-group">
<label>توضیحات سفارش (اختیاری)</label>
<textarea name="notes" placeholder="مثلاً: تحویل بعد از ساعت ۵ عصر"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
</div>
</form>

<aside class="summary">
<h3>📋 خلاصه سفارش</h3>

<div class="summary-items">
<?php foreach ($items as $it): ?>
<div class="summary-item">
<img src="<?= htmlspecialchars($it['image']) ?>" alt="" onerror="this.src='assets/no-image.png'">
<div>
<div class="name"><?= htmlspecialchars(mb_substr($it['name'], 0, 50)) ?></div>
<div class="qty">تعداد: <?= $it['qty'] ?></div>
</div>
<div class="price"><?= number_format($it['subtotal']) ?></div>
</div>
<?php endforeach; ?>
</div>

<div class="summary-row">
<span>جمع کالاها (<?= $cartCount ?>)</span>
<span><?= number_format($totalOriginal) ?> تومان</span>
</div>

<?php if ($discount > 0): ?>
<div class="summary-row discount">
<span>🎉 تخفیف</span>
<span><?= number_format($discount) ?> تومان</span>
</div>
<?php endif; ?>

<?php if ($couponDiscount > 0): ?>
<div class="summary-row discount">
<span>🎟 کد تخفیف <?= htmlspecialchars($couponCode) ?></span>
<span>− <?= number_format($couponDiscount) ?> تومان</span>
</div>
<?php endif; ?>

<div class="summary-row">
<span>هزینه ارسال</span>
<span id="shipVal"><?= $shipping > 0 ? number_format($shipping) . ' تومان' : '<span style="color:#16a34a">رایگان</span>' ?></span>
</div>

<div class="summary-row total">
<span>مبلغ قابل پرداخت</span>
<span class="amount" id="totalVal"><?= number_format($total) ?> تومان</span>
</div>

<?php if ($hasUnavailable): ?>
<div class="warning-box">
⚠️ سبد شما شامل محصول ناموجود است. ابتدا آن را از <a href="cart.php" style="color:#92400e;text-decoration:underline;">سبد خرید</a> حذف کنید.
</div>
<button type="button" class="btn-submit" disabled>💳 ادامه به پرداخت</button>
<?php else: ?>
<button type="submit" form="checkoutForm" class="btn-submit">💳 ادامه به پرداخت</button>
<?php endif; ?>

<a href="cart.php" class="back-link">← بازگشت به سبد خرید</a>
</aside>

</div>
</div>

<script>
(function () {
    var ship = <?= json_encode($shipMap, JSON_UNESCAPED_UNICODE) ?>, goods = <?= (int)$payableGoods ?>;
    var sel = document.querySelector('select[name="province"]');
    if (!sel) return;
    var fmt = function (n) { return n.toLocaleString('en-US'); };
    function upd() {
        var c = ship[sel.value];
        if (c === undefined) return;
        document.getElementById('shipVal').innerHTML = c > 0 ? fmt(c) + ' تومان' : '<span style="color:#16a34a">رایگان</span>';
        document.getElementById('totalVal').textContent = fmt(goods + c) + ' تومان';
    }
    sel.addEventListener('change', upd); upd();
})();
</script>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>