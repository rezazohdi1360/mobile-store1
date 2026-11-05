<?php
/**
 * صفحه نمایش محصول (طراحی کلاسیک)
 * URL: product.php?id=XXX
 */
require_once 'config/database.php';

if (!function_exists('ms_fa_digits')) {
    function ms_fa_digits($value) {
        return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}
if (!function_exists('ms_money')) {
    function ms_money($n) { return ms_fa_digits(number_format((float)$n)); }
}

// ============================================================
// ۱. گرفتن شناسه محصول از URL
// ============================================================
$productId = (int)($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: index.php');
    exit;
}

// ============================================================
// ۲. واکشی محصول از دیتابیس
// ============================================================
$stmt = $pdo->prepare("
    SELECT p.*, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.id = ? AND p.status = 1
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    die('<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>محصول یافت نشد</title></head>
    <body style="font-family:Vazirmatn,tahoma,sans-serif;direction:rtl;background:#faf7f2;color:#1b2a41;padding:80px 20px;text-align:center;">
        <h1 style="margin-bottom:14px;">محصول یافت نشد</h1>
        <a href="index.php" style="display:inline-block;background:#1b2a41;color:#d9b968;padding:11px 30px;border-radius:3px;text-decoration:none;font-weight:bold;">بازگشت به فروشگاه</a>
    </body></html>');
}

// ============================================================
// ۳. مشخصات فنی
// ============================================================
$specStmt = $pdo->prepare("
    SELECT group_name, spec_key, spec_value
    FROM product_specs
    WHERE product_id = ?
    ORDER BY sort_order ASC
");
$specStmt->execute([$productId]);
$specs = $specStmt->fetchAll();

$specsByGroup = [];
foreach ($specs as $s) {
    $specsByGroup[$s['group_name']][] = $s;
}

// ============================================================
// ۴. وضعیت موجودی
// ============================================================
$isAvailable = (
    $product['stock_status'] === 'in_stock'
    && $product['stock'] > 0
    && $product['price'] > 0
);
$hasSale = ($product['sale_price'] && $product['sale_price'] > 0 && $product['sale_price'] < $product['price']);

// ============================================================
// ۵. تصاویر
// ============================================================
$images = [];
if (!empty($product['all_images'])) {
    $images = array_values(array_filter(array_map('trim', explode(',', $product['all_images']))));
}
if (empty($images) && !empty($product['main_image'])) {
    $images = [$product['main_image']];
}
if (empty($images)) {
    $images = ['icon/placeholder/no-image.png'];
}
$mainImage = $product['main_image'] ?: $images[0];
if (!in_array($mainImage, $images, true)) {
    array_unshift($images, $mainImage);
}

// ============================================================
// ۶. محصولات مرتبط (هم‌دسته)
// ============================================================
$related = [];
if (!empty($product['category_id'])) {
    $relStmt = $pdo->prepare("
        SELECT * FROM products
        WHERE category_id = ? AND id <> ? AND status = 1
        ORDER BY CASE WHEN stock_status = 'in_stock' THEN 0 ELSE 1 END, id DESC
        LIMIT 4
    ");
    $relStmt->execute([$product['category_id'], $productId]);
    $related = $relStmt->fetchAll();
}

// ============================================================
// ۷. نظرات کاربران (ثبت → «در انتظار تأیید مدیر»)
// ============================================================
require_once __DIR__ . '/includes/reviews.php';
require_once __DIR__ . '/includes/coupons.php';   // jalali_str
reviews_ensure($pdo);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    $err = review_submit($pdo, $productId, $_POST);
    $_SESSION['review_flash'] = $err ? ['err', $err] : ['ok', 'نظر شما ثبت شد و پس از بررسی و تأیید مدیر نمایش داده می‌شود. سپاس از شما 🙏'];
    header('Location: product.php?id=' . $productId . '#reviews');
    exit;
}
$reviewFlash = $_SESSION['review_flash'] ?? null; unset($_SESSION['review_flash']);
$rvStmt = $pdo->prepare("SELECT author_name, rating, body, created_at FROM product_reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 50");
$rvStmt->execute([$productId]);
$reviews = $rvStmt->fetchAll();
$rvAvg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($product['name']) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
:root{
    --navy:#1b2a41;--navy-deep:#111c2d;--navy-soft:#2a3d5c;
    --gold:#b8923a;--gold-light:#d9b968;--gold-pale:#f4ead2;
    --burgundy:#7a1f2b;--green:#2f6b4f;--cream:#faf7f2;--paper:#fff;
    --ink:#22262e;--muted:#6b7280;--line:#e6dfd0;
    --shadow:0 2px 10px rgba(27,42,65,.07);
    --shadow-hover:0 14px 30px -10px rgba(27,42,65,.28);
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

.breadcrumb{color:var(--muted);font-size:13px;margin:24px 0 16px}
.breadcrumb a{color:var(--gold)}
.breadcrumb a:hover{color:var(--navy)}
.breadcrumb .sep{margin:0 6px;color:#c9c1ad}

/* کارت اصلی محصول */
.product-wrapper{display:grid;grid-template-columns:440px 1fr;gap:36px;background:var(--paper);border:1px solid var(--line);padding:30px;border-radius:6px;box-shadow:var(--shadow)}
.gallery-main{background:#fcfbf8;border:1px solid var(--line);border-radius:6px;padding:24px;display:grid;place-items:center;aspect-ratio:1/1;position:relative}
.gallery-main img{width:100%;height:100%;object-fit:contain}
.gallery-main .ribbon{position:absolute;top:18px;right:0;background:var(--burgundy);color:#fff;font-size:12px;font-weight:700;padding:5px 20px 5px 14px;clip-path:polygon(0 0,100% 0,100% 100%,0 100%,9px 50%)}
.thumbs{display:flex;gap:10px;margin-top:12px;overflow-x:auto;scrollbar-width:thin}
.thumb{width:72px;height:72px;flex-shrink:0;border:1.5px solid var(--line);border-radius:4px;background:#fcfbf8;padding:6px;cursor:pointer;transition:border-color .2s}
.thumb img{width:100%;height:100%;object-fit:contain}
.thumb:hover{border-color:var(--gold-light)}
.thumb.active{border-color:var(--gold);box-shadow:0 0 0 2px var(--gold-pale)}

.product-category{display:inline-block;font-size:12px;color:var(--gold);font-weight:700;border-bottom:1px solid var(--gold-light);margin-bottom:8px}
.product-title{font-size:23px;font-weight:800;line-height:1.7;color:var(--navy);margin-bottom:4px}
.product-title-en{color:var(--muted);font-size:13.5px;margin-bottom:16px;direction:ltr;text-align:right}
.meta-list{display:flex;flex-wrap:wrap;gap:8px 22px;font-size:13px;color:#4a5160;padding:14px 0;border-top:1px solid var(--line);border-bottom:1px solid var(--line);margin-bottom:18px}
.meta-list b{color:var(--navy)}
.stock-badge{display:inline-flex;align-items:center;gap:6px;padding:5px 16px;border-radius:3px;font-size:13px;font-weight:700;margin-bottom:14px}
.stock-badge.in-stock{background:#e4f0ea;color:var(--green);border:1px solid #bcd8c9}
.out-of-stock-box{background:#f6e7e9;border:1px solid #e3c4c9;border-radius:4px;padding:14px 18px;margin-bottom:14px;color:var(--burgundy);font-weight:700;display:flex;align-items:center;gap:10px}
.price-box{background:linear-gradient(135deg,var(--gold-pale),#fbf6e8);border:1px solid var(--gold-light);padding:20px 24px;border-radius:6px;margin-bottom:18px}
.old-price{color:#9a9486;text-decoration:line-through;font-size:15px}
.discount-badge{background:var(--burgundy);color:#fff;padding:2px 12px;border-radius:3px;font-size:12px;font-weight:700;margin-right:10px}
.final-price{font-size:30px;font-weight:800;color:var(--navy);margin-top:4px}
.final-price small{font-size:15px;color:var(--muted);font-weight:500}
.contact-price{font-size:18px;color:var(--burgundy);font-weight:700}
.btn-add-to-cart{background:var(--navy);color:var(--gold-light);border:1px solid var(--navy);padding:15px 30px;border-radius:3px;font-size:16px;font-weight:800;width:100%;transition:all .25s}
.btn-add-to-cart:hover{background:var(--gold);border-color:var(--gold);color:#fff}
.btn-disabled{background:#eceae4;color:#9a9486;padding:15px 30px;border-radius:3px;font-size:16px;font-weight:800;width:100%;cursor:not-allowed}
.btn-notify{display:block;text-align:center;background:var(--gold-pale);color:#7a5c14;border:1px solid var(--gold-light);padding:12px 20px;border-radius:3px;font-weight:700;margin-top:10px}
.btn-notify:hover{background:var(--gold-light);color:var(--navy-deep)}
.trust-row{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:18px}
.trust-item{text-align:center;font-size:12px;color:#4a5160;padding:12px 6px;border:1px solid var(--line);border-radius:4px;background:var(--cream)}
.trust-item span{display:block;font-size:20px;margin-bottom:2px}

/* بخش‌های محتوا */
.content-section{background:var(--paper);border:1px solid var(--line);padding:30px;border-radius:6px;margin-top:28px;box-shadow:var(--shadow)}
.rv-item{border-bottom:1px solid var(--line);padding:14px 0}.rv-item:last-child{border-bottom:0}
.rv-head{display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:4px}.rv-name{font-weight:700;color:var(--navy)}
.rv-stars{color:var(--gold);letter-spacing:2px;font-size:15px}.rv-date{color:var(--muted);font-size:12px}.rv-body{font-size:14px;line-height:2;color:var(--ink)}
.rv-form{background:var(--cream);border:1px solid var(--line);border-radius:6px;padding:18px;margin-top:20px}
.rv-form h3{font-size:16px;margin-bottom:12px;color:var(--navy)}
.rv-form input[type=text],.rv-form textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:6px;font-family:inherit;font-size:14px;margin-bottom:10px;background:#fff}
.rv-form textarea{min-height:100px;resize:vertical}
.star-pick{direction:ltr;display:inline-flex;flex-direction:row-reverse;margin-bottom:10px}
.star-pick input{display:none}.star-pick label{font-size:28px;color:#d6d3cb;cursor:pointer;padding:0 2px}
.star-pick input:checked ~ label,.star-pick label:hover,.star-pick label:hover ~ label{color:var(--gold)}
.rv-submit{background:var(--navy);color:var(--gold-light);padding:11px 28px;border-radius:6px;font-weight:700;font-size:14px}.rv-submit:hover{background:var(--navy-soft)}
.rv-msg{padding:12px 14px;border-radius:6px;margin-bottom:14px;font-size:14px}.rv-msg.ok{background:#e8f5ee;color:#1d5a3e;border:1px solid #bfe0cf}.rv-msg.err{background:#fdecec;color:#8a1f1f;border:1px solid #f3c2c2}
.content-section > h2{font-size:20px;font-weight:800;color:var(--navy);margin-bottom:22px;padding-bottom:12px;border-bottom:2px solid var(--gold);display:inline-block}
.spec-group{margin-bottom:26px}
.spec-group:last-child{margin-bottom:0}
.spec-group h3{font-size:15px;color:var(--gold);font-weight:800;margin-bottom:6px;display:flex;align-items:center;gap:8px}
.spec-group h3::before{content:'◆';font-size:9px}
.spec-row{display:grid;grid-template-columns:220px 1fr;padding:11px 14px;border-bottom:1px solid var(--line);font-size:13.5px}
.spec-row:nth-child(even){background:#fcfaf5}
.spec-key{color:var(--muted);font-weight:700}
.spec-value{color:var(--ink)}
.description-body{line-height:2.2;font-size:14.5px;color:#333a47}

/* مرتبط */
.section-header{text-align:center;margin:56px 0 26px}
.section-header h2{font-size:22px;font-weight:800;color:var(--navy)}
.section-header .divider{display:flex;align-items:center;justify-content:center;gap:12px;margin-top:6px;color:var(--gold)}
.section-header .divider::before,.section-header .divider::after{content:'';height:1px;width:80px;background:linear-gradient(90deg,transparent,var(--gold))}
.section-header .divider::after{background:linear-gradient(270deg,transparent,var(--gold))}
.section-header .divider span{font-size:12px}
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:20px}
.product-card{background:var(--paper);border:1px solid var(--line);border-radius:6px;overflow:hidden;display:flex;flex-direction:column;transition:all .3s;box-shadow:var(--shadow);position:relative}
.product-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-hover);border-color:var(--gold-light)}
.product-card.out-of-stock .p-image img{filter:grayscale(70%)}
.p-image{display:block;background:#fcfbf8;padding:18px;border-bottom:1px solid var(--line)}
.p-image img{width:100%;height:160px;object-fit:contain}
.p-info{padding:14px;display:flex;flex-direction:column;flex:1}
.p-title{font-size:13px;font-weight:600;line-height:1.8;height:47px;overflow:hidden;margin-bottom:10px;color:var(--navy)}
.p-title:hover{color:var(--gold)}
.p-price{margin-top:auto;font-size:16px;font-weight:800;color:var(--navy)}
.p-price small{font-size:11px;color:var(--muted);font-weight:500}
.p-price .none{color:var(--burgundy);font-size:13px}
.p-price .off{font-size:12px;color:#9a9486;text-decoration:line-through;display:block;font-weight:400}

/* فوتر */
.site-footer{background:var(--navy-deep);color:#97a2b6;margin-top:70px;border-top:4px solid var(--gold);text-align:center;padding:30px 0;font-size:13px}
.site-footer .links{display:flex;justify-content:center;gap:24px;margin-bottom:12px;flex-wrap:wrap}
.site-footer .links a:hover{color:var(--gold-light)}

/* Toast */
.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(100px);padding:13px 28px;border-radius:4px;font-size:14px;font-weight:600;color:#fff;box-shadow:0 14px 30px -8px rgba(0,0,0,.35);z-index:9999;opacity:0;transition:all .4s;border-bottom:3px solid var(--gold)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.success{background:var(--navy)}
.toast.error{background:var(--burgundy)}

@media(max-width:960px){
    .product-wrapper{grid-template-columns:1fr;gap:24px;padding:20px}
    .gallery-main{max-width:460px;margin:0 auto;width:100%}
}
@media(max-width:768px){
    .container{padding:0 16px}
    .top-bar .container{justify-content:center}
    .header-inner{flex-wrap:wrap;gap:12px}
    .search-box{order:3;max-width:100%;width:100%}
    .product-title{font-size:19px}
    .final-price{font-size:25px}
    .content-section{padding:20px}
    .spec-row{grid-template-columns:1fr;gap:2px}
    .products-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .p-image img{height:120px}
}
@media(max-width:400px){.products-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

    <div class="breadcrumb">
        <a href="index.php">خانه</a>
        <?php if ($product['category_name']): ?>
            <span class="sep">‹</span><a href="archive.php?cat=<?= (int)$product['category_id'] ?>"><?= htmlspecialchars($product['category_name']) ?></a>
        <?php endif; ?>
        <span class="sep">‹</span><span><?= htmlspecialchars(mb_substr($product['name'], 0, 50)) ?></span>
    </div>

    <div class="product-wrapper">

        <!-- گالری -->
        <div class="product-gallery">
            <div class="gallery-main">
                <?php if ($isAvailable && $hasSale): ?>
                    <span class="ribbon"><?= ms_fa_digits((int)$product['discount_percent']) ?>٪ تخفیف</span>
                <?php endif; ?>
                <img id="mainImg" src="<?= htmlspecialchars($mainImage) ?>"
                     alt="<?= htmlspecialchars($product['name']) ?>"
                     onerror="this.onerror=null;this.src='icon/placeholder/no-image.png'">
            </div>
            <?php if (count($images) > 1): ?>
            <div class="thumbs">
                <?php foreach ($images as $i => $img): ?>
                <div class="thumb <?= $img === $mainImage ? 'active' : '' ?>" data-src="<?= htmlspecialchars($img) ?>">
                    <img src="<?= htmlspecialchars($img) ?>" alt="" loading="lazy"
                         onerror="this.onerror=null;this.src='icon/placeholder/no-image.png'">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- اطلاعات -->
        <div class="product-info">

            <?php if ($product['category_name']): ?>
                <a href="archive.php?cat=<?= (int)$product['category_id'] ?>" class="product-category"><?= htmlspecialchars($product['category_name']) ?></a>
            <?php endif; ?>

            <h1 class="product-title"><?= htmlspecialchars($product['name']) ?></h1>

            <?php if (!empty($product['name_en'])): ?>
                <div class="product-title-en"><?= htmlspecialchars($product['name_en']) ?></div>
            <?php endif; ?>

            <?php if ($product['warranty'] || $product['seller'] || $product['rating'] > 0): ?>
            <div class="meta-list">
                <?php if ($product['warranty']): ?>
                    <div>🛡 گارانتی: <b><?= htmlspecialchars($product['warranty']) ?></b></div>
                <?php endif; ?>
                <?php if ($product['seller']): ?>
                    <div>🏪 فروشنده: <b><?= htmlspecialchars($product['seller']) ?></b></div>
                <?php endif; ?>
                <?php if ($product['rating'] > 0): ?>
                    <div>⭐ امتیاز: <b><?= ms_fa_digits($product['rating']) ?></b> از ۵ (<?= ms_fa_digits($product['review_count']) ?> نظر)</div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($isAvailable): ?>
                <div class="stock-badge in-stock">✔ موجود در انبار</div>
            <?php else: ?>
                <div class="out-of-stock-box">
                    <span style="font-size:22px;">⛔</span>
                    <span>این محصول فعلاً ناموجود است</span>
                </div>
            <?php endif; ?>

            <div class="price-box">
                <?php if ($product['price'] > 0): ?>
                    <?php if ($hasSale): ?>
                        <div>
                            <del class="old-price"><?= ms_money($product['price']) ?> تومان</del>
                            <span class="discount-badge"><?= ms_fa_digits((int)$product['discount_percent']) ?>٪ تخفیف</span>
                        </div>
                        <div class="final-price"><?= ms_money($product['sale_price']) ?> <small>تومان</small></div>
                    <?php else: ?>
                        <div class="final-price"><?= ms_money($product['price']) ?> <small>تومان</small></div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="contact-price">برای اطلاع از قیمت تماس بگیرید</div>
                <?php endif; ?>
            </div>

            <div class="product-actions">
                <?php if ($isAvailable): ?>
                    <button class="btn-add-to-cart" onclick="addToCart(<?= (int)$product['id'] ?>)">افزودن به سبد خرید</button>
                <?php else: ?>
                    <button class="btn-disabled" disabled>فعلاً ناموجود</button>
                    <a href="contact.php?product_id=<?= (int)$product['id'] ?>" class="btn-notify">🔔 اطلاع‌رسانی موجود شدن</a>
                <?php endif; ?>
            </div>

            <div class="trust-row">
                <div class="trust-item"><span>🛡️</span>اصالت کالا</div>
                <div class="trust-item"><span>🚚</span>ارسال سریع</div>
                <div class="trust-item"><span>💳</span>پرداخت امن</div>
            </div>

        </div>
    </div>

    <!-- مشخصات فنی -->
    <?php if (!empty($specsByGroup)): ?>
    <section class="content-section">
        <h2>مشخصات فنی</h2>
        <?php foreach ($specsByGroup as $group => $groupSpecs): ?>
        <div class="spec-group">
            <h3><?= htmlspecialchars($group) ?></h3>
            <?php foreach ($groupSpecs as $s): ?>
            <div class="spec-row">
                <div class="spec-key"><?= htmlspecialchars($s['spec_key']) ?></div>
                <div class="spec-value"><?= htmlspecialchars($s['spec_value']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>

    <!-- توضیحات -->
    <?php if (!empty($product['description'])): ?>
    <section class="content-section">
        <h2>بررسی تخصصی</h2>
        <div class="description-body"><?= nl2br(htmlspecialchars($product['description'])) ?></div>
    </section>
    <?php endif; ?>

    <!-- نظرات کاربران -->
    <section class="content-section" id="reviews">
        <h2>💬 نظرات کاربران<?php if ($reviews): ?> <small style="font-weight:400;font-size:14px;color:var(--muted)">(<?= ms_fa_digits(count($reviews)) ?> نظر — میانگین <?= ms_fa_digits(number_format($rvAvg, 1)) ?> از ۵)</small><?php endif; ?></h2>

        <?php if ($reviewFlash): ?><div class="rv-msg <?= $reviewFlash[0] ?>"><?= htmlspecialchars($reviewFlash[1]) ?></div><?php endif; ?>

        <?php if (!$reviews): ?>
            <p style="color:var(--muted);font-size:14px">هنوز نظری ثبت نشده است. اولین نفر باشید!</p>
        <?php else: foreach ($reviews as $rv): ?>
            <div class="rv-item">
                <div class="rv-head">
                    <span class="rv-name"><?= htmlspecialchars($rv['author_name']) ?></span>
                    <span class="rv-stars"><?= str_repeat('★', (int)$rv['rating']) . str_repeat('☆', 5 - (int)$rv['rating']) ?></span>
                    <span class="rv-date"><?= ms_fa_digits(jalali_str($rv['created_at'], false)) ?></span>
                </div>
                <div class="rv-body"><?= nl2br(htmlspecialchars($rv['body'])) ?></div>
            </div>
        <?php endforeach; endif; ?>

        <form method="post" action="product.php?id=<?= (int)$productId ?>#reviews" class="rv-form">
            <h3>✍️ نظر خود را بنویسید</h3>
            <?php if (empty($_SESSION['user_id'])): ?>
                <input type="text" name="name" maxlength="60" placeholder="نام شما" required>
            <?php else: ?>
                <div style="font-size:13px;color:var(--muted);margin-bottom:8px">ثبت با نام: <b><?= htmlspecialchars($_SESSION['name'] ?? '') ?></b></div>
            <?php endif; ?>
            <div style="font-size:13px;color:var(--muted)">امتیاز شما:</div>
            <div class="star-pick">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="st<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="st<?= $i ?>" title="<?= ms_fa_digits($i) ?> ستاره">★</label>
                <?php endfor; ?>
            </div>
            <textarea name="body" maxlength="2000" placeholder="تجربه خود از این محصول را بنویسید..." required></textarea>
            <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
            <div style="font-size:12px;color:var(--muted);margin-bottom:10px">نظر شما ابتدا توسط مدیر بررسی می‌شود و پس از تأیید نمایش داده خواهد شد.</div>
            <button type="submit" name="submit_review" value="1" class="rv-submit">ارسال نظر</button>
        </form>
    </section>

    <!-- محصولات مرتبط -->
    <?php if (!empty($related)): ?>
    <div class="section-header">
        <h2>محصولات مرتبط</h2>
        <div class="divider"><span>◆</span></div>
    </div>
    <div class="products-grid">
        <?php foreach ($related as $r):
            $rAvail = ($r['stock_status'] === 'in_stock' && $r['stock'] > 0 && $r['price'] > 0);
            $rSale = ($r['sale_price'] && $r['sale_price'] > 0 && $r['sale_price'] < $r['price']);
        ?>
        <article class="product-card <?= !$rAvail ? 'out-of-stock' : '' ?>">
            <a href="product.php?id=<?= (int)$r['id'] ?>" class="p-image">
                <img src="<?= htmlspecialchars($r['main_image'] ?: 'icon/placeholder/no-image.png') ?>" alt="" loading="lazy"
                     onerror="this.onerror=null;this.src='icon/placeholder/no-image.png'">
            </a>
            <div class="p-info">
                <a href="product.php?id=<?= (int)$r['id'] ?>" class="p-title"><?= htmlspecialchars(mb_substr($r['name'], 0, 70)) ?></a>
                <div class="p-price">
                    <?php if (!$rAvail): ?>
                        <span class="none">ناموجود</span>
                    <?php elseif ($rSale): ?>
                        <span class="off"><?= ms_money($r['price']) ?></span>
                        <?= ms_money($r['sale_price']) ?> <small>تومان</small>
                    <?php else: ?>
                        <?= ms_money($r['price']) ?> <small>تومان</small>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</div>

<?php
$footer_is_product_page = true;
$footer_product = [
    'id' => $product['id'],
    'title' => $product['name'],
    'image' => $mainImage,
    'price' => $hasSale ? $product['sale_price'] : $product['price'],
    'old_price' => $hasSale ? $product['price'] : 0,
];
?>
<?php require __DIR__ . '/includes/site_footer.php'; ?>

<script>
// تعویض تصویر اصلی با کلیک روی تصاویر کوچک
document.querySelectorAll('.thumb').forEach(function (t) {
    t.addEventListener('click', function () {
        document.getElementById('mainImg').src = t.dataset.src;
        document.querySelectorAll('.thumb').forEach(function (x) { x.classList.remove('active'); });
        t.classList.add('active');
    });
});

function addToCart(productId) {
    fetch('add_to_cart.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + productId
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (data.cart_count > 0) {
                const faCount = String(data.cart_count).replace(/\d/g, d => '۰۱۲۳۴۵۶۷۸۹'[d]);
                const badge = document.querySelector('.cart-badge');
                if (badge) {
                    badge.textContent = faCount;
                } else {
                    const cartLink = document.querySelector('.header-actions a[href="cart.php"]');
                    if (cartLink) {
                        const span = document.createElement('span');
                        span.className = 'cart-badge';
                        span.textContent = faCount;
                        cartLink.appendChild(span);
                    }
                }
            }
            showToast('✅ ' + data.message, false);
        } else {
            showToast('❌ ' + data.message, true);
        }
    })
    .catch(() => showToast('خطا در ارتباط با سرور', true));
}
function showToast(message, isError) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();
    const toast = document.createElement('div');
    toast.className = 'toast ' + (isError ? 'error' : 'success');
    toast.textContent = message;
    document.body.appendChild(toast);
    requestAnimationFrame(() => toast.classList.add('show'));
    setTimeout(() => { toast.classList.remove('show'); setTimeout(() => toast.remove(), 400); }, 2500);
}
</script>

</body>
</html>