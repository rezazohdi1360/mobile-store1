<?php
/**
 * هدر مشترک سایت (لوگو + جستجو + ورود/سبد + نوار ناوبری با منوی دسته‌بندی).
 * استفاده: <?php require __DIR__ . '/includes/site_header.php'; ?>  (بلافاصله بعد از <body>)
 * رنگ‌ها از متغیرهای تم گرفته می‌شود (--gold = رنگ تأکید، --navy-deep = زمینه‌ی تیره).
 */
$__cartN = array_sum($_SESSION['cart'] ?? []);
$__cats = [];
try { $__cats = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(); } catch (Throwable $e) {}
$__tag = site_get($pdo, 'site_tagline', 'تخصصی‌ترین مرجع ابزارآلات');
$__nm = preg_split('/\s+/u', trim(SITE_NAME), 2);
$__fa = function ($n) { return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); };
$__cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<style>
.sh2,.sh2 *{box-sizing:border-box}
.sh2 a{text-decoration:none}
.sh2-logo{color:inherit}
.sh2-top{background:var(--paper,#fff);border-bottom:1px solid var(--line,#e2e8f0);position:sticky;top:0;z-index:200}
.sh2-in{max-width:1280px;margin:0 auto;padding:12px 24px;display:flex;align-items:center;gap:18px}
.sh2-logo{display:flex;align-items:center;gap:10px;white-space:nowrap}
.sh2-mark{width:50px;height:50px;border-radius:14px;background:var(--gold,#f97316);display:grid;place-items:center;font-size:26px;color:#fff;box-shadow:0 6px 14px -4px rgba(249,115,22,.5)}
.sh2-logo img{height:50px;width:auto;border-radius:10px}
.sh2-lt{display:flex;flex-direction:column;line-height:1.3}
.sh2-lt b{font-size:22px;font-weight:900;color:var(--ink,#0f172a)}
.sh2-lt b em{font-style:normal;color:var(--gold,#f97316)}
.sh2-lt small{font-size:11px;color:var(--muted,#64748b)}
.sh2-search{flex:1;display:flex;align-items:center;background:var(--cream,#f1f5f9);border:1px solid var(--line,#e2e8f0);border-radius:14px;padding:4px;max-width:640px;margin-inline:auto}
.sh2-search input{flex:1;min-width:0;background:transparent;border:0;outline:0;padding:10px 14px;font:inherit;font-size:13px;color:var(--ink,#0f172a)}
.sh2-search button{background:var(--gold,#f97316);color:#fff;border:0;border-radius:10px;padding:10px 20px;font:inherit;font-size:13px;font-weight:800;cursor:pointer;box-shadow:0 6px 14px -6px rgba(249,115,22,.7)}
.sh2-search button:hover{filter:brightness(.93)}
.sh2-act{display:flex;align-items:center;gap:10px}
.sh2-btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:12px;font-size:13px;font-weight:700;border:1px solid var(--line,#e2e8f0);background:var(--paper,#fff);color:var(--ink,#0f172a);white-space:nowrap}
.sh2-btn i{font-style:normal;color:var(--gold,#f97316);font-size:16px}
.sh2-btn:hover{border-color:var(--gold,#f97316)}
.sh2 .sh2-cart{background:var(--navy-deep,#0f172a);border-color:var(--navy-deep,#0f172a);color:#fff;position:relative}
.sh2-cart:hover{background:var(--navy,#1e293b)}
.sh2-badge{position:absolute;top:-8px;right:-8px;min-width:22px;height:22px;border-radius:11px;background:var(--gold,#f97316);color:#fff;font-size:11px;font-weight:800;display:grid;place-items:center;padding:0 5px}
.sh2-nav{background:var(--navy-deep,#0f172a)}
.sh2-nav-in{max-width:1280px;margin:0 auto;padding:0 24px;display:flex;align-items:stretch;gap:4px;position:relative}
.sh2-cat{position:relative}
.sh2-cat>button{white-space:nowrap;display:flex;align-items:center;gap:10px;background:var(--gold,#f97316);color:#fff;border:0;padding:15px 26px;font:inherit;font-size:13px;font-weight:800;cursor:pointer;border-radius:0}
.sh2-cat>button:hover{filter:brightness(.94)}
.sh2-menu{display:none;position:absolute;top:100%;right:0;min-width:240px;max-height:70vh;overflow:auto;background:var(--paper,#fff);border:1px solid var(--line,#e2e8f0);border-radius:0 0 14px 14px;box-shadow:0 20px 40px -12px rgba(15,23,42,.35);z-index:300;padding:6px}
.sh2-cat.open .sh2-menu{display:block}
.sh2-menu a{display:block;padding:10px 14px;border-radius:9px;font-size:13px;font-weight:600;color:var(--ink,#0f172a)}
.sh2-menu a:hover{background:var(--gold-pale,#fff1e6);color:var(--gold,#f97316)}
.sh2-links{display:flex;align-items:center;overflow-x:auto;scrollbar-width:none}
.sh2-links::-webkit-scrollbar{display:none}
.sh2-links a{color:#e2e8f0;font-size:13px;font-weight:700;padding:15px 18px;white-space:nowrap;border-bottom:3px solid transparent}
.sh2-links a:hover,.sh2-links a.on{color:#fff;border-bottom-color:var(--gold,#f97316)}
.sh2-links a i{font-style:normal;color:var(--gold,#f97316)}
@media(max-width:900px){
 .sh2-in{flex-wrap:wrap;padding:10px 14px;gap:10px}
 .sh2-search{order:3;flex:1 1 100%;max-width:none}
 .sh2-act{margin-inline-start:auto}
 .sh2-btn{padding:9px 12px}
 .sh2-btn span.t{display:none}
 .sh2-nav-in{padding:0 10px}
 .sh2-cat>button{padding:13px 14px}
}
</style>
<header class="sh2 sh2-top"><div class="sh2-in">
  <a href="index.php" class="sh2-logo">
    <?php $__lu = function_exists('icon_trimmed_url') ? icon_trimmed_url(1) : ''; ?>
    <?php if ($__lu): ?><img src="<?= htmlspecialchars($__lu) ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>"><?php else: ?><span class="sh2-mark">🛠</span><?php endif; ?>
    <span class="sh2-lt"><b><?= htmlspecialchars($__nm[0]) ?><?= isset($__nm[1]) ? ' <em>' . htmlspecialchars($__nm[1]) . '</em>' : '' ?></b><small><?= htmlspecialchars($__tag) ?></small></span>
  </a>
  <form class="sh2-search" action="search.php" method="GET">
    <input type="text" name="q" placeholder="نام کالا یا برند مورد نظر را جستجو کنید...">
    <button type="submit">🔍 جستجو</button>
  </form>
  <div class="sh2-act">
    <?php if (function_exists('isLoggedIn') && isLoggedIn()): ?>
      <?php if (isAdmin()): ?><a href="admin/index.php" class="sh2-btn"><i>🔧</i><span class="t">پنل مدیریت</span></a>
      <?php else: ?><a href="account.php" class="sh2-btn"><i>👤</i><span class="t">حساب من</span></a><?php endif; ?>
    <?php else: ?>
      <a href="login.php" class="sh2-btn"><i>👤</i><span class="t">ورود / ثبت‌نام</span></a>
    <?php endif; ?>
    <a href="cart.php" class="sh2-btn sh2-cart"><i>🛒</i><span class="t">سبد خرید</span><span class="sh2-badge"><?= $__fa($__cartN) ?></span></a>
  </div>
</div></header>
<nav class="sh2 sh2-nav"><div class="sh2-nav-in">
  <div class="sh2-cat" id="sh2cat">
    <button type="button" id="sh2catBtn">☰ دسته‌بندی محصولات ⌄</button>
    <div class="sh2-menu">
      <a href="archive.php">همه محصولات</a>
      <?php foreach ($__cats as $__c): ?><a href="archive.php?cat=<?= (int)$__c['id'] ?>"><?= htmlspecialchars($__c['name']) ?></a><?php endforeach; ?>
    </div>
  </div>
  <div class="sh2-links">
    <a href="archive.php" class="<?= $__cur === 'archive.php' && empty($_GET['sort']) ? 'on' : '' ?>">فروشگاه <i>🔥</i></a>
    <a href="archive.php?sort=best" class="<?= ($_GET['sort'] ?? '') === 'best' ? 'on' : '' ?>">پرفروش‌ترین‌ها</a>
    <a href="archive.php?sort=cheap" class="<?= ($_GET['sort'] ?? '') === 'cheap' ? 'on' : '' ?>">ارزان‌ترین‌ها</a>
    <a href="contact.php" class="<?= $__cur === 'contact.php' ? 'on' : '' ?>">ارتباط با ما</a>
  </div>
</div></nav>
<script>
(function(){var c=document.getElementById('sh2cat'),b=document.getElementById('sh2catBtn');if(!c||!b)return;
b.addEventListener('click',function(e){e.stopPropagation();c.classList.toggle('open')});
document.addEventListener('click',function(e){if(!c.contains(e.target))c.classList.remove('open')});})();
</script>
