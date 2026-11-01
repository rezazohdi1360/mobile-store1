<?php
if (!isset($pdo)) require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if (!isAdmin()) redirect('../login.php');

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت | <?= sanitize($pageTitle ?? '') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body{font-family:'Vazirmatn',sans-serif;}</style>
</head>
<body class="bg-slate-100 text-slate-800">

<div class="flex min-h-screen">
    
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col fixed h-full">
        <div class="p-5 border-b border-slate-800 flex items-center gap-3">
            <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center">
                <i class="fa-solid fa-mobile-screen-button"></i>
            </div>
            <div>
                <div class="font-black text-sm">پنل مدیریت</div>
                <div class="text-[10px] text-slate-400"><?= htmlspecialchars(SITE_NAME) ?></div>
            </div>
        </div>
        <nav class="flex-1 p-3 space-y-1 text-sm overflow-y-auto">
            <a href="index.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-gauge w-5"></i> داشبورد
            </a>
            <a href="products.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-mobile w-5"></i> محصولات
            </a>
            <a href="../index.php" target="_blank" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-slate-800 transition-colors">
                <i class="fa-solid fa-external-link w-5"></i> مشاهده سایت
            </a>
            <a href="../logout.php" class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-400 hover:bg-red-500/10 transition-colors">
                <i class="fa-solid fa-sign-out-alt w-5"></i> خروج
            </a>
        </nav>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 mr-64">
        <header class="bg-white shadow-sm border-b p-4 sticky top-0 z-30">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-black text-slate-900"><?= sanitize($pageTitle ?? 'داشبورد') ?></h1>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-slate-500">
                        خوش آمدید، <?= sanitize($_SESSION['name']) ?>
                    </span>
                    <div class="w-9 h-9 bg-blue-100 text-blue-600 rounded-full flex items-center justify-center">
                        <i class="fa-solid fa-user"></i>
                    </div>
                </div>
            </div>
        </header>

        <?php if ($flash): ?>
            <div class="p-4">
                <div class="p-4 rounded-xl text-sm font-bold <?= $flash['type'] === 'success' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
                    <?= sanitize($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="p-6">