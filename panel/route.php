<?php
require_once __DIR__ . '/../includes/config/config.php';
require_once __DIR__ . '/../includes/Core/Auth.php';
require_once __DIR__ . '/../includes/Core/Session.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\Auth;

Session::start();

if (!Auth::check()) {
    header('Location: /panel/login.php');
    exit();
}

// Redirect to dashboard
header('Location: /panel/dashboard.php');
exit();