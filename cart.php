<?php
/**
 * صفحه سبد خرید (طراحی کلاسیک)
 */
require_once 'config/database.php';
require_once 'includes/coupons.php';
coupons_ensure($pdo);

if (!function_exists('ms_fa_digits')) {
    function ms_fa_digits($value) {
        return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}
if (!function_exists('ms_money')) {
    function ms_money($n) { return ms_fa_digits(number_format((float)$n)); }
}

// ============================================================
// پردازش اکشن‌ها (حذف / تغییر تعداد / خالی کردن)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $pid = (int)($_POST['product_id'] ?? 0);

    if ($action === 'remove' && $pid > 0) {
        unset($_SESSION['cart'][$pid]);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'update' && $pid > 0) {
        $newQty = (int)($_POST['quantity'] ?? 1);
        if ($newQty <= 0) {
            unset($_SESSION['cart'][$pid]);
        } else {
            $_SESSION['cart'][$pid] = min($newQty, 99);
        }
        header('Location: cart.php');
        exit;
    }

    if ($action === 'apply_coupon') {
        $_SESSION['coupon_code'] = strtoupper(trim(en_digits($_POST['coupon_code'] ?? '')));
        header('Location: cart.php');
        exit;
    }
    if ($action === 'remove_coupon') {
        unset($_SESSION['coupon_code'], $_SESSION['coupon_msg']);
        header('Location: cart.php');
        exit;
    }

    if ($action === 'clear') {
        $_SESSION['cart'] = [];
        header('Location: cart.php');
        exit;
    }
}

// ============================================================
// واکشی محصولات سبد
// ============================================================
$cart = $_SESSION['cart'] ?? [];
$items = [];
$totalPrice = 0;
$totalDiscount = 0;
$totalFinal = 0;
$hasUnavailable = false;

if (!empty($cart)) {
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $stmt = $pdo->prepare("
        SELECT id, name, main_image, price, sale_price, stock, stock_status
        FROM products
        WHERE id IN ($placeholders) AND status = 1
    ");
    $stmt->execute($ids);
    $dbProducts = $stmt->fetchAll();

    foreach ($dbProducts as $p) {
        $qty = (int)$cart[$p['id']];
        $unitPrice = ($p['sale_price'] && $p['sale_price'] < $p['price']) ? $p['sale_price'] : $p['price'];
        $unitOriginal = $p['price'];

        $subtotal = $unitPrice * $qty;
        $subtotalOriginal = $unitOriginal * $qty;
        $savings = $subtotalOriginal - $subtotal;

        $totalPrice += $subtotalOriginal;
        $totalDiscount += $savings;
        $totalFinal += $subtotal;

        $isAvailable = ($p['stock_status'] === 'in_stock' && $p['stock'] > 0);
        if (!$isAvailable) $hasUnavailable = true;

        $items[] = [
            'id' => $p['id'],
            'name' => $p['name'],
            'image' => $p['main_image'] ?: 'assets/no-image.png',
            'unit_price' => $unitPrice,
            'unit_original' => $unitOriginal,
            'qty' => $qty,
            'subtotal' => $subtotal,
            'savings' => $savings,
            'is_available' => $isAvailable,
            'stock' => $p['stock']
        ];
    }
}

// ───── کد تخفیف ─────
$couponDiscount = 0; $couponCode = ''; $couponError = '';
if (!empty($_SESSION['coupon_code']) && $items) {
    $ev = coupon_evaluate($pdo, $_SESSION['coupon_code'], $items);
    if ($ev['ok']) { $couponDiscount = $ev['discount']; $couponCode = $ev['coupon']['code']; }
    else { $couponError = $ev['error']; unset($_SESSION['coupon_code']); }
}
$totalFinal -= $couponDiscount;

// ───── هزینه ارسال ─────
$shipMin = $shipMax = 0;
if ($items) [$shipMin, $shipMax] = shipping_range($totalFinal);
$grandTotal = $totalFinal + $shipMin;

$cartCount = array_sum($cart);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>سبد خرید | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
:root{
    --navy:#1b2a41;--navy-deep:#111c2d;--navy-soft:#2a3d5c;
    --gold:#b8923a;--gold-light:#d9b968;--gold-pale:#f4ead2;
    --burgundy:#7a1f2b;--green:#2f6b4f;--cream:#faf7f2;--paper:#fff;
    --ink:#22262e;--muted:#6b7280;--line:#e6dfd0;
    --shadow:0 2px 10px rgba(27,42,65,.07);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Vazirmatn',tahoma,sans-serif;background:var(--cream);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit;transition:color .2s,background .2s,border-color .2s}
img{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;outline:none}
.container{max-width:1200px;margin:0 auto;padding:0 24px}

/* هدر */
.top-bar{background:var(--navy-deep);color:#cfd6e2;font-size:12.5px;padding:8px 0}
.top-bar .container{display:flex;justify-content:space-between;gap:16px;flex-wrap:wrap}
.top-bar b{color:var(--gold-light);font-weight:600}
.site-header{background:var(--paper);border-bottom:1px solid var(--line);position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(27,42,65,.05)}
.header-inner{display:flex;align-items:center;justify-content:space-between;padding:14px 0;gap:28px}
.logo{display:flex;align-items:center;gap:12px;white-space:nowrap}
.logo-mark{width:44px;height:44px;border-radius:50%;background:var(--navy);color:var(--gold-light);display:grid;place-items:center;font-size:21px;border:2px solid var(--gold);box-shadow:0 0 0 3px var(--paper),0 0 0 4px var(--gold-light)}
.logo-text{display:flex;flex-direction:column;line-height:1.25}
.logo-text strong{font-size:20px;font-weight:800;color:var(--navy)}
.logo-text small{font-size:10.5px;color:var(--gold);letter-spacing:.5px;font-weight:600}
.search-box{flex:1;max-width:500px;display:flex;border:1.5px solid var(--line);border-radius:4px;overflow:hidden;background:var(--cream);transition:all .25s}
.search-box:focus-within{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(184,146,58,.15)}
.search-box input{flex:1;border:none;background:transparent;padding:11px 16px;font-family:inherit;font-size:14px;outline:none}
.search-box button{background:var(--navy);color:var(--gold-light);padding:0 22px;font-size:15px}
.search-box button:hover{background:var(--navy-soft)}
.header-actions{display:flex;gap:6px}
.header-actions a{color:var(--navy);font-size:13px;font-weight:600;display:flex;align-items:center;gap:6px;padding:9px 14px;border-radius:4px;border:1px solid transparent}
.header-actions a:hover{border-color:var(--gold);color:var(--gold)}
.cart-badge{background:var(--burgundy);color:#fff;font-size:11px;min-width:20px;padding:1px 6px;border-radius:10px;font-weight:700;text-align:center}

/* عنوان */
.breadcrumb{color:var(--muted);font-size:13px;margin:24px 0 14px}
.breadcrumb a{color:var(--gold)}
.breadcrumb a:hover{color:var(--navy)}
.breadcrumb .sep{margin:0 6px;color:#c9c1ad}
.page-title{font-size:26px;font-weight:800;color:var(--navy);margin-bottom:22px;display:flex;align-items:center;gap:12px}
.page-title .count{background:var(--gold-pale);color:#7a5c14;border:1px solid var(--gold-light);font-size:13px;font-weight:700;padding:2px 14px;border-radius:20px}

/* مراحل */
.progress-steps{display:flex;justify-content:center;align-items:center;background:var(--paper);border:1px solid var(--line);padding:18px;border-radius:6px;margin-bottom:24px;box-shadow:var(--shadow)}
.step{display:flex;align-items:center;gap:8px;color:#a7a291;font-size:13px;font-weight:700}
.step .circle{width:30px;height:30px;border-radius:50%;background:#efe9da;display:grid;place-items:center;font-size:13px}
.step.active{color:var(--navy)}
.step.active .circle{background:var(--navy);color:var(--gold-light);box-shadow:0 0 0 3px var(--paper),0 0 0 4px var(--gold)}
.step-line{width:50px;height:1px;background:var(--line);margin:0 12px}

/* چیدمان */
.cart-layout{display:grid;grid-template-columns:1fr 360px;gap:26px;align-items:start}
.cart-items{display:flex;flex-direction:column;gap:14px}
.cart-item{background:var(--paper);border:1px solid var(--line);border-radius:6px;padding:18px;display:grid;grid-template-columns:100px 1fr auto;gap:18px;align-items:center;box-shadow:var(--shadow);transition:border-color .2s}
.cart-item:hover{border-color:var(--gold-light)}
.cart-item img{width:100px;height:100px;object-fit:contain;background:#fcfbf8;border:1px solid var(--line);border-radius:4px;padding:8px}
.item-info .title{font-size:14px;line-height:1.8;font-weight:600;color:var(--navy);margin-bottom:6px;display:block}
.item-info .title:hover{color:var(--gold)}
.item-info .old-price{color:#9a9486;text-decoration:line-through;font-size:12px}
.item-info .unit-price{color:var(--navy);font-weight:800;font-size:15px}
.item-info .savings{color:var(--burgundy);font-size:12px;margin-top:3px;font-weight:600}
.out-of-stock-tag{background:#f6e7e9;color:var(--burgundy);font-size:11px;padding:2px 10px;border-radius:3px;font-weight:700;margin-top:6px;display:inline-block}
.item-actions{display:flex;flex-direction:column;align-items:flex-end;gap:10px}
.qty-control{display:flex;align-items:center;border:1px solid var(--line);border-radius:4px;overflow:hidden;background:#fff}
.qty-control button{width:34px;height:34px;background:var(--cream);font-size:18px;font-weight:700;color:var(--navy);transition:all .2s}
.qty-control button:hover{background:var(--navy);color:var(--gold-light)}
.qty-control input{width:46px;height:34px;text-align:center;border:none;border-right:1px solid var(--line);border-left:1px solid var(--line);font-size:14px;font-weight:700;font-family:inherit;background:#fff;color:var(--navy)}
.item-subtotal{font-size:16px;font-weight:800;color:var(--navy)}
.btn-remove{background:transparent;color:var(--burgundy);border:1px solid #e3c4c9;padding:6px 14px;border-radius:3px;font-size:12.5px;font-weight:700}
.btn-remove:hover{background:var(--burgundy);color:#fff;border-color:var(--burgundy)}

/* خلاصه */
.summary{background:var(--paper);border:1px solid var(--line);border-radius:6px;padding:24px;box-shadow:var(--shadow);position:sticky;top:96px}
.summary h3{font-size:16px;font-weight:800;color:var(--navy);margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid var(--gold)}
.summary-row{display:flex;justify-content:space-between;padding:9px 0;font-size:13.5px;color:#3a4150}
.summary-row.discount{color:var(--burgundy);font-weight:600}
.summary-row .free{color:var(--green);font-weight:700}
.summary-row.total{border-top:1px dashed var(--gold);margin-top:10px;padding-top:16px;font-size:16px;font-weight:800;color:var(--navy)}
.summary-row.total .amount{font-size:19px}
.btn-checkout{display:block;width:100%;background:var(--navy);color:var(--gold-light);padding:14px;border-radius:3px;font-size:15px;font-weight:800;margin-top:18px;text-align:center;border:1px solid var(--navy);transition:all .25s}
.btn-checkout:hover{background:var(--gold);border-color:var(--gold);color:#fff}
.btn-checkout.disabled{background:#d9d5c8;border-color:#d9d5c8;color:#8d8878;cursor:not-allowed}
.warning-box{background:var(--gold-pale);color:#7a5c14;border:1px solid var(--gold-light);padding:12px;border-radius:4px;font-size:12.5px;margin-top:12px;line-height:1.9}
.btn-clear{display:block;width:100%;background:transparent;color:var(--burgundy);border:1px solid #e3c4c9;padding:10px;border-radius:3px;font-size:13px;font-weight:700;margin-top:10px}
.btn-clear:hover{background:#f6e7e9}
.secure-note{text-align:center;font-size:12px;color:var(--muted);margin-top:14px}

/* سبد خالی */
.empty-cart{background:var(--paper);border:1px solid var(--line);padding:80px 20px;border-radius:6px;text-align:center;box-shadow:var(--shadow)}
.empty-cart .icon{width:100px;height:100px;border-radius:50%;margin:0 auto 22px;display:grid;place-items:center;font-size:44px;background:var(--gold-pale);border:1px solid var(--gold-light)}
.empty-cart h2{color:var(--navy);margin-bottom:8px}
.empty-cart p{color:var(--muted);margin-bottom:24px}
.empty-cart a{display:inline-block;background:var(--navy);color:var(--gold-light);padding:12px 34px;border-radius:3px;font-weight:700}
.empty-cart a:hover{background:var(--gold);color:#fff}

/* فوتر */
.site-footer{background:var(--navy-deep);color:#97a2b6;margin-top:70px;border-top:4px solid var(--gold);text-align:center;padding:30px 0;font-size:13px}
.site-footer .links{display:flex;justify-content:center;gap:24px;margin-bottom:12px;flex-wrap:wrap}
.site-footer .links a:hover{color:var(--gold-light)}

@media(max-width:850px){
    .cart-layout{grid-template-columns:1fr}
    .summary{position:static}
}
@media(max-width:768px){
    .container{padding:0 16px}
    .top-bar .container{justify-content:center}
    .header-inner{flex-wrap:wrap;gap:12px}
    .search-box{order:3;max-width:100%;width:100%}
    .page-title{font-size:21px}
    .cart-item{grid-template-columns:80px 1fr;gap:12px;padding:14px}
    .cart-item img{width:80px;height:80px}
    .item-actions{grid-column:1/-1;flex-direction:row;justify-content:space-between;align-items:center;width:100%;padding-top:12px;border-top:1px dashed var(--line)}
    .progress-steps{padding:12px}
    .step{font-size:11px}
    .step .circle{width:24px;height:24px;font-size:11px}
    .step-line{width:18px;margin:0 6px}
}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

    <div class="breadcrumb">
        <a href="index.php">خانه</a><span class="sep">‹</span><span>سبد خرید</span>
    </div>

    <h1 class="page-title">
        سبد خرید
        <?php if ($cartCount > 0): ?>
            <span class="count"><?= ms_fa_digits($cartCount) ?> کالا</span>
        <?php endif; ?>
    </h1>

    <?php if (!empty($items)): ?>
        <div class="progress-steps">
            <div class="step active"><span class="circle">۱</span> سبد خرید</div>
            <div class="step-line"></div>
            <div class="step"><span class="circle">۲</span> اطلاعات ارسال</div>
            <div class="step-line"></div>
            <div class="step"><span class="circle">۳</span> پرداخت</div>
        </div>
    <?php endif; ?>

    <?php if (empty($items)): ?>

        <div class="empty-cart">
            <div class="icon">🛒</div>
            <h2>سبد خرید شما خالی است</h2>
            <p>هنوز محصولی به سبد اضافه نکرده‌اید.</p>
            <a href="archive.php">شروع خرید</a>
        </div>

    <?php else: ?>

        <div class="cart-layout">

            <div class="cart-items">
                <?php foreach ($items as $item): ?>
                <div class="cart-item">
                    <a href="product.php?id=<?= (int)$item['id'] ?>">
                        <img src="<?= htmlspecialchars($item['image']) ?>" alt=""
                             onerror="this.onerror=null;this.src='assets/no-image.png'">
                    </a>

                    <div class="item-info">
                        <a href="product.php?id=<?= (int)$item['id'] ?>" class="title">
                            <?= htmlspecialchars(mb_substr($item['name'], 0, 90)) ?>
                        </a>

                        <?php if ($item['unit_original'] > $item['unit_price']): ?>
                            <div class="old-price"><?= ms_money($item['unit_original']) ?> تومان</div>
                        <?php endif; ?>

                        <div class="unit-price"><?= ms_money($item['unit_price']) ?> تومان</div>

                        <?php if ($item['savings'] > 0): ?>
                            <div class="savings">سود شما: <?= ms_money($item['savings']) ?> تومان</div>
                        <?php endif; ?>

                        <?php if (!$item['is_available']): ?>
                            <span class="out-of-stock-tag">ناموجود شد</span>
                        <?php endif; ?>
                    </div>

                    <div class="item-actions">
                        <form method="POST" style="margin:0;">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                            <div class="qty-control">
                                <button type="submit" name="quantity" value="<?= $item['qty'] - 1 ?>" aria-label="کاهش">−</button>
                                <input type="text" value="<?= ms_fa_digits($item['qty']) ?>" readonly>
                                <button type="submit" name="quantity" value="<?= $item['qty'] + 1 ?>" aria-label="افزایش">+</button>
                            </div>
                        </form>

                        <div class="item-subtotal"><?= ms_money($item['subtotal']) ?> تومان</div>

                        <form method="POST" style="margin:0;" onsubmit="return confirm('حذف این محصول از سبد؟')">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>">
                            <button type="submit" class="btn-remove">حذف</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <aside class="summary">
                <h3>خلاصه سفارش</h3>

                <form method="POST" style="margin:0 0 14px;display:flex;gap:6px;flex-wrap:wrap">
                    <?php if ($couponCode): ?>
                        <input type="hidden" name="action" value="remove_coupon">
                        <div style="flex:1;font-size:12px;color:#16a34a;font-weight:700;padding:9px 0">✅ کد «<?= htmlspecialchars($couponCode) ?>» اعمال شد</div>
                        <button type="submit" style="border:1px solid #d9d5c8;background:#fff;border-radius:3px;padding:8px 12px;cursor:pointer;font-family:inherit;font-size:12px">حذف کد</button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="apply_coupon">
                        <input type="text" name="coupon_code" placeholder="کد تخفیف دارید؟" dir="ltr" style="flex:1;min-width:0;border:1px solid #d9d5c8;border-radius:3px;padding:9px;font-family:inherit;font-size:13px">
                        <button type="submit" style="border:0;background:#1b2a41;color:#fff;border-radius:3px;padding:8px 14px;cursor:pointer;font-family:inherit;font-size:12px;font-weight:700">اعمال</button>
                    <?php endif; ?>
                    <?php if ($couponError): ?><div style="width:100%;font-size:12px;color:#dc2626"><?= htmlspecialchars($couponError) ?></div><?php endif; ?>
                </form>

                <div class="summary-row">
                    <span>قیمت کالاها (<?= ms_fa_digits($cartCount) ?>)</span>
                    <span><?= ms_money($totalPrice) ?> تومان</span>
                </div>

                <?php if ($totalDiscount > 0): ?>
                <div class="summary-row discount">
                    <span>تخفیف</span>
                    <span><?= ms_money($totalDiscount) ?> تومان</span>
                </div>
                <?php endif; ?>

                <?php if ($couponDiscount > 0): ?>
                <div class="summary-row discount">
                    <span>🎟 کد تخفیف <?= htmlspecialchars($couponCode) ?></span>
                    <span>− <?= ms_money($couponDiscount) ?> تومان</span>
                </div>
                <?php endif; ?>

                <div class="summary-row">
                    <span>هزینه ارسال<?= $shipMax > $shipMin ? ' (بسته به استان)' : '' ?></span>
                    <?php if ($shipMin == 0 && $shipMax == 0): ?><span class="free">رایگان</span>
                    <?php else: ?><span><?= $shipMax > $shipMin ? 'از ' : '' ?><?= ms_money($shipMin) ?> تومان</span><?php endif; ?>
                </div>

                <div class="summary-row total">
                    <span>مبلغ قابل پرداخت</span>
                    <span class="amount"><?= ms_money($grandTotal) ?> تومان</span>
                </div>

                <?php if ($hasUnavailable): ?>
                    <div class="warning-box">
                        سبد شما شامل محصولات ناموجود است. لطفاً قبل از ادامه، آن‌ها را حذف کنید.
                    </div>
                    <button class="btn-checkout disabled" disabled>ادامه فرآیند خرید</button>
                <?php else: ?>
                    <a href="checkout.php" class="btn-checkout">ادامه فرآیند خرید</a>
                <?php endif; ?>

                <form method="POST" onsubmit="return confirm('سبد خرید خالی شود؟')" style="margin:0;">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn-clear">خالی کردن سبد</button>
                </form>

                <div class="secure-note">🔒 پرداخت امن و تضمین اصالت کالا</div>
            </aside>

        </div>

    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/site_footer.php'; ?>

</body>
</html>
