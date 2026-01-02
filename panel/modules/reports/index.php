<?php
/**
 * Reports Module Entry Point
 * Redirects to daily report
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Check basic permission
Permission::require('reports', 'view_dashboard');

// Redirect to daily report
header('Location: /panel/modules/reports/daily.php');
exit;
