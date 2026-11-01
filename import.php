<?php
/**
 * ایمپورت خودکار محصولات از پوشه‌ها
 *
 *   products/            ← فایل JSON هر محصول
 *   images/«نام محصول»/  ← 01.jpg (عکس اصلی) تا 05.jpg
 *
 * اجرا:  http://localhost/mobile-store/import.php            ← افزودن محصولات جدید + به‌روزرسانی موجودها
 *        http://localhost/mobile-store/import.php?mode=new     ← فقط محصولات جدید
 *        http://localhost/mobile-store/import.php?mode=images  ← فقط به‌روزرسانی عکس‌ها
 * (با خط فرمان هم اجرا می‌شود:  php import.php)
 */
set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/product_files.php';

$cli = (PHP_SAPI === 'cli');
if (!$cli && !isAdmin()) {
    header('Content-Type: text/html; charset=utf-8');
    die("<div dir='rtl' style='font-family:tahoma;padding:30px'>🔒 برای اجرای ایمپورت ابتدا به‌عنوان مدیر وارد شوید: <a href='admin/login.php'>ورود به پنل مدیریت</a></div>");
}

$mode    = $_GET['mode'] ?? ($argv[1] ?? 'all');          // all | new | images
$logFile = __DIR__ . '/import_log.txt';

function logMsg($msg) {
    global $logFile, $cli;
    file_put_contents($logFile, date('[H:i:s] ') . strip_tags($msg) . "\n", FILE_APPEND);
    echo $cli ? strip_tags(str_replace('<br>', "\n", $msg)) . "\n" : $msg . "<br>";
    @flush();
}

function imp_slug($name, $key) {
    $s = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $name);
    $s = trim(preg_replace('/\s+/', '-', $s), '-');
    $s = mb_substr($s, 0, 80);
    return ($s ?: 'product') . '-' . (preg_replace('/[^a-z0-9]/i', '', $key) ?: substr(md5($key), 0, 6));
}

function imp_category($path, PDO $pdo) {
    $main = trim(preg_split('/\s*[<>|]\s*/u', $path)[0] ?? '');
    if ($main === '') return null;
    $st = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $st->execute([$main]);
    if ($id = $st->fetchColumn()) return (int)$id;
    $slug = preg_replace('/\s+/', '-', preg_replace('/[^\p{L}\p{N}\s-]/u', '', $main)) ?: 'cat-' . uniqid();
    try {
        $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$main, $slug]);
    } catch (Exception $e) {   // slug تکراری
        $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$main, $slug . '-' . uniqid()]);
    }
    return (int)$pdo->lastInsertId();
}

if (!$cli) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>ایمپورت محصولات</title>
    <style>body{font-family:tahoma;direction:rtl;background:#111;color:#0f0;padding:20px;line-height:1.9}a{color:#0ff}strong{color:#ff0}</style></head><body>";
}

logMsg("🚀 شروع ایمپورت خودکار (حالت: $mode)...");

// ───── خواندن محصولات: اول پوشه products، در نبود آن products.json قدیمی ─────
$errs = [];
$items = pf_load_products($errs);
foreach ($errs as $e) logMsg("⚠ $e");
if (!$items && is_file(__DIR__ . '/products.json')) {
    logMsg("ℹ پوشه products خالی است؛ از فایل products.json استفاده می‌شود.");
    $items = pf_parse_json((string)file_get_contents(__DIR__ . '/products.json')) ?: [];
    foreach ($items as &$x) { $x['_images'] = []; $x['_dir'] = null; } unset($x);
}
if (!$items) die(($cli ? '' : '') . "❌ هیچ محصولی پیدا نشد. فایل‌های JSON را در پوشه products بگذارید.");

$total = count($items);
logMsg("✅ تعداد محصولات: <strong>$total</strong>");

$inserted = $updated = $skipped = $errors = $noImg = 0;
$start = microtime(true);

$find = $pdo->prepare("SELECT id FROM products WHERE external_id = ? LIMIT 1");
$ins = $pdo->prepare("
    INSERT INTO products (name, name_en, slug, external_id, brand, category_id, category_path, price, sale_price, discount_percent,
        stock, stock_status, rating, review_count, warranty, seller, source_url, main_image, all_images, description, short_desc, status)
    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)");
$upd = $pdo->prepare("
    UPDATE products SET name=?, name_en=?, brand=?, category_id=?, category_path=?, price=?, sale_price=?, discount_percent=?,
        stock_status=?, rating=?, review_count=?, warranty=?, seller=?, source_url=?, main_image=?, all_images=?,
        description=?, short_desc=?, updated_at=NOW() WHERE id=?");
$updImg = $pdo->prepare("UPDATE products SET main_image=?, all_images=?, updated_at=NOW() WHERE id=?");
$delSpec = $pdo->prepare("DELETE FROM product_specs WHERE product_id = ?");
$insSpec = $pdo->prepare("INSERT INTO product_specs (product_id, group_name, spec_key, spec_value, sort_order) VALUES (?,?,?,?,?)");

$pdo->beginTransaction();
foreach ($items as $i => $x) {
    try {
        $key = pf_key($x);
        $r = pf_row($x);
        if ($r['name'] === '') { $skipped++; continue; }
        if (!$x['_images']) { $noImg++; logMsg("🖼 بدون پوشه عکس: " . htmlspecialchars($r['name'])); }

        $find->execute([$key]);
        $pid = (int)$find->fetchColumn();

        if ($pid) {
            if ($mode === 'new') continue;
            if ($mode === 'images') {
                if ($x['_images']) { $updImg->execute([$r['img'], $r['all'], $pid]); $updated++; }
                continue;
            }
            $upd->execute([$r['name'], $r['name_en'], $r['brand'], imp_category($r['path'], $pdo), $r['path'], $r['price'], $r['sale'], $r['pct'],
                $r['avail'] ? 'in_stock' : 'out_of_stock', $r['rating'], $r['reviews'], $r['warranty'], $r['seller'], $r['url'],
                $r['img'], $r['all'], $r['desc'], $r['short'], $pid]);
            $updated++;
        } else {
            if ($mode === 'images') continue;
            $ins->execute([$r['name'], $r['name_en'], imp_slug($r['name'], $key), $key, $r['brand'], imp_category($r['path'], $pdo), $r['path'],
                $r['price'], $r['sale'], $r['pct'], $r['avail'] ? 10 : 0, $r['avail'] ? 'in_stock' : 'out_of_stock',
                $r['rating'], $r['reviews'], $r['warranty'], $r['seller'], $r['url'], $r['img'], $r['all'], $r['desc'], $r['short']]);
            $pid = (int)$pdo->lastInsertId();
            $inserted++;
        }

        if ($r['specs'] && $mode !== 'images') {
            $delSpec->execute([$pid]);
            foreach ($r['specs'] as $n => [$sk, $sv]) $insSpec->execute([$pid, 'مشخصات فنی', $sk, $sv, $n + 1]);
        }

        if (($inserted + $updated) % 50 === 0 && ($inserted + $updated) > 0) {
            $pdo->commit(); $pdo->beginTransaction();
            logMsg("📦 پیشرفت: <strong>" . ($inserted + $updated) . "</strong> / $total");
        }
    } catch (Exception $e) {
        $errors++;
        if ($errors <= 10) logMsg("❌ خطا در #$i: " . htmlspecialchars($e->getMessage()));
    }
}
$pdo->commit();

logMsg("");
logMsg("✅ ایمپورت کامل شد!");
logMsg("📥 جدید: <strong>$inserted</strong> | 🔄 به‌روزرسانی: <strong>$updated</strong> | 🖼 بدون عکس: <strong>$noImg</strong> | ⏭ نادیده: <strong>$skipped</strong> | ❌ خطا: <strong>$errors</strong>");
logMsg("⏱ زمان: <strong>" . round(microtime(true) - $start, 1) . " ثانیه</strong>");
logMsg("<br>🔗 <a href='index.php'>مشاهده سایت</a> | <a href='admin/products.php'>مدیریت محصولات</a> | <a href='admin/'>پنل مدیریت</a>");
if (!$cli) echo "</body></html>";
