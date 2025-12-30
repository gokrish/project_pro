<?php
/**
 * Submissions Module Entry Point
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Check permission
if (!Permission::can('submissions', 'view_all') && !Permission::can('submissions', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Redirect to list
header('Location: /panel/modules/submissions/list.php');
exit;