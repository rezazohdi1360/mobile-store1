<?php
require_once __DIR__ . '/../config/site.php';
// ───── توابع کمکی (اگر قبلاً تعریف نشده باشند) ─────
if (!function_exists('fa_num')) {
    function fa_num($n) {
        return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
    }
}
// تعداد سبد خرید: اگر در هدر متغیر $cart_count دارید همان استفاده می‌شود
$ft_cart_count = isset($cart_count) ? (int)$cart_count : (isset($_SESSION['cart']) && is_array($_SESSION['cart']) ? count($_SESSION['cart']) : 0);

// نوار ثابت فقط در صفحه محصول نمایش داده می‌شود (وقتی $product تعریف شده باشد)
$ft_has_product = !empty($footer_is_product_page) && isset($footer_product) && is_array($footer_product);
if ($ft_has_product) {
    $ft_id        = (int)($footer_product['id'] ?? 0);
    $ft_title     = ($footer_product['title'] ?? '');
    $ft_image     = $footer_product['image'] ?? 'assets/no-image.png';
    $ft_price     = (int)($footer_product['price'] ?? 0);
    $ft_old_price = (int)($footer_product['old_price'] ?? 0);
}
?>

    <style>
    .ft-wrap{background:#0f172a;color:#cbd5e1;margin-top:3rem;border-top:1px solid #1e293b;font-family:inherit;padding-bottom:<?= $ft_has_product ? '130px' : '70px' ?>}
    .ft-inner{max-width:80rem;margin:0 auto;padding:2.5rem 1rem 0}
    .ft-cols{display:grid;grid-template-columns:repeat(4,1fr);gap:2rem;padding-bottom:2rem;border-bottom:1px solid #1e293b}
    .ft-col h4{font-size:1.05rem;font-weight:800;color:#fff;margin-bottom:1rem}
    .ft-col ul{list-style:none;margin:0;padding:0}
    .ft-col li{margin-bottom:.65rem}
    .ft-col li a{font-size:.85rem;color:#94a3b8;text-decoration:none}
    .ft-col li a:hover{color:#fff}
    .ft-enamad{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;width:150px;min-height:150px;border:1px dashed #475569;border-radius:.5rem;padding:.9rem;text-align:center}
    .ft-enamad-link{display:flex;flex-direction:column;align-items:center;gap:.35rem;text-align:center;width:150px;color:inherit;text-decoration:none}
    .ft-enamad-img{display:block;background:#fff;border-radius:.6rem;padding:.4rem;width:120px;height:120px}
    .ft-enamad-img img{width:100%;height:100%;object-fit:contain;display:block}
    .ft-enamad-link strong{color:#e5e7eb;font-size:.9rem}
    .ft-enamad-link small{font-size:.75rem;color:#94a3b8}
    .ft-enamad .st{color:#fbbf24;letter-spacing:2px}
    .ft-enamad small{font-size:.7rem;color:#94a3b8}
    .ft-copy{text-align:center;padding:1.4rem 0 .9rem;font-size:.8rem;color:#94a3b8}
    .ft-pay{display:flex;justify-content:center;flex-wrap:wrap;gap:.6rem;padding-bottom:1.3rem}
    .ft-pay span{background:#fff;color:#1e293b;font:800 11px Arial,sans-serif;padding:5px 10px;border-radius:3px;min-width:52px;text-align:center}

    .ft-top{position:fixed;left:16px;bottom:<?= $ft_has_product ? '96px' : '76px' ?>;width:44px;height:44px;border-radius:50%;background:#fff;color:#0f172a;border:0;box-shadow:0 2px 10px rgba(0,0,0,.3);font-size:18px;cursor:pointer;z-index:90}
    .ft-top:hover{background:#2563eb;color:#fff}

    .ft-sticky{position:fixed;left:0;right:0;bottom:0;z-index:95;background:#fff;box-shadow:0 -4px 16px rgba(0,0,0,.12);border-top:1px solid #e2e8f0;color:#0f172a}
    .ft-sticky-row{display:flex;align-items:center;gap:14px;max-width:80rem;margin:0 auto;padding:8px 16px}
    .ft-sticky img{width:52px;height:52px;object-fit:contain;flex-shrink:0}
    .ft-st-title{flex:1;font-size:.85rem;font-weight:600;line-height:1.6}
    .ft-st-price{white-space:nowrap}
    .ft-st-price del{display:block;font-size:.72rem;color:#94a3b8}
    .ft-st-price b{color:#2563eb;font-size:1rem}
    .ft-qty{display:flex;border:1px solid #e2e8f0;border-radius:4px;overflow:hidden}
    .ft-qty button{width:34px;height:40px;background:#fff;border:0;font-size:16px;cursor:pointer}
    .ft-qty button:hover{background:#f1f5f9}
    .ft-qty input{width:40px;text-align:center;border:0;border-inline:1px solid #e2e8f0;font:inherit;font-size:.85rem;outline:none}
    .ft-add{background:#2563eb;color:#fff;border:0;height:42px;padding:0 22px;border-radius:4px;font-weight:700;font-size:.85rem;cursor:pointer}
    .ft-add:hover{background:#1d4ed8}
    .ft-ico{display:flex;gap:4px}
    .ft-ico button{width:38px;height:38px;border:0;background:transparent;border-radius:50%;font-size:19px;cursor:pointer;color:#0f172a}
    .ft-ico button:hover{background:#f1f5f9}
    .ft-ico button.on{color:#dc2626}

    .ft-mnav{display:none;grid-template-columns:repeat(3,1fr);background:#fff;border-top:1px solid #e2e8f0}
    .ft-mnav a{position:relative;display:flex;flex-direction:column;align-items:center;gap:2px;padding:7px 0;font-size:11px;font-weight:700;color:#0f172a;text-decoration:none}
    .ft-mnav a i{font-style:normal;font-size:20px}
    .ft-mnav .bd{position:absolute;top:2px;right:calc(50% - 24px);background:#dc2626;color:#fff;border-radius:999px;min-width:16px;padding:0 4px;font-size:10px;text-align:center}
    .ft-toast{position:fixed;bottom:150px;left:50%;transform:translateX(-50%);background:#0f172a;color:#fff;padding:10px 24px;border-radius:6px;font-size:.85rem;z-index:9999;opacity:0;transition:opacity .3s;pointer-events:none}
    .ft-toast.show{opacity:1}

    @media(max-width:768px){
        .ft-cols{grid-template-columns:repeat(2,1fr)}
        .ft-mnav{display:grid}
        .ft-wrap{padding-bottom:<?= $ft_has_product ? '230px' : '70px' ?>}
        .ft-top{bottom:<?= $ft_has_product ? '200px' : '76px' ?>}
        .ft-sticky-row{flex-wrap:wrap;gap:8px;padding:8px 12px}
        .ft-st-title{flex-basis:calc(100% - 66px);font-size:.78rem}
        .ft-sticky img{width:44px;height:44px}
        .ft-st-price{order:3}.ft-qty{order:4}.ft-add{order:5;flex:1}.ft-ico{order:6}
    }
    @media(max-width:480px){.ft-cols{grid-template-columns:1fr}}
    </style>

    <footer class="ft-wrap">
        <div class="ft-inner">
            <div class="ft-cols">
                <div class="ft-col">
                    <h4>دسته بندی ها</h4>
                    <ul>
                        <li><a href="archive.php?cat=1">تلفن هوشمند</a></li>
                        <li><a href="search.php?q=لپ+تاپ">لپ تاپ</a></li>
                        <li><a href="search.php?q=سخت+افزار">سخت افزار</a></li>
                        <li><a href="search.php?q=دوربین+عکاسی">دوربین عکاسی</a></li>
                        <li><a href="search.php?q=هدفون">هدفون</a></li>
                    </ul>
                </div>
                <div class="ft-col">
                    <h4>دسترسی سریع</h4>
                    <ul>
                        <li><a href="archive.php">پرتخفیف ها</a></li>
                        <li><a href="stores.php">فروشگاه های ما</a></li>
                        <li><a href="contact.php">تماس با ما</a></li>
                        <li><a href="return-policy.php">شرایط بازگشت</a></li>
                        <li><a href="terms.php">قوانین</a></li>
                    </ul>
                </div>
                <div class="ft-col">
                    <h4>لینک های مفید</h4>
                    <ul>
                        <li><a href="return-policy.php">شرایط بازگشت</a></li>
                        <li><a href="stores.php">فروشگاه های ما</a></li>
                        <li><a href="archive.php">پرتخفیف ها</a></li>
                        <li><a href="contact.php">تماس با ما</a></li>
                        <li><a href="terms.php">قوانین</a></li>
                    </ul>
                </div>
                <div class="ft-col">
                    <h4>نماد های اعتماد الکترونیک</h4>
                    <?php
                    $__enImg = icon_url(4) ?: icon_url(2);
                    $__enUrl = isset($pdo) ? trim((string)site_get($pdo, 'enamad_url', '')) : '';
                    if (SITE_ENAMAD_HTML !== ''): echo SITE_ENAMAD_HTML;
                    elseif ($__enImg): ?>
                    <?php if ($__enUrl !== ''): ?><a href="<?= htmlspecialchars($__enUrl) ?>" target="_blank" rel="noopener" class="ft-enamad-link"><?php else: ?><span class="ft-enamad-link"><?php endif; ?>
                        <span class="ft-enamad-img"><img src="<?= htmlspecialchars($__enImg) ?>" alt="نماد اعتماد الکترونیک" loading="lazy"></span>
                        <strong>نماد اعتماد الکترونیک</strong>
                        <small>جهت اطمینان کلیک نمایید</small>
                    <?php if ($__enUrl !== ''): ?></a><?php else: ?></span><?php endif; ?>
                    <?php else: ?>
                    <div class="ft-enamad">
                        <strong>نماد اعتماد الکترونیک</strong>
                        <small>جهت اطمینان کلیک نمایید</small>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <p class="ft-copy">تمام حقوق سایت محفوظ و متعلق به «<?= htmlspecialchars(SITE_NAME) ?>» (<?= htmlspecialchars(SITE_OWNER) ?>) می‌باشد. ©<?= fa_num(1405) ?></p>
            <p class="ft-copy" style="padding-top:0">
                <?php if (SITE_PHONE): ?>تلفن تماس: <a href="tel:<?= htmlspecialchars(SITE_PHONE) ?>" style="color:inherit" dir="ltr"><?= fa_num(htmlspecialchars(SITE_PHONE)) ?></a><?php endif; ?>
                <?php if (SITE_PHONE2): ?> &nbsp;|&nbsp; <a href="tel:<?= htmlspecialchars(SITE_PHONE2) ?>" style="color:inherit" dir="ltr"><?= fa_num(htmlspecialchars(SITE_PHONE2)) ?></a><?php endif; ?>
                <?php if (SITE_EMAIL): ?> &nbsp;|&nbsp; <a href="mailto:<?= htmlspecialchars(SITE_EMAIL) ?>" style="color:inherit"><?= htmlspecialchars(SITE_EMAIL) ?></a><?php endif; ?>
                <?php if (SITE_ADDRESS): ?><br><?= htmlspecialchars(SITE_ADDRESS) ?><?php endif; ?>
            </p>

            <div class="ft-pay">
                <span style="color:#1a3a8f">VISA</span>
                <span style="color:#c0392b">Mastercard</span>
                <span style="color:#1c5bb0">PayPal</span>
                <span style="color:#2a7bc0">AMEX</span>
                <span style="color:#1a3a8f">VISA Electron</span>
                <span style="color:#c0392b">Maestro</span>
            </div>
        </div>
    </footer>

    <button class="ft-top" id="ftTop" aria-label="بازگشت به بالا">⌃</button>

    <div class="ft-sticky">
        <?php if ($ft_has_product): ?>
        <div class="ft-sticky-row">
            <img src="<?= htmlspecialchars($ft_image) ?>" alt="" onerror="this.onerror=null;this.src='assets/no-image.png'">
            <div class="ft-st-title"><?= htmlspecialchars($ft_title) ?></div>
            <div class="ft-st-price">
                <?php if ($ft_old_price > $ft_price && $ft_price > 0): ?>
                    <del><?= fa_num(number_format($ft_old_price)) ?> تومان</del>
                <?php endif; ?>
                <b><?= $ft_price > 0 ? fa_num(number_format($ft_price)) . ' تومان' : 'تماس بگیرید' ?></b>
            </div>
            <div class="ft-qty">
                <button type="button" id="ftPlus" aria-label="افزایش">+</button>
                <input type="text" id="ftQty" value="۱" readonly>
                <button type="button" id="ftMinus" aria-label="کاهش">-</button>
            </div>
            <?php if ($ft_price > 0): ?>
            <button type="button" class="ft-add" id="ftAdd" data-id="<?= $ft_id ?>">افزودن به سبد خرید</button>
            <?php endif; ?>
            <div class="ft-ico">
                <button type="button" id="ftWish" aria-label="علاقه‌مندی">♡</button>
                <button type="button" id="ftCompare" aria-label="مقایسه">⇄</button>
            </div>
        </div>
        <?php endif; ?>
        <nav class="ft-mnav">
            <a href="cart.php"><i>🛒</i>سبد خرید<?php if ($ft_cart_count > 0): ?><span class="bd" id="ftBadge"><?= fa_num($ft_cart_count) ?></span><?php endif; ?></a>
            <a href="index.php"><i>☰</i>منو</a>
            <a href="account.php"><i>👤</i>حساب کاربری من</a>
        </nav>
    </div>

    <div class="ft-toast" id="ftToast"></div>

    <script>
    (function () {
        var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
        var toastEl = document.getElementById('ftToast'), tmr;
        function toast(msg) {
            toastEl.textContent = msg; toastEl.classList.add('show');
            clearTimeout(tmr); tmr = setTimeout(function () { toastEl.classList.remove('show'); }, 2200);
        }

        document.getElementById('ftTop').addEventListener('click', function () {
            window.scrollTo({top: 0, behavior: 'smooth'});
        });

        var add = document.getElementById('ftAdd');
        if (!add) return;

        var n = 1, qty = document.getElementById('ftQty');
        document.getElementById('ftPlus').addEventListener('click', function () { n = Math.min(n + 1, 99); qty.value = fa(n); });
        document.getElementById('ftMinus').addEventListener('click', function () { n = Math.max(n - 1, 1); qty.value = fa(n); });

        add.addEventListener('click', function () {
            var id = add.getAttribute('data-id');
            fetch('add_to_cart.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'id=' + encodeURIComponent(id) + '&qty=' + n
            })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.success) {
                    if (d.cart_count > 0) {
                        var b = document.getElementById('ftBadge');
                        if (!b) {
                            b = document.createElement('span'); b.className = 'bd'; b.id = 'ftBadge';
                            document.querySelector('.ft-mnav a').appendChild(b);
                        }
                        b.textContent = fa(d.cart_count);
                    }
                    toast('✅ ' + d.message);
                } else { toast('❌ ' + d.message); }
            })
            .catch(function () { toast('خطا در ارتباط با سرور'); });
        });

        var wish = document.getElementById('ftWish');
        wish.addEventListener('click', function () {
            var on = wish.classList.toggle('on');
            wish.textContent = on ? '♥' : '♡';
            toast(on ? 'به علاقه‌مندی‌ها اضافه شد' : 'از علاقه‌مندی‌ها حذف شد');
        });
        document.getElementById('ftCompare').addEventListener('click', function () {
            toast('به لیست مقایسه اضافه شد');
        });
    })();
    </script>

