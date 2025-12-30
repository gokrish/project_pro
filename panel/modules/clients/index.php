<?php
/**
 * Clients Module Entry Point
 * Handles routing for all client operations
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Check basic permission
if (!Permission::can('clients', 'view_all') && !Permission::can('clients', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Get action parameter
$action = input('action', 'list');

// Define included flag for sub-pages
define('INCLUDED_FROM_INDEX', true);

// Route to appropriate page
switch ($action) {
    case 'list':
        require __DIR__ . '/list.php';
        break;
        
    case 'view':
        require __DIR__ . '/view.php';
        break;
        
    case 'create':
        Permission::require('clients', 'create');
        require __DIR__ . '/create.php';
        break;
        
    case 'edit':
        Permission::require('clients', 'edit');
        require __DIR__ . '/edit.php';
        break;
        
    default:
        require __DIR__ . '/list.php';
        break;
}