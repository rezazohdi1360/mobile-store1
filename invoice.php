<?php
/** دانلود فاکتور PDF سفارش: invoice.php?code=ORD-...   (فقط صاحب سفارش / آخرین سفارش همین نشست / مدیر) */
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/invoice.php';

$code = trim((string)($_GET['code'] ?? ($_SESSION['last_order_code'] ?? '')));
$st = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
$st->execute([$code]);
$order = $st->fetch();
$allowed = $order && (isAdmin()
    || (($_SESSION['last_order_code'] ?? '') === $order['order_code'])
    || (!empty($_SESSION['user_id']) && (int)$order['user_id'] === (int)$_SESSION['user_id']));
if (!$allowed) { http_response_code(403); header('Content-Type: text/plain; charset=utf-8'); die('دسترسی به این فاکتور مجاز نیست.'); }

$it = $pdo->prepare("SELECT oi.*, COALESCE(NULLIF(oi.product_image, ''), p.main_image) AS product_image
                     FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id");
$it->execute([$order['id']]);
$items = $it->fetchAll();

try {
    $pdf = invoice_build_pdf($order, $items);
    while (ob_get_level() > 0) ob_end_clean();
    $pdf->Output('invoice-' . preg_replace('/[^A-Za-z0-9_-]/', '', $order['order_code']) . '.pdf', isset($_GET['inline']) ? 'I' : 'D');
} catch (Throwable $e) {
    error_log('invoice: ' . $e->getMessage());
    http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); echo 'خطا در ساخت فاکتور: ' . $e->getMessage();
}
