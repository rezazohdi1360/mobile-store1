<?php
/** به‌روزرسانی قیمت محصولات از دی‌جی‌کالا — پردازش دسته‌ای با نوار پیشرفت */
set_time_limit(120);
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/digikala.php';
require_once '../includes/price_report.php';

if (!isAdmin()) { if (isset($_GET['batch'])) { http_response_code(403); exit; } redirect('../login.php'); }
dk_ensure($pdo);

const DK_BATCH = 5;

// ───── شروع/پایان یک اجرا (برای گزارش PDF) ─────
if (isset($_GET['start'])) { header('Content-Type: application/json'); echo json_encode(['run' => pr_start($pdo)]); exit; }
if (isset($_GET['finish'])) {
    header('Content-Type: application/json');
    pr_finish($pdo, (int)($_POST['run'] ?? 0), (int)($_POST['checked'] ?? 0), (int)($_POST['updated'] ?? 0), (int)($_POST['errors'] ?? 0));
    echo json_encode(['ok' => true]); exit;
}

// ───── پردازش یک دسته (AJAX) ─────
if (isset($_GET['batch'])) {
    header('Content-Type: application/json; charset=utf-8');
    $last = (int)($_POST['last_id'] ?? 0);
    $runId = (int)($_POST['run'] ?? 0);
    $syncStock = !empty($_POST['sync_stock']);
    $onlyActive = !empty($_POST['only_active']);
    $conf = dk_conf($pdo);

    $where = "WHERE id > ? AND (external_id LIKE 'dkp-%' OR source_url LIKE '%dkp-%')" . ($onlyActive ? " AND status = 1" : "");
    $st = $pdo->prepare("SELECT id, name, external_id, source_url, price, sale_price, discount_percent, main_image, stock, stock_status FROM products $where ORDER BY id ASC LIMIT " . DK_BATCH);
    $st->execute([$last]);
    $rows = $st->fetchAll();

    $out = []; $upd = $pdo->prepare("UPDATE products SET price=?, sale_price=?, discount_percent=?, last_price_update=NOW() WHERE id=?");
    $touch = $pdo->prepare("UPDATE products SET last_price_update=NOW() WHERE id=?");
    foreach ($rows as $r) {
        $last = (int)$r['id'];
        $o = ['id' => (int)$r['id'], 'name' => mb_substr($r['name'], 0, 60), 'old' => (int)($r['sale_price'] ?: $r['price']), 'new' => null, 'state' => 'error', 'msg' => ''];
        $id = dk_id($r);
        if (!$id) { $o['msg'] = 'شناسه دی‌جی‌کالا ندارد'; $out[] = $o; continue; }
        [$code, $body] = dk_fetch($id, $conf);
        if ($code === 404) { $o['state'] = 'missing'; $o['msg'] = 'در دی‌جی‌کالا پیدا نشد'; $out[] = $o; continue; }
        if ($code !== 200) { $o['msg'] = $code ? "پاسخ $code" : 'عدم اتصال: ' . mb_substr((string)$body, 0, 80); $out[] = $o; usleep(400000); continue; }
        $d = dk_parse($body);
        if (!$d) { $o['msg'] = 'ساختار پاسخ نامعتبر'; $out[] = $o; continue; }

        $changed = false;
        if ($d['price'] > 0) {
            $o['new'] = $d['sale'] ?: $d['price'];
            if ((int)$r['price'] !== $d['price'] || (int)$r['sale_price'] !== (int)$d['sale']) {
                $upd->execute([$d['price'], $d['sale'], $d['pct'], $r['id']]); $changed = true;
                $o['pct'] = $o['new'] && $o['old'] ? (int)round(($o['new'] - $o['old']) * 100 / $o['old']) : 0;
                if ($runId && $o['old'] !== (int)$o['new']) pr_record($pdo, $runId, ['id' => $r['id'], 'name' => $r['name'], 'image' => $r['main_image'], 'old' => $o['old'], 'new' => $o['new'], 'old_pct' => $r['discount_percent'], 'new_pct' => $d['pct']]);
            } else $touch->execute([$r['id']]);
            $o['state'] = $changed ? 'updated' : 'same';
        } else {
            $o['state'] = 'noprice'; $o['msg'] = 'قیمت ندارد' . ($d['status'] ? " ({$d['status']})" : '');
        }
        if ($syncStock) {
            if (!$d['available'] && $r['stock_status'] === 'in_stock') { $pdo->prepare("UPDATE products SET stock_status='out_of_stock' WHERE id=?")->execute([$r['id']]); $o['msg'] .= ' ← ناموجود شد'; if ($runId) pr_record($pdo, $runId, ['id' => $r['id'], 'name' => $r['name'], 'image' => $r['main_image'], 'old' => $o['old'], 'new' => $o['old'], 'stock' => 'out']); }
            elseif ($d['available'] && $r['stock_status'] !== 'in_stock') { $pdo->prepare("UPDATE products SET stock_status='in_stock', stock=CASE WHEN stock<=0 THEN 1 ELSE stock END WHERE id=?")->execute([$r['id']]); $o['msg'] .= ' ← موجود شد'; if ($runId) pr_record($pdo, $runId, ['id' => $r['id'], 'name' => $r['name'], 'image' => $r['main_image'], 'old' => $o['old'], 'new' => $o['old'], 'stock' => 'in']); }
        }
        $out[] = $o;
        usleep(500000);   // فاصله بین درخواست‌ها
    }
    echo json_encode(['rows' => $out, 'last_id' => $last, 'done' => count($rows) < DK_BATCH], JSON_UNESCAPED_UNICODE);
    exit;
}

// ───── ذخیره تنظیمات ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_dk'])) {
    site_set($pdo, 'dk_proxy', trim($_POST['dk_proxy'] ?? ''));
    $b = trim($_POST['dk_api_base'] ?? '');
    site_set($pdo, 'dk_api_base', preg_match('#^https?://#i', $b) ? $b : '');
    setFlash('success', 'تنظیمات ذخیره شد');
    redirect('price_update.php');
}

$total = (int)$pdo->query("SELECT COUNT(*) FROM products WHERE external_id LIKE 'dkp-%' OR source_url LIKE '%dkp-%'")->fetchColumn();
$all   = (int)$pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
$lastUpd = '';
try { $lastUpd = (string)$pdo->query("SELECT MAX(last_price_update) FROM products")->fetchColumn(); } catch (Throwable $e) {}
$conf = dk_conf($pdo);
$runs = pr_runs($pdo, 8);

$pageTitle = 'به‌روزرسانی قیمت از دی‌جی‌کالا';
require_once '../includes/header.php';
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
    <div>
      <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2"><span class="w-2 h-7 bg-brand-orange rounded-full"></span>💰 به‌روزرسانی قیمت از دی‌جی‌کالا</h1>
      <p class="text-xs text-slate-500 mt-1">قیمت فعلی هر محصول از API دی‌جی‌کالا خوانده و روی محصول اعمال می‌شود (قیمت اصلی + قیمت با تخفیف).</p>
    </div>
    <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
  </div>

  <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-5">
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3 text-center text-xs mb-4">
      <div class="bg-slate-50 rounded-xl p-3"><div class="text-2xl font-black text-blue-600" id="stTotal"><?= $total ?></div>محصول دارای شناسه دی‌جی‌کالا</div>
      <div class="bg-slate-50 rounded-xl p-3"><div class="text-2xl font-black text-slate-500"><?= $all - $total ?></div>بدون شناسه (به‌روز نمی‌شوند)</div>
      <div class="bg-slate-50 rounded-xl p-3 col-span-2 md:col-span-1"><div class="text-sm font-black text-slate-700 mt-2"><?= $lastUpd ? sanitize($lastUpd) : '—' ?></div>آخرین به‌روزرسانی قیمت</div>
    </div>

    <div class="flex items-center gap-4 flex-wrap text-xs mb-4">
      <label class="flex items-center gap-2"><input type="checkbox" id="optActive" checked> فقط محصولات نمایش‌داده‌شده در سایت</label>
      <label class="flex items-center gap-2"><input type="checkbox" id="optStock"> موجودی را هم با دی‌جی‌کالا هماهنگ کن (ناموجود/موجود)</label>
    </div>
    <div class="flex gap-2 flex-wrap">
      <button id="goBtn" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-6 py-3 rounded-xl text-sm" <?= $total ? '' : 'disabled' ?>>🔄 شروع به‌روزرسانی قیمت‌ها</button>
      <button id="stopBtn" class="bg-red-500 hover:bg-red-600 text-white font-bold px-6 py-3 rounded-xl text-sm hidden" style="display:none">⏹ توقف</button>
    </div>
    <div class="text-[11px] text-amber-600 mt-3">⚠ تخفیف دستی‌ای که برای محصول گذاشته‌اید با قیمت دی‌جی‌کالا جایگزین می‌شود. محصولات هر ۵ تا با فاصله بررسی می‌شوند؛ صفحه را تا پایان باز نگه دارید.</div>

    <div id="reportBox" style="display:none" class="mt-4"><a id="reportLink" href="#" class="inline-block bg-indigo-600 hover:bg-indigo-700 text-white font-bold px-6 py-3 rounded-xl text-sm">📄 دانلود گزارش PDF (کاهش/افزایش قیمت، پرفروش‌ها، بیشترین تخفیف)</a></div>
    <div id="prog" style="display:none" class="mt-5">
      <div class="w-full bg-slate-100 rounded-full h-3 overflow-hidden"><div id="bar" class="h-3 bg-emerald-500" style="width:0%"></div></div>
      <div class="text-xs text-slate-600 mt-2" id="sum"></div>
    </div>
  </div>

  <div id="logWrap" style="display:none" class="bg-white border border-slate-200 rounded-2xl overflow-x-auto mb-5">
    <table class="w-full text-xs"><thead class="bg-slate-50"><tr class="text-slate-500">
      <th class="px-3 py-2 text-right">#</th><th class="px-3 py-2 text-right">محصول</th><th class="px-3 py-2 text-right">قیمت قبلی</th><th class="px-3 py-2 text-right">قیمت جدید</th><th class="px-3 py-2 text-right">وضعیت</th></tr></thead>
    <tbody id="log" class="divide-y divide-slate-100"></tbody></table>
  </div>

  <?php if ($runs): ?>
  <div class="bg-white border border-slate-200 rounded-2xl p-5 mb-5">
    <div class="text-sm font-black text-slate-800 mb-3">📄 گزارش‌های قبلی</div>
    <?php foreach ($runs as $rn): ?>
      <div class="flex justify-between items-center text-xs py-2 border-b border-slate-100">
        <span>اجرای #<?= (int)$rn['id'] ?> — <?= sanitize($rn['started_at']) ?> — بررسی‌شده: <?= (int)$rn['checked'] ?> | به‌روز شده: <?= (int)$rn['updated'] ?></span>
        <a class="text-indigo-600 font-bold" href="price_report.php?run=<?= (int)$rn['id'] ?>">⬇ PDF</a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <form method="post" class="bg-white border border-slate-200 rounded-2xl p-5">
    <div class="text-sm font-black text-slate-800 mb-3">⚙ تنظیمات اتصال</div>
    <div class="grid md:grid-cols-2 gap-3 text-xs">
      <label>پراکسی (اختیاری — اگر سرور شما به دی‌جی‌کالا دسترسی ندارد)
        <input name="dk_proxy" dir="ltr" value="<?= sanitize($conf['proxy']) ?>" placeholder="socks5://127.0.0.1:1080 یا http://host:port" class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"></label>
      <label>آدرس پایه API (پیش‌فرض خالی)
        <input name="dk_api_base" dir="ltr" value="<?= sanitize(site_get($pdo, 'dk_api_base', '')) ?>" placeholder="https://api.digikala.com" class="w-full mt-1 px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl"></label>
    </div>
    <button name="save_dk" value="1" class="mt-3 bg-slate-800 text-white font-bold px-5 py-2 rounded-xl text-xs">💾 ذخیره تنظیمات</button>
  </form>
</div>

<script>
(function(){
  var go=document.getElementById('goBtn'), stop=document.getElementById('stopBtn'), bar=document.getElementById('bar'), sum=document.getElementById('sum'),
      log=document.getElementById('log'), TOTAL=<?= (int)$total ?>, running=false, runId=0, c={updated:0,same:0,error:0,other:0,n:0};
  function fa(n){return String(n).replace(/\d/g,function(d){return '۰۱۲۳۴۵۶۷۸۹'[d]});}
  function money(v){return v==null?'—':fa(Number(v).toLocaleString('en-US'));}
  var label={updated:['✅ به‌روز شد','#059669'],same:['➖ بدون تغییر','#64748b'],error:['❌ خطا','#dc2626'],missing:['⚠ پیدا نشد','#d97706'],noprice:['⚠ بدون قیمت','#d97706']};
  function render(){
    sum.textContent='بررسی‌شده: '+fa(c.n)+' از '+fa(TOTAL)+' | به‌روز شده: '+fa(c.updated)+' | بدون تغییر: '+fa(c.same)+' | خطا: '+fa(c.error)+' | سایر: '+fa(c.other);
    bar.style.width=Math.min(100,TOTAL?c.n*100/TOTAL:100)+'%';
  }
  function finish(msg){
    if(runId){var f=new FormData();f.append('run',runId);f.append('checked',c.n);f.append('updated',c.updated);f.append('errors',c.error);
      fetch('price_update.php?finish=1',{method:'POST',body:f,credentials:'same-origin'}).then(function(){
        var a=document.getElementById('reportLink'); a.href='price_report.php?run='+runId; document.getElementById('reportBox').style.display='block';
        var g=document.createElement('iframe'); g.style.display='none'; g.src=a.href; document.body.appendChild(g);
      }); runId=0;}
    running=false;go.disabled=false;stop.style.display='none';sum.textContent+=' — '+msg;}
  function step(last){
    if(!running) return finish('متوقف شد');
    var fd=new FormData(); fd.append('last_id',last); fd.append('run',runId);
    if(document.getElementById('optStock').checked) fd.append('sync_stock','1');
    if(document.getElementById('optActive').checked) fd.append('only_active','1');
    fetch('price_update.php?batch=1',{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){if(!r.ok)throw new Error('HTTP '+r.status);return r.json();}).then(function(d){
      d.rows.forEach(function(o){
        c.n++; if(o.state==='updated')c.updated++; else if(o.state==='same')c.same++; else if(o.state==='error')c.error++; else c.other++;
        var l=label[o.state]||[o.state,'#000'], tr=document.createElement('tr');
        tr.innerHTML='<td class="px-3 py-2">'+o.id+'</td><td class="px-3 py-2"></td><td class="px-3 py-2">'+money(o.old)+'</td><td class="px-3 py-2 font-bold">'+money(o.new)+'</td><td class="px-3 py-2" style="color:'+l[1]+'">'+l[0]+(o.msg?' <span style="color:#94a3b8">'+o.msg.replace(/</g,'&lt;')+'</span>':'')+'</td>';
        tr.children[1].textContent=o.name; log.insertBefore(tr,log.firstChild);
      });
      render();
      if(d.done) finish('پایان ✔'); else step(d.last_id);
    }).catch(function(e){finish('خطا در ارتباط: '+e.message);});
  }
  go.addEventListener('click',function(){
    if(!confirm('قیمت محصولات از دی‌جی‌کالا خوانده و اعمال شود؟')) return;
    c={updated:0,same:0,error:0,other:0,n:0}; log.innerHTML=''; running=true; go.disabled=true; stop.style.display='inline-block';
    document.getElementById('reportBox').style.display='none';
    document.getElementById('prog').style.display='block'; document.getElementById('logWrap').style.display='block'; render();
    fetch('price_update.php?start=1',{credentials:'same-origin'}).then(function(r){return r.json();}).then(function(d){runId=d.run;step(0);}).catch(function(){step(0);});
  });
  stop.addEventListener('click',function(){running=false;});
})();
</script>
<?php require_once '../includes/footer.php'; ?>
