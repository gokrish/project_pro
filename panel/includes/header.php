<?php
/**
 * Header Component
 * Top navigation bar with search, notifications, and user menu
 * 
 * @version 5.0
 * 
 */

use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Permission;

$user = Auth::user();
$pageTitle = $pageTitle ?? 'ProConsultancy';
?>
<!DOCTYPE html>
<html lang="en" class="light-style layout-menu-fixed">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title><?= htmlspecialchars($pageTitle) ?> - ProConsultancy</title>
    
    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Professional recruitment and talent management system') ?>">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/panel/assets/images/favicon.ico">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- ===================================================================
         CSS RESOURCES (Order is important!)
         =================================================================== -->
    
    <!-- 1. Core Framework -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" 
          crossorigin="anonymous">
    
    <!-- 2. Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css">
    
    <!-- 3. Custom CSS (Cascading Order: Variables → Theme → Layout → Components → Main) -->
    <link rel="stylesheet" href="/panel/assets/css/variables.css">
    <link rel="stylesheet" href="/panel/assets/css/theme.css">
    <link rel="stylesheet" href="/panel/assets/css/layout.css">
    <link rel="stylesheet" href="/panel/assets/css/components.css">
    <link rel="stylesheet" href="/panel/assets/css/main.css">
    
    <!-- 4. Module-specific CSS (if needed) -->
    <?php if (!empty($customCSS)): ?>
        <?php foreach ($customCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- ===================================================================
         INLINE JAVASCRIPT CONFIGURATION
         (Only config, actual scripts load in footer for performance)
         =================================================================== -->
    <script>
        // Global application configuration
        window.APP_CONFIG = {
            baseUrl: '<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST']) ?>',
            apiUrl: '/panel/api',
            csrfToken: '<?= CSRFToken::get() ?>',
            user: {
                code: '<?= htmlspecialchars($user['user_code']) ?>',
                name: '<?= htmlspecialchars($user['name']) ?>',
                email: '<?= htmlspecialchars($user['email']) ?>',
                level: '<?= htmlspecialchars($user['level']) ?>'
            },
            debug: <?= (defined('APP_DEBUG') && APP_DEBUG) ? 'true' : 'false' ?>
        };
        
        // Debug mode logging
        if (window.APP_CONFIG.debug) {
            console.log('%c🚀 ProConsultancy Debug Mode Enabled', 'color: #667eea; font-size: 14px; font-weight: bold;');
            console.log('User:', window.APP_CONFIG.user);
            console.log('CSRF Token:', window.APP_CONFIG.csrfToken);
        }
    </script>
</head>
<body>
    <?php
$flash = getFlashMessage();
if ($flash):
?>
<div class="position-fixed top-0 end-0 p-3" style="z-index: 11000;">
    <div class="toast show align-items-center text-white bg-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>
</div>
<script>
// Auto-hide toast after 5 seconds
setTimeout(function() {
    var toasts = document.querySelectorAll('.toast');
    toasts.forEach(function(toast) {
        var bsToast = new bootstrap.Toast(toast);
        bsToast.hide();
    });
}, 5000);
</script>
<?php endif; ?>
    <!-- Layout wrapper -->
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">
            
            <!-- Sidebar (imported separately) -->
            <?php require_once __DIR__ . '/sidebar.php'; ?>
            
            <!-- Layout container -->
            <div class="layout-page">
                
                <!-- Top Navbar -->
                <nav class="layout-navbar navbar navbar-expand-xl navbar-detached align-items-center bg-navbar-theme">
                    <div class="container-fluid">
                        
                        <!-- Mobile menu toggle -->
                        <div class="layout-menu-toggle navbar-nav align-items-xl-center me-3 me-xl-0 d-xl-none">
                            <a class="nav-item nav-link px-0 me-xl-4" href="javascript:void(0)" id="mobileMenuToggle">
                                <i class="bx bx-menu bx-sm"></i>
                            </a>
                        </div>
                        
                        <div class="navbar-nav-right d-flex align-items-center" id="navbar-collapse">
                            
                            <!-- Search -->
                            <div class="navbar-nav align-items-center">
                                <div class="nav-item d-flex align-items-center">
                                    <i class="bx bx-search fs-4 lh-0"></i>
                                    <input type="text" 
                                           class="form-control border-0 shadow-none" 
                                           placeholder="Search candidates, jobs, clients..." 
                                           aria-label="Search"
                                           id="globalSearch"
                                           style="width: 300px;">
                                </div>
                            </div>
                            
                            <ul class="navbar-nav flex-row align-items-center ms-auto">
                                
                                <!-- Quick Actions -->
                                <li class="nav-item dropdown-shortcuts navbar-dropdown dropdown me-2">
                                    <a class="nav-link dropdown-toggle hide-arrow" 
                                       href="javascript:void(0);" 
                                       data-bs-toggle="dropdown" 
                                       aria-expanded="false">
                                        <i class="bx bx-grid-alt bx-sm"></i>
                                    </a>
                                    <div class="dropdown-menu dropdown-menu-end py-0">
                                        <div class="dropdown-menu-header border-bottom">
                                            <div class="dropdown-header d-flex align-items-center py-3">
                                                <h5 class="text-body mb-0 me-auto">Quick Actions</h5>
                                            </div>
                                        </div>
                                        <div class="dropdown-shortcuts-list scrollable-container">
                                            <div class="row row-bordered g-0">
                                                <?php if (Permission::can('candidates', 'create')): ?>
                                                <div class="dropdown-shortcuts-item col">
                                                    <a href="/panel/modules/candidates/create.php" class="dropdown-item">
                                                        <i class="bx bx-user-plus mb-2"></i>
                                                        <div>Add Candidate</div>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (Permission::can('jobs', 'create')): ?>
                                                <div class="dropdown-shortcuts-item col">
                                                    <a href="/panel/modules/jobs/create.php" class="dropdown-item">
                                                        <i class="bx bx-briefcase-alt mb-2"></i>
                                                        <div>Create Job</div>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="row row-bordered g-0">
                                                <?php if (Permission::can('applications', 'view_pipeline')): ?>
                                                <div class="dropdown-shortcuts-item col">
                                                    <a href="/panel/modules/applications/list.php" class="dropdown-item">
                                                        <i class="bx bx-spreadsheet mb-2"></i>
                                                        <div>Pipeline</div>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if (Permission::can('reports', 'view_reports')): ?>
                                                <div class="dropdown-shortcuts-item col">
                                                    <a href="/panel/modules/reports/daily.php" class="dropdown-item">
                                                        <i class="bx bx-bar-chart-alt-2 mb-2"></i>
                                                        <div>Reports</div>
                                                    </a>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                
                                <!-- Notifications -->
                                <li class="nav-item navbar-dropdown dropdown-notifications navbar-dropdown dropdown me-3 me-xl-1">
                                    <a class="nav-link dropdown-toggle hide-arrow" 
                                       href="javascript:void(0);" 
                                       data-bs-toggle="dropdown" 
                                       data-bs-auto-close="outside" 
                                       aria-expanded="false">
                                        <i class="bx bx-bell bx-sm"></i>
                                        <span class="badge bg-danger rounded-pill badge-notifications" id="notificationBadge" style="display: none;">0</span>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end py-0">
                                        <li class="dropdown-menu-header border-bottom">
                                            <div class="dropdown-header d-flex align-items-center py-3">
                                                <h5 class="text-body mb-0 me-auto">Notifications</h5>
                                                <a href="javascript:void(0)" class="dropdown-notifications-all text-body" id="markAllRead">
                                                    <i class="bx fs-6 bx-envelope-open"></i>
                                                </a>
                                            </div>
                                        </li>
                                        <li class="dropdown-notifications-list scrollable-container" id="notificationList">
                                            <ul class="list-group list-group-flush">
                                                <!-- Notifications loaded via JavaScript -->
                                                <li class="list-group-item list-group-item-action text-center">
                                                    <small class="text-muted">No new notifications</small>
                                                </li>
                                            </ul>
                                        </li>
                                        <li class="dropdown-menu-footer border-top">
                                            <a href="/panel/notifications.php" class="dropdown-item d-flex justify-content-center p-3">
                                                View all notifications
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                                
                                <!-- User Menu -->
                                <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                    <a class="nav-link dropdown-toggle hide-arrow" 
                                       href="javascript:void(0);" 
                                       data-bs-toggle="dropdown">
                                        <div class="avatar avatar-online">
                                            <div class="avatar-initial bg-label-primary rounded-circle">
                                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                            </div>
                                        </div>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="#">
                                                <div class="d-flex">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="avatar avatar-online">
                                                            <div class="avatar-initial bg-label-primary rounded-circle">
                                                                <?= strtoupper(substr($user['name'], 0, 2)) ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <span class="fw-semibold d-block"><?= htmlspecialchars($user['name']) ?></span>
                                                        <small class="text-muted"><?= ucfirst($user['level']) ?></small>
                                                    </div>
                                                </div>
                                            </a>
                                        </li>
                                        <li>
                                            <div class="dropdown-divider"></div>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="/panel/profile.php">
                                                <i class="bx bx-user me-2"></i>
                                                <span class="align-middle">My Profile</span>
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="/panel/settings.php">
                                                <i class="bx bx-cog me-2"></i>
                                                <span class="align-middle">Settings</span>
                                            </a>
                                        </li>
                                        <?php if (Permission::can('users', 'view_activity') || $user['level'] === 'admin'): ?>
                                        <li>
                                            <a class="dropdown-item" href="/panel/activity_log.php">
                                                <i class="bx bx-file me-2"></i>
                                                <span class="align-middle">Activity Logs</span>
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <div class="dropdown-divider"></div>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="/panel/logout.php">
                                                <i class="bx bx-power-off me-2"></i>
                                                <span class="align-middle">Log Out</span>
                                            </a>
                                        </li>
                                    </ul>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
                <!-- / Top Navbar -->
                
                <!-- Content wrapper -->
                <div class="content-wrapper">
                    <!-- Flash Messages -->
                    <?php require_once __DIR__ . '/flash-messages.php'; ?>
                    
                    <!-- Breadcrumbs -->
                    <?php if (!empty($breadcrumbs)): ?>
                        <?php require_once __DIR__ . '/breadcrumbs.php'; ?>
                    <?php endif; ?>
                    
                    <!-- Content -->
                    <div class="container-xxl flex-grow-1 container-p-y">