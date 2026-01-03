<?php
/**
 * Logout - Token-based
 */
require_once __DIR__ . '/../includes/config/config.php';
require_once __DIR__ . '/../includes/Core/Session.php';
require_once __DIR__ . '/../includes/Core/Auth.php';

use ProConsultancy\Core\Session;
use ProConsultancy\Core\Auth;

Session::start();

// Logout (deletes token from database)
Auth::logout();

// Redirect to login
header('Location: /panel/login.php');
exit;
