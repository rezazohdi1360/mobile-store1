<?php
/**
 * API اپلیکیشن اندروید — خروجی همه‌جا JSON:  { "ok": true, "data": ... }  یا  { "ok": false, "error": "..." }
 * آدرس:  http://سرور/mobile-store/api/index.php?action=...
 */
error_reporting(E_ALL);
ini_set('display_errors', '0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out($data, $code = 200) {
    http_response_code($code);
    $body = json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    header('Content-Length: ' . strlen($body));
    echo $body;
    exit;
}
function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}
set_exception_handler(function ($e) {
    error_log('API error: ' . $e->getMessage());
    fail('خطای داخلی سرور', 500);
});

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/coupons.php';
require_once __DIR__ . '/../includes/featured.php';
require_once __DIR__ . '/../includes/telegram.php';
coupons_ensure($pdo);
featured_ensure($pdo);

// ───────────── جدول توکن‌ها ─────────────
try { $pdo->exec("CREATE TABLE IF NOT EXISTS api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_persian_ci"); } catch (Throwable $e) { error_log('api_tokens: ' . $e->getMessage()); }

// ───────────── ورودی ─────────────
$action = $_GET['action'] ?? '';
$in = $_POST;
$raw = file_get_contents('php://input');
if ($raw !== '' && $raw !== false) {
    $j = json_decode($raw, true);
    if (is_array($j)) $in = array_merge($in, $j);
}
function inp($k, $d = '') { global $in; return $in[$k] ?? $_GET[$k] ?? $d; }

// ───────────── کمکی‌ها ─────────────
function base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))), '/');
    return ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $dir . '/';
}
function img_url($p) {
    $p = trim((string)$p);
    if ($p === '') $p = 'assets/no-image.png';
    if (preg_match('#^https?://#i', $p)) return $p;
    $segs = array_map(fn($s) => rawurlencode(rawurldecode($s)), explode('/', ltrim($p, '/')));
    return base_url() . implode('/', $segs);
}
function final_price($p) { return ($p['sale_price'] && $p['sale_price'] < $p['price']) ? (int)$p['sale_price'] : (int)$p['price']; }
function in_stock($p) { return $p['stock_status'] === 'in_stock' && (int)$p['stock'] > 0 && (int)$p['price'] > 0; }
function product_out(array $p) {
    $fp = final_price($p);
    return [
        'id' => (int)$p['id'], 'name' => $p['name'], 'brand' => (string)$p['brand'],
        'price' => (int)$p['price'], 'final_price' => $fp,
        'discount_percent' => $fp < (int)$p['price'] ? (int)$p['discount_percent'] : 0,
        'in_stock' => in_stock($p), 'image' => img_url($p['main_image']),
        'rating' => (float)$p['rating'],
    ];
}
function bearer() {
    $t = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($t === '' && !empty($_SERVER['HTTP_AUTHORIZATION']) && stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') === 0) $t = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
    if ($t === '' && function_exists('getallheaders')) foreach (getallheaders() as $k => $v) if (strtolower($k) === 'x-auth-token') $t = $v;
    return trim($t);
}
function auth_user(PDO $pdo, $required = true) {
    $t = bearer();
    if ($t !== '') {
        $st = $pdo->prepare("SELECT u.id, u.name, u.email, u.phone, u.role FROM api_tokens t JOIN users u ON u.id = t.user_id WHERE t.token_hash = ? LIMIT 1");
        $st->execute([hash('sha256', $t)]);
        if ($u = $st->fetch()) return $u;
    }
    if ($required) fail('ابتدا وارد حساب کاربری شوید', 401);
    return null;
}
function new_token(PDO $pdo, $userId) {
    $t = bin2hex(random_bytes(32));
    $pdo->prepare("INSERT INTO api_tokens (user_id, token_hash) VALUES (?, ?)")->execute([$userId, hash('sha256', $t)]);
    return $t;
}
function user_out($u) { return ['id' => (int)$u['id'], 'name' => $u['name'], 'email' => $u['email'], 'phone' => (string)$u['phone']]; }

function build_quote(PDO $pdo, $rawItems, $couponCode, $province) {
    $qty = [];
    foreach ((array)$rawItems as $it) {
        $id = (int)($it['id'] ?? 0); $q = max(1, min(99, (int)($it['qty'] ?? 1)));
        if ($id > 0) $qty[$id] = ($qty[$id] ?? 0) + $q;
    }
    $lines = []; $goods = 0; $original = 0; $unavailable = false;
    if ($qty) {
        $ids = array_keys($qty);
        $st = $pdo->prepare("SELECT * FROM products WHERE status = 1 AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        $st->execute($ids);
        foreach ($st->fetchAll() as $p) {
            $q = $qty[$p['id']]; $unit = final_price($p); $ok = in_stock($p) && (int)$p['stock'] >= 1;
            if (!$ok) $unavailable = true;
            $lines[] = ['id' => (int)$p['id'], 'name' => $p['name'], 'image' => img_url($p['main_image']), 'unit_price' => $unit,
                        'original_price' => (int)$p['price'], 'qty' => $q, 'subtotal' => $unit * $q, 'available' => $ok];
            $goods += $unit * $q; $original += (int)$p['price'] * $q;
        }
    }
    $coupon = null; $couponDiscount = 0;
    if (trim((string)$couponCode) !== '' && $lines) {
        $ev = coupon_evaluate($pdo, $couponCode, array_map(fn($l) => ['id' => $l['id'], 'subtotal' => $l['subtotal']], $lines));
        if ($ev['ok']) { $couponDiscount = $ev['discount']; $coupon = ['code' => $ev['coupon']['code'], 'discount' => $couponDiscount, 'error' => '', 'id' => (int)$ev['coupon']['id']]; }
        else $coupon = ['code' => strtoupper(trim($couponCode)), 'discount' => 0, 'error' => $ev['error'], 'id' => 0];
    }
    $payable = $goods - $couponDiscount;
    $shipping = $lines ? ($province ? shipping_quote($province, $payable) : shipping_range($payable)[0]) : 0;
    return ['lines' => $lines, 'goods' => $goods, 'original' => $original, 'product_discount' => $original - $goods,
            'coupon' => $coupon, 'shipping' => $shipping, 'total' => $payable + $shipping,
            'has_unavailable' => $unavailable || count($lines) < count($qty)];
}

// ───────────── مسیردهی ─────────────
switch ($action) {

case 'settings':
    out([
        'site_name' => SITE_NAME, 'owner' => SITE_OWNER, 'phone' => SITE_PHONE, 'phone2' => SITE_PHONE2, 'email' => SITE_EMAIL,
        'address' => SITE_ADDRESS, 'hours' => SITE_HOURS, 'instagram' => SITE_INSTAGRAM, 'telegram' => SITE_TELEGRAM,
        'provinces' => shipping_provinces(), 'base_url' => base_url(),
        'logo' => ($l = icon_trimmed_url(1)) ? img_url($l) : '',
    ]);

case 'home':
    $cats = $pdo->query("SELECT c.id, c.name, COUNT(p.id) AS cnt FROM categories c JOIN products p ON p.category_id = c.id AND p.status = 1 GROUP BY c.id, c.name ORDER BY cnt DESC LIMIT 20")->fetchAll();
    $featured = $pdo->query("SELECT * FROM products WHERE status = 1 AND featured = 1 ORDER BY id DESC LIMIT 12")->fetchAll();
    if (!$featured) $featured = $pdo->query("SELECT * FROM products WHERE status = 1 AND stock_status = 'in_stock' AND stock > 0 AND price > 0 ORDER BY rating DESC, review_count DESC LIMIT 8")->fetchAll();
    $disc = $pdo->query("SELECT * FROM products WHERE status = 1 AND stock_status = 'in_stock' AND sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price ORDER BY discount_percent DESC LIMIT 10")->fetchAll();
    $new = $pdo->query("SELECT * FROM products WHERE status = 1 ORDER BY id DESC LIMIT 10")->fetchAll();
    out([
        'categories' => array_map(fn($c) => ['id' => (int)$c['id'], 'name' => $c['name'], 'count' => (int)$c['cnt']], $cats),
        'featured' => array_map('product_out', $featured), 'discounted' => array_map('product_out', $disc), 'newest' => array_map('product_out', $new),
    ]);

case 'products':
    $cat = (int)inp('cat', 0); $q = trim((string)inp('q', ''));
    $page = max(1, (int)inp('page', 1)); $per = max(1, min(50, (int)inp('per', 20)));
    $where = "WHERE status = 1"; $params = [];
    if ($cat > 0) { $where .= " AND category_id = ?"; $params[] = $cat; }
    if ($q !== '') { $where .= " AND (name LIKE ? OR name_en LIKE ? OR brand LIKE ?)"; $l = '%' . $q . '%'; array_push($params, $l, $l, $l); }
    $order = ['new' => 'id DESC', 'cheap' => 'price ASC', 'expensive' => 'price DESC', 'discount' => 'discount_percent DESC'][inp('sort', 'new')] ?? 'id DESC';
    $c = $pdo->prepare("SELECT COUNT(*) FROM products $where"); $c->execute($params);
    $total = (int)$c->fetchColumn();
    $st = $pdo->prepare("SELECT * FROM products $where ORDER BY $order LIMIT $per OFFSET " . (($page - 1) * $per));
    $st->execute($params);
    out(['items' => array_map('product_out', $st->fetchAll()), 'page' => $page, 'pages' => max(1, (int)ceil($total / $per)), 'total' => $total]);

case 'product':
    $id = (int)inp('id', 0);
    $st = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 1");
    $st->execute([$id]);
    if (!($p = $st->fetch())) fail('محصول پیدا نشد', 404);
    $images = array_values(array_filter(array_map('trim', explode(',', (string)$p['all_images']))));
    if (!$images && $p['main_image']) $images = [$p['main_image']];
    $images = array_map('img_url', $images ?: ['']);
    $sp = $pdo->prepare("SELECT group_name, spec_key, spec_value FROM product_specs WHERE product_id = ? ORDER BY sort_order");
    $sp->execute([$id]);
    $groups = [];
    foreach ($sp->fetchAll() as $s) $groups[$s['group_name']][] = ['key' => $s['spec_key'], 'value' => $s['spec_value']];
    $specs = [];
    foreach ($groups as $g => $items) $specs[] = ['group' => (string)$g, 'items' => $items];
    $rel = [];
    if ($p['category_id']) {
        $r = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND id <> ? AND status = 1 ORDER BY id DESC LIMIT 8");
        $r->execute([$p['category_id'], $id]);
        $rel = array_map('product_out', $r->fetchAll());
    }
    $pdo->prepare("UPDATE products SET views = views + 1 WHERE id = ?")->execute([$id]);
    out(array_merge(product_out($p), [
        'short_desc' => (string)$p['short_desc'], 'description' => (string)$p['description'], 'warranty' => (string)$p['warranty'],
        'seller' => (string)$p['seller'], 'review_count' => (int)$p['review_count'], 'images' => $images, 'specs' => $specs, 'related' => $rel,
    ]));

case 'register':
    $name = trim((string)inp('name')); $email = trim((string)inp('email')); $phone = str_replace([' ', '-'], '', en_digits(trim((string)inp('phone')))); $pw = (string)inp('password');
    if (mb_strlen($name) < 3) fail('نام باید حداقل ۳ حرف باشد');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('ایمیل معتبر نیست');
    if (!preg_match('/^09[0-9]{9}$/', $phone)) fail('شماره موبایل باید به شکل 09xxxxxxxxx باشد');
    if (strlen($pw) < 6) fail('رمز عبور باید حداقل ۶ کاراکتر باشد');
    $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1"); $st->execute([$email]);
    if ($st->fetch()) fail('این ایمیل قبلاً ثبت شده است');
    $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')")->execute([$name, $email, $phone, password_hash($pw, PASSWORD_DEFAULT)]);
    $uid = (int)$pdo->lastInsertId();
    out(['token' => new_token($pdo, $uid), 'user' => ['id' => $uid, 'name' => $name, 'email' => $email, 'phone' => $phone]]);

case 'login':
    $id = trim((string)inp('email')); $pw = (string)inp('password');
    if ($id === '' || $pw === '') fail('ایمیل و رمز عبور را وارد کنید');
    $st = $pdo->prepare("SELECT * FROM users WHERE email = ? OR username = ? LIMIT 1"); $st->execute([$id, $id]);
    $u = $st->fetch();
    if (!$u || !password_verify($pw, $u['password'])) fail('ایمیل یا رمز عبور اشتباه است', 401);
    out(['token' => new_token($pdo, $u['id']), 'user' => user_out($u)]);

case 'me':
    out(user_out(auth_user($pdo)));

case 'logout':
    if ($t = bearer()) $pdo->prepare("DELETE FROM api_tokens WHERE token_hash = ?")->execute([hash('sha256', $t)]);
    out(true);

case 'quote':
    $qt = build_quote($pdo, inp('items', []), inp('coupon', ''), trim((string)inp('province', '')));
    if ($qt['coupon']) unset($qt['coupon']['id']);
    out($qt);

case 'checkout':
    $user = auth_user($pdo, false);
    $name = trim((string)inp('name')); $phone = str_replace([' ', '-', '(', ')'], '', en_digits(trim((string)inp('phone'))));
    $email = trim((string)inp('email')); $province = trim((string)inp('province')); $city = trim((string)inp('city'));
    $address = trim((string)inp('address')); $postal = trim((string)inp('postal_code')); $notes = trim((string)inp('notes'));
    if (mb_strlen($name) < 3) fail('نام باید حداقل ۳ حرف باشد');
    if (!preg_match('/^09[0-9]{9}$/', $phone)) fail('شماره موبایل باید به شکل 09xxxxxxxxx باشد');
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) fail('ایمیل معتبر نیست');
    if (!in_array($province, shipping_provinces(), true)) fail('استان را انتخاب کنید');
    if ($city === '') fail('شهر را وارد کنید');
    if (mb_strlen($address) < 10) fail('آدرس کامل را وارد کنید');
    $qt = build_quote($pdo, inp('items', []), inp('coupon', ''), $province);
    if (!$qt['lines']) fail('سبد خرید خالی است');
    if ($qt['has_unavailable']) fail('سبد شما شامل محصول ناموجود است');
    $couponId = ($qt['coupon'] && !$qt['coupon']['error']) ? $qt['coupon']['id'] : 0;
    $couponCode = $couponId ? $qt['coupon']['code'] : null; $couponDisc = $couponId ? $qt['coupon']['discount'] : 0;

    $pdo->beginTransaction();
    try {
        do {
            $code = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
            $c = $pdo->prepare("SELECT id FROM orders WHERE order_code = ?"); $c->execute([$code]);
        } while ($c->fetch());
        $pdo->prepare("INSERT INTO orders (order_code, user_id, customer_name, phone, email, province, city, address, postal_code, notes,
                subtotal, discount, shipping, total, payment_method, payment_status, status, coupon_code, coupon_discount)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
            ->execute([$code, $user['id'] ?? null, $name, $phone, $email, $province, $city, $address, $postal, $notes,
                $qt['goods'], $qt['product_discount'] + $couponDisc, $qt['shipping'], $qt['total'], 'online', 'pending', 'pending', $couponCode, $couponDisc]);
        $oid = (int)$pdo->lastInsertId();
        $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_name, product_image, unit_price, quantity, subtotal) VALUES (?,?,?,?,?,?,?)");
        foreach ($qt['lines'] as $l) $ins->execute([$oid, $l['id'], $l['name'], $l['image'], $l['unit_price'], $l['qty'], $l['subtotal']]);
        if ($couponId) $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    out(['order_id' => $oid, 'order_code' => $code, 'total' => $qt['total']]);

case 'pay':   // درگاه پرداخت شبیه‌سازی‌شده — مثل payment.php سایت
    $user = auth_user($pdo, false);
    $st = $pdo->prepare("SELECT * FROM orders WHERE order_code = ? LIMIT 1"); $st->execute([trim((string)inp('order_code'))]);
    $o = $st->fetch();
    if (!$o) fail('سفارش پیدا نشد', 404);
    if ($o['user_id'] && (!$user || (int)$user['id'] !== (int)$o['user_id'])) fail('دسترسی به این سفارش مجاز نیست', 403);
    if ($o['payment_status'] === 'paid') out(['order_code' => $o['order_code'], 'paid' => true]);
    if ($o['payment_status'] === 'cancelled') fail('این سفارش لغو شده است');
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing' WHERE id = ?")->execute([$o['id']]);
        $it = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?"); $it->execute([$o['id']]);
        $up = $pdo->prepare("UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?");
        foreach ($it->fetchAll() as $r) if ($r['product_id']) $up->execute([$r['quantity'], $r['product_id']]);
        $pdo->commit();
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    // پاسخ را کامل بفرست، بعد گزارش تلگرام (اگر فعال باشد)
    $body = json_encode(['ok' => true, 'data' => ['order_code' => $o['order_code'], 'paid' => true]], JSON_UNESCAPED_UNICODE);
    header('Content-Length: ' . strlen($body)); echo $body;
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request(); else { while (ob_get_level() > 0) @ob_end_flush(); flush(); }
    try { telegram_notify_order($pdo, (int)$o['id']); } catch (Throwable $e) {}
    exit;

case 'orders':
    $u = auth_user($pdo);
    $st = $pdo->prepare("SELECT id, order_code, total, status, payment_status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 100");
    $st->execute([$u['id']]);
    out(array_map(fn($o) => ['id' => (int)$o['id'], 'code' => $o['order_code'], 'total' => (int)$o['total'], 'status' => $o['status'],
        'paid' => $o['payment_status'] === 'paid', 'date' => jalali_str($o['created_at'], false)], $st->fetchAll()));

case 'order':
    $u = auth_user($pdo);
    $st = $pdo->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?"); $st->execute([(int)inp('id'), $u['id']]);
    if (!($o = $st->fetch())) fail('سفارش پیدا نشد', 404);
    $it = $pdo->prepare("SELECT product_id, product_name, product_image, unit_price, quantity, subtotal FROM order_items WHERE order_id = ?"); $it->execute([$o['id']]);
    out(['id' => (int)$o['id'], 'code' => $o['order_code'], 'status' => $o['status'], 'paid' => $o['payment_status'] === 'paid',
        'date' => jalali_str($o['created_at'], false), 'subtotal' => (int)$o['subtotal'], 'discount' => (int)$o['discount'], 'shipping' => (int)$o['shipping'],
        'total' => (int)$o['total'], 'coupon_code' => (string)($o['coupon_code'] ?? ''), 'province' => $o['province'], 'city' => $o['city'], 'address' => $o['address'],
        'items' => array_map(fn($r) => ['product_id' => (int)$r['product_id'], 'name' => $r['product_name'], 'image' => img_url($r['product_image']),
            'unit_price' => (int)$r['unit_price'], 'qty' => (int)$r['quantity'], 'subtotal' => (int)$r['subtotal']], $it->fetchAll())]);

case 'contact':
    $name = trim((string)inp('name')); $phone = trim((string)inp('phone')); $msg = trim((string)inp('message'));
    if ($name === '' || $phone === '' || $msg === '') fail('همه فیلدها را پر کنید');
    $pdo->prepare("INSERT INTO contact_messages (name, phone, message, product_id) VALUES (?,?,?,NULL)")->execute([$name, $phone, mb_substr($msg, 0, 2000)]);
    out(true);

default:
    fail('عملیات نامعتبر است', 404);
}
