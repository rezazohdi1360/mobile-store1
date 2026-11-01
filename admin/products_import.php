<?php
/**
 * ویرایش جزئیات محصولات انتخاب‌شده (باکس مشخصات در مدیریت محصولات)
 *   GET  ?editpanel=1&ids=..  (یا &all=1&q=..) → HTML باکس مشخصات
 *   POST action=apply_edit                       → اجرای ویرایش (خالی‌کردن مشخصات بدون تیک)
 */
set_time_limit(0);
ini_set('memory_limit', '1024M');
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/product_files.php';

if (!isAdmin()) { http_response_code(403); die('دسترسی غیرمجاز'); }

// فیلدهای قابل حذف (نام و قیمت اجباری‌اند)
const PIM_FIELDS = [
    'name_en'  => 'نام لاتین',
    'brand'    => 'برند',
    'category' => 'دسته‌بندی',
    'sale'     => 'قیمت قبل/تخفیف',
    'rating'   => 'امتیاز و تعداد نظرات',
    'warranty' => 'گارانتی',
    'seller'   => 'فروشنده',
    'url'      => 'آدرس منبع (لینک وب)',
    'desc'     => 'توضیحات (بررسی تخصصی)',
    'images'   => 'عکس‌ها',
];

function pim_sid($k) { return substr(md5($k), 0, 8); }
function pim_fa($n) { return strtr((string)$n, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }


/** شناسه‌ها: یا لیست انتخابی، یا (حالت «همه») همه‌ی محصولات مطابق جستجو */
function pim_resolve(PDO $pdo, $src) {
    if (!empty($src['all'])) {
        $q = sanitize((string)($src['q'] ?? ''));
        if ($q !== '') { $st = $pdo->prepare("SELECT id FROM products WHERE name LIKE ?"); $st->execute(["%$q%"]); }
        else $st = $pdo->query("SELECT id FROM products");
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
    return pim_ids($src['ids'] ?? '');
}
/** نقشه‌ی شناسه محصول (external_id) ← آیتم فایل JSON (+ عکس‌های پوشه) */
function pim_sources() {
    static $map = null;
    if ($map !== null) return $map;
    $map = [];
    $e = [];
    foreach (pf_load_products($e) as $x) $map[pf_key($x)] = $x;
    if (!$map && is_file(__DIR__ . '/../products.json')) {
        foreach ((pf_parse_json((string)file_get_contents(__DIR__ . '/../products.json')) ?: []) as $x) { $x['_images'] = []; $map[pf_key($x)] = $x; }
    }
    return $map;
}
/** عکس‌های یک محصول از منبع folder یا url → [main, all] یا null اگر آن منبع برایش نبود */
function pim_images($x, $source) {
    if ($source === 'folder') {
        return !empty($x['_images']) ? [$x['_images'][0], implode(',', $x['_images'])] : null;
    }
    $main = trim((string)($x['آدرس عکس اصلی'] ?? ''));
    if ($main === '') return null;
    return [$main, trim((string)($x['همه تصاویر'] ?? '')) ?: $main];
}

function pim_ids($csv) { return array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$csv)), fn($v) => $v > 0))); }

// ════════════════════ پنل ویرایش جزئیات محصولات انتخاب‌شده (جدول مدیریت محصولات) ════════════════════
if (isset($_GET['editpanel'])) {
    header('Content-Type: text/html; charset=utf-8');
    $ids = pim_resolve($pdo, $_GET);
    $allMode = !empty($_GET['all']); $allQ = (string)($_GET['q'] ?? '');
    if (!$ids) { echo '<div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-xl p-3 text-xs">ابتدا با باکس انتخاب (ستون عملیات) محصولات را انتخاب کنید.</div>'; exit; }
    $in = implode(',', $ids);   // فقط عدد صحیح
    $rows = $pdo->query("SELECT * FROM products WHERE id IN ($in)")->fetchAll();
    $cnt = ['name_en' => 'name_en', 'brand' => 'brand', 'category' => 'category_path', 'warranty' => 'warranty', 'seller' => 'seller', 'url' => 'source_url', 'desc' => 'description', 'images' => 'main_image'];
    $stat = [];
    foreach (PIM_FIELDS as $f => $_) {
        if ($f === 'stock') continue;
        $stat[$f] = ['n' => 0, 'sample' => ''];
        foreach ($rows as $r) {
            $v = match ($f) {
                'sale' => ($r['sale_price'] ? number_format($r['price']) . ' ← ' . number_format($r['sale_price']) : ''),
                'rating' => ((float)$r['rating'] > 0 ? $r['rating'] : ''),
                default => (string)($r[$cnt[$f]] ?? ''),
            };
            if ($v !== '' && $v !== null) { $stat[$f]['n']++; if ($stat[$f]['sample'] === '') $stat[$f]['sample'] = mb_substr(strip_tags((string)$v), 0, 50); }
        }
    }
    $specs = $pdo->query("SELECT spec_key, COUNT(*) c, MIN(spec_value) smp FROM product_specs WHERE product_id IN ($in) GROUP BY spec_key ORDER BY c DESC")->fetchAll();
    ?>
    <form id="editForm" method="post" action="products_import.php" class="bg-white border border-blue-200 rounded-2xl p-4 space-y-3">
      <input type="hidden" name="action" value="apply_edit">
      <?php if ($allMode): ?><input type="hidden" name="all" value="1"><input type="hidden" name="q" value="<?= sanitize($allQ) ?>">
      <?php else: ?><input type="hidden" name="ids" value="<?= implode(',', $ids) ?>"><?php endif; ?>
      <div class="text-sm font-black text-slate-800">✏️ ویرایش جزئیات <?= pim_fa(count($ids)) ?> محصول انتخاب‌شده — با برداشتن تیک، آن مشخصه برای همه‌ی این محصولات <u>خالی</u> می‌شود</div>
      <?php
        $src = pim_sources(); $nFolder = $nUrl = $nNone = 0;
        foreach ($rows as $r) {
            $x = $src[$r['external_id'] ?? ''] ?? null;
            if (!$x) { $nNone++; continue; }
            if (pim_images($x, 'folder')) $nFolder++;
            if (pim_images($x, 'url')) $nUrl++;
        }
      ?>
      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 space-y-2 text-xs">
        <div class="font-black text-slate-800">🖼 منبع نمایش عکس محصولات</div>
        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="img_source" value="keep" checked> بدون تغییر</label>
        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="img_source" value="folder"> عکس از <b>پوشه images</b> <span class="text-slate-400">(<?= pim_fa($nFolder) ?> محصول دارای عکس در پوشه)</span></label>
        <label class="flex items-center gap-2 cursor-pointer"><input type="radio" name="img_source" value="url"> عکس از <b>آدرس عکس داخل فایل JSON</b> <span class="text-slate-400">(<?= pim_fa($nUrl) ?> محصول دارای آدرس)</span></label>
        <?php if ($nNone): ?><div class="text-amber-600"><?= pim_fa($nNone) ?> محصول فایل JSON مرجع ندارد (دستی افزوده شده) و تغییر منبع روی آن‌ها اعمال نمی‌شود.</div><?php endif; ?>
        <div class="text-slate-400">اگر تیک «عکس‌ها» در پایین برداشته شود، عکس همه خالی می‌شود و منبع بالا نادیده گرفته می‌شود.</div>
      </div>

      <div class="bg-slate-50 border border-slate-200 rounded-xl p-3 text-xs flex items-center gap-3 flex-wrap">
        <label class="flex items-center gap-2 font-black text-slate-800 cursor-pointer"><input type="checkbox" name="set_stock" value="1"> 📦 تنظیم موجودی همه‌ی این محصولات روی</label>
        <input type="number" name="stock_qty" min="0" max="100000" value="10" class="w-24 px-2 py-1.5 border border-slate-200 rounded-lg text-sm" dir="ltr">
        <span class="text-slate-500">عدد (۰ = ناموجود)</span>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
        <?php foreach ($stat as $f => $d): ?>
        <label class="flex items-start gap-2 bg-slate-50 hover:bg-slate-100 rounded-lg px-3 py-2 cursor-pointer text-xs">
          <input type="checkbox" name="fields[]" value="<?= $f ?>" checked class="mt-1">
          <span class="min-w-0"><b><?= PIM_FIELDS[$f] ?></b> <span class="text-slate-400">(<?= pim_fa($d['n']) ?> محصول)</span>
            <?php if ($d['sample'] !== ''): ?><span class="block text-slate-500 truncate">مثال: <?= sanitize($d['sample']) ?></span><?php endif; ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php if ($specs): ?>
      <div class="text-xs font-black text-slate-700">⚙ مشخصات فنی (<?= pim_fa(count($specs)) ?> نوع)</div>
      <div class="max-h-48 overflow-y-auto grid grid-cols-1 md:grid-cols-2 gap-1 border border-slate-100 rounded-lg p-2">
        <?php foreach ($specs as $s2): ?>
        <label class="flex items-start gap-2 hover:bg-slate-50 rounded px-2 py-1 text-xs cursor-pointer">
          <input type="checkbox" name="keep_specs[]" value="<?= pim_sid($s2['spec_key']) ?>" checked class="mt-0.5">
          <span class="min-w-0"><b><?= sanitize($s2['spec_key']) ?></b> <span class="text-slate-400">(<?= pim_fa($s2['c']) ?>)</span>
            <span class="block text-slate-500 truncate">مثال: <?= sanitize(mb_substr((string)$s2['smp'], 0, 40)) ?></span></span>
        </label>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <button type="submit" onclick="return confirm('ویرایش روی محصولات انتخاب‌شده اجرا شود؟ (مشخصات بدون تیک خالی می‌شوند)')"
              class="bg-green-600 hover:bg-green-700 text-white font-bold px-5 py-2.5 rounded-xl text-xs">✅ ثبت ویرایش و اجرا</button>
    </form>
    <?php
    exit;
}

// ════════════════════ اجرای ویرایش روی محصولات انتخاب‌شده ════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'apply_edit') {
    $ids = pim_resolve($pdo, $_POST);
    if (!$ids) { setFlash('error', 'محصولی انتخاب نشده است'); redirect('products.php'); }
    $keep = array_flip(array_map('strval', (array)($_POST['fields'] ?? [])));
    $keepSpec = array_flip(array_map('strval', (array)($_POST['keep_specs'] ?? [])));
    $set = [];
    if (!isset($keep['name_en']))  $set[] = "name_en = ''";
    if (!isset($keep['brand']))    $set[] = "brand = ''";
    if (!isset($keep['category'])) $set[] = "category_id = NULL, category_path = ''";
    if (!isset($keep['sale']))     $set[] = "sale_price = NULL, discount_percent = 0";
    if (!isset($keep['rating']))   $set[] = "rating = 0, review_count = 0";
    if (!isset($keep['warranty'])) $set[] = "warranty = ''";
    if (!isset($keep['seller']))   $set[] = "seller = ''";
    if (!isset($keep['url']))      $set[] = "source_url = ''";
    if (!isset($keep['desc']))     $set[] = "description = '', short_desc = ''";
    if (!isset($keep['images']))   $set[] = "main_image = '', all_images = ''";
    $cleared = 0; $specDel = 0; $imgSet = 0; $imgMiss = 0; $stockSet = 0;
    $imgSource = in_array($_POST['img_source'] ?? 'keep', ['folder', 'url'], true) ? $_POST['img_source'] : 'keep';
    $setStock = !empty($_POST['set_stock']);
    $qty = max(0, min(100000, (int)($_POST['stock_qty'] ?? 0)));
    $pdo->beginTransaction();
    try {
        foreach (array_chunk($ids, 500) as $chunk) {
            $in = implode(',', $chunk);   // فقط عدد صحیح
            if ($setStock) {
                $n = $pdo->exec("UPDATE products SET stock = $qty, stock_status = CASE WHEN $qty > 0 AND price > 0 THEN 'in_stock' ELSE 'out_of_stock' END WHERE id IN ($in)");
                $stockSet += (int)$n;
            }
            if ($imgSource !== 'keep' && isset($keep['images'])) {
                $src = pim_sources();
                $up = $pdo->prepare("UPDATE products SET main_image = ?, all_images = ? WHERE id = ?");
                foreach ($pdo->query("SELECT id, external_id FROM products WHERE id IN ($in)")->fetchAll() as $r) {
                    $img = isset($src[$r['external_id'] ?? '']) ? pim_images($src[$r['external_id']], $imgSource) : null;
                    if ($img) { $up->execute([$img[0], $img[1], $r['id']]); $imgSet++; } else $imgMiss++;
                }
            }
            if ($set) { $pdo->exec("UPDATE products SET " . implode(', ', $set) . " WHERE id IN ($in)"); $cleared = count($set); }
            $keys = $pdo->query("SELECT DISTINCT spec_key FROM product_specs WHERE product_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN);
            $del = $pdo->prepare("DELETE FROM product_specs WHERE spec_key = ? AND product_id IN ($in)");
            foreach ($keys as $k) {
                if (!isset($keepSpec[pim_sid($k)])) { $del->execute([$k]); $specDel += $del->rowCount(); }
            }
        }
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); setFlash('error', 'خطا در ویرایش: ' . $e->getMessage()); redirect('products.php'); }
    $msg = "✅ ویرایش روی " . count($ids) . " محصول اجرا شد (مشخصه خالی‌شده: $cleared گروه، مشخصه فنی حذف‌شده: $specDel";
    if ($setStock) $msg .= "، موجودی تنظیم‌شده: $stockSet محصول ← $qty";
    if ($imgSource !== 'keep' && isset($keep['images'])) $msg .= "، عکس از " . ($imgSource === 'folder' ? 'پوشه' : 'آدرس JSON') . ": $imgSet محصول" . ($imgMiss ? " ($imgMiss محصول منبع عکس نداشتند و تغییری نکردند)" : '');
    setFlash('success', $msg . ')');
    redirect('products.php');
}

redirect('products.php');
