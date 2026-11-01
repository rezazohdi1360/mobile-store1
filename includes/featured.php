<?php
/** محصولات منتخب (پیشنهاد ویژه صفحه اصلی) */
function featured_ensure(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('featured', $cols)) $pdo->exec("ALTER TABLE products ADD COLUMN featured TINYINT NOT NULL DEFAULT 0");
    } catch (Exception $e) { /* بی‌صدا */ }
}
