<?php
/** فایل‌های دانلودی محصولات (پوشه downloads/) — بعد از پرداخت برای خریدار قابل دانلود است */

function dl_dir() {
    $d = dirname(__DIR__) . '/downloads';
    if (!is_dir($d)) @mkdir($d, 0775, true);
    return $d;
}

function dl_ensure(PDO $pdo) {
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        $cols = [];
        foreach ($pdo->query("SHOW COLUMNS FROM products") as $c) $cols[] = $c['Field'];
        if (!in_array('download_file', $cols)) $pdo->exec("ALTER TABLE products ADD COLUMN download_file VARCHAR(255) NULL");
    } catch (Throwable $e) {
        try { $pdo->query("SELECT download_file FROM products LIMIT 1"); }
        catch (Throwable $e2) { try { $pdo->exec("ALTER TABLE products ADD COLUMN download_file VARCHAR(255) NULL"); } catch (Throwable $e3) { error_log('dl_ensure: ' . $e3->getMessage()); } }
    }
}

/** نام فایل امن (بدون مسیر و نویسه‌های خطرناک؛ حروف فارسی مجاز) */
function dl_safe_name($name) {
    $name = basename(str_replace('\\', '/', (string)$name));
    $name = preg_replace('/[^\p{L}\p{N}._\- ()]+/u', '_', $name);
    $name = trim($name, ". \t");
    return $name === '' ? '' : $name;
}

/** فهرست فایل‌های داخل پوشه downloads */
function dl_list() {
    $out = [];
    foreach (@scandir(dl_dir()) ?: [] as $f) {
        if ($f === '.' || $f === '..' || $f === '.htaccess' || $f === 'index.html') continue;
        $p = dl_dir() . '/' . $f;
        if (is_file($p)) $out[] = ['name' => $f, 'size' => filesize($p)];
    }
    return $out;
}

function dl_size($b) {
    if ($b >= 1048576) return round($b / 1048576, 1) . ' MB';
    return max(1, round($b / 1024)) . ' KB';
}

/** مسیر کامل فایل اگر وجود داشته باشد */
function dl_path($name) {
    $n = dl_safe_name($name);
    if ($n === '') return null;
    $p = dl_dir() . '/' . $n;
    return is_file($p) ? $p : null;
}

/**
 * پردازش آپلود/انتخاب فایل در فرم محصول.
 * @return array [نام فایل نهایی|null, پیام خطا|null]  (نام null یعنی تغییری نکند؛ '' یعنی حذف)
 */
function dl_handle_form($current = '') {
    if (!empty($_POST['download_remove'])) return ['', null];
    $f = $_FILES['download_upload'] ?? null;
    if ($f && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $msgs = [UPLOAD_ERR_INI_SIZE => 'حجم فایل از upload_max_filesize در php.ini بیشتر است', UPLOAD_ERR_FORM_SIZE => 'حجم فایل بیش از حد مجاز است', UPLOAD_ERR_PARTIAL => 'آپلود ناقص انجام شد'];
            return [null, $msgs[$f['error']] ?? 'خطا در آپلود فایل (کد ' . $f['error'] . ')'];
        }
        $name = dl_safe_name($f['name']);
        if ($name === '') return [null, 'نام فایل نامعتبر است'];
        if (preg_match('/\.(php\d?|phtml|phar|htaccess|exe|bat|cmd|sh)$/i', $name)) return [null, 'این نوع فایل مجاز نیست'];
        $dest = dl_dir() . '/' . $name;
        if (is_file($dest)) { $dot = strrpos($name, '.'); $name = ($dot ? substr($name, 0, $dot) : $name) . '-' . time() . ($dot ? substr($name, $dot) : ''); $dest = dl_dir() . '/' . $name; }
        if (!@move_uploaded_file($f['tmp_name'], $dest)) return [null, 'ذخیره فایل در پوشه downloads ممکن نشد (دسترسی نوشتن را بررسی کنید)'];
        return [$name, null];
    }
    $sel = dl_safe_name($_POST['download_existing'] ?? '');
    if ($sel !== '' && dl_path($sel)) return [$sel, null];
    return [null, null];
}

/** بخش HTML فرم مدیر */
function dl_form_html($current = '') {
    $h = '<div class="border border-emerald-200 bg-emerald-50 rounded-2xl p-4 space-y-3">';
    $h .= '<label class="block text-sm font-black text-emerald-800">📥 فایل دانلودی (بعد از پرداخت برای خریدار)</label>';
    if ($current !== '') {
        $ok = dl_path($current);
        $h .= '<div class="text-xs font-bold ' . ($ok ? 'text-emerald-700' : 'text-red-600') . '">فایل فعلی: <span style="direction:ltr;display:inline-block">' . htmlspecialchars($current) . '</span>' . ($ok ? ' (' . dl_size(filesize($ok)) . ')' : ' — در پوشه downloads پیدا نشد!') . '</div>';
        $h .= '<label class="flex items-center gap-2 text-xs text-red-600 font-bold"><input type="checkbox" name="download_remove" value="1"> حذف اتصال فایل از این محصول</label>';
    }
    $h .= '<div><div class="text-xs text-slate-600 mb-1">آپلود فایل جدید در پوشه downloads:</div><input type="file" name="download_upload" class="block w-full text-xs"></div>';
    $files = dl_list();
    if ($files) {
        $h .= '<div><div class="text-xs text-slate-600 mb-1">یا انتخاب از فایل‌های موجود در پوشه:</div><select name="download_existing" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-xl text-xs" style="direction:ltr"><option value="">— بدون تغییر —</option>';
        foreach ($files as $f) $h .= '<option value="' . htmlspecialchars($f['name']) . '">' . htmlspecialchars($f['name']) . ' (' . dl_size($f['size']) . ')</option>';
        $h .= '</select></div>';
    }
    $h .= '<p class="text-[10px] text-slate-500">فایل‌ها در پوشهٔ <b>downloads/</b> ریشهٔ سایت ذخیره می‌شوند و مستقیم قابل دسترسی نیستند؛ فقط خریدارِ پرداخت‌کرده لینک دانلود می‌گیرد. برای فایل‌های حجیم مقدار upload_max_filesize و post_max_size را در php.ini افزایش دهید، یا فایل را دستی در پوشه کپی کنید.</p>';
    return $h . '</div>';
}

/** فایل‌های دانلودی اقلام یک سفارش */
function dl_order_files(PDO $pdo, $orderId) {
    dl_ensure($pdo);
    $st = $pdo->prepare("SELECT oi.id AS item_id, oi.product_name, p.download_file FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? AND p.download_file IS NOT NULL AND p.download_file <> ''");
    $st->execute([$orderId]);
    $out = [];
    foreach ($st->fetchAll() as $r) { $path = dl_path($r['download_file']); if ($path) $out[] = $r + ['size' => filesize($path)]; }
    return $out;
}
