<?php
/**
 * تم‌های رنگی سایت — متغیرهای CSS هر صفحه (--navy, --gold, ...) بازنویسی می‌شوند.
 * مدیر تم پیش‌فرض را از «تنظیمات سایت» انتخاب می‌کند؛ بازدیدکننده هم (اگر مدیر اجازه داده باشد) با دکمه 🎨 تم خودش را برمی‌گزیند.
 */
function theme_list() {
    $base = ['paper' => '#ffffff', 'ink' => '#22262e', 'muted' => '#6b7280', 'green' => '#2f6b4f'];
    return [
        'tools'   => ['name' => 'ابزار (نارنجی و سرمه‌ای)', 'v' => $base + ['navy' => '#1e293b', 'navy-deep' => '#0f172a', 'navy-soft' => '#334155', 'gold' => '#f97316', 'gold-light' => '#f59e0b', 'gold-deep' => '#ea580c', 'gold-pale' => '#ffedd5', 'burgundy' => '#c2410c', 'cream' => '#f1f5f9', 'line' => '#e2e8f0', 'ink' => '#0f172a', 'muted' => '#64748b']],
        'classic' => ['name' => 'کلاسیک (سرمه‌ای و طلایی)', 'v' => $base + ['navy' => '#1b2a41', 'navy-deep' => '#111c2d', 'navy-soft' => '#2a3d5c', 'gold' => '#b8923a', 'gold-light' => '#d9b968', 'gold-pale' => '#f4ead2', 'burgundy' => '#7a1f2b', 'cream' => '#faf7f2', 'line' => '#e6dfd0']],
        'ocean'   => ['name' => 'اقیانوس (آبی و فیروزه‌ای)', 'v' => $base + ['navy' => '#0b3c5d', 'navy-deep' => '#072a42', 'navy-soft' => '#1d6a96', 'gold' => '#16a3a8', 'gold-light' => '#5fd1cf', 'gold-pale' => '#dcf4f3', 'burgundy' => '#0e7490', 'cream' => '#f1f8fa', 'line' => '#d3e4ea']],
        'forest'  => ['name' => 'جنگل (سبز و کهربایی)', 'v' => $base + ['navy' => '#1f4d3a', 'navy-deep' => '#12332a', 'navy-soft' => '#2f6b50', 'gold' => '#d39a1f', 'gold-light' => '#efc35a', 'gold-pale' => '#fbf0cc', 'burgundy' => '#8a3b12', 'cream' => '#f4f8f1', 'line' => '#dbe6d6']],
        'royal'   => ['name' => 'سلطنتی (بنفش و صورتی)', 'v' => $base + ['navy' => '#3b2a6b', 'navy-deep' => '#271a4d', 'navy-soft' => '#5a43a0', 'gold' => '#d6336c', 'gold-light' => '#f58fb2', 'gold-pale' => '#fde3ee', 'burgundy' => '#a61e4d', 'cream' => '#f8f5fc', 'line' => '#e4dcf1']],
        'sunset'  => ['name' => 'غروب (نارنجی و قهوه‌ای)', 'v' => $base + ['navy' => '#7c2d12', 'navy-deep' => '#561d0b', 'navy-soft' => '#b0481c', 'gold' => '#f08c00', 'gold-light' => '#ffc247', 'gold-pale' => '#ffefc9', 'burgundy' => '#c92a2a', 'cream' => '#fff8f0', 'line' => '#f1dfc9']],
        'rose'    => ['name' => 'گل رز (سرخابی و صورتی)', 'v' => $base + ['navy' => '#831843', 'navy-deep' => '#5a0f2e', 'navy-soft' => '#b0306a', 'gold' => '#ec4899', 'gold-light' => '#f9a8d4', 'gold-pale' => '#fce7f3', 'burgundy' => '#be123c', 'cream' => '#fff5f9', 'line' => '#f3d9e5']],
        'dark'    => ['name' => 'شب (تیره)', 'v' => ['navy' => '#0b1120', 'navy-deep' => '#050810', 'navy-soft' => '#1c2a47', 'gold' => '#fbbf24', 'gold-light' => '#fcd34d', 'gold-pale' => '#3a3014', 'burgundy' => '#ef4444', 'green' => '#34d399', 'cream' => '#0d1424', 'paper' => '#16203a', 'ink' => '#e5e9f2', 'muted' => '#94a3b8', 'line' => '#2a3a5e']],
    ];
}

function theme_current(PDO $pdo) {
    $list = theme_list();
    $def = site_get($pdo, 'theme_default', 'tools');
    if (!isset($list[$def])) $def = 'tools';
    if (site_get($pdo, 'theme_switcher', '1') !== '0') {
        $c = $_COOKIE['site_theme'] ?? '';
        if (isset($list[$c])) return $c;
    }
    return $def;
}

function theme_css($key) {
    $t = theme_list()[$key] ?? null;
    if (!$t) return '';
    $css = ':root{';
    foreach ($t['v'] as $k => $v) $css .= "--$k:$v;";
    $css .= '}';
    if ($key === 'dark') {   // رنگ‌های ثابت سفید داخل صفحه‌ها در تم تیره
        $css .= 'body{background:var(--cream);color:var(--ink)}input,select,textarea{background:#101a30!important;color:var(--ink)!important;border-color:var(--line)!important}'
              . '.site-header,.top-bar,.product-card,.content-section,.card{background:var(--paper)}'
              . 'img{background:transparent}.product-image,.product-image img{background:#fff}h1,h2,h3,h4,strong,b{color:inherit}'
              . '.logo-text strong,.rv-name,.content-section > h2,.section-header h2,.product-title a{color:var(--ink)!important}'
              . 'table,td,th{color:var(--ink)}.header-actions a,.top-bar a{color:#dbe2f0}.header-actions .register-btn{color:#0b1120}.header-actions .admin-btn{color:var(--gold-light)}'
              . '.search-box input{color:var(--ink)}.search-box button{background:var(--gold);color:#111}';
    }
    return $css;
}

/** تم تیره: هر قاعده‌ی CSS صفحه که «color: var(--navy)» دارد (متن تیره روی زمینه روشن) روشن می‌شود */
function theme_dark_text_fix($buf) {
    $sel = [];
    if (!preg_match_all('#<style[^>]*>(.*?)</style>#si', $buf, $m)) return '';
    foreach ($m[1] as $css) {
        if (!preg_match_all('#([^{}@]+)\{([^{}]*)\}#', $css, $rules, PREG_SET_ORDER)) continue;
        foreach ($rules as $r) {
            if (preg_match('#(?<![-\w])color\s*:\s*var\(--navy(?:-deep|-soft)?\)#i', $r[2]) && stripos($r[1], 'hover') === false) $sel[] = trim($r[1]);
        }
    }
    return $sel ? implode(',', array_unique($sel)) . '{color:#e8ecf5!important}' : '';
}

/** دکمه‌ی انتخاب تم برای بازدیدکننده (شناور) */
function theme_switcher_html(PDO $pdo, $cur) {
    if (site_get($pdo, 'theme_switcher', '1') === '0') return '';
    $items = '';
    foreach (theme_list() as $k => $t) {
        $v = $t['v'];
        $items .= '<button type="button" data-theme="' . $k . '" title="' . htmlspecialchars($t['name']) . '" class="__th' . ($k === $cur ? ' on' : '') . '">'
            . '<span style="background:linear-gradient(135deg,' . $v['navy'] . ' 50%,' . $v['gold'] . ' 50%)"></span><em>' . htmlspecialchars($t['name']) . '</em></button>';
    }
    return '<style>#__thw{position:fixed;left:14px;bottom:84px;z-index:9998;font-family:inherit;direction:rtl}'
        . '#__thb{width:46px;height:46px;border-radius:50%;background:var(--navy);color:var(--gold-light);font-size:22px;box-shadow:0 6px 18px rgba(0,0,0,.3);border:2px solid var(--gold);cursor:pointer}'
        . '#__thp{display:none;position:absolute;left:0;bottom:56px;background:#fff;color:#222;border-radius:12px;box-shadow:0 12px 36px rgba(0,0,0,.3);padding:10px;width:230px;max-height:70vh;overflow:auto}'
        . '#__thw.open #__thp{display:block}#__thp h5{font-size:12px;margin:0 4px 8px;color:#555}'
        . '.__th{display:flex;align-items:center;gap:10px;width:100%;padding:7px 8px;border:0;background:none;border-radius:8px;cursor:pointer;font-family:inherit;font-size:12.5px;color:#222;text-align:right}'
        . '.__th:hover{background:#f1f5f9}.__th.on{background:#eef2ff;font-weight:700}.__th span{width:26px;height:26px;border-radius:50%;flex:none;border:2px solid #fff;box-shadow:0 0 0 1px #cbd5e1}.__th em{font-style:normal}</style>'
        . '<div id="__thw"><button type="button" id="__thb" title="تغییر تم سایت" aria-label="تغییر تم">🎨</button><div id="__thp"><h5>🎨 تم سایت را انتخاب کنید</h5>' . $items . '</div></div>'
        . '<script>(function(){var w=document.getElementById("__thw");document.getElementById("__thb").onclick=function(e){e.stopPropagation();w.classList.toggle("open")};'
        . 'document.addEventListener("click",function(e){if(!w.contains(e.target))w.classList.remove("open")});'
        . 'w.querySelectorAll(".__th").forEach(function(b){b.onclick=function(){document.cookie="site_theme="+b.dataset.theme+";path=/;max-age=31536000;samesite=lax";location.reload()}})})();</script>';
}

/** اتصال به خروجی صفحه‌های عمومی (نه پنل مدیریت/API): تزریق CSS تم قبل از </head> و دکمه‌ی انتخاب قبل از </body> */
function theme_boot(PDO $pdo) {
    if (PHP_SAPI === 'cli') return;
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (strpos($script, '/admin/') !== false || strpos($script, '/api/') !== false || preg_match('#/(invoice|import|welcome|payment)\.php$#', $script)) return;
    $GLOBALS['__theme_pdo'] = $pdo;
    ob_start(function ($buf) {
        if (stripos($buf, '</head>') === false || strpos($buf, '--navy') === false) return $buf;
        $pdo = $GLOBALS['__theme_pdo'];
        try {
            $cur = theme_current($pdo);
            $css = $cur === 'classic' ? '' : '<style id="site-theme">' . theme_css($cur) . ($cur === 'dark' ? theme_dark_text_fix($buf) : '') . '</style>';
            $buf = preg_replace('#</head>#i', $css . '</head>', $buf, 1);
            $buf = preg_replace('#</body>#i', theme_switcher_html($pdo, $cur) . '</body>', $buf, 1);
        } catch (Throwable $e) {}
        return $buf;
    });
}
