<?php
/**
 * توابع کمکی
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function sanitize($str) {
    return htmlspecialchars(trim((string)$str), ENT_QUOTES, 'UTF-8');
}

function isAdmin() {
    return !empty($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'admin';
}

function isLoggedIn() {
    return !empty($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (empty($_SESSION['flash'])) return null;
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

function showFlash() {
    $flash = getFlash();
    if (!$flash) return '';
    
    $colors = [
        'success' => 'bg-emerald-50 border-emerald-200 text-emerald-800',
        'error'   => 'bg-red-50 border-red-200 text-red-800',
        'warning' => 'bg-amber-50 border-amber-200 text-amber-800',
        'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
    ];
    $icons = [
        'success' => '✅',
        'error'   => '❌',
        'warning' => '⚠️',
        'info'    => 'ℹ️',
    ];
    
    $color = $colors[$flash['type']] ?? $colors['info'];
    $icon = $icons[$flash['type']] ?? 'ℹ️';
    
    return "<div class=\"$color border rounded-xl p-4 mb-4 text-sm font-bold\">$icon " 
        . htmlspecialchars($flash['message']) . "</div>";
}

function faToEn($str) {
    if (!$str) return $str;
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($fa, $en, $str);
}

function makeSlug($str) {
    $str = trim((string)$str);
    $str = preg_replace('/\s+/', '-', $str);
    $str = preg_replace('/[^\p{L}\p{N}\-]/u', '', $str);
    return $str ?: 'item-' . uniqid();
}

/**
 * لیست ارسالی از فرم: هم فیلد معمولی name[] و هم نسخه‌ی فشرده‌ی name_json
 * (برای دور زدن محدودیت max_input_vars وقتی هزاران چک‌باکس داریم)
 */
function post_list($name) {
    $list = (array)($_POST[$name] ?? []);
    if (!empty($_POST[$name . '_json'])) {
        $j = json_decode((string)$_POST[$name . '_json'], true);
        if (is_array($j)) $list = array_merge($list, $j);
    }
    return array_values(array_unique($list));
}
