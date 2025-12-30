<?php
/**
 * Jobs Module Entry Point
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Check permission
if (!Permission::can('jobs', 'view_all') && !Permission::can('jobs', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Get action
$action = input('action', 'list');

// Define flag
define('INCLUDED_FROM_INDEX', true);

// Route
switch ($action) {
    case 'list':
        require __DIR__ . '/list.php';
        break;
        
    case 'view':
        require __DIR__ . '/view.php';
        break;
        
    case 'create':
        Permission::require('jobs', 'create');
        require __DIR__ . '/create.php';
        break;
        
    case 'edit':
        Permission::require('jobs', 'edit');
        require __DIR__ . '/edit.php';
        break;
        
    case 'approve':
        Permission::require('jobs', 'approve');
        require __DIR__ . '/approve.php';
        break;
        
    case 'pending':
        Permission::require('jobs', 'approve');
        require __DIR__ . '/pending-approvals.php';
        break;
        
    default:
        require __DIR__ . '/list.php';
        break;
}