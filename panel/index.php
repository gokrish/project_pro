<?php
/**
 * Panel Entry Point
 * Redirect to appropriate landing page
 * 
 * @version 5.0
 */

require_once __DIR__ . '/includes/_init.php';

use ProConsultancy\Core\Auth;

// Redirect to dashboard if logged in
if (Auth::check()) {
    header('Location: /panel/dashboard.php');
    exit;
}

// Redirect to login if not logged in
header('Location: /panel/login.php');
exit;