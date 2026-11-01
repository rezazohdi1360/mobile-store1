<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();
session_start();
setFlash('success', 'با موفقیت خارج شدید');
redirect('index.php');