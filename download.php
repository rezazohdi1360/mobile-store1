<?php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/downloads.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$code = trim((string)($_GET['code'] ?? ''));
$item = (int)($_GET['item'] ?? 0);
$fail = function ($msg, $c = 403) { http_response_code($c); header('Content-Type: text/plain; charset=utf-8'); echo $msg; exit; };

$st = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1");
$st->execute([$code]);
$order = $st->fetch();
if (!$order) $fail('سفارش پیدا نشد', 404);
$ok = isAdmin()
    || (($_SESSION['last_order_code'] ?? '') === $order['order_code'])
    || (!empty($_SESSION['user_id']) && (int)$order['user_id'] === (int)$_SESSION['user_id']);
if (!$ok) $fail('دسترسی ندارید');
if (($order['payment_status'] ?? '') !== 'paid' && !isAdmin()) $fail('دانلود فایل فقط بعد از پرداخت موفق امکان‌پذیر است');

foreach (dl_order_files($pdo, $order['id']) as $f) {
    if ((int)$f['item_id'] !== $item) continue;
    $path = dl_path($f['download_file']);
    if (!$path) break;
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/octet-stream');
    header("Content-Disposition: attachment; filename=\"" . rawurlencode($f['download_file']) . "\"; filename*=UTF-8''" . rawurlencode($f['download_file']));
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    readfile($path);
    exit;
}
$fail('فایلی برای این قلم پیدا نشد', 404);
