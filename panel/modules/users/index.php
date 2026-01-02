<?php
/**
 * User Management - Entry Point
 * Redirects to user list
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Check permission
Permission::require('users', 'view_all');

// Redirect to list
header('Location: /panel/modules/users/list.php');
exit;