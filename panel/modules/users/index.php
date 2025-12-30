<?php
/**
 * Users Module Entry Point
 * Redirect to list page
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Admin only
Permission::require('users', 'view');

header('Location: /panel/modules/users/list.php');
exit;