<?php
/**
 * Maintenance Mode Page
 * Shown when system is under maintenance
 * 
 * @version 2.0
 * 
 * USAGE:
 * 1. Set MAINTENANCE_MODE = true in config
 * 2. Add this to _common.php:
 *    if (defined('MAINTENANCE_MODE') && MAINTENANCE_MODE && !Auth::isSuperAdmin()) {
 *        require_once __DIR__ . '/../errors/maintenance.php';
 *        exit;
 *    }
 */
    
use ProConsultancy\Core\Branding;
http_response_code(503);

// Prevent errors from breaking the maintenance page
try {
    // Load configuration
    define('PANEL_ACCESS', true);
    require_once __DIR__ . '/../includes/config/config.php';

    
    // Get maintenance settings
    $maintenanceMessage = defined('MAINTENANCE_MESSAGE') 
        ? MAINTENANCE_MESSAGE 
        : 'We are currently performing scheduled maintenance to improve your experience.';
    
    $estimatedTime = defined('MAINTENANCE_ETA') 
        ? MAINTENANCE_ETA 
        : 'a few hours';
    
    // Get branding
    try {
        $companyName = Branding::companyName();
    } catch (Exception $e) {
        $companyName = 'ProConsultancy';
    }
    
    $pageTitle = 'Maintenance Mode';
    $baseUrl = defined('BASE_URL') ? BASE_URL : '';
    
} catch (Exception $e) {
    // If everything fails, use defaults
    $companyName = 'ProConsultancy';
    $pageTitle = 'Maintenance Mode';
    $baseUrl = '';
    $maintenanceMessage = 'We are currently performing scheduled maintenance.';
    $estimatedTime = 'a few hours';
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
    
    <!-- Inline styles (maintenance page should be self-contained) -->
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #4299e1 0%, #2b6cb0 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .maintenance-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            animation: fadeIn 0.5s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .maintenance-icon {
            font-size: 100px;
            color: #bee3f8;
            margin-bottom: 30px;
            animation: rotate 3s linear infinite;
        }
        
        @keyframes rotate {
            from {
                transform: rotate(0deg);
            }
            to {
                transform: rotate(360deg);
            }
        }
        
        .maintenance-title {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #4299e1 0%, #2b6cb0 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 20px;
        }
        
        .maintenance-description {
            font-size: 18px;
            color: #2d3748;
            margin-bottom: 15px;
            line-height: 1.6;
            font-weight: 500;
        }
        
        .maintenance-message {
            font-size: 16px;
            color: #718096;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .maintenance-eta {
            background: #ebf8ff;
            border-left: 4px solid #4299e1;
            padding: 15px 20px;
            margin: 30px 0;
            text-align: left;
            border-radius: 4px;
        }
        
        .maintenance-eta strong {
            color: #2c5282;
        }
        
        .maintenance-support {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e2e8f0;
            font-size: 14px;
            color: #718096;
        }
        
        .maintenance-support a {
            color: #4299e1;
            text-decoration: none;
            font-weight: 600;
        }
        
        .maintenance-support a:hover {
            text-decoration: underline;
        }
        
        /* Responsive */
        @media (max-width: 576px) {
            .maintenance-container {
                padding: 40px 20px;
            }
            
            .maintenance-icon {
                font-size: 60px;
            }
            
            .maintenance-title {
                font-size: 24px;
            }
            
            .maintenance-description {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="maintenance-container">
        <!-- Maintenance Icon -->
        <div class="maintenance-icon">
            <i class="bx bx-cog"></i>
        </div>
        
        <!-- Title -->
        <h1 class="maintenance-title">We'll Be Right Back!</h1>
        
        <!-- Description -->
        <p class="maintenance-description">
            <?= htmlspecialchars($companyName) ?> is currently undergoing maintenance
        </p>
        
        <!-- Custom Message -->
        <p class="maintenance-message">
            <?= htmlspecialchars($maintenanceMessage) ?>
        </p>
        
        <!-- Estimated Time -->
        <div class="maintenance-eta">
            <strong><i class="bx bx-time-five"></i> Estimated Time:</strong> 
            We expect to be back online in approximately <?= htmlspecialchars($estimatedTime) ?>.
        </div>
        
        <!-- Support Section -->
        <div class="maintenance-support">
            For urgent matters, please contact our support team at 
            <a href="mailto:support@proconsultancy.be">support@proconsultancy.be</a>
            <br>
            <small>We apologize for any inconvenience.</small>
        </div>
    </div>
</body>
</html>
