<?php
/**
 * Jobs Module Entry Point
 * Redirect to list page
 */

// Define base URL if not already defined
if (!defined('BASE_URL')) {
    define('BASE_URL', '/panel');
}

// Redirect to list page
header('Location: ' . BASE_URL . '/modules/jobs/list.php');
exit;
