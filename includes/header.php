<?php
if (!function_exists('isAdmin')) {
    require_once __DIR__ . '/functions.php';
}
$pageTitle = $pageTitle ?? 'پنل مدیریت';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= sanitize($pageTitle) ?> | <?= htmlspecialchars(SITE_NAME) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: { orange: '#f97316', hover: '#ea580c' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
    <style>
        body { font-family: 'Vazirmatn', tahoma, sans-serif; }
        .line-clamp-1 { display:-webkit-box; -webkit-line-clamp:1; -webkit-box-orient:vertical; overflow:hidden; }
        .line-clamp-2 { display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    </style>
</head>
<body class="bg-slate-100 min-h-screen">

<div class="bg-slate-900 text-white py-3 px-4 sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto flex justify-between items-center">
        <a href="index.php" class="font-black text-lg flex items-center gap-2">
            <span class="w-2 h-6 bg-brand-orange rounded-full"></span>
            📱 پنل مدیریت
        </a>
        <div class="flex items-center gap-3 text-xs">
            <span class="hidden md:inline">👤 <?= sanitize($_SESSION['name'] ?? 'مدیر') ?></span>
            <a href="../index.php" target="_blank" class="bg-slate-700 hover:bg-slate-600 px-3 py-2 rounded-lg font-bold">🌐 سایت</a>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 px-3 py-2 rounded-lg font-bold">🚪 خروج</a>
        </div>
    </div>
</div>

<div class="max-w-7xl mx-auto px-4 pt-4">
    <?= showFlash() ?>
</div>