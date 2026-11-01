<?php
/**
 * صفحه اصلی فروشگاه - طراحی کلاسیک
 */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/coupons.php';

// صفحه خوش‌آمدگویی: یک‌بار در هر نشست، هنگام ورود به صفحه اصلی
if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER['REQUEST_METHOD'] === 'GET' && empty($_SESSION['welcomed']) && !isset($_GET['skip']) && welcome_enabled($pdo)) {
    header('Location: welcome.php');
    exit;
}

// کدهای تخفیف فعال برای نمایش در Hero
$heroCoupons = [];
try {
    coupons_ensure($pdo);
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM coupon_products WHERE coupon_id = ?");
    foreach ($pdo->query("SELECT * FROM coupons WHERE status = 1 ORDER BY ends_at ASC, id DESC LIMIT 12")->fetchAll() as $cp) {
        if (coupon_state($cp) !== 'active') continue;
        $cnt->execute([$cp['id']]); $cp['n_products'] = (int)$cnt->fetchColumn();
        $heroCoupons[] = $cp;
        if (count($heroCoupons) >= 4) break;
    }
} catch (Throwable $e) { $heroCoupons = []; }

// ============================================================
// توابع کمکی نمایش (فقط در صورت نبودن تعریف می‌شوند)
// ============================================================
if (!function_exists('ms_fa_digits')) {
    function ms_fa_digits($value) {
        return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}
if (!function_exists('ms_money')) {
    function ms_money($n) {
        return ms_fa_digits(number_format((float)$n));
    }
}
if (!function_exists('ms_product_card')) {
    function ms_product_card(array $p, bool $forceDiscount = false): void {
        $isAvailable = ($p['stock_status'] === 'in_stock' && $p['stock'] > 0 && $p['price'] > 0);
        $hasSale = ($p['sale_price'] && $p['sale_price'] > 0 && $p['sale_price'] < $p['price']);
        ?>
        <article class="product-card <?= !$isAvailable ? 'out-of-stock' : '' ?>">
            <?php if (!$isAvailable): ?>
                <span class="ribbon ribbon-off">ناموجود</span>
            <?php elseif ($hasSale): ?>
                <span class="ribbon ribbon-sale"><?= ms_fa_digits((int)$p['discount_percent']) ?>٪ تخفیف</span>
            <?php endif; ?>
            <a href="product.php?id=<?= (int)$p['id'] ?>" class="product-image">
                <img src="<?= htmlspecialchars($p['main_image'] ?: 'assets/no-image.png') ?>"
                     alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy"
                     onerror="this.onerror=null;this.src='assets/no-image.png'">
            </a>
            <div class="product-info">
                <h3 class="product-title">
                    <a href="product.php?id=<?= (int)$p['id'] ?>"><?= htmlspecialchars(mb_substr($p['name'], 0, 70)) ?></a>
                </h3>
                <div class="product-price">
                    <?php if ($p['price'] > 0): ?>
                        <?php if ($hasSale): ?>
                            <del class="old-price"><?= ms_money($p['price']) ?></del>
                            <span class="final-price"><?= ms_money($p['sale_price']) ?> <small>تومان</small></span>
                        <?php else: ?>
                            <span class="final-price"><?= ms_money($p['price']) ?> <small>تومان</small></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="no-price">تماس بگیرید</span>
                    <?php endif; ?>
                </div>
                <?php if ($isAvailable): ?>
                    <button class="btn-add-to-cart" onclick="addToCart(<?= (int)$p['id'] ?>, event)">افزودن به سبد خرید</button>
                <?php else: ?>
                    <button class="btn-disabled" disabled>ناموجود</button>
                <?php endif; ?>
            </div>
        </article>
        <?php
    }
}

// ============================================================
// ۱. دسته‌بندی‌ها
// ============================================================
$categories = $pdo->query("
    SELECT c.id, c.name, c.slug, COUNT(p.id) AS product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
    GROUP BY c.id
    HAVING product_count > 0
    ORDER BY product_count DESC
    LIMIT 12
")->fetchAll();

// ============================================================
// ۲. محصولات ویژه
// ============================================================
require_once 'includes/featured.php';
featured_ensure($pdo);
// اول محصولات منتخب مدیر؛ اگر مدیر چیزی انتخاب نکرده باشد، پرامتیازترین‌ها
$featuredProducts = $pdo->query("
    SELECT * FROM products
    WHERE status = 1 AND featured = 1
    ORDER BY id DESC
    LIMIT 12
")->fetchAll();
if (!$featuredProducts) $featuredProducts = $pdo->query("
    SELECT * FROM products
    WHERE status = 1 AND stock_status = 'in_stock' AND stock > 0 AND price > 0
    ORDER BY rating DESC, review_count DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// ۳. جدیدترین محصولات
// ============================================================
$newestProducts = $pdo->query("
    SELECT * FROM products WHERE status = 1 ORDER BY id DESC LIMIT 8
")->fetchAll();

// ============================================================
// ۴. تخفیف‌دارها
// ============================================================
$discountProducts = $pdo->query("
    SELECT * FROM products
    WHERE status = 1 AND stock_status = 'in_stock'
      AND sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price
    ORDER BY discount_percent DESC
    LIMIT 8
")->fetchAll();

// ============================================================
// ۵. آمار
// ============================================================
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
$totalInStock  = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 1 AND stock_status = 'in_stock'")->fetchColumn();
$cartCount     = array_sum($_SESSION['cart'] ?? []);

$catIcons = ['📱','🎧','🔋','🔌','⌚','🛡️','📷','🎮','💾','🖥️','🔊','🧰'];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars(SITE_NAME) ?> | فروشگاه آنلاین موبایل</title>
<meta name="description" content="جدیدترین گوشی‌های موبایل و لوازم جانبی با گارانتی معتبر و بهترین قیمت">
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
:root{
    --navy:#1b2a41;
    --navy-deep:#111c2d;
    --navy-soft:#2a3d5c;
    --gold:#b8923a;
    --gold-light:#d9b968;
    --gold-pale:#f4ead2;
    --burgundy:#7a1f2b;
    --cream:#faf7f2;
    --paper:#ffffff;
    --ink:#22262e;
    --muted:#6b7280;
    --line:#e6dfd0;
    --green:#2f6b4f;
    --shadow:0 2px 10px rgba(27,42,65,.07);
    --shadow-hover:0 14px 30px -10px rgba(27,42,65,.28);
}
*{box-sizing:border-box;margin:0;padding:0}
html{scroll-behavior:smooth}
body{
    font-family:'Vazirmatn',tahoma,sans-serif;
    background:var(--cream);
    color:var(--ink);
    line-height:1.7;
    -webkit-font-smoothing:antialiased;
}
a{text-decoration:none;color:inherit;transition:color .2s,background .2s,border-color .2s}
img{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;outline:none}
ul{list-style:none}
.container{max-width:1280px;margin:0 auto;padding:0 24px}

/* ============ Hero (بنر اصلی + دو کارت کناری) ============ */
.hero-grid{display:grid;grid-template-columns:2fr 1fr;gap:18px;margin:22px 0 10px}
.hero-main{position:relative;overflow:hidden;border-radius:22px;padding:56px 48px;min-height:380px;display:flex;flex-direction:column;justify-content:center;
    color:#fff;background:radial-gradient(circle at 15% 85%,rgba(255,255,255,.07),transparent 45%),linear-gradient(135deg,var(--navy-deep),var(--navy));
    box-shadow:0 22px 40px -22px rgba(15,23,42,.6)}
.hero-main::before{content:'';position:absolute;inset:0;background-image:radial-gradient(rgba(255,255,255,.09) 1.2px,transparent 1.2px);background-size:22px 22px;pointer-events:none}
.hero-main>*{position:relative}
.hero-wm{position:absolute!important;left:34px;bottom:10px;font-size:210px;line-height:1;filter:grayscale(1) brightness(1.6);opacity:.14;pointer-events:none}
.hero-badge{align-self:flex-start;background:var(--gold);color:#fff;font-size:12px;font-weight:800;padding:6px 16px;border-radius:999px;margin-bottom:20px}
.hero-main h1{font-size:40px;font-weight:900;line-height:1.5;margin:0}
.hero-main h1 em{display:block;font-style:normal;color:var(--gold)}
.hero-main p{font-size:15px;color:#cbd5e1;margin:14px 0 26px;max-width:560px}
.hero-btns{display:flex;gap:12px;flex-wrap:wrap}
.hb{padding:13px 28px;border-radius:12px;font-size:14px;font-weight:800;transition:transform .2s}
.hb:hover{transform:translateY(-2px)}
.hb-1{background:var(--gold);color:#fff;box-shadow:0 12px 24px -10px var(--gold)}
.hb-2{border:1px solid rgba(255,255,255,.28);color:#fff;background:rgba(255,255,255,.04)}
.hero-side{display:grid;grid-template-rows:1fr 1fr;gap:18px}
.hs{position:relative;overflow:hidden;border-radius:22px;padding:26px 28px;display:flex;flex-direction:column;justify-content:space-between;min-height:180px;color:#fff;box-shadow:0 18px 34px -22px rgba(15,23,42,.6)}
.hs-1{background:linear-gradient(135deg,var(--gold-light),var(--gold-deep,var(--gold)))}
.hs-2{background:linear-gradient(135deg,var(--navy-deep),var(--navy))}
.hs-tag{align-self:flex-start;background:rgba(255,255,255,.22);font-size:11px;font-weight:700;padding:4px 12px;border-radius:8px}
.hs-2 .hs-tag{background:var(--gold)}
.hs h3{font-size:21px;font-weight:900;margin:10px 0 2px;line-height:1.4}
.hs p{font-size:12px;opacity:.85;margin:0}
.hs-go{font-size:13px;font-weight:800;border-bottom:1px solid currentColor;align-self:flex-start;padding-bottom:1px}
.hs-2 .hs-go{color:var(--gold)}
.hs-wm{position:absolute;left:18px;bottom:6px;font-size:96px;line-height:1;filter:grayscale(1) brightness(2);opacity:.16;pointer-events:none}
.hs>div,.hs>a{position:relative}
.hero-coupons{margin:30px 0 0;max-width:none}
.hc-title{font-size:12.5px;color:var(--gold-light);margin-bottom:10px}
.hc-list{display:flex;flex-wrap:wrap;gap:10px}
.hc{position:relative;overflow:hidden;min-width:170px;padding:12px 20px 12px 20px;border-radius:14px;color:#fff;text-align:center;border:2px dashed rgba(255,255,255,.65);
    box-shadow:0 10px 24px -8px rgba(0,0,0,.45);cursor:pointer;font-family:inherit;transition:transform .2s}
.hc:hover{transform:translateY(-3px) rotate(-.6deg)}
.hc::after{content:'';position:absolute;top:0;left:-60%;width:40%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.35),transparent);transform:skewX(-20deg);animation:hcshine 3.6s infinite}
@keyframes hcshine{0%{left:-60%}60%,100%{left:130%}}
.hc .hc-val{display:block;font-size:19px;font-weight:800;text-shadow:0 1px 3px rgba(0,0,0,.3)}
.hc .hc-code{display:inline-block;margin:6px 0 3px;background:rgba(255,255,255,.92);color:#222;padding:3px 14px;border-radius:8px;font-family:Consolas,monospace;font-size:16px;letter-spacing:2px;font-weight:800}
.hc small{display:block;font-size:11.5px;opacity:.95}
.hc-0{background:linear-gradient(135deg,#ff416c,#ff8a00)}
.hc-1{background:linear-gradient(135deg,#00b4db,#0083b0 55%,#6a11cb)}
.hc-2{background:linear-gradient(135deg,#11998e,#38ef7d)}
.hc-3{background:linear-gradient(135deg,#8e2de2,#f64f9b)}
.hc-ok{outline:3px solid #fff}

/* ============ بخش‌ها ============ */
.section{margin:56px 0}
.section-header{text-align:center;margin-bottom:30px;position:relative}
.section-header h2{
    display:inline-block;font-size:24px;font-weight:800;color:var(--navy);
    position:relative;padding:0 24px;
}
.section-header .divider{
    display:flex;align-items:center;justify-content:center;gap:12px;margin-top:8px;color:var(--gold);
}
.section-header .divider::before,.section-header .divider::after{
    content:'';height:1px;width:80px;
    background:linear-gradient(90deg,transparent,var(--gold));
}
.section-header .divider::after{background:linear-gradient(270deg,transparent,var(--gold))}
.section-header .divider span{font-size:12px}
.section-more{text-align:center;margin-top:28px}
.section-more a{
    display:inline-block;padding:10px 30px;border:1.5px solid var(--navy);
    color:var(--navy);font-weight:700;font-size:13px;border-radius:3px;
}
.section-more a:hover{background:var(--navy);color:var(--gold-light)}

/* ============ دسته‌بندی ============ */
.categories-grid{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:18px;
}
.category-card{
    background:var(--paper);border:1px solid var(--line);border-radius:6px;
    padding:28px 14px 22px;text-align:center;position:relative;
    transition:all .3s;box-shadow:var(--shadow);
}
.category-card::after{
    content:'';position:absolute;bottom:0;right:50%;transform:translateX(50%);
    width:0;height:3px;background:var(--gold);transition:width .3s;
}
.category-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-hover);border-color:var(--gold-light)}
.category-card:hover::after{width:60%}
.category-card .icon{
    width:62px;height:62px;margin:0 auto 14px;border-radius:50%;
    display:grid;place-items:center;font-size:28px;
    background:var(--gold-pale);border:1px solid var(--gold-light);
}
.category-card .name{font-size:14px;font-weight:700;color:var(--navy);margin-bottom:2px}
.category-card .count{font-size:12px;color:var(--muted)}

/* ============ محصولات ============ */
.products-grid{
    display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px;
}
.product-card{
    background:var(--paper);border:1px solid var(--line);border-radius:6px;
    overflow:hidden;position:relative;display:flex;flex-direction:column;
    transition:all .3s;box-shadow:var(--shadow);
}
.product-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-hover);border-color:var(--gold-light)}
.product-card.out-of-stock{opacity:.88}
.product-card.out-of-stock .product-image img{filter:grayscale(70%)}
.ribbon{
    position:absolute;top:14px;right:0;z-index:5;
    color:#fff;font-size:11.5px;font-weight:700;
    padding:4px 14px 4px 12px;
    clip-path:polygon(0 0,100% 0,100% 100%,0 100%,8px 50%);
    padding-right:18px;
}
.ribbon-sale{background:var(--burgundy)}
.ribbon-off{background:#6b7280}
.product-image{
    display:block;background:#fcfbf8;padding:22px;text-align:center;
    border-bottom:1px solid var(--line);overflow:hidden;
}
.product-image img{
    width:100%;height:190px;object-fit:contain;transition:transform .4s;
}
.product-card:hover .product-image img{transform:scale(1.06)}
.product-info{padding:16px;flex:1;display:flex;flex-direction:column}
.product-title{
    font-size:13.5px;line-height:1.8;font-weight:600;
    height:50px;overflow:hidden;margin-bottom:12px;
}
.product-title a{color:var(--navy)}
.product-title a:hover{color:var(--gold)}
.product-price{margin-top:auto;min-height:56px;display:flex;flex-direction:column;justify-content:flex-end}
.old-price{color:#9a9486;font-size:12.5px;text-decoration:line-through}
.final-price{font-size:18px;font-weight:800;color:var(--navy)}
.final-price small{font-size:11.5px;color:var(--muted);font-weight:500}
.no-price{color:var(--burgundy);font-size:13px;font-weight:700}
.btn-add-to-cart{
    width:100%;margin-top:14px;padding:11px;border-radius:3px;
    background:var(--navy);color:var(--gold-light);
    font-size:13px;font-weight:700;border:1px solid var(--navy);
    transition:all .25s;
}
.btn-add-to-cart:hover{background:var(--gold);border-color:var(--gold);color:#fff}
.btn-disabled{
    width:100%;margin-top:14px;padding:11px;border-radius:3px;
    background:#eceae4;color:#9a9486;font-size:13px;font-weight:700;cursor:not-allowed;
}

/* ============ فوتر ============ */
.site-footer{
    background:var(--navy-deep);color:#b6bfce;
    margin-top:80px;border-top:4px solid var(--gold);
}
.footer-main{padding:52px 0 28px}
.footer-grid{
    display:grid;grid-template-columns:1.4fr 1fr 1fr 1.2fr;gap:40px;
}
.footer-col h4{
    color:#fff;font-size:15px;font-weight:700;margin-bottom:18px;
    padding-bottom:10px;position:relative;
}
.footer-col h4::after{
    content:'';position:absolute;bottom:0;right:0;width:36px;height:2px;background:var(--gold);
}
.footer-col p{font-size:13px;line-height:2;color:#97a2b6}
.footer-col li{margin-bottom:10px;font-size:13px}
.footer-col a{color:#97a2b6}
.footer-col a:hover{color:var(--gold-light)}
.footer-bottom{
    border-top:1px solid #243248;text-align:center;
    padding:18px 0;font-size:12.5px;color:#7d889c;
}

/* ============ Toast ============ */
.toast{
    position:fixed;bottom:30px;left:50%;
    transform:translateX(-50%) translateY(100px);
    padding:13px 28px;border-radius:4px;font-size:14px;font-weight:600;color:#fff;
    box-shadow:0 14px 30px -8px rgba(0,0,0,.35);
    z-index:9999;opacity:0;transition:all .4s;
    border-bottom:3px solid var(--gold);
}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.success{background:var(--navy)}
.toast.error{background:var(--burgundy)}

/* ============ ریسپانسیو ============ */
@media(max-width:992px){
    .footer-grid{grid-template-columns:1fr 1fr}
}
@media(max-width:768px){
    .container{padding:0 16px}
    .section{margin:40px 0}
    .section-header h2{font-size:20px}
    .products-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .product-image{padding:12px}
    .product-image img{height:130px}
    .product-info{padding:12px}
    .product-title{font-size:12.5px;height:46px}
    .final-price{font-size:15px}
    .categories-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .footer-grid{grid-template-columns:1fr;gap:28px}
}
@media(max-width:900px){
    .hero-grid{grid-template-columns:1fr}
    .hero-main{padding:34px 24px;min-height:300px}
    .hero-main h1{font-size:27px}
    .hero-wm{font-size:120px}
    .hero-side{grid-template-rows:none;grid-template-columns:1fr 1fr}
}
@media(max-width:560px){ .hero-side{grid-template-columns:1fr} }
@media(max-width:400px){
    .products-grid{grid-template-columns:1fr}
}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

<!-- ============ Hero ============ -->
<?php
$__hd = ['badge' => 'جشنواره فروش ویژه', 't1' => 'جشنواره تخفیف‌های شگفت‌انگیز', 't2' => 'ابزارآلات حرفه‌ای و صنعتی',
    'sub' => 'تا ۴۰٪ تخفیف ویژه برای انواع ابزارآلات با ضمانت اصالت و ۳۰ روز ضمانت بازگشت.',
    'b1' => 'مشاهده و خرید', 'b1u' => 'archive.php', 'b2' => 'لیست قیمت روز', 'b2u' => 'archive.php?sort=cheap',
    'c1tag' => 'پیشنهاد ویژه', 'c1t' => 'پرفروش‌ترین ابزارها', 'c1s' => 'ارسال رایگان + هدیه', 'c1l' => 'خرید آنلاین', 'c1u' => 'archive.php?sort=best',
    'c2tag' => 'تجهیزات کارگاهی', 'c2t' => 'پک‌های تخفیفی ابزار', 'c2s' => 'مخصوص کارگاه‌ها و تکنیسین‌ها', 'c2l' => 'مشاهده پک‌ها', 'c2u' => 'archive.php?sort=discount'];
$__hs = json_decode(site_get($pdo, 'home_hero', ''), true);
if (is_array($__hs)) foreach ($__hs as $__k => $__v) if (isset($__hd[$__k]) && trim((string)$__v) !== '') $__hd[$__k] = (string)$__v;
$__u = function ($u) { return preg_match('#^(https?://|[A-Za-z0-9_./?=&%-]+$)#', $u) ? htmlspecialchars($u) : 'archive.php'; };
$__e = function ($k) use ($__hd) { return htmlspecialchars($__hd[$k]); };
?>
<section class="hero-grid">
    <div class="hero-main">
        <span class="hero-wm">🛠</span>
        <span class="hero-badge"><?= $__e('badge') ?></span>
        <h1><?= $__e('t1') ?><em><?= $__e('t2') ?></em></h1>
        <p><?= $__e('sub') ?></p>
        <div class="hero-btns">
            <a href="<?= $__u($__hd['b1u']) ?>" class="hb hb-1"><?= $__e('b1') ?> ←</a>
            <a href="<?= $__u($__hd['b2u']) ?>" class="hb hb-2"><?= $__e('b2') ?></a>
        </div>
    <div class="hero-coupons">
        <div class="hc-title">🎟 کد تخفیف فعال — روی کد کلیک کنید تا کپی شود</div>
        <div class="hc-list">
        <?php foreach ($heroCoupons as $ci => $cp): ?>
            <button type="button" class="hc hc-<?= $ci % 4 ?>" data-code="<?= htmlspecialchars($cp['code']) ?>" title="کپی کد تخفیف">
                <span class="hc-val"><?= $cp['type'] === 'percent' ? ms_fa_digits((int)$cp['value']) . '٪ تخفیف' : ms_fa_digits(number_format((int)$cp['value'])) . ' تومان تخفیف' ?></span>
                <span class="hc-code"><?= htmlspecialchars($cp['code']) ?></span>
                <small><?= $cp['title'] ? htmlspecialchars($cp['title']) . ' • ' : '' ?>تا <?= ms_fa_digits(jalali_str($cp['ends_at'], false)) ?><?= $cp['n_products'] ? ' • روی ' . ms_fa_digits($cp['n_products']) . ' کالا' : '' ?></small>
            </button>
        <?php endforeach; ?>
        </div>
    </div>
    <script>
    document.querySelectorAll('.hc').forEach(function(b){b.addEventListener('click',function(){
        var c=b.dataset.code, done=function(){b.classList.add('hc-ok');var s=b.querySelector('small'),o=s.textContent;s.textContent='✔ کپی شد';setTimeout(function(){b.classList.remove('hc-ok');s.textContent=o},1600)};
        if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(c).then(done,done)}else{var t=document.createElement('textarea');t.value=c;document.body.appendChild(t);t.select();try{document.execCommand('copy')}catch(e){}t.remove();done()}
    })});
    </script>
    </div>
    <div class="hero-side">
        <div class="hs hs-1"><span class="hs-wm">⚡</span>
            <div><span class="hs-tag"><?= $__e('c1tag') ?></span><h3><?= $__e('c1t') ?></h3><p><?= $__e('c1s') ?></p></div>
            <a href="<?= $__u($__hd['c1u']) ?>" class="hs-go"><?= $__e('c1l') ?> ‹</a></div>
        <div class="hs hs-2"><span class="hs-wm">🧰</span>
            <div><span class="hs-tag"><?= $__e('c2tag') ?></span><h3><?= $__e('c2t') ?></h3><p><?= $__e('c2s') ?></p></div>
            <a href="<?= $__u($__hd['c2u']) ?>" class="hs-go"><?= $__e('c2l') ?> ‹</a></div>
    </div>
</section>

<!-- ============ دسته‌بندی‌ها ============ -->
<?php if (!empty($categories)): ?>
<section class="section">
    <div class="section-header">
        <h2>دسته‌بندی‌ها</h2>
        <div class="divider"><span>◆</span></div>
    </div>
    <div class="categories-grid">
    <?php foreach ($categories as $i => $c): ?>
        <a href="archive.php?cat=<?= (int)$c['id'] ?>" class="category-card">
            <div class="icon"><?= $catIcons[$i % count($catIcons)] ?></div>
            <div class="name"><?= htmlspecialchars($c['name']) ?></div>
            <div class="count"><?= ms_fa_digits(number_format($c['product_count'])) ?> محصول</div>
        </a>
    <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ============ پیشنهاد ویژه ============ -->
<?php if (!empty($featuredProducts)): ?>
<section class="section">
    <div class="section-header">
        <h2>پیشنهاد ویژه</h2>
        <div class="divider"><span>◆</span></div>
    </div>
    <div class="products-grid">
        <?php foreach ($featuredProducts as $p) ms_product_card($p); ?>
    </div>
    <div class="section-more"><a href="archive.php">مشاهده همه محصولات</a></div>
</section>
<?php endif; ?>

<!-- ============ تخفیف‌ها ============ -->
<?php if (!empty($discountProducts)): ?>
<section class="section">
    <div class="section-header">
        <h2>تخفیف‌های ویژه</h2>
        <div class="divider"><span>◆</span></div>
    </div>
    <div class="products-grid">
        <?php foreach ($discountProducts as $p) ms_product_card($p, true); ?>
    </div>
    <div class="section-more"><a href="archive.php">مشاهده همه تخفیف‌ها</a></div>
</section>
<?php endif; ?>

<!-- ============ جدیدترین‌ها ============ -->
<?php if (!empty($newestProducts)): ?>
<section class="section">
    <div class="section-header">
        <h2>جدیدترین محصولات</h2>
        <div class="divider"><span>◆</span></div>
    </div>
    <div class="products-grid">
        <?php foreach ($newestProducts as $p) ms_product_card($p); ?>
    </div>
    <div class="section-more"><a href="archive.php">مشاهده همه محصولات</a></div>
</section>
<?php endif; ?>

</div>

<?php require __DIR__ . '/includes/site_footer.php'; ?>

<script>
function addToCart(productId, event) {
    if (event) event.preventDefault();
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
    if (isError === undefined) isError = false;
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast ' + (isError ? 'error' : 'success');
    toast.textContent = message;
    document.body.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('show'));

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 400);
    }, 2500);
}
</script>

</body>
</html>