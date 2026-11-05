<?php
/**
 * فاکتور PDF (فارسی، راست‌به‌چپ، همراه عکس محصولات) با کتابخانه‌ی TCPDF (پوشه‌ی lib/tcpdf)
 */
require_once __DIR__ . '/coupons.php';   // jalali_str
require_once __DIR__ . '/assets.php';    // icon_*

function inv_fa($v) { return strtr((string)$v, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']); }
function inv_money($n) { return inv_fa(number_format((float)$n, 0, '', '٬')) . ' تومان'; }
/** متن لاتین/کد را داخل جمله‌ی راست‌به‌چپ سالم نگه می‌دارد */
function inv_ltr($s) { return "\u{202A}" . $s . "\u{202C}"; }
/** فایل تصویر → data-URI (TCPDF در HTML با مسیر مطلق مشکل دارد) */
function inv_img_tag($file, $attrs) {
    if (!$file || !is_file($file)) return '';
    $mime = preg_match('/\.png$/i', $file) ? 'png' : 'jpeg';
    return '<img src="data:image/' . $mime . ';base64,' . base64_encode(file_get_contents($file)) . '" ' . $attrs . '>';
}
function inv_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function inv_boot() {
    if (class_exists('TCPDF', false)) return;
    $tmp = sys_get_temp_dir() . '/';
    foreach ([
        'K_TCPDF_EXTERNAL_CONFIG' => true, 'K_PATH_MAIN' => dirname(__DIR__) . '/lib/tcpdf/', 'K_PATH_FONTS' => dirname(__DIR__) . '/lib/tcpdf/fonts/',
        'K_PATH_URL' => '/', 'K_PATH_IMAGES' => dirname(__DIR__) . '/', 'K_BLANK_IMAGE' => dirname(__DIR__) . '/icon/placeholder/no-image.png', 'K_PATH_CACHE' => $tmp,
        'K_PATH_URL_CACHE' => $tmp, 'PDF_PAGE_FORMAT' => 'A4', 'PDF_PAGE_ORIENTATION' => 'P', 'PDF_CREATOR' => 'Store', 'PDF_AUTHOR' => 'Store', 'PDF_UNIT' => 'mm',
        'PDF_MARGIN_HEADER' => 5, 'PDF_MARGIN_FOOTER' => 10, 'PDF_MARGIN_TOP' => 27, 'PDF_MARGIN_BOTTOM' => 25, 'PDF_MARGIN_LEFT' => 15, 'PDF_MARGIN_RIGHT' => 15,
        'PDF_FONT_NAME_MAIN' => 'helvetica', 'PDF_FONT_SIZE_MAIN' => 10, 'PDF_FONT_NAME_DATA' => 'helvetica', 'PDF_FONT_SIZE_DATA' => 8, 'PDF_FONT_MONOSPACED' => 'courier',
        'PDF_IMAGE_SCALE_RATIO' => 1.25, 'HEAD_MAGNIFICATION' => 1.1, 'K_CELL_HEIGHT_RATIO' => 1.25, 'K_TITLE_MAGNIFICATION' => 1.3, 'K_SMALL_RATIO' => 2/3,
        'K_THAI_TOPCHARS' => true, 'K_TCPDF_CALLS_IN_HTML' => false, 'K_TCPDF_THROW_EXCEPTION_ERROR' => false, 'K_TIMEZONE' => 'Asia/Tehran',
    ] as $k => $v) if (!defined($k)) define($k, $v);
    require_once dirname(__DIR__) . '/lib/tcpdf/tcpdf.php';
}

/** عکس محصول → مسیر فایل JPEG کوچک (کش‌شده) برای PDF؛ همیشه یک فایل معتبر برمی‌گرداند */
function inv_image($src) {
    $root = dirname(__DIR__);
    $cacheDir = $root . '/uploads/cache/invoice';
    if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
    $src = trim((string)$src);
    $out = $cacheDir . '/' . md5($src ?: 'none') . '.jpg';
    if (is_file($out) && filesize($out) > 200) return $out;

    $data = null;
    if (preg_match('#^https?://#i', $src)) {
        $ch = curl_init($src);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_ENCODING => '']);
        if (is_file($root . '/config/cacert.pem')) curl_setopt($ch, CURLOPT_CAINFO, $root . '/config/cacert.pem');
        $d = curl_exec($ch); $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($d !== false && $code === 200) $data = $d;
    } elseif ($src !== '') {
        foreach ([$root . '/' . ltrim($src, '/'), $root . '/' . ltrim(rawurldecode($src), '/')] as $f) {
            if (is_file($f)) { $data = file_get_contents($f); break; }
        }
    }
    if (!function_exists('imagecreatefromstring')) {
        // PHP بدون افزونه GD: عکس JPEG بدون تغییر استفاده می‌شود، در غیر این صورت تصویر پیش‌فرض
        if ($data && substr($data, 0, 2) === "\xFF\xD8" && strlen($data) < 600000) { file_put_contents($out, $data); return $out; }
        $nf = $root . '/icon/placeholder/no-image.jpg';
        return is_file($nf) ? $nf : null;
    }
    $im = $data ? @imagecreatefromstring($data) : false;
    if (!$im) $im = @imagecreatefromstring((string)file_get_contents($root . '/icon/placeholder/no-image.png'));
    if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im); $box = 160; $sc = min($box / max($w, 1), $box / max($h, 1), 1);
    $nw = max(1, (int)round($w * $sc)); $nh = max(1, (int)round($h * $sc));
    $dst = imagecreatetruecolor($nw, $nh);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $im, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagejpeg($dst, $out, 85);
    imagedestroy($im); imagedestroy($dst);
    return is_file($out) ? $out : null;
}

/** @return TCPDF */
function invoice_build_pdf(array $order, array $items) {
    inv_boot();
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
    $pdf->SetCreator(SITE_NAME); $pdf->SetAuthor(SITE_NAME); $pdf->SetTitle('فاکتور ' . $order['order_code']);
    $pdf->SetMargins(12, 12, 12); $pdf->SetAutoPageBreak(true, 14);
    $pdf->setRTL(true);
    $pdf->SetFont('vazirmatn', '', 9);
    $pdf->AddPage();

    $navy = '#1b2a41'; $gold = '#b8923a';
    $date = inv_fa(jalali_str($order['created_at'] ?? date('Y-m-d H:i:s'), true));
    $paid = ($order['payment_status'] ?? '') === 'paid';

    // ── سربرگ: لوگو + مشخصات فروشگاه ──
    $logo = icon_path(1); $logoHtml = '';
    if ($logo && function_exists('icon_trimmed_url')) {
        $t = icon_trimmed_url(1);
        $f = $t ? dirname(__DIR__) . '/' . preg_replace('/\?.*$/', '', rawurldecode($t)) : null;
        $logoHtml = inv_img_tag($f, 'height="55"');
    }
    $shopLines = '<b style="font-size:15pt;color:' . $navy . '">' . inv_h(SITE_NAME) . '</b><br>'
        . '<span style="color:#555">مدیر: ' . inv_h(SITE_OWNER) . '</span>'
        . (SITE_PHONE ? '<br><span style="color:#555">تلفن: ' . inv_fa(inv_h(SITE_PHONE)) . '</span>' : '')
        . (SITE_ADDRESS ? '<br><span style="color:#555">' . inv_h(SITE_ADDRESS) . '</span>' : '');
    $html = '<table cellpadding="4" width="100%"><tr>'
        . '<td width="22%">' . $logoHtml . '</td>'
        . '<td width="48%">' . $shopLines . '</td>'
        . '<td width="30%" align="left"><b style="font-size:17pt;color:' . $gold . '">فاکتور فروش</b><br>'
        . '<span style="color:#555">شماره: ' . inv_h(inv_ltr($order['order_code'])) . '</span><br><span style="color:#555">تاریخ: ' . $date . '</span></td>'
        . '</tr></table><hr style="color:' . $gold . '">';

    // ── مشخصات خریدار ──
    $html .= '<table cellpadding="5" cellspacing="0" width="100%" border="0.5" bordercolor="#d8d2c4"><tr style="background-color:#f4ead2"><td colspan="2"><b>مشخصات خریدار</b></td></tr>'
        . '<tr><td width="50%">نام: <b>' . inv_h($order['customer_name']) . '</b></td><td width="50%">تلفن: ' . inv_fa(inv_h($order['phone'])) . '</td></tr>'
        . '<tr><td colspan="2">نشانی: ' . inv_h(implode('، ', array_filter([$order['province'] ?? '', $order['city'] ?? '', $order['address'] ?? ''], fn($x) => trim((string)$x) !== '')))
        . (!empty($order['postal_code']) ? ' — کد پستی: ' . inv_fa(inv_h($order['postal_code'])) : '') . '</td></tr>'
        . '<tr><td colspan="2">وضعیت پرداخت: <b style="color:' . ($paid ? '#2f6b4f' : '#b45309') . '">' . ($paid ? 'پرداخت‌شده' : 'در انتظار پرداخت') . '</b></td></tr></table><br>';

    // ── اقلام ──
    $html .= '<table cellpadding="4" cellspacing="0" width="100%" border="0.5" bordercolor="#d8d2c4">'
        . '<tr style="background-color:' . $navy . ';color:#ffffff" align="center"><th width="7%">ردیف</th><th width="15%">تصویر</th><th width="34%">نام کالا</th><th width="9%">تعداد</th><th width="17%">قیمت واحد</th><th width="18%">جمع</th></tr>';
    $i = 0;
    foreach ($items as $it) {
        $i++;
        $img = inv_image($it['product_image'] ?? '');
        $imgTag = inv_img_tag($img, 'width="46" height="46"');
        $html .= '<tr nobr="true" align="center"><td width="7%">' . inv_fa($i) . '</td><td width="15%">' . $imgTag . '</td><td width="34%" align="right">' . inv_h($it['product_name']) . '</td>'
            . '<td width="9%">' . inv_fa((int)$it['quantity']) . '</td><td width="17%">' . inv_money($it['unit_price']) . '</td><td width="18%"><b>' . inv_money($it['subtotal']) . '</b></td></tr>';
    }
    $html .= '</table><br>';

    // ── جمع‌ها ──
    $sub = (float)$order['subtotal']; $disc = (float)($order['discount'] ?? 0); $cd = (float)($order['coupon_discount'] ?? 0);
    $rows = [['جمع کالاها', inv_money($sub), '']];
    if ($disc - $cd > 0) $rows[] = ['تخفیف کالاها', '− ' . inv_money($disc - $cd), '#b91c1c'];
    if ($cd > 0) $rows[] = ['کد تخفیف' . (!empty($order['coupon_code']) ? ' (' . inv_h($order['coupon_code']) . ')' : ''), '− ' . inv_money($cd), '#b91c1c'];
    $rows[] = ['هزینه ارسال', inv_money($order['shipping']), ''];
    $html .= '<table cellpadding="4" width="52%" border="0.5" bordercolor="#d8d2c4" cellspacing="0">';
    foreach ($rows as [$l, $v, $c]) $html .= '<tr><td width="50%">' . $l . '</td><td width="50%" align="left"' . ($c ? ' style="color:' . $c . '"' : '') . '>' . $v . '</td></tr>';
    $html .= '<tr style="background-color:#f4ead2"><td><b>مبلغ قابل پرداخت</b></td><td align="left"><b style="font-size:11pt">' . inv_money($order['total']) . '</b></td></tr></table>';

    $html .= '<br><br><div style="color:#777;font-size:8pt" align="center">از خرید شما سپاسگزاریم — ' . inv_h(SITE_NAME)
        . (SITE_PHONE ? ' — ' . inv_fa(inv_h(SITE_PHONE)) : '') . '</div>';

    $pdf->writeHTML($html, true, false, true, false, '');
    return $pdf;
}
