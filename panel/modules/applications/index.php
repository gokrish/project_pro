<?php
/**
 * Applications Module Router
 * Central routing for all application-related pages
 */

// Load common bootstrap
require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Jobs Application';
$breadcrumbs = [
    'Application' => '#'
];

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

$page = $_GET['page'] ?? 'dashboard';

$allowedPages = [
    'dashboard', 'list', 'view', 'screening', 'pending_approval',
    'approved', 'submitted', 'interviewing', 'offered', 'placed',
    'rejected', 'pipeline', 'bulk_actions'
];

if (!in_array($page, $allowedPages)) {
    $page = 'dashboard';
}

require $page . '.php';
?>