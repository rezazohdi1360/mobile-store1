<?php
$pageTitle = 'محصولات';
require_once 'includes/header.php';

$search = sanitize($_GET['q'] ?? '');
$brand = sanitize($_GET['brand'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$sql = "SELECT * FROM products WHERE status=1";
$params = [];

if ($search) { $sql .= " AND name LIKE ?"; $params[] = "%$search%"; }
if ($brand) { $sql .= " AND brand=?"; $params[] = $brand; }

switch ($sort) {
    case 'cheap': $sql .= " ORDER BY COALESCE(sale_price, price) ASC"; break;
    case 'expensive': $sql .= " ORDER BY COALESCE(sale_price, price) DESC"; break;
    case 'popular': $sql .= " ORDER BY sold DESC"; break;
    default: $sql .= " ORDER BY id DESC";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$brands = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' AND status=1 ORDER BY brand LIMIT 20")->fetchAll();
?>

<div class="max-w-7xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-black text-slate-900 mb-6 flex items-center gap-2">
        <span class="w-2 h-7 bg-blue-600 rounded-full"></span>
        محصولات
    </h1>

    <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
        
        <!-- Sidebar -->
        <aside class="lg:col-span-1">
            <div class="bg-white rounded-2xl border border-slate-200 p-5 sticky top-32">
                <form method="get" class="mb-5">
                    <div class="relative">
                        <input type="text" name="q" value="<?= sanitize($search) ?>" placeholder="جستجو..." 
                               class="w-full bg-slate-100 pr-3 pl-9 py-2.5 rounded-lg border text-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-3 text-slate-400"></i>
                    </div>
                </form>
                
                <?php if (!empty($brands)): ?>
                <div>
                    <h3 class="font-bold text-sm text-slate-900 mb-3">
                        <i class="fa-solid fa-award text-blue-600"></i> برندها
                    </h3>
                    <div class="flex flex-wrap gap-1.5">
                        <a href="products.php" class="text-xs px-2.5 py-1 rounded-lg border <?= !$brand ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">همه</a>
                        <?php foreach ($brands as $b): ?>
                            <a href="?brand=<?= urlencode($b['brand']) ?>" class="text-xs px-2.5 py-1 rounded-lg border <?= $brand === $b['brand'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-slate-50 text-slate-600 border-slate-200' ?>">
                                <?= sanitize($b['brand']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </aside>

        <!-- Products -->
        <div class="lg:col-span-3">
            <div class="bg-white rounded-2xl border p-4 mb-5 flex justify-between items-center">
                <div class="text-sm text-slate-600">
                    <strong class="text-slate-900"><?= faNumber(count($products)) ?></strong> محصول
                </div>
                <form method="get" class="flex items-center gap-2">
                    <?php if ($brand): ?><input type="hidden" name="brand" value="<?= sanitize($brand) ?>"><?php endif; ?>
                    <?php if ($search): ?><input type="hidden" name="q" value="<?= sanitize($search) ?>"><?php endif; ?>
                    <label class="text-xs text-slate-500">مرتب‌سازی:</label>
                    <select name="sort" onchange="this.form.submit()" class="bg-slate-50 border rounded-lg px-3 py-1.5 text-xs">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین</option>
                        <option value="cheap" <?= $sort === 'cheap' ? 'selected' : '' ?>>ارزان‌ترین</option>
                        <option value="expensive" <?= $sort === 'expensive' ? 'selected' : '' ?>>گران‌ترین</option>
                        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>محبوب‌ترین</option>
                    </select>
                </form>
            </div>
            
            <?php if (empty($products)): ?>
                <div class="bg-white rounded-2xl border p-16 text-center">
                    <i class="fa-solid fa-magnifying-glass text-5xl text-slate-300 mb-4"></i>
                    <h3 class="font-bold text-lg text-slate-700">محصولی یافت نشد</h3>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php foreach ($products as $p): 
                        $price = displayPrice($p);
                    ?>
                        <div class="bg-white border rounded-2xl p-4 flex flex-col hover:shadow-xl transition-all relative">
                            <?php if ($price['has_discount']): ?>
                                <span class="absolute top-3 right-3 bg-red-600 text-white font-bold text-[10px] px-2 py-1 rounded-full z-10">
                                    <?= $price['percent'] ?>٪
                                </span>
                            <?php endif; ?>
                            
                            <a href="product.php?id=<?= $p['id'] ?>" class="bg-slate-50 rounded-xl p-4 mb-3 text-center block">
                                <img src="<?= sanitize($p['main_image']) ?>" 
                                     class="w-full h-40 object-contain"
                                     onerror="this.src='https://placehold.co/400x400/f1f5f9/1e293b?text=Product'">
                            </a>
                            
                            <?php if (!empty($p['brand'])): ?>
                                <span class="text-[11px] text-slate-400"><?= sanitize($p['brand']) ?></span>
                            <?php endif; ?>
                            
                            <a href="product.php?id=<?= $p['id'] ?>">
                                <h3 class="font-bold text-sm text-slate-800 mt-1 mb-2 line-clamp-2 h-10">
                                    <?= sanitize(mb_substr($p['name'], 0, 70)) ?>
                                </h3>
                            </a>
                            
                            <div class="mt-auto pt-3 border-t flex items-center justify-between">
                                <div>
                                    <?php if ($price['has_discount']): ?>
                                        <span class="text-xs text-slate-400 line-through block"><?= faNumber($price['original']) ?></span>
                                    <?php endif; ?>
                                    <span class="text-sm font-extrabold text-blue-600">
                                        <?= faNumber($price['final']) ?> <span class="text-[10px] font-normal text-slate-500">تومان</span>
                                    </span>
                                </div>
                                <a href="cart.php?add=<?= $p['id'] ?>" class="text-blue-600 hover:bg-blue-50 p-2 rounded-lg">
                                    <i class="fa-solid fa-cart-plus text-lg"></i>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>