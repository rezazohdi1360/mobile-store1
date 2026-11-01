<?php
require_once '../config/database.php';
require_once '../includes/functions.php';

if (!isAdmin()) redirect('../login.php');

if (isset($_GET['delete']) && (int)$_GET['delete'] !== $_SESSION['user_id']) {
    $pdo->prepare("DELETE FROM users WHERE id=?")->execute([(int)$_GET['delete']]);
    setFlash('success', 'کاربر حذف شد');
    redirect('users.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_role'])) {
    $uid = (int)$_POST['user_id'];
    $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("UPDATE users SET role=? WHERE id=?")->execute([$role, $uid]);
        setFlash('success', 'نقش کاربر تغییر کرد');
    } else {
        setFlash('error', 'نمی‌توانید نقش خودتان را تغییر دهید');
    }
    redirect('users.php');
}

$users = $pdo->query("
    SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS orders_count 
    FROM users u ORDER BY u.id DESC
")->fetchAll();

$pageTitle = 'مدیریت کاربران';
require_once '../includes/header.php';
?>

<div class="max-w-6xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-black text-slate-900 flex items-center gap-2">
            <span class="w-2 h-7 bg-brand-orange rounded-full"></span>
            👥 مدیریت کاربران
        </h1>
        <a href="index.php" class="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2.5 rounded-xl text-xs">← داشبورد</a>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr class="text-xs text-slate-500">
                        <th class="px-4 py-3 text-right font-bold">#</th>
                        <th class="px-4 py-3 text-right font-bold">نام</th>
                        <th class="px-4 py-3 text-right font-bold">ایمیل</th>
                        <th class="px-4 py-3 text-right font-bold">تلفن</th>
                        <th class="px-4 py-3 text-right font-bold">سفارشات</th>
                        <th class="px-4 py-3 text-right font-bold">نقش</th>
                        <th class="px-4 py-3 text-right font-bold">عملیات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($users as $u): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 text-xs font-bold"><?= $u['id'] ?></td>
                            <td class="px-4 py-3 text-xs font-bold text-slate-800">
                                <?= sanitize($u['name']) ?>
                                <?php if ($u['id'] === $_SESSION['user_id']): ?>
                                    <span class="text-[10px] bg-orange-100 text-brand-orange px-2 py-0.5 rounded-full mr-1">خودتان</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3 text-xs" style="direction:ltr; text-align:right;"><?= sanitize($u['email']) ?></td>
                            <td class="px-4 py-3 text-xs" style="direction:ltr; text-align:right;"><?= sanitize($u['phone'] ?: '—') ?></td>
                            <td class="px-4 py-3 text-xs"><span class="text-brand-orange font-bold"><?= $u['orders_count'] ?></span></td>
                            <td class="px-4 py-3">
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="text-[10px] bg-amber-100 text-amber-700 px-2 py-1 rounded-full font-bold">👑 مدیر</span>
                                <?php else: ?>
                                    <span class="text-[10px] bg-blue-100 text-blue-700 px-2 py-1 rounded-full font-bold">👤 کاربر</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-4 py-3">
                                <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                                    <div class="flex gap-1">
                                        <form method="post" class="inline">
                                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                            <select name="role" onchange="this.form.submit()" class="text-[10px] border border-slate-200 rounded-lg px-2 py-1 font-bold">
                                                <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>👤 کاربر</option>
                                                <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>👑 مدیر</option>
                                            </select>
                                            <input type="hidden" name="change_role" value="1">
                                        </form>
                                        <a href="?delete=<?= $u['id'] ?>" onclick="return confirm('حذف شود؟')" class="text-[10px] bg-red-100 hover:bg-red-200 text-red-700 px-2 py-1 rounded-lg font-bold">🗑</a>
                                    </div>
                                <?php else: ?>
                                    <span class="text-[10px] text-slate-400">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>