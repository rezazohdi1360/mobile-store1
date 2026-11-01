<?php
/** دانلود گزارش PDF به‌روزرسانی قیمت: price_report.php?run=ID (پیش‌فرض: آخرین اجرا) */
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/price_report.php';
if (!isAdmin()) { http_response_code(403); exit('دسترسی ندارید'); }
pr_ensure($pdo);
$run = (int)($_GET['run'] ?? 0);
if (!$run) $run = (int)$pdo->query("SELECT MAX(id) FROM price_runs")->fetchColumn();
try {
    $pdf = pr_build_pdf($pdo, $run);
    while (ob_get_level() > 0) ob_end_clean();
    $pdf->Output('price-report-' . $run . '.pdf', isset($_GET['inline']) ? 'I' : 'D');
} catch (Throwable $e) { http_response_code(500); header('Content-Type: text/plain; charset=utf-8'); echo 'خطا در ساخت گزارش: ' . $e->getMessage(); }
