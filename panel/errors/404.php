<?php
/**
 * 404 Not Found Error Page
 * Page/Resource Not Found
 * 
 * @version 5.1 (REFACTORED)
 * 
 * IMPROVEMENTS:
 * - Proper config loading
 * - External CSS
 * - 404 logging (track broken links)
 * - Branding integration
 * - Try-catch protection
 * - BASE_URL usage
 */

// Set HTTP status code
http_response_code(404);

// Prevent errors from breaking the error page
try {
    // Load configuration
    define('PANEL_ACCESS', true);
    require_once __DIR__ . '/../includes/config/config.php';
    
    // Try to load core classes
    use ProConsultancy\Core\Logger;
    use ProConsultancy\Core\Branding;
    
    // Log the 404 error (helps track broken links)
    try {
        Logger::getInstance()->info('404 Not Found', [
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'referer' => $_SERVER['HTTP_REFERER'] ?? 'Direct',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
        ]);
    } catch (Exception $e) {
        // Logging failed, continue anyway
    }
    
    // Get branding
    try {
        $companyName = Branding::companyName();
    } catch (Exception $e) {
        $companyName = 'ProConsultancy';
    }
    
    $pageTitle = '404 - Page Not Found';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
} catch (Exception $e) {
    // If everything fails, use defaults
    $companyName = 'ProConsultancy';
    $pageTitle = '404 - Page Not Found';
    $baseUrl = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($pageTitle) ?> - <?= htmlspecialchars($companyName) ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    
    <!-- Bootstrap (optional, for consistency) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    
    <!-- Error Page Styles -->
    <link rel="stylesheet" href="<?= $baseUrl ?>/panel/errors/error.css">
</head>
<body class="error-404">
    <div class="error-container">
        <!-- Error Icon -->
        <div class="error-icon">
            <i class="bx bx-search-alt"></i>
        </div>
        
        <!-- Error Code -->
        <div class="error-code">404</div>
        
        <!-- Error Title -->
        <h1 class="error-title">Page Not Found</h1>
        
        <!-- Error Description -->
        <p class="error-description">
            The page you're looking for doesn't exist or has been moved. 
            Please check the URL or navigate back to a safe place.
        </p>
        
        <!-- Action Buttons -->
        <div class="error-actions">
            <a href="<?= $baseUrl ?>/panel/dashboard.php" class="btn btn-primary">
                <i class="bx bx-home-alt"></i>
                Go to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i>
                Go Back
            </a>
        </div>
        
        <!-- Support Section -->
        <div class="error-support">
            Need help? <a href="mailto:support@proconsultancy.be">Contact Support</a> or 
            visit our <a href="<?= $baseUrl ?>/docs">documentation</a>.
        </div>
    </div>
</body>
</html>