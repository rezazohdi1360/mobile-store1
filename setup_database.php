<?php
/**
 * 🚀 راه‌اندازی خودکار دیتابیس
 * اجرا: http://localhost/mobile-store/setup_database.php
 */

header('Content-Type: text/html; charset=utf-8');
echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'>
<title>راه‌اندازی دیتابیس</title>
<style>
body{font-family:tahoma;background:#f8fafc;padding:30px;line-height:1.8;}
.box{max-width:800px;margin:auto;background:#fff;padding:30px;border-radius:16px;box-shadow:0 4px 12px rgba(0,0,0,0.1);}
.ok{color:#16a34a;font-weight:bold;}
.err{color:#dc2626;font-weight:bold;}
.warn{color:#d97706;font-weight:bold;}
pre{background:#0f172a;color:#0f0;padding:15px;border-radius:8px;direction:ltr;text-align:left;overflow-x:auto;}
h1{color:#2563eb;border-bottom:2px solid #2563eb;padding-bottom:10px;}
.box-item{padding:12px;margin:8px 0;border-radius:8px;background:#f1f5f9;}
</style></head><body><div class='box'>";

echo "<h1>🔧 راه‌اندازی دیتابیس فروشگاه جامع موبایل 72412</h1>";

// ========== اطلاعات اتصال ==========
$adminUser = 'root';
$adminPass = '';  // اگر root رمز دارد، اینجا وارد کنید

$newUser = 'rezazohdi';
$newPass = '1328';
$dbName  = 'mobile_db';
// ===================================

try {
    // 1. اتصال با root
    echo "<div class='box-item'>🔄 در حال اتصال به MySQL...</div>";
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", $adminUser, $adminPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<div class='box-item ok'>✅ اتصال به MySQL برقرار شد</div>";

    // 2. ساخت دیتابیس
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
    echo "<div class='box-item ok'>✅ دیتابیس <code>$dbName</code> ساخته شد</div>";

    // 3. ساخت کاربر
    try {
        $pdo->exec("CREATE USER IF NOT EXISTS '$newUser'@'localhost' IDENTIFIED BY '$newPass'");
        echo "<div class='box-item ok'>✅ کاربر <code>$newUser</code> ساخته شد</div>";
    } catch (Exception $e) {
        $pdo->exec("ALTER USER '$newUser'@'localhost' IDENTIFIED BY '$newPass'");
        echo "<div class='box-item warn'>⚠️ کاربر از قبل وجود داشت - رمز به‌روزرسانی شد</div>";
    }

    // 4. اعطای دسترسی
    $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$newUser'@'localhost'");
    $pdo->exec("FLUSH PRIVILEGES");
    echo "<div class='box-item ok'>✅ دسترسی کامل داده شد</div>";

    // 5. تست اتصال با کاربر جدید
    $testPdo = new PDO("mysql:host=localhost;dbname=$dbName;charset=utf8mb4", $newUser, $newPass);
    echo "<div class='box-item ok'>✅ تست اتصال با کاربر <code>$newUser</code> موفق بود</div>";

    // 6. نصب جداول اگر وجود ندارند
    $tables = $testPdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
    
    if (empty($tables)) {
        echo "<hr><div class='box-item warn'>⚠️ جداول وجود ندارند - در حال نصب...</div>";
        
        $sqlFile = __DIR__ . '/database.sql';
        if (file_exists($sqlFile)) {
            $sql = file_get_contents($sqlFile);
            // حذف CREATE DATABASE و USE (چون قبلاً ساختیم)
            $sql = preg_replace('/CREATE DATABASE.*?;/i', '', $sql);
            $sql = preg_replace('/USE .*?;/i', '', $sql);
            $testPdo->exec($sql);
            echo "<div class='box-item ok'>✅ تمام جداول با موفقیت ساخته شدند!</div>";
        } else {
            echo "<div class='box-item err'>❌ فایل database.sql پیدا نشد در: $sqlFile</div>";
        }
    } else {
        echo "<div class='box-item ok'>✅ جداول قبلاً نصب شده‌اند</div>";
    }

    // 7. نمایش اطلاعات
    echo "<hr><h3>📋 اطلاعات نهایی:</h3>";
    echo "<pre>";
    echo "Host:     localhost\n";
    echo "Database: $dbName\n";
    echo "Username: $newUser\n";
    echo "Password: $newPass\n";
    echo "</pre>";

    echo "<div class='box-item warn'>⚠️ برای امنیت، این فایل را حذف کنید!</div>";
    
    echo "<p style='text-align:center;margin-top:20px;'>";
    echo "<a href='test_connection.php' style='display:inline-block;background:#2563eb;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin:5px;'>🔌 تست اتصال</a>";
    echo "<a href='index.php' style='display:inline-block;background:#16a34a;color:white;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:bold;margin:5px;'>🏠 رفتن به سایت</a>";
    echo "</p>";

} catch (PDOException $e) {
    echo "<div class='box-item err'>❌ خطا: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<hr><h3>🔍 راه‌حل:</h3>";
    echo "<ol>";
    echo "<li>مطمئن شوید XAMPP روشن است (Apache + MySQL سبز)</li>";
    echo "<li>اگر root رمز دارد، متغیر <code>\$adminPass</code> را در فایل ویرایش کنید</li>";
    echo "<li>یا از روش 2 (phpMyAdmin) استفاده کنید</li>";
    echo "</ol>";
}

echo "</div></body></html>";