<?php

use App\Core\App;

/**
 * ProConsultancy ATS - Entry Point
 */

// Define root path
define('ROOT_PATH', dirname(__DIR__));

// Load Composer Autoloader
require_once ROOT_PATH . '/vendor/autoload.php';

// Bootstrap Application
$app = new App();
$app->run();
