<?php
/**
 * Manual autoloader for PHPMailer
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace
    if (strpos($class, 'PHPMailer\\PHPMailer\\') === 0) {
        $file = __DIR__ . '/PHPMailer/' . str_replace('PHPMailer\\PHPMailer\\', '', $class) . '.php';
        
        if (file_exists($file)) {
            require_once $file;
        }
    }
});