<?php
/**
 * صفحه جستجوی محصولات
 * URL: search.php?q=کلمه
 */
require_once 'config/database.php';

// ============================================================
// ۱. گرفتن پارامتر جستجو
// ============================================================
$query = trim($_GET['q'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 24;
$offset = ($page - 1) * $perPage;

// ============================================================
// ۲. جستجو در دیتابیس
// ============================================================
$products = [];
$totalProducts = 0;

if (!empty($query)) {
    // آماده‌سازی عبارت جستجو (LIKE)
    $searchTerm = '%' . $query . '%';
    
    // شمارش کل
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM products 
        WHERE status = 1 
          AND (name LIKE ? OR name_en LIKE ? OR brand LIKE ?)
    ");
    $countStmt->execute([$searchTerm, $searchTerm, $searchTerm]);
    $totalProducts = $countStmt->fetchColumn();
    
    // واکشی محصولات
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE status = 1 
          AND (name LIKE ? OR name_en LIKE ? OR brand LIKE ?)
        ORDER BY 
            CASE WHEN stock_status = 'in_stock' THEN 0 ELSE 1 END,
            CASE WHEN name LIKE ? THEN 0 ELSE 1 END,
            id DESC
        LIMIT $perPage OFFSET $offset
    ");
    $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $query . '%']);
    $products = $stmt->fetchAll();
}

$totalPages = ceil($totalProducts / $perPage);
$cartCount = array_sum($_SESSION['cart'] ?? []);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جستجو: <?= htmlspecialchars($query) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        * { box-sizing: border-box; }
        body { font-family: tahoma, sans-serif; background: #f8fafc; margin: 0; padding: 0; }
        a { text-decoration: none; }
        .container { max-width: 1300px; margin: 0 auto; padding: 0 20px; }

        /* ============ هدر ============ */
        .site-header {
            background: #fff;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            gap: 20px;
        }
        .logo {
            font-size: 22px;
            font-weight: bold;
            color: #2563eb;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .logo span { font-size: 28px; }
        .search-box {
            flex: 1;
            max-width: 500px;
            display: flex;
            background: #f3f4f6;
            border-radius: 12px;
            overflow: hidden;
        }
        .search-box input {
            flex: 1;
            border: none;
            background: transparent;
            padding: 12px 16px;
            font-family: tahoma, sans-serif;
            font-size: 14px;
            outline: none;
        }
        .search-box button {
            background: #2563eb;
            color: #fff;
            border: none;
            padding: 0 22px;
            cursor: pointer;
            font-size: 16px;
        }
        .header-actions { display: flex; align-items: center; gap: 15px; }
        .header-actions a {
            color: #374151;
            font-size: 14px;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 8px;
        }
        .header-actions a:hover { background: #eff6ff; color: #2563eb; }
        .cart-badge {
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            padding: 2px 7px;
            border-radius: 10px;
            font-weight: bold;
        }

        /* ============ محتوا ============ */
        .breadcrumb {
            color: #6b7280;
            font-size: 14px;
            margin: 25px 0 20px;
        }
        .breadcrumb a { color: #2563eb; }

        .search-header {
            background: #fff;
            padding: 25px 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .search-header h1 {
            margin: 0 0 10px 0;
            font-size: 22px;
            color: #111;
        }
        .search-header h1 .query {
            color: #2563eb;
        }
        .search-header .count {
            color: #6b7280;
            font-size: 14px;
        }

        /* ============ گرید محصولات ============ */
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
            gap: 18px;
        }

        .product-card {
            background: #fff;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.25s;
            position: relative;
            display: flex;
            flex-direction: column;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 24px rgba(0,0,0,0.12);
        }
        .product-card.out-of-stock { opacity: 0.8; }
        .product-card.out-of-stock .product-image img { filter: grayscale(40%); }

        .badge-unavailable {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(135deg, #6b7280, #4b5563);
            color: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            z-index: 10;
        }
        .badge-discount {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #ef4444;
            color: #fff;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            z-index: 10;
        }

        .product-image {
            display: block;
            background: #f9fafb;
            padding: 15px;
            text-align: center;
        }
        .product-image img {
            width: 100%;
            height: 180px;
            object-fit: contain;
            display: block;
        }

        .product-info { padding: 15px; flex: 1; display: flex; flex-direction: column; }
        .product-title {
            font-size: 13px;
            line-height: 1.6;
            color: #111;
            margin: 0 0 12px 0;
            height: 62px;
            overflow: hidden;
            font-weight: normal;
        }
        .product-title a { color: #111; }
        .product-title a:hover { color: #2563eb; }

        .product-price { margin-top: auto; }
        .old-price {
            color: #9ca3af;
            text-decoration: line-through;
            font-size: 12px;
        }
        .final-price {
            display: block;
            font-size: 17px;
            font-weight: bold;
            color: #16a34a;
            margin-top: 4px;
        }
        .final-price small { font-size: 11px; color: #6b7280; font-weight: normal; }
        .no-price { color: #d97706; font-size: 13px; font-weight: bold; }

        .btn-add-to-cart {
            display: block;
            width: 100%;
            background: #16a34a;
            color: #fff;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            margin-top: 12px;
            font-family: tahoma, sans-serif;
        }
        .btn-add-to-cart:hover { background: #15803d; }
        .btn-disabled {
            display: block;
            width: 100%;
            background: #e5e7eb;
            color: #9ca3af;
            border: none;
            padding: 10px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: bold;
            margin-top: 12px;
            cursor: not-allowed;
            font-family: tahoma, sans-serif;
        }

        /* ============ پیام خالی ============ */
        .empty-state {
            background: #fff;
            padding: 80px 20px;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .empty-state .icon { font-size: 64px; margin-bottom: 20px; }
        .empty-state h2 { color: #374151; margin: 0 0 10px 0; }
        .empty-state p { color: #6b7280; margin-bottom: 25px; }
        .empty-state .suggestions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        .empty-state .suggestions a {
            background: #eff6ff;
            color: #2563eb;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: bold;
        }
        .empty-state .suggestions a:hover { background: #2563eb; color: #fff; }

        /* ============ صفحه‌بندی ============ */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 6px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            background: #fff;
            color: #374151;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        }
        .pagination a:hover { background: #eff6ff; color: #2563eb; }
        .pagination .active { background: #2563eb; color: #fff; }

        @media (max-width: 768px) {
            .header-inner { flex-wrap: wrap; }
            .search-box { order: 3; max-width: 100%; width: 100%; }
            .products-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
            .product-image img { height: 130px; }
        }
    </style>
</head>
<body>

<?php require __DIR__ . '/includes/site_header.php'; ?>

<div class="container">

    <div class="breadcrumb">
        <a href="index.php">🏠 خانه</a> › <span>جستجو</span>
    </div>

    <!-- ============ هدر نتایج ============ -->
    <div class="search-header">
        <?php if (!empty($query)): ?>
            <h1>🔍 نتایج جستجو برای: <span class="query">«<?= htmlspecialchars($query) ?>»</span></h1>
            <div class="count">
                <?php if ($totalProducts > 0): ?>
                    ✅ <?= number_format($totalProducts) ?> محصول یافت شد
                <?php else: ?>
                    ❌ محصولی یافت نشد
                <?php endif; ?>
            </div>
        <?php else: ?>
            <h1>🔍 جستجو در محصولات</h1>
            <div class="count">عبارت مورد نظر خود را در کادر بالا وارد کنید</div>
        <?php endif; ?>
    </div>

    <!-- ============ نتایج ============ -->
    <?php if (empty($query)): ?>

        <div class="empty-state">
            <div class="icon">🔍</div>
            <h2>چه چیزی را جستجو می‌کنید؟</h2>
            <p>نام محصول، برند یا مدل را در کادر بالا وارد کنید</p>
            <div class="suggestions">
                <a href="search.php?q=گوشی">📱 گوشی</a>
                <a href="search.php?q=سامسونگ">سامسونگ</a>
                <a href="search.php?q=شیائومی">شیائومی</a>
                <a href="search.php?q=اپل">اپل</a>
                <a href="search.php?q=لپ‌تاپ">💻 لپ‌تاپ</a>
            </div>
        </div>

    <?php elseif (empty($products)): ?>

        <div class="empty-state">
            <div class="icon">😔</div>
            <h2>نتیجه‌ای یافت نشد</h2>
            <p>برای «<?= htmlspecialchars($query) ?>» محصولی پیدا نکردیم.</p>
            <p style="font-size:13px;">می‌توانید با کلمات دیگری امتحان کنید:</p>
            <div class="suggestions">
                <a href="search.php?q=گوشی">📱 گوشی</a>
                <a href="search.php?q=سامسونگ">سامسونگ</a>
                <a href="search.php?q=شیائومی">شیائومی</a>
                <a href="search.php?q=اپل">اپل</a>
                <a href="search.php?q=هدفون">🎧 هدفون</a>
            </div>
        </div>

    <?php else: ?>

        <div class="products-grid">
            <?php foreach ($products as $p): 
                $isAvailable = ($p['stock_status'] === 'in_stock' && $p['stock'] > 0 && $p['price'] > 0);
            ?>
                <div class="product-card <?= !$isAvailable ? 'out-of-stock' : '' ?>">
                    <?php if (!$isAvailable): ?>
                        <span class="badge-unavailable">⛔ ناموجود</span>
                    <?php elseif ($p['sale_price'] && $p['sale_price'] < $p['price']): ?>
                        <span class="badge-discount"><?= $p['discount_percent'] ?>%</span>
                    <?php endif; ?>

                    <a href="product.php?id=<?= $p['id'] ?>" class="product-image">
                        <img src="<?= htmlspecialchars($p['main_image'] ?: 'assets/no-image.png') ?>" 
                             alt="<?= htmlspecialchars($p['name']) ?>"
                             loading="lazy"
                             onerror="this.src='assets/no-image.png'">
                    </a>

                    <div class="product-info">
                        <h3 class="product-title">
                            <a href="product.php?id=<?= $p['id'] ?>">
                                <?= htmlspecialchars(mb_substr($p['name'], 0, 70)) ?>
                            </a>
                        </h3>

                        <div class="product-price">
                            <?php if ($p['price'] > 0): ?>
                                <?php if ($p['sale_price'] && $p['sale_price'] < $p['price']): ?>
                                    <del class="old-price"><?= number_format($p['price']) ?></del>
                                    <span class="final-price">
                                        <?= number_format($p['sale_price']) ?>
                                        <small>تومان</small>
                                    </span>
                                <?php else: ?>
                                    <span class="final-price">
                                        <?= number_format($p['price']) ?>
                                        <small>تومان</small>
                                    </span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="no-price">تماس بگیرید</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($isAvailable): ?>
                            <button class="btn-add-to-cart" onclick="addToCart(<?= $p['id'] ?>, event)">
                                🛒 افزودن به سبد
                            </button>
                        <?php else: ?>
                            <button class="btn-disabled" disabled>⛔ ناموجود</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- صفحه‌بندی -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?q=<?= urlencode($query) ?>&page=<?= $page - 1 ?>">‹ قبلی</a>
                <?php endif; ?>

                <?php 
                $start = max(1, $page - 3);
                $end = min($totalPages, $page + 3);
                for ($i = $start; $i <= $end; $i++): 
                ?>
                    <?php if ($i == $page): ?>
                        <span class="active"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?q=<?= urlencode($query) ?>&page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?q=<?= urlencode($query) ?>&page=<?= $page + 1 ?>">بعدی ›</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

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
            const badge = document.querySelector('.cart-badge');
            if (data.cart_count > 0) {
                if (badge) {
                    badge.textContent = data.cart_count;
                } else {
                    const cartLink = document.querySelector('a[href="cart.php"]');
                    if (cartLink) {
                        const span = document.createElement('span');
                        span.className = 'cart-badge';
                        span.textContent = data.cart_count;
                        cartLink.appendChild(span);
                    }
                }
            }
            showToast('✅ ' + data.message);
        } else {
            showToast('❌ ' + data.message, true);
        }
    })
    .catch(err => showToast('خطا در ارتباط با سرور', true));
}

function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        bottom: 30px;
        left: 50%;
        transform: translateX(-50%);
        background: ${isError ? '#dc2626' : '#16a34a'};
        color: #fff;
        padding: 14px 28px;
        border-radius: 12px;
        font-family: tahoma;
        font-size: 14px;
        font-weight: bold;
        box-shadow: 0 8px 24px rgba(0,0,0,0.2);
        z-index: 9999;
    `;
    document.body.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}
</script>

<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body>
</html>