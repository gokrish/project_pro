<?php
/**
 * Simple PSR-4 style autoloader
 * Project: ProConsultancy
 */

spl_autoload_register(function ($class) {

    // Only load our namespace
    $prefix = 'ProConsultancy\\';
    $baseDir = __DIR__ . '/../includes/';

    // Does the class use our namespace?
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    // Remove namespace prefix
    $relativeClass = substr($class, strlen($prefix));

    // Replace namespace separators with directory separators
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Autoload failed: {$file}");
    }
});
