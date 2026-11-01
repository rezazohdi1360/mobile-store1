<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/coupons.php';

if (!isAdmin()) redirect('../login.php');
coupons_ensure($pdo);

// ───── حذف / فعال‌سازی ─────
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM coupon_products WHERE coupon_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM coupons WHERE id=?")->execute([$id]);
    setFlash('success', 'کد تخفیف حذف شد');
    redirect('coupons.php');
}
if (isset($_GET['toggle'])) {
    $pdo->prepare("UPDATE coupons SET status = 1 - status WHERE id=?")->execute([(int)$_GET['toggle']]);
    redirect('coupons.php');
}

// ───── ذخیره (ایجاد / ویرایش) ─────
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_coupon'])) {
    $id     = (int)($_POST['id'] ?? 0);
    $code   = strtoupper(preg_replace('/[^A-Za-z0-9_\-]/', '', en_digits($_POST['code'] ?? '')));
    $title  = trim($_POST['title'] ?? '');
    $type   = ($_POST['type'] ?? '') === 'amount' ? 'amount' : 'percent';
    $value  = (int)preg_replace('/\D/', '', en_digits($_POST['value'] ?? '0'));
    $start  = jalali_to_date($_POST['starts_j'] ?? '');
    $end    = jalali_to_date($_POST['ends_j'] ?? '');
    $max    = trim($_POST['max_uses'] ?? '') === '' ? null : (int)en_digits($_POST['max_uses']);
    $status = isset($_POST['status']) ? 1 : 0;
    $pids   = array_unique(array_map('intval', post_list('products')));

    if ($code === '') $errors[] = 'کد تخفیف را وارد کنید (حروف و اعداد انگلیسی)';
    if ($value <= 0) $errors[] = 'مقدار تخفیف را وارد کنید';
    if ($type === 'percent' && $value > 100) $errors[] = 'درصد تخفیف نمی‌تواند بیش از ۱۰۰ باشد';
    if (!$start) $errors[] = 'تاریخ شروع (شمسی) معتبر نیست';
    if (!$end)   $errors[] = 'تاریخ پایان (شمسی) معتبر نیست';
    if ($start && $end && $end < $start) $errors[] = 'تاریخ پایان باید بعد از تاریخ شروع باشد';
    if (!$pids) $errors[] = 'حداقل یک محصول را تیک بزنید';

    if (!$errors) {
        $dup = $pdo->prepare("SELECT id FROM coupons WHERE code=? AND id<>?");
        $dup->execute([$code, $id]);
        if ($dup->fetch()) $errors[] = 'این کد قبلاً ثبت شده است';
    }

    if (!$errors) {
        $starts = $start . ' 00:00:00';
        $ends   = $end . ' 23:59:59';
        if ($id) {
            $pdo->prepare("UPDATE coupons SET code=?, title=?, type=?, value=?, starts_at=?, ends_at=?, max_uses=?, status=? WHERE id=?")
                ->execute([$code, $title, $type, $value, $starts, $ends, $max, $status, $id]);
        } else {
            $pdo->prepare("INSERT INTO coupons (code, title, type, value, starts_at, ends_at, max_uses, status) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$code, $title, $type, $value, $starts, $ends, $max, $status]);
            $id = (int)$pdo->lastInsertId();
        }
        $pdo->prepare("DELETE FROM coupon_products WHERE coupon_id=?")->execute([$id]);
        $ins = $pdo->prepare("INSERT IGNORE INTO coupon_products (coupon_id, product_id) VALUES (?,?)");
        foreach ($pids as $pid) $ins->execute([$id, $pid]);
        setFlash('success', 'کد تخفیف ذخیره شد');
        redirect('coupons.php');
    }
}

// ───── داده‌های فرم ─────
$edit = null; $editPids = [];
if (isset($_GET['edit']) || isset($_GET['new']) || $errors) {
    if ($errors) {
        $edit = ['id' => (int)($_POST['id'] ?? 0), 'code' => $_POST['code'] ?? '', 'title' => $_POST['title'] ?? '',
                 'type' => $_POST['type'] ?? 'percent', 'value' => $_POST['value'] ?? '', 'max_uses' => $_POST['max_uses'] ?? '',
                 'status' => isset($_POST['status']) ? 1 : 0, 'starts_j' => $_POST['starts_j'] ?? '', 'ends_j' => $_POST['ends_j'] ?? ''];
        $editPids = array_map('intval', post_list('products'));
    } elseif (isset($_GET['edit'])) {
        $st = $pdo->prepare("SELECT * FROM coupons WHERE id=?");
        $st->execute([(int)$_GET['edit']]);
        $edit = $st->fetch() ?: null;
        if ($edit) {
            $edit['starts_j'] = jalali_str($edit['starts_at'], false);
            $edit['ends_j']   = jalali_str($edit['ends_at'], false);
            $st = $pdo->prepare("SELECT product_id FROM coupon_products WHERE coupon_id=?");
            $st->execute([$edit['id']]);
            $editPids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
        }
    } else {
        $edit = ['id' => 0, 'code' => '', 'title' => '', 'type' => 'percent', 'value' => '', 'max_uses' => '', 'status' => 1,
                 'starts_j' => jalali_str(date('Y-m-d'), false), 'ends_j' => jalali_str(date('Y-m-d', strtotime('+7 days')), false)];
    }
}

$coupons = $pdo->query("SELECT c.*, (SELECT COUNT(*) FROM coupon_products cp WHERE cp.coupon_id=c.id) AS pcount FROM coupons c ORDER BY c.id DESC")->fetchAll();
$allProducts = $edit ? $pdo->query("SELECT id, name, price, sale_price FROM products ORDER BY id DESC LIMIT 3000")->fetchAll() : [];

$stateLabel = [
    'active'   => ['🟢 فعال', 'bg-emerald-100 text-emerald-700'],
    'upcoming' => ['🕒 شروع نشده', 'bg-blue-100 text-blue-700'],
    'expired'  => ['⌛ منقضی', 'bg-slate-200 text-slate-600'],
    'inactive' => ['⛔ غیرفعال', 'bg-red-100 text-red-700'],
    'used_up'  => ['🔒 ظرفیت پر', 'bg-amber-100 text-amber-700'],
];

$pageTitle = 'کدهای تخفیف';
require_once '../includes/header.php';
?>
<style>
.jdp-pop{position:absolute;z-index:60;background:#fff;border:1px solid #e2e8f0;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.18);padding:10px;width:264px;display:none;direction:rtl}
.jdp-pop.open{display:block}
.jdp-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;font-weight:800;font-size:13px}
.jdp-head button{border:0;background:#f1f5f9;border-radius:8px;width:28px;height:28px;cursor:pointer;font-size:14px}
.jdp-grid{display:grid;grid-template-columns:repeat(7,1fr);gap:2px;text-align:center}
.jdp-grid .wd{font-size:10px;color:#94a3b8;padding:4px 0;font-weight:700}
.jdp-grid .d{font-size:12px;padding:6px 0;border-radius:8px;cursor:pointer}
.jdp-grid .d:hover{background:#ffedd5}
.jdp-grid .d.sel{background:#f97316;color:#fff;font-weight:800}
.jdp-grid .d.today{outline:1px solid #f97316}
.jdp-grid .d.fri{color:#dc2626}
</style>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <div>
            <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
                <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
                🎟 کدهای تخفیف
            </h1>
            <p class="text-xs text-slate-500 mt-1">کد تخفیف زمان‌دار؛ فقط روی محصولات تیک‌خورده اعمال می‌شود. تاریخ‌ها شمسی هستند.</p>
        </div>
        <div class="flex gap-2">
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
            <a href="?new=1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-4 py-2.5 rounded-xl text-xs">➕ کد تخفیف جدید</a>
        </div>
    </div>

    <?php if ($errors): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-2xl p-4 mb-4 text-xs leading-7">
            <?php foreach ($errors as $e): ?>• <?= sanitize($e) ?><br><?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php if ($edit): ?>
    <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5 mb-6">
        <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
        <h2 class="font-black text-sm mb-4"><?= $edit['id'] ? '✏ ویرایش کد تخفیف' : '➕ کد تخفیف جدید' ?></h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">کد تخفیف (انگلیسی)</label>
                <div class="flex gap-2">
                    <input type="text" name="code" id="codeInput" dir="ltr" value="<?= sanitize($edit['code']) ?>" required placeholder="NOROOZ1405"
                           class="flex-1 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm uppercase">
                    <button type="button" onclick="document.getElementById('codeInput').value='OFF'+Math.random().toString(36).slice(2,8).toUpperCase()"
                            class="px-3 bg-slate-100 hover:bg-slate-200 rounded-xl text-xs font-bold">🎲</button>
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">عنوان (اختیاری)</label>
                <input type="text" name="title" value="<?= sanitize($edit['title']) ?>" placeholder="جشنواره نوروزی"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">نوع و مقدار تخفیف</label>
                <div class="flex gap-2">
                    <select name="type" class="px-2 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                        <option value="percent" <?= $edit['type'] === 'percent' ? 'selected' : '' ?>>درصد (٪)</option>
                        <option value="amount"  <?= $edit['type'] === 'amount'  ? 'selected' : '' ?>>مبلغ ثابت (تومان)</option>
                    </select>
                    <input type="text" name="value" value="<?= sanitize($edit['value']) ?>" required placeholder="مثلاً 15" dir="ltr"
                           class="flex-1 min-w-0 px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
                </div>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">📅 تاریخ شروع (شمسی)</label>
                <input type="text" name="starts_j" class="jdp w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm cursor-pointer" dir="ltr" readonly required value="<?= sanitize($edit['starts_j']) ?>">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">📅 تاریخ پایان (شمسی)</label>
                <input type="text" name="ends_j" class="jdp w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm cursor-pointer" dir="ltr" readonly required value="<?= sanitize($edit['ends_j']) ?>">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-600 mb-2">حداکثر تعداد استفاده (خالی = نامحدود)</label>
                <input type="text" name="max_uses" value="<?= sanitize((string)($edit['max_uses'] ?? '')) ?>" dir="ltr"
                       class="w-full px-3 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-sm">
            </div>
        </div>

        <label class="flex items-center gap-2 text-xs font-bold mb-4 cursor-pointer">
            <input type="checkbox" name="status" value="1" <?= $edit['status'] ? 'checked' : '' ?> class="w-4 h-4"> کد فعال باشد
        </label>

        <div class="border border-slate-200 rounded-2xl p-3 mb-4">
            <div class="flex items-center gap-3 flex-wrap mb-3">
                <div class="text-xs font-black">🛍 محصولات مشمول تخفیف <span id="pcount" class="text-brand-orange"></span></div>
                <input type="text" id="psearch" placeholder="جستجوی محصول..." class="flex-1 min-w-[160px] px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs">
                <button type="button" id="pAll" class="text-[11px] bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-xl font-bold">تیک همه (نمایش‌داده‌شده)</button>
                <button type="button" id="pNone" class="text-[11px] bg-slate-100 hover:bg-slate-200 px-3 py-2 rounded-xl font-bold">برداشتن همه</button>
            </div>
            <div id="plist" class="max-h-80 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-1">
                <?php foreach ($allProducts as $p): ?>
                <label class="pitem flex items-start gap-2 text-xs p-2 rounded-lg hover:bg-slate-50 cursor-pointer" data-name="<?= sanitize(mb_strtolower($p['name'])) ?>">
                    <input type="checkbox" data-compact="products" name="products[]" value="<?= (int)$p['id'] ?>" class="pchk mt-0.5" <?= in_array((int)$p['id'], $editPids, true) ? 'checked' : '' ?>>
                    <span class="flex-1 leading-5"><?= sanitize(mb_substr($p['name'], 0, 80)) ?>
                        <span class="text-slate-400">— <?= number_format($p['sale_price'] && $p['sale_price'] < $p['price'] ? $p['sale_price'] : $p['price']) ?></span></span>
                </label>
                <?php endforeach; ?>
                <?php if (!$allProducts): ?><div class="text-xs text-slate-400 p-3">هنوز محصولی ثبت نشده است.</div><?php endif; ?>
            </div>
        </div>

        <div class="flex gap-2">
            <button name="save_coupon" value="1" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-6 py-2.5 rounded-xl text-xs">💾 ذخیره</button>
            <a href="coupons.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-5 py-2.5 rounded-xl text-xs">انصراف</a>
        </div>
    </form>
<?php endif; ?>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-200">
                <tr class="text-xs text-slate-500">
                    <th class="px-4 py-3 text-right font-bold">کد</th>
                    <th class="px-4 py-3 text-right font-bold">تخفیف</th>
                    <th class="px-4 py-3 text-right font-bold">بازه زمانی (شمسی)</th>
                    <th class="px-4 py-3 text-right font-bold">محصولات</th>
                    <th class="px-4 py-3 text-right font-bold">استفاده</th>
                    <th class="px-4 py-3 text-right font-bold">وضعیت</th>
                    <th class="px-4 py-3 text-right font-bold">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
            <?php if (!$coupons): ?>
                <tr><td colspan="7" class="px-4 py-12 text-center text-slate-400 text-xs">هنوز کد تخفیفی ساخته نشده است</td></tr>
            <?php endif; ?>
            <?php foreach ($coupons as $c): $st = coupon_state($c); ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3"><div class="font-black text-xs" dir="ltr"><?= sanitize($c['code']) ?></div>
                        <?php if ($c['title']): ?><div class="text-[10px] text-slate-400"><?= sanitize($c['title']) ?></div><?php endif; ?></td>
                    <td class="px-4 py-3 text-xs font-bold text-brand-orange"><?= $c['type'] === 'percent' ? fa_digits((int)$c['value']) . '٪' : fa_digits(number_format($c['value'])) . ' تومان' ?></td>
                    <td class="px-4 py-3 text-xs">از <?= jalali_str($c['starts_at']) ?><br>تا <?= jalali_str($c['ends_at']) ?></td>
                    <td class="px-4 py-3 text-xs"><?= fa_digits($c['pcount']) ?> محصول</td>
                    <td class="px-4 py-3 text-xs"><?= fa_digits($c['used_count']) ?><?= $c['max_uses'] !== null ? ' / ' . fa_digits($c['max_uses']) : '' ?></td>
                    <td class="px-4 py-3"><span class="text-[10px] px-2 py-1 rounded-full font-bold <?= $stateLabel[$st][1] ?>"><?= $stateLabel[$st][0] ?></span></td>
                    <td class="px-4 py-3"><div class="flex gap-1 flex-wrap">
                        <a href="?edit=<?= $c['id'] ?>" class="text-[10px] bg-blue-100 hover:bg-blue-200 text-blue-700 px-2 py-1 rounded-lg font-bold">✏ ویرایش</a>
                        <a href="?toggle=<?= $c['id'] ?>" class="text-[10px] bg-amber-100 hover:bg-amber-200 text-amber-700 px-2 py-1 rounded-lg font-bold"><?= $c['status'] ? '⏸ غیرفعال' : '▶ فعال' ?></a>
                        <a href="?delete=<?= $c['id'] ?>" onclick="return confirm('حذف شود؟')" class="text-[10px] bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded-lg font-bold">🗑</a>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</div>

<script>
/* ───── تقویم شمسی ───── */
(function () {
    function g2j(gy, gm, gd) {
        var gdm = [0,31,59,90,120,151,181,212,243,273,304,334];
        var gy2 = gm > 2 ? gy + 1 : gy;
        var days = 355666 + 365*gy + Math.floor((gy2+3)/4) - Math.floor((gy2+99)/100) + Math.floor((gy2+399)/400) + gd + gdm[gm-1];
        var jy = -1595 + 33*Math.floor(days/12053); days %= 12053;
        jy += 4*Math.floor(days/1461); days %= 1461;
        if (days > 365) { jy += Math.floor((days-1)/365); days = (days-1)%365; }
        var jm, jd;
        if (days < 186) { jm = 1 + Math.floor(days/31); jd = 1 + days%31; }
        else { jm = 7 + Math.floor((days-186)/30); jd = 1 + (days-186)%30; }
        return [jy, jm, jd];
    }
    function j2g(jy, jm, jd) {
        jy += 1595;
        var days = -355668 + 365*jy + Math.floor(jy/33)*8 + Math.floor(((jy%33)+3)/4) + jd + (jm < 7 ? (jm-1)*31 : (jm-7)*30 + 186);
        var gy = 400*Math.floor(days/146097); days %= 146097;
        if (days > 36524) { gy += 100*Math.floor(--days/36524); days %= 36524; if (days >= 365) days++; }
        gy += 4*Math.floor(days/1461); days %= 1461;
        if (days > 365) { gy += Math.floor((days-1)/365); days = (days-1)%365; }
        var gd = days + 1;
        var sal = [0,31,((gy%4==0&&gy%100!=0)||gy%400==0)?29:28,31,30,31,30,31,31,30,31,30,31], gm;
        for (gm = 0; gm < 13 && gd > sal[gm]; gm++) gd -= sal[gm];
        return [gy, gm, gd];
    }
    var MONTHS = ['فروردین','اردیبهشت','خرداد','تیر','مرداد','شهریور','مهر','آبان','آذر','دی','بهمن','اسفند'];
    var WD = ['ش','ی','د','س','چ','پ','ج'];
    var fa = function (n) { return String(n).replace(/\d/g, function (d) { return '۰۱۲۳۴۵۶۷۸۹'[d]; }); };
    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    function monthLen(y, m) { if (m <= 6) return 31; if (m <= 11) return 30; var g = j2g(y, 12, 30), b = g2j(g[0], g[1], g[2]); return (b[0] === y && b[1] === 12 && b[2] === 30) ? 30 : 29; }

    var pop = document.createElement('div'); pop.className = 'jdp-pop'; document.body.appendChild(pop);
    var cur = null, vy, vm;

    function render() {
        var t = new Date(), tj = g2j(t.getFullYear(), t.getMonth()+1, t.getDate());
        var sel = cur.value.match(/^(\d{4})\/(\d{2})\/(\d{2})$/), sy = sel ? +sel[1] : 0, sm = sel ? +sel[2] : 0, sd = sel ? +sel[3] : 0;
        var g = j2g(vy, vm, 1), first = (new Date(g[0], g[1]-1, g[2]).getDay() + 1) % 7;
        var h = '<div class="jdp-head"><button type="button" data-n="1">›</button><span>' + MONTHS[vm-1] + ' ' + fa(vy) + '</span><button type="button" data-n="-1">‹</button></div><div class="jdp-grid">';
        WD.forEach(function (w) { h += '<div class="wd">' + w + '</div>'; });
        for (var i = 0; i < first; i++) h += '<div></div>';
        for (var d = 1, L = monthLen(vy, vm); d <= L; d++) {
            var col = (first + d - 1) % 7, c = 'd' + (col === 6 ? ' fri' : '') + (vy===sy&&vm===sm&&d===sd ? ' sel' : '') + (vy===tj[0]&&vm===tj[1]&&d===tj[2] ? ' today' : '');
            h += '<div class="' + c + '" data-d="' + d + '">' + fa(d) + '</div>';
        }
        pop.innerHTML = h + '</div>';
    }
    function open(inp) {
        cur = inp;
        var m = inp.value.match(/^(\d{4})\/(\d{2})\/\d{2}$/), t = new Date(), tj = g2j(t.getFullYear(), t.getMonth()+1, t.getDate());
        vy = m ? +m[1] : tj[0]; vm = m ? +m[2] : tj[1];
        render();
        var r = inp.getBoundingClientRect();
        pop.style.top = (window.scrollY + r.bottom + 4) + 'px';
        pop.style.left = Math.max(8, Math.min(window.scrollX + r.left, document.documentElement.clientWidth - 272)) + 'px';
        pop.classList.add('open');
    }
    pop.addEventListener('click', function (e) {
        var n = e.target.getAttribute('data-n'), d = e.target.getAttribute('data-d');
        if (n) { vm += +n; if (vm > 12) { vm = 1; vy++; } if (vm < 1) { vm = 12; vy--; } render(); }
        if (d) { cur.value = vy + '/' + pad(vm) + '/' + pad(+d); pop.classList.remove('open'); }
    });
    document.addEventListener('click', function (e) {
        if (e.target.classList && e.target.classList.contains('jdp')) { open(e.target); return; }
        if (!pop.contains(e.target)) pop.classList.remove('open');
    });
})();

/* ───── انتخاب محصولات ───── */
(function () {
    var items = Array.prototype.slice.call(document.querySelectorAll('.pitem'));
    if (!items.length) return;
    var cnt = document.getElementById('pcount');
    function upd() { var n = document.querySelectorAll('.pchk:checked').length; cnt.textContent = '(' + n + ' انتخاب‌شده)'; }
    document.getElementById('psearch').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        items.forEach(function (i) { i.style.display = (!q || i.getAttribute('data-name').indexOf(q) > -1) ? '' : 'none'; });
    });
    document.getElementById('pAll').addEventListener('click', function () { items.forEach(function (i) { if (i.style.display !== 'none') i.querySelector('.pchk').checked = true; }); upd(); });
    document.getElementById('pNone').addEventListener('click', function () { items.forEach(function (i) { i.querySelector('.pchk').checked = false; }); upd(); });
    document.getElementById('plist').addEventListener('change', upd);
    upd();
})();
</script>
<script src="../assets/compact-form.js"></script>
<?php require_once '../includes/footer.php'; ?>
