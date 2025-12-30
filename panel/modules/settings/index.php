<?php
/**
 * Settings Module Entry Point
 * Redirect to company settings
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;

// Admin only
Permission::require('settings', 'view');

header('Location: /panel/modules/settings/company.php');
exit;