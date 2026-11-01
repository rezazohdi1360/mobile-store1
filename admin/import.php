<?php
set_time_limit(0);
ini_set('memory_limit', '1024M');
error_reporting(E_ALL);

require_once 'config/database.php';

$jsonFile = __DIR__ . '/products.json';

function logMsg($msg) {
    echo $msg . "<br>";
    flush();
}

function faToEn($str) {
    if (!$str) return $str;
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, $str);
}

function cleanPrice($price) {
    if (empty($price)) return 0;
    return (int)preg_replace('/[^\d]/', '', faToEn($price));
}

function makeSlugImport($name, $extId = '') {
    $slug = preg_replace('/[^\p{L}\p{N}\s-]/u', '', $name);
    $slug = preg_replace('/\s+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = mb_substr($slug, 0, 80);
    if ($extId) $slug .= '-' . preg_replace('/[^a-z0-9]/i', '', $extId);
    return $slug ?: 'product-' . uniqid();
}

function getCategoryId($path, $pdo) {
    if (empty($path)) return null;
    $parts = preg_split('/\s*[<>|]\s*/u', $path);
    $mainCat = trim($parts[0] ?? '');
    if (empty($mainCat)) return null;

    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? LIMIT 1");
    $stmt->execute([$mainCat]);
    $cat = $stmt->fetch();
    if ($cat) return $cat['id'];

    try {
        $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
        $stmt->execute([$mainCat, makeSlugImport($mainCat)]);
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        return null;
    }
}

function extractSpecs($item) {
    $specs = [];
    foreach ($item as $key => $value) {
        if (mb_strpos($key, 'مشخصه:') === 0) {
            $specKey = trim(str_replace('مشخصه:', '', $key));
            if (!empty($value) && $value !== 'نامشخص') {
                $specs[] = ['group' => 'مشخصات فنی', 'key' => $specKey, 'value' => (string)$value];
            }
        }
    }
    return $specs;
}

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><style>body{font-family:tahoma;direction:rtl;background:#111;color:#0f0;padding:20px;line-height:1.8;}</style></head><body>";

logMsg("🚀 شروع ایمپورت...");

if (!file_exists($jsonFile)) die("❌ فایل JSON پیدا نشد");

$jsonContent = file_get_contents($jsonFile);
logMsg("📊 حجم: " . number_format(strlen($jsonContent) / 1024 / 1024, 2) . " مگابایت");

$data = json_decode($jsonContent, true);
$products = $data['products'] ?? $data;
$total = count($products);
logMsg("✅ تعداد: <strong>$total</strong><br>");

$inserted = 0; $updated = 0; $errors = 0; $skipped = 0;
$inStock = 0; $outOfStock = 0;
$startTime = microtime(true);

$pdo->beginTransaction();

foreach ($products as $i => $item) {
    try {
        $externalId = trim($item['شناسه'] ?? '');
        $name = trim($item['نام محصول'] ?? '');
        if (empty($name)) { $skipped++; continue; }

        $nameEn = trim($item['نام لاتین'] ?? '');
        $brand = trim($item['برند'] ?? '');
        $categoryPath = trim($item['دسته‌بندی'] ?? '');

        $priceWithDiscount = cleanPrice($item['قیمت (تومان)'] ?? '');
        $originalPrice = cleanPrice($item['قیمت قبل از تخفیف (تومان)'] ?? '');
        $discountPercent = (int)preg_replace('/[^\d]/', '', faToEn($item['درصد تخفیف'] ?? '0'));

        if ($originalPrice > 0 && $originalPrice > $priceWithDiscount && $priceWithDiscount > 0) {
            $finalPrice = $priceWithDiscount;
            $basePrice = $originalPrice;
        } else {
            $finalPrice = null;
            $basePrice = $priceWithDiscount ?: $originalPrice;
        }

        $stockStatusFromJson = trim($item['وضعیت موجودی'] ?? '');
        $isAvailable = ($basePrice > 0 && $stockStatusFromJson !== 'out_of_stock');

        if ($isAvailable) {
            $stockStatus = 'in_stock'; $stockQty = 10; $inStock++;
        } else {
            $basePrice = $basePrice ?: 0;
            $stockStatus = 'out_of_stock'; $stockQty = 0; $outOfStock++;
        }

        $rating = (float)faToEn($item['امتیاز'] ?? 0);
        $reviewCount = (int)preg_replace('/[^\d]/', '', faToEn($item['تعداد نظرات'] ?? 0));
        $warranty = trim($item['گارانتی'] ?? '');
        $seller = trim($item['فروشنده'] ?? '');
        $sourceUrl = trim($item['آدرس وب'] ?? '');
        $mainImage = trim($item['آدرس عکس اصلی'] ?? '');
        $allImages = trim($item['همه تصاویر'] ?? '');
        $description = trim($item['بررسی تخصصی'] ?? '');
        $shortDesc = mb_substr(strip_tags($description), 0, 490);

        $categoryId = getCategoryId($categoryPath, $pdo);

        $existing = null;
        if ($externalId) {
            $stmt = $pdo->prepare("SELECT id FROM products WHERE external_id = ? LIMIT 1");
            $stmt->execute([$externalId]);
            $existing = $stmt->fetch();
        }

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE products SET 
                    name=?, name_en=?, brand=?, category_id=?, category_path=?,
                    price=?, sale_price=?, discount_percent=?,
                    stock=?, stock_status=?,
                    rating=?, review_count=?, warranty=?, seller=?,
                    source_url=?, main_image=?, all_images=?,
                    description=?, short_desc=?, updated_at=NOW()
                WHERE id=?
            ");
            $stmt->execute([
                $name, $nameEn, $brand, $categoryId, $categoryPath,
                $basePrice, $finalPrice, $discountPercent,
                $stockQty, $stockStatus,
                $rating, $reviewCount, $warranty, $seller,
                $sourceUrl, $mainImage, $allImages,
                $description, $shortDesc, $existing['id']
            ]);
            $productId = $existing['id'];
            $updated++;
        } else {
            $slug = makeSlugImport($name, $externalId ?: uniqid());
            $checkSlug = $pdo->prepare("SELECT id FROM products WHERE slug = ? LIMIT 1");
            $checkSlug->execute([$slug]);
            if ($checkSlug->fetch()) $slug .= '-' . uniqid();

            $stmt = $pdo->prepare("
                INSERT INTO products (
                    name, name_en, slug, external_id, brand, category_id, category_path,
                    price, sale_price, discount_percent, stock, stock_status,
                    rating, review_count, warranty, seller, source_url,
                    main_image, all_images, description, short_desc, status
                ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)
            ");
            $stmt->execute([
                $name, $nameEn, $slug, $externalId, $brand, $categoryId, $categoryPath,
                $basePrice, $finalPrice, $discountPercent, $stockQty, $stockStatus,
                $rating, $reviewCount, $warranty, $seller, $sourceUrl,
                $mainImage, $allImages, $description, $shortDesc
            ]);
            $productId = $pdo->lastInsertId();
            $inserted++;
        }

        $specs = extractSpecs($item);
        if (!empty($specs)) {
            $pdo->prepare("DELETE FROM product_specs WHERE product_id = ?")->execute([$productId]);
            $specStmt = $pdo->prepare("INSERT INTO product_specs (product_id, group_name, spec_key, spec_value, sort_order) VALUES (?,?,?,?,?)");
            $order = 1;
            foreach ($specs as $s) {
                $specStmt->execute([$productId, $s['group'], $s['key'], $s['value'], $order++]);
            }
        }

        if (($inserted + $updated) % 50 === 0 && ($inserted + $updated) > 0) {
            $pdo->commit();
            $pdo->beginTransaction();
            $elapsed = round(microtime(true) - $startTime, 1);
            logMsg("📦 پیشرفت: <strong>" . ($inserted + $updated) . "</strong> / $total (زمان: {$elapsed}s)");
        }

    } catch (Exception $e) {
        $errors++;
        if ($errors <= 10) logMsg("❌ خطا در #$i: " . htmlspecialchars($e->getMessage()));
    }
}

$pdo->commit();
$totalTime = round(microtime(true) - $startTime, 1);

logMsg("");
logMsg("═══════════════════════════════════");
logMsg("✅ ایمپورت کامل شد!");
logMsg("═══════════════════════════════════");
logMsg("📥 درج شده: <strong>$inserted</strong>");
logMsg("🔄 آپدیت: <strong>$updated</strong>");
logMsg("⏭ نادیده: <strong>$skipped</strong>");
logMsg("❌ خطا: <strong>$errors</strong>");
logMsg("✅ موجود: <strong>$inStock</strong>");
logMsg("⛔ ناموجود: <strong>$outOfStock</strong>");
logMsg("⏱ زمان: <strong>{$totalTime} ثانیه</strong>");
logMsg("<br>🔗 <a href='index.php' style='color:#0ff;'>مشاهده سایت</a>");

echo "</body></html>";