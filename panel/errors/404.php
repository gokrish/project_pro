<?php
/**
 * 404 Not Found Error Page
 */


use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Branding;

// 2. Set HTTP status code
http_response_code(404);

// 3. Define the path to your vendor/autoload (Crucial for Valet)
// Since you are using namespaces, you MUST include the Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration
define('PANEL_ACCESS', true);
require_once __DIR__ . '/../includes/config/config.php';

// 4. LOGIC IN TRY-CATCH
try {
    // Log the 404 error
          Logger::getInstance()->warning('404 Forbidden access attempt', [
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'user' => $isAuthenticated ? $user['user_code'] : 'Guest',
            'user_role' => $userRole,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    
    $companyName = Branding::companyName();
    $pageTitle = '404 - Page Not Found';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
} catch (\Throwable $e) {
    // Catch everything (Exceptions and Errors)
    $companyName = 'ProConsultancy';
    $pageTitle = '404 - Page Not Found';
    $baseUrl = '';
}
?>