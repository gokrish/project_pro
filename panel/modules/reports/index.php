<?php
/**
 * Reports Module Entry Point
 * Redirect to reports dashboard
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Manager and Admin only
Permission::require('reports', 'view');

header('Location: /panel/modules/reports/daily.php');
exit;