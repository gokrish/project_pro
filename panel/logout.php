<?php
/**
 * Logout Handler
 * End user session
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Core/Logger.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/Auth.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;

Session::start();

// Log logout
Logger::getInstance()->info('User logged out', [
    'user_code' => Auth::userCode(),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);

// Logout
Auth::logout();

// Redirect to login
header('Location: /panel/login.php');
exit;