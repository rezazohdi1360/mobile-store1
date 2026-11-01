<?php
/** گزارش PDF به‌روزرسانی قیمت از دی‌جی‌کالا: کاهش/افزایش قیمت، تغییر موجودی، پرفروش‌ترین‌ها، بیشترین تخفیف‌ها */
require_once __DIR__ . '/invoice.php';

function pr_ensure(PDO $pdo) {
    static $done = false; if ($done) return; $done = true;
    foreach ([
        "CREATE TABLE IF NOT EXISTS price_runs (id INT AUTO_INCREMENT PRIMARY KEY, started_at DATETIME NOT NULL, finished_at DATETIME NULL, checked INT DEFAULT 0, updated INT DEFAULT 0, errors INT DEFAULT 0) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS price_changes (id INT AUTO_INCREMENT PRIMARY KEY, run_id INT NOT NULL, product_id INT NOT NULL, name VARCHAR(300), image VARCHAR(500), old_price BIGINT DEFAULT 0, new_price BIGINT DEFAULT 0, old_discount INT DEFAULT 0, new_discount INT DEFAULT 0, stock_change VARCHAR(20) NULL, KEY idx_run (run_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ] as $q) { try { $pdo->exec($q); } catch (Throwable $e) { /* جدول از قبل هست یا پشتیبانی نمی‌شود */ } }
}

function pr_start(PDO $pdo) {
    pr_ensure($pdo);
    $pdo->prepare("INSERT INTO price_runs (started_at) VALUES (?)")->execute([date('Y-m-d H:i:s')]);
    return (int)$pdo->lastInsertId();
}

function pr_finish(PDO $pdo, $run, $checked, $updated, $errors) {
    $pdo->prepare("UPDATE price_runs SET finished_at=?, checked=?, updated=?, errors=? WHERE id=?")->execute([date('Y-m-d H:i:s'), (int)$checked, (int)$updated, (int)$errors, (int)$run]);
}

function pr_record(PDO $pdo, $run, array $p) {
    $pdo->prepare("INSERT INTO price_changes (run_id, product_id, name, image, old_price, new_price, old_discount, new_discount, stock_change) VALUES (?,?,?,?,?,?,?,?,?)")
        ->execute([(int)$run, (int)$p['id'], $p['name'], $p['image'] ?? '', (int)$p['old'], (int)$p['new'], (int)($p['old_pct'] ?? 0), (int)($p['new_pct'] ?? 0), $p['stock'] ?? null]);
}

function pr_runs(PDO $pdo, $limit = 10) {
    pr_ensure($pdo);
    try { return $pdo->query("SELECT * FROM price_runs ORDER BY id DESC LIMIT " . (int)$limit)->fetchAll(); } catch (Throwable $e) { return []; }
}

function pr_pct($old, $new) { return $old > 0 ? (int)round(($new - $old) * 100 / $old) : 0; }

function pr_table($title, $color, array $head, array $rows, $empty = 'موردی وجود ندارد') {
    $h = '<h3 style="color:' . $color . '">' . $title . ' <span style="color:#777;font-size:8pt">(' . inv_fa(count($rows)) . ' مورد)</span></h3>';
    if (!$rows) return $h . '<div style="color:#888">' . $empty . '</div><br>';
    $h .= '<table cellpadding="3" cellspacing="0" width="100%" border="0.5" bordercolor="#d8d2c4"><tr style="background-color:' . $color . ';color:#ffffff" align="center">';
    foreach ($head as $c) $h .= '<th width="' . $c[1] . '%">' . $c[0] . '</th>';
    $h .= '</tr>';
    foreach ($rows as $r) {
        $h .= '<tr nobr="true" align="center">';
        foreach ($r as $i => $cell) $h .= '<td width="' . $head[$i][1] . '%"' . ($i === 2 ? ' align="right"' : '') . '>' . $cell . '</td>';
        $h .= '</tr>';
    }
    return $h . '</table><br>';
}

function pr_thumb($src) { return inv_img_tag(inv_image($src), 'width="30" height="30"'); }

/** @return TCPDF */
function pr_build_pdf(PDO $pdo, $runId) {
    inv_boot(); pr_ensure($pdo);
    $st = $pdo->prepare("SELECT * FROM price_runs WHERE id=?"); $st->execute([(int)$runId]); $run = $st->fetch();
    if (!$run) throw new RuntimeException('گزارشی با این شماره یافت نشد');
    $st = $pdo->prepare("SELECT * FROM price_changes WHERE run_id=? ORDER BY id"); $st->execute([(int)$runId]); $chg = $st->fetchAll();

    $down = $up = $stk = [];
    foreach ($chg as $c) {
        $pc = pr_pct((int)$c['old_price'], (int)$c['new_price']);
        $c['pc'] = $pc;
        if ((int)$c['old_price'] !== (int)$c['new_price']) { if ($c['new_price'] < $c['old_price']) $down[] = $c; else $up[] = $c; }
        if (!empty($c['stock_change'])) $stk[] = $c;
    }
    usort($down, fn($a, $b) => $a['pc'] <=> $b['pc']);
    usort($up, fn($a, $b) => $b['pc'] <=> $a['pc']);

    $navy = '#1b2a41'; $gold = '#b8923a';
    $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(false); $pdf->setPrintFooter(false);
    $pdf->SetCreator(SITE_NAME); $pdf->SetTitle('گزارش به‌روزرسانی قیمت'); $pdf->SetMargins(12, 12, 12); $pdf->SetAutoPageBreak(true, 14);
    $pdf->setRTL(true); $pdf->SetFont('vazirmatn', '', 9); $pdf->AddPage();

    $html = '<table cellpadding="4" width="100%"><tr><td width="60%"><b style="font-size:15pt;color:' . $navy . '">' . inv_h(SITE_NAME) . '</b><br><span style="color:#555">مدیر: ' . inv_h(SITE_OWNER) . '</span></td>'
        . '<td width="40%" align="left"><b style="font-size:15pt;color:' . $gold . '">گزارش به‌روزرسانی قیمت</b><br><span style="color:#555">منبع: دی‌جی‌کالا</span><br><span style="color:#555">تاریخ: ' . inv_fa(jalali_str($run['finished_at'] ?: $run['started_at'], true)) . '</span></td></tr></table><hr style="color:' . $gold . '">';

    $box = fn($t, $v, $bg) => '<td align="center" style="background-color:' . $bg . '"><b style="font-size:14pt">' . inv_fa($v) . '</b><br><span style="font-size:8pt">' . $t . '</span></td>';
    $html .= '<table cellpadding="6" width="100%" cellspacing="3"><tr>'
        . $box('بررسی‌شده', (int)$run['checked'], '#eef2f7') . $box('قیمت کاهش‌یافته', count($down), '#e3f4ea') . $box('قیمت افزایش‌یافته', count($up), '#fde8e8')
        . $box('تغییر موجودی', count($stk), '#fff4d6') . $box('خطا', (int)$run['errors'], '#f1f1f1') . '</tr></table><br>';

    $ph = [['ردیف', 6], ['تصویر', 9], ['نام کالا', 41], ['قیمت قبلی', 17], ['قیمت جدید', 17], ['تغییر', 10]];
    $mk = function (array $list) {
        $o = []; $i = 0;
        foreach (array_slice($list, 0, 40) as $c) { $i++;
            $o[] = [inv_fa($i), pr_thumb($c['image']), inv_h($c['name']), inv_money($c['old_price']), '<b>' . inv_money($c['new_price']) . '</b>', inv_fa(($c['pc'] > 0 ? '+' : '') . $c['pc']) . '٪'];
        }
        return $o;
    };
    $html .= pr_table('محصولات با کاهش قیمت', '#2f6b4f', $ph, $mk($down));
    $html .= pr_table('محصولات با افزایش قیمت', '#b91c1c', $ph, $mk($up));

    $sr = []; $i = 0;
    foreach ($stk as $c) { $i++; $sr[] = [inv_fa($i), pr_thumb($c['image']), inv_h($c['name']), $c['stock_change'] === 'out' ? '<b style="color:#b91c1c">ناموجود شد</b>' : '<b style="color:#2f6b4f">موجود شد</b>']; }
    if ($sr) $html .= pr_table('تغییر موجودی', '#b45309', [['ردیف', 6], ['تصویر', 9], ['نام کالا', 61], ['وضعیت', 24]], $sr);

    // پرفروش‌ترین‌ها (سفارش‌های پرداخت‌شده)
    $best = [];
    try {
        $r = $pdo->query("SELECT oi.product_id, MAX(oi.product_name) AS name, SUM(oi.quantity) AS q, SUM(oi.subtotal) AS s, MAX(p.main_image) AS img
            FROM order_items oi JOIN orders o ON o.id = oi.order_id LEFT JOIN products p ON p.id = oi.product_id
            WHERE o.payment_status = 'paid' GROUP BY oi.product_id ORDER BY q DESC, s DESC LIMIT 10")->fetchAll();
        $i = 0; foreach ($r as $x) { $i++; $best[] = [inv_fa($i), pr_thumb($x['img']), inv_h($x['name']), inv_fa((int)$x['q']) . ' عدد', inv_money($x['s'])]; }
    } catch (Throwable $e) {}
    $html .= pr_table('بیشترین فروش (سفارش‌های پرداخت‌شده)', $navy, [['ردیف', 6], ['تصویر', 9], ['نام کالا', 45], ['تعداد فروش', 17], ['مبلغ فروش', 23]], $best, 'هنوز سفارش پرداخت‌شده‌ای ثبت نشده است');

    // بیشترین تخفیف‌ها (وضعیت فعلی)
    $disc = []; $i = 0;
    foreach ($pdo->query("SELECT name, main_image, price, sale_price, discount_percent FROM products WHERE status = 1 AND sale_price IS NOT NULL AND sale_price > 0 AND sale_price < price ORDER BY discount_percent DESC, price DESC LIMIT 10")->fetchAll() as $x) {
        $i++; $disc[] = [inv_fa($i), pr_thumb($x['main_image']), inv_h($x['name']), '<s>' . inv_money($x['price']) . '</s>', '<b>' . inv_money($x['sale_price']) . '</b>', '<b style="color:#b91c1c">' . inv_fa((int)$x['discount_percent']) . '٪</b>'];
    }
    $html .= pr_table('بیشترین تخفیف‌ها', $gold, [['ردیف', 6], ['تصویر', 9], ['نام کالا', 41], ['قیمت اصلی', 17], ['قیمت نهایی', 17], ['تخفیف', 10]], $disc);

    $html .= '<div style="color:#777;font-size:8pt" align="center">تولیدشده توسط ' . inv_h(SITE_NAME) . '</div>';
    $pdf->writeHTML($html, true, false, true, false, '');
    return $pdf;
}
