<?php
/**
 * ارسال گزارش فروش به کانال تلگرام (عکس محصول + متن)
 */
require_once __DIR__ . '/../config/site.php';
require_once __DIR__ . '/coupons.php';

function tg_conf(PDO $pdo) {
    return [
        'enabled' => site_get($pdo, 'tg_enabled', '0') === '1',
        'token'   => trim(site_get($pdo, 'tg_token', '')),
        'chat'    => trim(site_get($pdo, 'tg_chat', '')),
        'proxy'   => trim(site_get($pdo, 'tg_proxy', '')),
        'api'     => rtrim(trim(site_get($pdo, 'tg_api', '')) ?: 'https://api.telegram.org', '/'),
    ];
}

function tg_esc($s) { return htmlspecialchars((string)$s, ENT_NOQUOTES, 'UTF-8'); }

/** فراخوانی API تلگرام. خروجی: [ok(bool), پیام/توضیح] */
function tg_call(array $conf, $method, array $params) {
    if (!function_exists('curl_init')) return [false, 'افزونه cURL در PHP فعال نیست (در php.ini خط extension=curl را فعال کنید)'];
    $ch = curl_init(($conf['api'] ?? 'https://api.telegram.org') . "/bot{$conf['token']}/$method");
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $params,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 40,
    ]);
    if ($conf['proxy'] !== '') curl_setopt($ch, CURLOPT_PROXY, $conf['proxy']);
    // XAMPP روی ویندوز معمولاً بسته گواهی (CA) ندارد؛ بسته همراه پروژه استفاده می‌شود
    $ca = __DIR__ . '/../config/cacert.pem';
    if (is_file($ca)) curl_setopt($ch, CURLOPT_CAINFO, $ca);
    $res = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) return [false, 'عدم اتصال به تلگرام: ' . $err];
    $j = json_decode($res, true);
    if (!empty($j['ok'])) return [true, 'ارسال شد'];
    return [false, 'خطای تلگرام: ' . ($j['description'] ?? $res)];
}

/** دانلود عکس (آدرس اینترنتی یا مسیر محلی) در فایل موقت */
function tg_fetch_image($src) {
    $tmp = tempnam(sys_get_temp_dir(), 'tgimg');
    if (preg_match('#^https?://#i', $src)) {
        if (!function_exists('curl_init')) return null;
        $fp = fopen($tmp, 'wb');
        $ch = curl_init($src);
        curl_setopt_array($ch, [CURLOPT_FILE => $fp, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_TIMEOUT => 15, CURLOPT_USERAGENT => 'Mozilla/5.0', CURLOPT_MAXFILESIZE => 8 * 1024 * 1024]);
        $ok = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$ok || $code >= 400 || filesize($tmp) < 200) { @unlink($tmp); return null; }
    } else {
        $path = realpath(__DIR__ . '/../' . rawurldecode(ltrim($src, '/')));
        if (!$path || strpos($path, realpath(__DIR__ . '/..')) !== 0 || !is_file($path) || !copy($path, $tmp)) { @unlink($tmp); return null; }
    }
    $info = @getimagesize($tmp);
    if (!$info) { @unlink($tmp); return null; }
    $ext = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_GIF => 'gif'][$info[2]] ?? null;
    if (!$ext) { @unlink($tmp); return null; }
    $final = $tmp . '.' . $ext;
    rename($tmp, $final);
    return $final;
}

/** متن گزارش فروش (بدون اطلاعات شخصی خریدار) */
function tg_order_text(PDO $pdo, array $order, array $items) {
    $name = defined('SITE_NAME') ? SITE_NAME : 'فروشگاه';
    $ts = strtotime($order['created_at']);
    $lines = [];
    $lines[] = '🛒 <b>فروش جدید</b> | ' . tg_esc($name);
    $lines[] = '🧾 کد سفارش: <code>' . tg_esc($order['order_code']) . '</code>';
    $lines[] = '📅 ' . jalali_str(date('Y-m-d')) . ' — ساعت ' . fa_digits(date('H:i'));
    if (!empty($order['province'])) $lines[] = '📍 ' . tg_esc($order['province']);
    $lines[] = '';
    $i = 1;
    foreach ($items as $it) {
        $lines[] = fa_digits($i++) . '. ' . tg_esc(mb_substr($it['product_name'], 0, 90));
        $lines[] = '    ' . fa_digits((int)$it['quantity']) . ' عدد × ' . fa_digits(number_format($it['unit_price'])) . ' تومان';
    }
    $lines[] = '';
    if ((int)($order['coupon_discount'] ?? 0) > 0)
        $lines[] = '🎟 کد تخفیف ' . tg_esc($order['coupon_code']) . ': −' . fa_digits(number_format($order['coupon_discount'])) . ' تومان';
    if ((int)($order['shipping'] ?? 0) > 0) $lines[] = '🚚 هزینه ارسال: ' . fa_digits(number_format($order['shipping'])) . ' تومان';
    $lines[] = '💰 <b>مبلغ پرداخت‌شده: ' . fa_digits(number_format($order['total'])) . ' تومان</b>';
    return implode("\n", $lines);
}

/** ارسال گزارش یک سفارش پرداخت‌شده. خروجی: [ok, پیام] */
function telegram_notify_order(PDO $pdo, $orderId) {
    $conf = tg_conf($pdo);
    if (!$conf['enabled'] || $conf['token'] === '' || $conf['chat'] === '') return [false, 'غیرفعال'];

    $st = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $st->execute([$orderId]);
    $order = $st->fetch();
    if (!$order) return [false, 'سفارش پیدا نشد'];
    $st = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
    $st->execute([$orderId]);
    $items = $st->fetchAll();

    $text = tg_order_text($pdo, $order, $items);

    // عکس‌ها (حداکثر ۱۰ تا، بدون تکرار)
    $files = []; $seen = [];
    foreach ($items as $it) {
        $src = trim((string)($it['product_image'] ?? ''));
        if ($src === '' || isset($seen[$src]) || count($files) >= 10) continue;
        $seen[$src] = 1;
        if ($f = tg_fetch_image($src)) $files[] = $f;
    }

    $caption = mb_strlen($text) <= 1000;
    $res = [false, ''];
    if ($files) {
        if (count($files) === 1) {
            $p = ['chat_id' => $conf['chat'], 'photo' => new CURLFile($files[0])];
            if ($caption) { $p['caption'] = $text; $p['parse_mode'] = 'HTML'; }
            $res = tg_call($conf, 'sendPhoto', $p);
        } else {
            $media = []; $p = ['chat_id' => $conf['chat']];
            foreach ($files as $i => $f) {
                $m = ['type' => 'photo', 'media' => "attach://p$i"];
                if ($i === 0 && $caption) { $m['caption'] = $text; $m['parse_mode'] = 'HTML'; }
                $media[] = $m;
                $p["p$i"] = new CURLFile($f);
            }
            $p['media'] = json_encode($media, JSON_UNESCAPED_UNICODE);
            $res = tg_call($conf, 'sendMediaGroup', $p);
        }
        foreach ($files as $f) @unlink($f);
        if ($res[0] && !$caption) $res = tg_call($conf, 'sendMessage', ['chat_id' => $conf['chat'], 'text' => $text, 'parse_mode' => 'HTML']);
    }
    if (!$files || !$res[0]) {   // بدون عکس یا ارسال عکس ناموفق → فقط متن
        $res2 = tg_call($conf, 'sendMessage', ['chat_id' => $conf['chat'], 'text' => $text, 'parse_mode' => 'HTML']);
        if ($res2[0] || !$files) $res = $res2;
    }
    site_set($pdo, 'tg_last', date('Y-m-d H:i:s') . ' | سفارش ' . $order['order_code'] . ' | ' . ($res[0] ? '✅ ارسال شد' : '❌ ' . $res[1]));
    return $res;
}

/** ارسال بعد از بستن اتصال مشتری تا پرداخت کند نشود */
function telegram_defer(PDO $pdo, $orderId) {
    $conf = tg_conf($pdo);
    if (!$conf['enabled']) return;
    register_shutdown_function(function () use ($pdo, $orderId) {
        ignore_user_abort(true);
        @set_time_limit(120);
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            if (!headers_sent()) { header('Connection: close'); header('Content-Length: 0'); }
            while (ob_get_level() > 0) @ob_end_flush();
            flush();
        }
        try { telegram_notify_order($pdo, $orderId); } catch (Throwable $e) { /* بی‌صدا */ }
    });
}
