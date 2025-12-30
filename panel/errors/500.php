<?php
/**
 * 500 Internal Server Error Page
 * Critical server errors
 * 
 * @version 5.0
 * 
 */

// Set HTTP status code
http_response_code(500);

// Initialize error tracking
$errorId = uniqid('ERR-');
$showDetails = false;

// Prevent errors from breaking the error page
try {
    // Load configuration
    define('PANEL_ACCESS', true);
    require_once __DIR__ . '/../includes/config/config.php';
    
    // Try to load core classes
    use ProConsultancy\Core\Logger;
    use ProConsultancy\Core\Branding;
    
    // Check if we should show error details
    $showDetails = defined('APP_DEBUG') && APP_DEBUG;
    
    // Log the critical error
    try {
        Logger::getInstance()->critical('500 Internal Server Error', [
            'error_id' => $errorId,
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'Unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'error_details' => isset($GLOBALS['last_error']) ? $GLOBALS['last_error'] : 'Unknown',
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ]);
        
        // TODO: Send notification to admin (optional)
        // Mailer::send('admin@proconsultancy.be', 'Critical Error', "Error ID: $errorId");
        
    } catch (Exception $e) {
        // Logging failed, continue anyway
    }
    
    // Get branding
    try {
        $companyName = Branding::companyName();
    } catch (Exception $e) {
        $companyName = 'ProConsultancy';
    }
    
    $pageTitle = '500 - Server Error';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
} catch (Exception $e) {
    // If everything fails, use defaults
    $companyName = 'ProConsultancy';
    $pageTitle = '500 - Server Error';
    $baseUrl = '';
    $showDetails = false;
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
<body class="error-500">
    <div class="error-container">
        <!-- Error Icon -->
        <div class="error-icon">
            <i class="bx bx-error-circle"></i>
        </div>
        
        <!-- Error Code -->
        <div class="error-code">500</div>
        
        <!-- Error Title -->
        <h1 class="error-title">Server Error</h1>
        
        <!-- Error Description -->
        <p class="error-description">
            Something went wrong on our end. We've been notified and are working to fix it. 
            Please try again in a few moments.
        </p>
        
        <!-- Error ID (for support reference) -->
        <div class="error-id">
            Error Reference ID: <strong><?= htmlspecialchars($errorId) ?></strong>
            <br>
            <small>Please provide this ID when contacting support</small>
        </div>
        
        <!-- Debug Information (only in development) -->
        <?php if ($showDetails && isset($GLOBALS['last_error'])): ?>
        <div class="error-details">
            <strong>Debug Information (Development Mode):</strong><br>
            <?= htmlspecialchars(print_r($GLOBALS['last_error'], true)) ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="error-actions">
            <a href="<?= $baseUrl ?>/panel/dashboard.php" class="btn btn-primary">
                <i class="bx bx-home-alt"></i>
                Return to Dashboard
            </a>
            <a href="javascript:location.reload()" class="btn btn-secondary">
                <i class="bx bx-refresh"></i>
                Refresh Page
            </a>
        </div>
        
        <!-- Support Section -->
        <div class="error-support">
            If this problem persists, please 
            <a href="mailto:support@proconsultancy.be?subject=Error%20Report%20<?= urlencode($errorId) ?>">
                contact technical support
            </a> 
            with the error reference ID above.
        </div>
    </div>
</body>
</html>