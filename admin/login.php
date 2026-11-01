<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('admin/index.php');

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'ایمیل و رمز عبور الزامی است';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            redirect('admin/index.php');
        } else {
            $error = 'ایمیل یا رمز عبور اشتباه است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ورود | پنل مدیریت</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>body { font-family: 'Vazirmatn', tahoma; }</style>
</head>
<body class="bg-slate-100 min-h-screen flex items-center justify-center p-4">

<div class="bg-white rounded-2xl p-8 w-full max-w-md shadow-lg">
    <div class="text-center mb-6">
        <div class="text-4xl mb-2">📱</div>
        <h1 class="text-xl font-black">ورود به پنل مدیریت</h1>
    </div>
    
    <?php if ($error): ?>
        <div class="bg-red-50 border border-red-200 text-red-700 p-3 rounded-xl mb-4 text-sm font-bold">
            ❌ <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">ایمیل</label>
            <input type="email" name="email" required dir="ltr"
                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500">
        </div>
        
        <div>
            <label class="block text-xs font-bold text-slate-600 mb-2">رمز عبور</label>
            <input type="password" name="password" required dir="ltr"
                   class="w-full px-4 py-3 border border-slate-200 rounded-xl focus:outline-none focus:border-orange-500">
        </div>
        
        <button class="w-full bg-orange-500 hover:bg-orange-600 text-white font-bold py-3 rounded-xl">
            ورود
        </button>
    </form>
    
    <div class="text-center text-xs text-slate-400 mt-6">
        <a href="index.php">بازگشت به فروشگاه</a>
    </div>
</div>

</body>
</html>