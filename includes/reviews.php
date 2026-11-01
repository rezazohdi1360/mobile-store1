<?php
/** نظرات کاربران روی محصولات — ثبت توسط کاربر (در انتظار) → بررسی و ثبت نهایی توسط مدیر */

function reviews_ensure(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS product_reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            user_id INT NULL,
            author_name VARCHAR(100) NOT NULL,
            rating TINYINT NOT NULL DEFAULT 5,
            body TEXT NOT NULL,
            status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME NULL,
            KEY idx_prod_status (product_id, status),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci");
    } catch (Throwable $e) { error_log('reviews_ensure: ' . $e->getMessage()); }
}

/** ثبت نظر جدید (وضعیت: در انتظار تأیید مدیر). @return string|null پیام خطا یا null در صورت موفقیت */
function review_submit(PDO $pdo, $productId, array $in) {
    if (trim((string)($in['website'] ?? '')) !== '') return null;                // honeypot: ربات
    if (time() - (int)($_SESSION['last_review_at'] ?? 0) < 30) return 'لطفاً چند لحظه بعد دوباره تلاش کنید.';
    $name = trim(preg_replace('/\s+/u', ' ', (string)($in['name'] ?? '')));
    $body = trim((string)($in['body'] ?? ''));
    $rating = (int)($in['rating'] ?? 0);
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['name'])) $name = (string)$_SESSION['name'];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) return 'نام باید بین ۲ تا ۶۰ حرف باشد.';
    if ($rating < 1 || $rating > 5) return 'امتیاز را (۱ تا ۵ ستاره) انتخاب کنید.';
    if (mb_strlen($body) < 5) return 'متن نظر حداقل ۵ حرف باشد.';
    if (mb_strlen($body) > 2000) return 'متن نظر حداکثر ۲۰۰۰ حرف باشد.';
    reviews_ensure($pdo);
    $pdo->prepare("INSERT INTO product_reviews (product_id, user_id, author_name, rating, body, status) VALUES (?,?,?,?,?, 'pending')")
        ->execute([(int)$productId, $_SESSION['user_id'] ?? null, $name, $rating, $body]);
    $_SESSION['last_review_at'] = time();
    return null;
}

function reviews_pending_count(PDO $pdo) {
    reviews_ensure($pdo);
    try { return (int)$pdo->query("SELECT COUNT(*) FROM product_reviews WHERE status = 'pending'")->fetchColumn(); }
    catch (Throwable $e) { return 0; }
}
