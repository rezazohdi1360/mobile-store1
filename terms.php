<?php
require_once 'config/database.php';
?><!DOCTYPE html>
<html lang="fa" dir="rtl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>قوانین و مقررات | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>body{font-family:Tahoma,sans-serif;background:#f8fafc;margin:0;color:#1e293b}.box{max-width:800px;margin:30px auto;padding:24px;background:#fff;border-radius:12px;line-height:2}a{color:#2563eb}</style></head>
<body><div class="box"><p><a href="index.php">← صفحه اصلی</a></p><h1>قوانین و مقررات</h1><p>استفاده از خدمات «<?= htmlspecialchars(SITE_NAME) ?>» به معنی پذیرش قوانین سایت است. قیمت و موجودی کالاها ممکن است بدون اطلاع قبلی تغییر کند. برای پیگیری سفارش با <span dir="ltr"><?= htmlspecialchars(SITE_PHONE) ?></span> تماس بگیرید.</p></div>
<?php require __DIR__ . '/includes/site_footer.php'; ?>
</body></html>
