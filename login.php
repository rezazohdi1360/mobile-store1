<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) {
    if (isAdmin()) redirect('admin/index.php');
    redirect('account.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'نام کاربری و رمز عبور الزامی است';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            
            // اگر ادمین بود → پنل ادمین، وگرنه → حساب کاربری
            if ($user['role'] === 'admin') {
                redirect('admin/index.php');
            } else {
                redirect('account.php');
            }
        } else {
            $error = 'نام کاربری یا رمز عبور اشتباه است';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ورود | <?= htmlspecialchars(SITE_NAME) ?></title>
<style>
*{box-sizing:border-box}
body{font-family:tahoma,sans-serif;background:#f8fafc;margin:0;padding:0;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
a{text-decoration:none}
.auth-card{background:#fff;border-radius:20px;padding:35px 30px;width:100%;max-width:450px;box-shadow:0 10px 40px rgba(0,0,0,0.08)}
.auth-header{text-align:center;margin-bottom:25px}
.auth-header .icon{font-size:48px;margin-bottom:10px}
.auth-header h1{font-size:22px;margin:0 0 8px 0;color:#111}
.auth-header p{font-size:13px;color:#6b7280;margin:0}
.form-group{margin-bottom:15px}
.form-group label{display:block;font-size:13px;font-weight:bold;color:#374151;margin-bottom:6px}
.form-group input{width:100%;padding:12px 14px;border:1px solid #e5e7eb;border-radius:10px;font-family:tahoma;font-size:14px;transition:border .2s}
.form-group input:focus{outline:none;border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.1)}
.alert{padding:12px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:bold}
.alert.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.btn{width:100%;background:#2563eb;color:#fff;border:none;padding:14px;border-radius:10px;font-size:15px;font-weight:bold;cursor:pointer;font-family:tahoma;transition:background .2s}
.btn:hover{background:#1d4ed8}
.auth-footer{text-align:center;margin-top:20px;font-size:13px;color:#6b7280}
.auth-footer a{color:#2563eb;font-weight:bold}
.auth-footer a:hover{text-decoration:underline}
.demo-box{background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;padding:12px;margin-top:15px;font-size:11px;color:#6b7280;line-height:1.8}
.demo-box strong{color:#374151}
.back-link{display:block;text-align:center;margin-top:12px;font-size:12px;color:#9ca3af}
</style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <div class="icon">🔐</div>
        <h1>ورود به حساب کاربری</h1>
        <p>برای خرید و پیگیری سفارش وارد شوید</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>نام کاربری</label>
            <input type="text" name="username" required dir="ltr" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>رمز عبور</label>
            <input type="password" name="password" required dir="ltr">
        </div>
        
        <button type="submit" class="btn">🔓 ورود</button>
    </form>
    
    <div class="auth-footer">
        حساب کاربری ندارید؟ <a href="register.php">ثبت‌نام کنید</a>
    </div>
    
    <div class="demo-box">
        <strong>🔑 اطلاعات ورود:</strong><br>
        نام کاربری: reza<br>
        رمز عبور: 1328
    </div>
    
    <a href="index.php" class="back-link">← بازگشت به فروشگاه</a>
</div>

</body>
</html>