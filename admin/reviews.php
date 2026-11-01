<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/reviews.php';
require_once '../includes/coupons.php';   // jalali_str

if (!isAdmin()) redirect('../login.php');
reviews_ensure($pdo);

$tab = in_array($_GET['tab'] ?? '', ['pending', 'approved', 'rejected', 'all'], true) ? $_GET['tab'] : 'pending';

// ───── عملیات ─────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    if ($act === 'approve' && $id) {                 // ثبت نهایی (با امکان ویرایش متن)
        $body = trim((string)($_POST['body'] ?? ''));
        if (mb_strlen($body) >= 2) {
            $pdo->prepare("UPDATE product_reviews SET body = ?, status = 'approved', reviewed_at = NOW() WHERE id = ?")->execute([mb_substr($body, 0, 2000), $id]);
            setFlash('success', '✅ نظر ثبت شد و در صفحه محصول نمایش داده می‌شود');
        } else setFlash('error', 'متن نظر خالی است');
    } elseif ($act === 'reject' && $id) {
        $pdo->prepare("UPDATE product_reviews SET status = 'rejected', reviewed_at = NOW() WHERE id = ?")->execute([$id]);
        setFlash('success', 'نظر رد شد');
    } elseif ($act === 'delete' && $id) {
        $pdo->prepare("DELETE FROM product_reviews WHERE id = ?")->execute([$id]);
        setFlash('success', 'نظر حذف شد');
    } elseif ($act === 'approve_all') {
        $n = $pdo->exec("UPDATE product_reviews SET status = 'approved', reviewed_at = NOW() WHERE status = 'pending'");
        setFlash('success', "$n نظر ثبت شد");
    }
    redirect('reviews.php?tab=' . $tab);
}

$cnt = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($pdo->query("SELECT status, COUNT(*) c FROM product_reviews GROUP BY status")->fetchAll() as $r) $cnt[$r['status']] = (int)$r['c'];

$where = $tab === 'all' ? '' : "WHERE r.status = " . $pdo->quote($tab);
$rows = $pdo->query("SELECT r.*, p.name AS product_name FROM product_reviews r LEFT JOIN products p ON p.id = r.product_id $where ORDER BY r.id DESC LIMIT 200")->fetchAll();

$fa = fn($v) => strtr((string)$v, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
$pageTitle = 'نظرات کاربران';
require_once '../includes/header.php';
$tabs = ['pending' => 'در انتظار تأیید', 'approved' => 'ثبت‌شده', 'rejected' => 'رد‌شده', 'all' => 'همه'];
?>
<div class="max-w-5xl mx-auto px-4 py-8">
  <div class="flex justify-between items-center mb-5 flex-wrap gap-3">
    <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2"><span class="w-2 h-7 bg-brand-orange rounded-full"></span>💬 نظرات کاربران</h1>
    <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
  </div>

  <div class="flex gap-2 flex-wrap mb-4 items-center">
    <?php foreach ($tabs as $k => $label): ?>
      <a href="?tab=<?= $k ?>" class="px-4 py-2 rounded-xl text-xs font-bold <?= $tab === $k ? 'bg-slate-900 text-white' : 'bg-white border border-slate-200 text-slate-700' ?>">
        <?= $label ?><?php if ($k !== 'all'): ?> (<?= $fa($cnt[$k]) ?>)<?php endif; ?></a>
    <?php endforeach; ?>
    <?php if ($cnt['pending'] > 1): ?>
      <form method="post" class="mr-auto" onsubmit="return confirm('همه‌ی نظرات در انتظار بدون بررسی ثبت شوند؟')">
        <input type="hidden" name="act" value="approve_all"><button class="bg-emerald-600 text-white px-4 py-2 rounded-xl text-xs font-bold">✅ ثبت همه‌ی در انتظارها</button></form>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-10 text-center text-slate-400 text-sm">نظری در این بخش وجود ندارد.</div>
  <?php endif; ?>

  <div class="space-y-3">
  <?php foreach ($rows as $r):
      $stCls = ['pending' => 'bg-amber-100 text-amber-700', 'approved' => 'bg-green-100 text-green-700', 'rejected' => 'bg-red-100 text-red-700'][$r['status']];
      $stLbl = ['pending' => 'در انتظار', 'approved' => 'ثبت‌شده', 'rejected' => 'رد‌شده'][$r['status']]; ?>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
      <div class="flex items-center gap-3 flex-wrap text-xs mb-2">
        <b class="text-slate-800"><?= sanitize($r['author_name']) ?></b>
        <span class="text-amber-500 tracking-widest"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></span>
        <span class="text-slate-400"><?= $fa(jalali_str($r['created_at'], false)) ?></span>
        <span class="px-2 py-0.5 rounded-full <?= $stCls ?>"><?= $stLbl ?></span>
        <a href="../product.php?id=<?= (int)$r['product_id'] ?>#reviews" target="_blank" class="text-blue-600 mr-auto truncate max-w-xs">🔗 <?= sanitize($r['product_name'] ?? 'محصول حذف‌شده') ?></a>
      </div>
      <form method="post">
        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
        <textarea name="body" rows="3" class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-sm leading-7"><?= sanitize($r['body']) ?></textarea>
        <div class="flex gap-2 mt-2 flex-wrap">
          <?php if ($r['status'] !== 'approved'): ?>
            <button name="act" value="approve" class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold px-4 py-2 rounded-xl text-xs">✅ ثبت نظر (نمایش در سایت)</button>
          <?php else: ?>
            <button name="act" value="approve" class="bg-slate-700 text-white font-bold px-4 py-2 rounded-xl text-xs">💾 ذخیره ویرایش متن</button>
          <?php endif; ?>
          <?php if ($r['status'] !== 'rejected'): ?>
            <button name="act" value="reject" class="bg-amber-500 hover:bg-amber-600 text-white font-bold px-4 py-2 rounded-xl text-xs">❌ رد</button>
          <?php endif; ?>
          <button name="act" value="delete" onclick="return confirm('نظر حذف شود؟')" class="bg-red-100 hover:bg-red-200 text-red-700 font-bold px-4 py-2 rounded-xl text-xs">🗑 حذف</button>
        </div>
      </form>
    </div>
  <?php endforeach; ?>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
