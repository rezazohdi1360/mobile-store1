<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/shipping.php';

if (!isAdmin()) redirect('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $updated = 0;
    foreach ($_POST['settings'] as $key => $value) {
        $stmt = $pdo->prepare("UPDATE shipping_settings SET setting_value=? WHERE setting_key=?");
        $stmt->execute([trim($value), $key]);
        if ($stmt->rowCount() > 0) $updated++;
    }
    setFlash('success', "تنظیمات ذخیره شد ($updated مورد)");
    redirect('shipping.php');
}

if (isset($_GET['reset'])) {
    $defaults = ['base_post_express'=>'200000','base_post_normal'=>'200000','base_freight'=>'200000','base_cod'=>'200000','free_shipping_threshold'=>'0'];
    foreach ($defaults as $k => $v) {
        $pdo->prepare("UPDATE shipping_settings SET setting_value=? WHERE setting_key=?")->execute([$v, $k]);
    }
    setFlash('success', 'تنظیمات به پیش‌فرض بازگشت');
    redirect('shipping.php');
}

$settings = $pdo->query("SELECT * FROM shipping_settings ORDER BY sort_order")->fetchAll();
$grouped = [];
foreach ($settings as $s) $grouped[$s['setting_group']][] = $s;

$pageTitle = 'تنظیمات ارسال';
require_once '../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6 flex-wrap gap-3">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            🚚 تنظیمات هزینه ارسال
        </h1>
        <div class="flex gap-2">
            <a href="?reset=1" onclick="return confirm('به پیش‌فرض بازگردد؟')" class="bg-amber-100 hover:bg-amber-200 text-amber-700 font-bold px-4 py-2.5 rounded-xl text-xs">🔄 پیش‌فرض</a>
            <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
        </div>
    </div>

    <div class="bg-orange-50 border border-orange-200 rounded-2xl p-4 mb-6 text-xs text-orange-800 leading-7">
        💡 <strong>راهنما:</strong> هزینه هر استان = <strong>هزینه پایه × ضریب استان</strong><br>
        مثال: پست پیشتاز تهران = <?= number_format((int)getShippingSetting('base_post_express', 200000)) ?> × <?= getShippingSetting('factor_تهران', 0.8) ?> = <strong><?= number_format(calculateShippingCost('تهران','post_express')) ?> تومان</strong>
    </div>

    <form method="post">
        <?php if (isset($grouped['base_cost'])): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-5">
            <h2 class="font-bold text-sm text-slate-900 mb-5 pb-3 border-b border-slate-100">💰 هزینه‌های پایه (تومان)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <?php foreach ($grouped['base_cost'] as $s): ?>
                    <div>
                        <label class="block text-xs font-bold text-slate-600 mb-2"><?= htmlspecialchars($s['setting_label']) ?></label>
                        <input type="number" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" value="<?= htmlspecialchars($s['setting_value']) ?>" min="0" step="1000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (isset($grouped['free_shipping'])): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-5">
            <h2 class="font-bold text-sm text-slate-900 mb-5 pb-3 border-b border-slate-100">🎁 ارسال رایگان</h2>
            <?php foreach ($grouped['free_shipping'] as $s): ?>
                <div>
                    <label class="block text-xs font-bold text-slate-600 mb-2"><?= htmlspecialchars($s['setting_label']) ?></label>
                    <input type="number" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" value="<?= htmlspecialchars($s['setting_value']) ?>" min="0" step="100000" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 rounded-xl text-sm focus:outline-none focus:border-brand-orange">
                    <p class="text-[10px] text-slate-400 mt-1">0 = ارسال رایگان غیرفعال</p>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (isset($grouped['province_factor'])): ?>
        <div class="bg-white border border-slate-200 rounded-2xl p-6 mb-5">
            <h2 class="font-bold text-sm text-slate-900 mb-5 pb-3 border-b border-slate-100">📍 ضریب استان‌ها</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($grouped['province_factor'] as $s): ?>
                    <div class="bg-slate-50 rounded-xl p-3">
                        <label class="block text-xs font-bold text-slate-600 mb-2"><?= htmlspecialchars($s['setting_label']) ?></label>
                        <input type="number" name="settings[<?= htmlspecialchars($s['setting_key']) ?>]" value="<?= htmlspecialchars($s['setting_value']) ?>" min="0" max="5" step="0.1" class="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-sm focus:outline-none focus:border-brand-orange">
                        <p class="text-[10px] text-slate-400 mt-1">پست پیشتاز: <?= number_format(round((int)getShippingSetting('base_post_express', 200000) * (float)$s['setting_value'] / 1000) * 1000) ?> تومان</p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="bg-white border border-slate-200 rounded-2xl p-5 flex justify-between items-center sticky bottom-4 shadow-lg">
            <span class="text-xs text-slate-500">⚠️ بعد از تغییر، ذخیره کنید</span>
            <button name="save_settings" class="bg-brand-orange hover:bg-brand-hover text-white font-bold px-8 py-3 rounded-xl text-sm shadow-md">💾 ذخیره تغییرات</button>
        </div>
    </form>
</div>

<?php require_once '../includes/footer.php'; ?>