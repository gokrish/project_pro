<?php
/**
 * 403 Forbidden Error Page
 * Access Denied - User doesn't have permission
 * 
 * @version 5.0
 */

// Set HTTP status code
http_response_code(403);

// Prevent errors from breaking the error page
try {
    // Load configuration
    define('PANEL_ACCESS', true);
    require_once __DIR__ . '/../includes/config/config.php';
    
    // Try to load core classes
    use ProConsultancy\Core\Auth;
    use ProConsultancy\Core\Logger;
    use ProConsultancy\Core\Branding;
    
    // Check if user is authenticated
    $isAuthenticated = false;
    $user = null;
    $userRole = 'Guest';
    
    try {
        $user = Auth::user();
        $isAuthenticated = ($user !== null);
        $userRole = $isAuthenticated ? ucfirst($user['level']) : 'Guest';
    } catch (Exception $e) {
        // User not authenticated
    }
    
    // Log the 403 error
    try {
        Logger::getInstance()->warning('403 Forbidden access attempt', [
            'url' => $_SERVER['REQUEST_URI'] ?? 'Unknown',
            'user' => $isAuthenticated ? $user['user_code'] : 'Guest',
            'user_role' => $userRole,
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
    
    $pageTitle = '403 - Access Denied';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
} catch (Exception $e) {
    // If everything fails, use defaults
    $companyName = 'ProConsultancy';
    $pageTitle = '403 - Access Denied';
    $baseUrl = '';
    $isAuthenticated = false;
    $userRole = 'Guest';
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
<body class="error-403">
    <div class="error-container">
        <!-- Error Icon -->
        <div class="error-icon">
            <i class="bx bx-lock-alt"></i>
        </div>
        
        <!-- Error Code -->
        <div class="error-code">403</div>
        
        <!-- Error Title -->
        <h1 class="error-title">Access Denied</h1>
        
        <!-- Error Description -->
        <p class="error-description">
            You don't have permission to access this resource.
            <?php if ($isAuthenticated): ?>
            Your current role (<?= htmlspecialchars($userRole) ?>) doesn't allow this action.
            <?php else: ?>
            Please log in with an account that has the required permissions.
            <?php endif; ?>
        </p>
        
        <!-- User Info (if authenticated) -->
        <?php if ($isAuthenticated): ?>
        <div class="user-info">
            <strong>Current User:</strong> <?= htmlspecialchars($user['name']) ?><br>
            <strong>Role:</strong> <?= htmlspecialchars($userRole) ?>
        </div>
        <?php endif; ?>
        
        <!-- Action Buttons -->
        <div class="error-actions">
            <?php if ($isAuthenticated): ?>
            <a href="<?= $baseUrl ?>/panel/dashboard.php" class="btn btn-primary">
                <i class="bx bx-home-alt"></i>
                Return to Dashboard
            </a>
            <a href="javascript:history.back()" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i>
                Go Back
            </a>
            <?php else: ?>
            <a href="<?= $baseUrl ?>/panel/login.php" class="btn btn-primary">
                <i class="bx bx-log-in"></i>
                Log In
            </a>
            <a href="<?= $baseUrl ?>/" class="btn btn-secondary">
                <i class="bx bx-home-alt"></i>
                Go Home
            </a>
            <?php endif; ?>
        </div>
        
        <!-- Support Section -->
        <div class="error-support">
            If you believe this is an error, please contact your system administrator or 
            <a href="mailto:support@proconsultancy.be">contact support</a>.
        </div>
    </div>
</body>
</html>