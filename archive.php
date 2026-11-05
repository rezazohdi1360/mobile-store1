<?php
/**
 * آرشیو محصولات - نمایش محصولات یک دسته‌بندی (طراحی کلاسیک)
 * URL: archive.php?cat=1
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
// ۱. گرفتن پارامترها
// ============================================================
$categoryId = (int)($_GET['cat'] ?? 0);
$page = max(1, (int)($_GET['page'] ?? 1));
$sort = in_array($_GET['sort'] ?? '', ['best', 'cheap', 'discount'], true) ? $_GET['sort'] : '';
$sortExpr = [
    'best'     => "(SELECT COALESCE(SUM(oi.quantity),0) FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id = products.id AND o.payment_status = 'paid') DESC, id DESC",
    'cheap'    => "COALESCE(NULLIF(sale_price,0), price) ASC, id DESC",
    'discount' => "discount_percent DESC, id DESC",
][$sort] ?? "id DESC";
$perPage = 24;
$offset = ($page - 1) * $perPage;

// ============================================================
// ۲. واکشی اطلاعات دسته‌بندی
// ============================================================
$category = null;
if ($categoryId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$categoryId]);
    $category = $stmt->fetch();
}

// ============================================================
// ۳. واکشی محصولات
// ============================================================
if ($category) {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 1");
    $countStmt->execute([$categoryId]);
    $totalProducts = $countStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT * FROM products
        WHERE category_id = ? AND status = 1
        ORDER BY CASE WHEN stock_status = 'in_stock' THEN 0 ELSE 1 END, $sortExpr
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute([$categoryId]);
    $products = $stmt->fetchAll();
} else {
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
    $stmt = $pdo->query("
        SELECT * FROM products
        WHERE status = 1
        ORDER BY CASE WHEN stock_status = 'in_stock' THEN 0 ELSE 1 END, $sortExpr
        LIMIT $perPage OFFSET $offset
    ");
    $products = $stmt->fetchAll();
}

$totalPages = (int)ceil($totalProducts / $perPage);

// ============================================================
// ۴. همه دسته‌بندی‌ها برای منو
// ============================================================
$allCategories = $pdo->query("
    SELECT c.*, COUNT(p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON p.category_id = c.id AND p.status = 1
    GROUP BY c.id
    ORDER BY product_count DESC
")->fetchAll();

$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $category ? htmlspecialchars($category['name']) : 'همه محصولات' ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
<link rel="preconnect" href="https://cdn.jsdelivr.net">
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
:root{
    --navy:#1b2a41;--navy-deep:#111c2d;--navy-soft:#2a3d5c;
    --gold:#b8923a;--gold-light:#d9b968;--gold-pale:#f4ead2;
    --burgundy:#7a1f2b;--cream:#faf7f2;--paper:#fff;
    --ink:#22262e;--muted:#6b7280;--line:#e6dfd0;
    --shadow:0 2px 10px rgba(27,42,65,.07);
    --shadow-hover:0 14px 30px -10px rgba(27,42,65,.28);
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Vazirmatn',tahoma,sans-serif;background:var(--cream);color:var(--ink);line-height:1.7;-webkit-font-smoothing:antialiased}
a{text-decoration:none;color:inherit;transition:color .2s,background .2s,border-color .2s}
img{max-width:100%;display:block}
button{font-family:inherit;cursor:pointer;border:none;outline:none}
ul{list-style:none}
.container{max-width:1280px;margin:0 auto;padding:0 24px}

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

/* عنوان صفحه */
.breadcrumb{color:var(--muted);font-size:13px;margin:24px 0 16px}
.breadcrumb a{color:var(--gold)}
.breadcrumb a:hover{color:var(--navy)}
.breadcrumb .sep{margin:0 6px;color:#c9c1ad}
.archive-header{
    background:linear-gradient(135deg,var(--navy-deep),var(--navy));
    color:#fff;border-radius:6px;padding:30px 34px;margin-bottom:26px;
    border:1px solid var(--gold);position:relative;
}
.archive-header::before{content:'';position:absolute;inset:7px;border:1px solid rgba(217,185,104,.4);border-radius:3px;pointer-events:none}
.archive-header h1{font-size:26px;font-weight:800;margin-bottom:4px;position:relative}
.archive-header h1 em{font-style:normal;color:var(--gold-light)}
.archive-header .count{font-size:13.5px;color:#c3cad8;position:relative}

/* چیدمان */
.layout{display:grid;grid-template-columns:260px 1fr;gap:26px;align-items:start}
.sidebar{background:var(--paper);border:1px solid var(--line);border-radius:6px;padding:20px;box-shadow:var(--shadow);position:sticky;top:96px}
.sidebar h3{font-size:15px;font-weight:800;color:var(--navy);margin-bottom:14px;padding-bottom:10px;border-bottom:2px solid var(--gold)}
.cat-list li{margin-bottom:3px}
.cat-list a{display:flex;justify-content:space-between;align-items:center;padding:9px 12px;color:#3a4150;border-radius:4px;font-size:13.5px;border-right:3px solid transparent}
.cat-list a:hover{background:var(--gold-pale);color:var(--navy)}
.cat-list a.active{background:var(--navy);color:var(--gold-light);font-weight:700;border-right-color:var(--gold)}
.cat-list .badge{background:#efe9da;color:var(--muted);padding:1px 9px;border-radius:10px;font-size:11px}
.cat-list a.active .badge{background:rgba(217,185,104,.25);color:var(--gold-light)}

/* محصولات */
.products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(225px,1fr));gap:20px}
.product-card{background:var(--paper);border:1px solid var(--line);border-radius:6px;overflow:hidden;position:relative;display:flex;flex-direction:column;transition:all .3s;box-shadow:var(--shadow)}
.product-card:hover{transform:translateY(-6px);box-shadow:var(--shadow-hover);border-color:var(--gold-light)}
.product-card.out-of-stock{opacity:.88}
.product-card.out-of-stock .product-image img{filter:grayscale(70%)}
.ribbon{position:absolute;top:14px;right:0;z-index:5;color:#fff;font-size:11.5px;font-weight:700;padding:4px 18px 4px 12px;clip-path:polygon(0 0,100% 0,100% 100%,0 100%,8px 50%)}
.ribbon-sale{background:var(--burgundy)}
.ribbon-off{background:#6b7280}
.product-image{display:block;background:#fcfbf8;padding:20px;text-align:center;border-bottom:1px solid var(--line);overflow:hidden}
.product-image img{width:100%;height:180px;object-fit:contain;transition:transform .4s}
.product-card:hover .product-image img{transform:scale(1.06)}
.product-info{padding:15px;flex:1;display:flex;flex-direction:column}
.product-title{font-size:13.5px;line-height:1.8;font-weight:600;height:50px;overflow:hidden;margin-bottom:12px}
.product-title a{color:var(--navy)}
.product-title a:hover{color:var(--gold)}
.product-price{margin-top:auto;min-height:56px;display:flex;flex-direction:column;justify-content:flex-end}
.old-price{color:#9a9486;font-size:12.5px;text-decoration:line-through}
.final-price{font-size:17px;font-weight:800;color:var(--navy)}
.final-price small{font-size:11.5px;color:var(--muted);font-weight:500}
.no-price{color:var(--burgundy);font-size:13px;font-weight:700}
.btn-add-to-cart{width:100%;margin-top:14px;padding:11px;border-radius:3px;background:var(--navy);color:var(--gold-light);font-size:13px;font-weight:700;border:1px solid var(--navy);transition:all .25s}
.btn-add-to-cart:hover{background:var(--gold);border-color:var(--gold);color:#fff}
.btn-disabled{width:100%;margin-top:14px;padding:11px;border-radius:3px;background:#eceae4;color:#9a9486;font-size:13px;font-weight:700;cursor:not-allowed}

/* صفحه‌بندی */
.pagination{display:flex;justify-content:center;gap:6px;margin:40px 0 10px;flex-wrap:wrap}
.pagination a,.pagination span{min-width:40px;text-align:center;padding:8px 14px;border-radius:3px;background:var(--paper);border:1px solid var(--line);color:var(--navy);font-size:13.5px;font-weight:600}
.pagination a:hover{border-color:var(--gold);color:var(--gold)}
.pagination .active{background:var(--navy);color:var(--gold-light);border-color:var(--navy)}

.no-products{background:var(--paper);border:1px solid var(--line);padding:70px 20px;text-align:center;border-radius:6px;color:var(--muted)}
.no-products h2{color:var(--navy);margin-bottom:8px}
.no-products a{display:inline-block;margin-top:16px;padding:10px 28px;border:1.5px solid var(--navy);color:var(--navy);font-weight:700;border-radius:3px}
.no-products a:hover{background:var(--navy);color:var(--gold-light)}

/* فوتر */
.site-footer{background:var(--navy-deep);color:#97a2b6;margin-top:70px;border-top:4px solid var(--gold);text-align:center;padding:30px 0;font-size:13px}
.site-footer .links{display:flex;justify-content:center;gap:24px;margin-bottom:12px;flex-wrap:wrap}
.site-footer .links a:hover{color:var(--gold-light)}

/* Toast */
.toast{position:fixed;bottom:30px;left:50%;transform:translateX(-50%) translateY(100px);padding:13px 28px;border-radius:4px;font-size:14px;font-weight:600;color:#fff;box-shadow:0 14px 30px -8px rgba(0,0,0,.35);z-index:9999;opacity:0;transition:all .4s;border-bottom:3px solid var(--gold)}
.toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.toast.success{background:var(--navy)}
.toast.error{background:var(--burgundy)}

@media(max-width:900px){
    .layout{grid-template-columns:1fr}
    .sidebar{position:static}
    .cat-list{display:flex;gap:6px;overflow-x:auto;scrollbar-width:none;padding-bottom:4px}
    .cat-list li{margin:0;flex-shrink:0}
    .cat-list a{border:1px solid var(--line);border-radius:20px;white-space:nowrap;gap:8px}
}
@media(max-width:768px){
    .container{padding:0 16px}
    .top-bar .container{justify-content:center}
    .header-inner{flex-wrap:wrap;gap:12px}
    .search-box{order:3;max-width:100%;width:100%}
    .archive-header{padding:22px 20px}
    .archive-header h1{font-size:21px}
    .products-grid{grid-template-columns:repeat(2,1fr);gap:12px}
    .product-image{padding:12px}
    .product-image img{height:125px}
    .product-info{padding:12px}
    .product-title{font-size:12.5px;height:46px}
    .final-price{font-size:14.5px}
}
@media(max-width:400px){.products-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

    <div class="breadcrumb">
        <a href="index.php">خانه</a><span class="sep">‹</span>
        <?php if ($category): ?>
            <span><?= htmlspecialchars($category['name']) ?></span>
        <?php else: ?>
            <span>همه محصولات</span>
        <?php endif; ?>
    </div>

    <div class="archive-header">
        <h1>
            <?php if ($category): ?>
                <em>◆</em> <?= htmlspecialchars($category['name']) ?>
            <?php else: ?>
                <em>◆</em> همه محصولات
            <?php endif; ?>
        </h1>
        <div class="count"><?= ms_money($totalProducts) ?> محصول یافت شد</div>
    </div>

    <div class="layout">

        <aside class="sidebar">
            <h3>دسته‌بندی‌ها</h3>
            <ul class="cat-list">
                <li>
                    <a href="archive.php" class="<?= !$category ? 'active' : '' ?>"><span>همه محصولات</span></a>
                </li>
                <?php foreach ($allCategories as $c): ?>
                <li>
                    <a href="archive.php?cat=<?= (int)$c['id'] ?>"
                       class="<?= ($category && $category['id'] == $c['id']) ? 'active' : '' ?>">
                        <span><?= htmlspecialchars($c['name']) ?></span>
                        <span class="badge"><?= ms_fa_digits($c['product_count']) ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </aside>

        <main>
            <?php if (empty($products)): ?>
                <div class="no-products">
                    <h2>محصولی یافت نشد</h2>
                    <p>در این دسته‌بندی هنوز محصولی اضافه نشده است.</p>
                    <a href="index.php">بازگشت به صفحه اصلی</a>
                </div>
            <?php else: ?>

                <div class="products-grid">
                <?php foreach ($products as $p):
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
                            <img src="<?= htmlspecialchars($p['main_image'] ?: 'icon/placeholder/no-image.png') ?>"
                                 alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy"
                                 onerror="this.onerror=null;this.src='icon/placeholder/no-image.png'">
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
                <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?cat=<?= $categoryId ?>&sort=<?= $sort ?>&page=<?= $page - 1 ?>">‹ قبلی</a>
                    <?php endif; ?>
                    <?php
                    $start = max(1, $page - 3);
                    $end = min($totalPages, $page + 3);
                    for ($i = $start; $i <= $end; $i++):
                        if ($i == $page): ?>
                            <span class="active"><?= ms_fa_digits($i) ?></span>
                        <?php else: ?>
                            <a href="?cat=<?= $categoryId ?>&sort=<?= $sort ?>&page=<?= $i ?>"><?= ms_fa_digits($i) ?></a>
                        <?php endif;
                    endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?cat=<?= $categoryId ?>&sort=<?= $sort ?>&page=<?= $page + 1 ?>">بعدی ›</a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>
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
