<?php
// Define constants
define('PANEL_ACCESS', true);
define('ROOT_PATH', dirname(__DIR__));
define('TESTING', true);

// Load configuration
require_once ROOT_PATH . '/includes/config/config.php';

// Load test helpers
require_once __DIR__ . '/TestCase.php';