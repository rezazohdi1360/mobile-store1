<?php
/**
 * افزودن محصول به سبد خرید (AJAX)
 * POST / GET: id
 */
require_once 'config/database.php';
header('Content-Type: application/json; charset=utf-8');

$productId = (int)($_POST['id'] ?? $_GET['id'] ?? 0);

// --- اعتبارسنجی ---
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'شناسه محصول نامعتبر است']);
    exit;
}

// --- واکشی محصول ---
$stmt = $pdo->prepare("
    SELECT id, name, price, sale_price, stock, stock_status 
    FROM products 
    WHERE id = ? AND status = 1 
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'محصول یافت نشد']);
    exit;
}

// --- چک موجودی ---
if ($product['stock_status'] !== 'in_stock' || $product['stock'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'این محصول فعلاً ناموجود است']);
    exit;
}

if ($product['price'] <= 0) {
    echo json_encode(['success' => false, 'message' => 'این محصول قیمت معتبر ندارد']);
    exit;
}

// --- افزودن به سبد ---
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$currentQty = $_SESSION['cart'][$productId] ?? 0;

// چک سقف موجودی
if ($currentQty >= $product['stock']) {
    echo json_encode([
        'success' => false, 
        'message' => 'حداکثر موجودی این محصول در سبد شماست'
    ]);
    exit;
}

$_SESSION['cart'][$productId] = $currentQty + 1;

// --- شمارش کل آیتم‌های سبد ---
$cartCount = array_sum($_SESSION['cart']);

echo json_encode([
    'success' => true,
    'message' => 'به سبد خرید اضافه شد',
    'cart_count' => $cartCount,
    'product_id' => $productId,
    'quantity' => $_SESSION['cart'][$productId]
]);