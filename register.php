<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

if (isLoggedIn()) redirect('account.php');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    
    if (mb_strlen($name) < 3) {
        $error = 'نام باید حداقل ۳ حرف باشد';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'ایمیل معتبر نیست';
    } elseif (!preg_match('/^09[0-9]{9}$/', str_replace([' ', '-'], '', $phone))) {
        $error = 'شماره موبایل باید به شکل 09xxxxxxxxx باشد';
    } elseif (strlen($password) < 6) {
        $error = 'رمز عبور باید حداقل ۶ کاراکتر باشد';
    } elseif ($password !== $password2) {
        $error = 'رمز عبور و تکرار آن یکسان نیستند';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'این ایمیل قبلاً ثبت شده است';
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name, email, phone, password, role) VALUES (?, ?, ?, ?, 'user')");
            $stmt->execute([$name, $email, $phone, $hashed]);
            
            $_SESSION['user_id'] = $pdo->lastInsertId();
            $_SESSION['name'] = $name;
            $_SESSION['email'] = $email;
            $_SESSION['role'] = 'user';
            
            redirect('account.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ثبت‌نام | <?= htmlspecialchars(SITE_NAME) ?></title>
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
.alert{padding:12px;border-radius:10px;margin-bottom:18px;font-size:13px;font-weight:bold;line-height:1.7}
.alert.error{background:#fef2f2;color:#dc2626;border:1px solid #fecaca}
.alert.success{background:#dcfce7;color:#16a34a;border:1px solid #86efac}
.btn{width:100%;background:#2563eb;color:#fff;border:none;padding:14px;border-radius:10px;font-size:15px;font-weight:bold;cursor:pointer;font-family:tahoma;transition:background .2s}
.btn:hover{background:#1d4ed8}
.auth-footer{text-align:center;margin-top:20px;font-size:13px;color:#6b7280}
.auth-footer a{color:#2563eb;font-weight:bold}
.auth-footer a:hover{text-decoration:underline}
.back-link{display:block;text-align:center;margin-top:12px;font-size:12px;color:#9ca3af}
</style>
</head>
<body>

<div class="auth-card">
    <div class="auth-header">
        <div class="icon">📱</div>
        <h1>ثبت‌نام در <?= htmlspecialchars(SITE_NAME) ?></h1>
        <p>حساب کاربری بسازید و خرید کنید</p>
    </div>
    
    <?php if ($error): ?>
        <div class="alert error">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    
    <form method="POST">
        <div class="form-group">
            <label>نام و نام خانوادگی *</label>
            <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>ایمیل *</label>
            <input type="email" name="email" required dir="ltr" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>شماره موبایل *</label>
            <input type="tel" name="phone" required placeholder="09xxxxxxxxx" dir="ltr" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
        </div>
        
        <div class="form-group">
            <label>رمز عبور *</label>
            <input type="password" name="password" required dir="ltr" minlength="6">
        </div>
        
        <div class="form-group">
            <label>تکرار رمز عبور *</label>
            <input type="password" name="password2" required dir="ltr" minlength="6">
        </div>
        
        <button type="submit" class="btn">✍ ثبت‌نام</button>
    </form>
    
    <div class="auth-footer">
        قبلاً ثبت‌نام کردید؟ <a href="login.php">ورود</a>
    </div>
    
    <a href="index.php" class="back-link">← بازگشت به فروشگاه</a>
</div>

</body>
</html>