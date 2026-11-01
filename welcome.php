<?php
/** صفحه خوش‌آمدگویی — اطلاعات فروشگاه و پیام خوش‌آمد به کاربر */
require_once 'config/database.php';
require_once 'includes/functions.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!welcome_enabled($pdo) || isset($_GET['skip'])) { $_SESSION['welcomed'] = 1; header('Location: index.php'); exit; }
$_SESSION['welcomed'] = 1;

$userName = trim((string)($_SESSION['name'] ?? ''));
$custom = trim((string)site_get($pdo, 'welcome_message', ''));
$msg = $custom !== '' ? $custom : 'جدیدترین گوشی‌ها و لوازم جانبی با بهترین قیمت، گارانتی معتبر و ارسال سریع به سراسر کشور.';

try {
    $totalProducts = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 1")->fetchColumn();
    $totalInStock  = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE status = 1 AND stock_status = 'in_stock' AND stock > 0")->fetchColumn();
} catch (Throwable $e) { $totalProducts = $totalInStock = 0; }
$fa = fn($v) => strtr((string)$v, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
$logo = icon_trimmed_url(1);
$banner = icon_url(3);
$h = date('G');
$greet = $h < 5 ? 'شب بخیر' : ($h < 12 ? 'صبح بخیر' : ($h < 17 ? 'ظهر بخیر' : ($h < 20 ? 'عصر بخیر' : 'شب بخیر')));
?><!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>خوش آمدید | <?= htmlspecialchars(SITE_NAME) ?></title>
<link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Vazirmatn',Tahoma,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px;
  background:<?= $banner ? "linear-gradient(rgba(13,40,60,.72),rgba(13,40,60,.85)),url('" . htmlspecialchars($banner) . "') center/cover" : "radial-gradient(circle at 20% 15%,#1d8a9a55,transparent 45%),linear-gradient(135deg,#0f2a3d,#134e5e 60%,#1b6b7a)" ?>;color:#fff}
.card{width:100%;max-width:560px;background:rgba(255,255,255,.97);color:#1e293b;border-radius:20px;padding:30px 26px;text-align:center;box-shadow:0 30px 60px -20px rgba(0,0,0,.55)}
.logo{height:120px;width:auto;max-width:80%;object-fit:contain;margin-bottom:6px}
.emoji{font-size:64px}
h1{font-size:23px;color:#134e5e;margin:8px 0 6px}
.hello{color:#0e7490;font-weight:700;margin-bottom:10px}
p.msg{font-size:14px;line-height:2;color:#475569;margin-bottom:18px}
.stats{display:flex;justify-content:center;gap:10px;margin-bottom:18px}
.stat{flex:1;background:#f0f9ff;border-radius:12px;padding:10px 4px}
.stat b{display:block;font-size:20px;color:#0e7490}.stat span{font-size:11.5px;color:#64748b}
.info{text-align:right;background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:12px 16px;font-size:13px;line-height:2.1;margin-bottom:20px}
.info b{color:#134e5e}.info a{color:inherit;text-decoration:none}
.btn{display:inline-block;background:#0e7490;color:#fff;text-decoration:none;font-weight:800;font-size:15px;padding:13px 38px;border-radius:12px;transition:.2s}
.btn:hover{background:#155e75;transform:translateY(-1px)}
.auto{font-size:11.5px;color:#94a3b8;margin-top:12px}
</style>
</head>
<body>
<main class="card">
    <?php if ($logo): ?><img class="logo" src="<?= htmlspecialchars($logo) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>"><?php else: ?><div class="emoji">📱</div><?php endif; ?>
    <h1>به «<?= htmlspecialchars(SITE_NAME) ?>» خوش آمدید</h1>
    <div class="hello"><?= $greet ?><?= $userName !== '' ? '، ' . htmlspecialchars($userName) . ' عزیز' : '، مهمان عزیز' ?> 👋</div>
    <p class="msg"><?= nl2br(htmlspecialchars($msg)) ?></p>

    <?php if ($totalProducts > 0): ?>
    <div class="stats">
        <div class="stat"><b><?= $fa(number_format($totalProducts)) ?></b><span>محصول متنوع</span></div>
        <div class="stat"><b><?= $fa(number_format($totalInStock)) ?></b><span>آماده ارسال</span></div>
        <div class="stat"><b>۲۴/۷</b><span>پشتیبانی</span></div>
    </div>
    <?php endif; ?>

    <div class="info">
        <div><b>فروشگاه:</b> <?= htmlspecialchars(SITE_NAME) ?></div>
        <div><b>مدیر:</b> <?= htmlspecialchars(SITE_OWNER) ?></div>
        <?php if (SITE_PHONE): ?><div><b>تلفن:</b> <a href="tel:<?= htmlspecialchars(SITE_PHONE) ?>" dir="ltr"><?= $fa(htmlspecialchars(SITE_PHONE)) ?></a><?php if (SITE_PHONE2): ?> ، <a href="tel:<?= htmlspecialchars(SITE_PHONE2) ?>" dir="ltr"><?= $fa(htmlspecialchars(SITE_PHONE2)) ?></a><?php endif; ?></div><?php endif; ?>
        <?php if (SITE_EMAIL): ?><div><b>ایمیل:</b> <a href="mailto:<?= htmlspecialchars(SITE_EMAIL) ?>"><?= htmlspecialchars(SITE_EMAIL) ?></a></div><?php endif; ?>
        <?php if (SITE_ADDRESS): ?><div><b>آدرس:</b> <?= htmlspecialchars(SITE_ADDRESS) ?></div><?php endif; ?>
        <?php if (SITE_HOURS): ?><div><b>ساعت کاری:</b> <?= htmlspecialchars(SITE_HOURS) ?></div><?php endif; ?>
        <?php if (SITE_INSTAGRAM): ?><div><b>اینستاگرام:</b> <a href="<?= htmlspecialchars(SITE_INSTAGRAM) ?>" target="_blank" rel="noopener" dir="ltr"><?= htmlspecialchars(SITE_INSTAGRAM) ?></a></div><?php endif; ?>
        <?php if (SITE_TELEGRAM): ?><div><b>تلگرام:</b> <a href="<?= htmlspecialchars(SITE_TELEGRAM) ?>" target="_blank" rel="noopener" dir="ltr"><?= htmlspecialchars(SITE_TELEGRAM) ?></a></div><?php endif; ?>
    </div>

    <a class="btn" href="index.php?skip=1" id="go">ورود به فروشگاه</a>
    <div class="auto">ورود خودکار تا <span id="n">۱۰</span> ثانیه دیگر</div>
</main>
<script>
(function(){var n=10,el=document.getElementById('n'),fa='۰۱۲۳۴۵۶۷۸۹';
var t=setInterval(function(){n--;el.textContent=String(n).replace(/\d/g,function(d){return fa[d]});if(n<=0){clearInterval(t);location.href='index.php?skip=1';}},1000);
document.getElementById('go').addEventListener('click',function(){clearInterval(t)});})();
</script>
</body>
</html>
