<?php
/**
 * ============================================================================
 * HEADER
 * ============================================================================
 */

use ProConsultancy\Core\Auth;

$user = Auth::user();
$user_initials = strtoupper(substr($user['name'], 0, 2));
$pageTitle = $pageTitle ?? 'Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - ProConsultancy</title>
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/panel/assets/img/favicon.ico">
    
    <!-- Boxicons -->
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" 
          integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" 
          crossorigin="anonymous">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="/panel/assets/css/layout.css">
    
    <?php if (!empty($customCSS)): ?>
        <?php foreach ($customCSS as $css): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Global Search JS -->
    <script src="/panel/assets/js/global-search.js" defer></script>
</head>
<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <?php require_once __DIR__ . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Top Navigation Bar -->
            <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
                <div class="container-fluid px-4">
                    <!-- Page Title -->
                    <h4 class="mb-0 fw-semibold"><?= htmlspecialchars($pageTitle) ?></h4>
                    
                    <!-- Right Side Navigation -->
                    <div class="d-flex align-items-center ms-auto gap-3">
                        
                        <!-- Search -->
                        <div class="d-flex align-items-center">
                            <i class="bx bx-search fs-5 text-muted me-2"></i>
                            <input type="text" 
                                   class="form-control border-0 bg-light" 
                                   placeholder="Search candidates, jobs, clients..." 
                                   id="globalSearch" 
                                   style="width: 300px;">
                        </div>
                        
                        <!-- Quick Actions Dropdown -->
                        <div class="dropdown">
                            <a class="nav-link p-1" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-grid-alt fs-5 text-muted"></i>
                            </a>
                            <div class="dropdown-menu dropdown-menu-end p-2" style="min-width: 320px;">
                                <h6 class="dropdown-header fw-semibold">Quick Actions</h6>
                                <div class="row g-2">
                                    <div class="col-6">
                                        <a href="/panel/modules/candidates/create.php" class="dropdown-item text-center py-3 rounded">
                                            <i class="bx bx-user-plus fs-3 text-primary d-block mb-1"></i>
                                            <small>Add Candidate</small>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="/panel/modules/jobs/create.php" class="dropdown-item text-center py-3 rounded">
                                            <i class="bx bx-briefcase-alt fs-3 text-success d-block mb-1"></i>
                                            <small>Create Job</small>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="/panel/modules/submissions/create.php" class="dropdown-item text-center py-3 rounded">
                                            <i class="bx bx-send fs-3 text-info d-block mb-1"></i>
                                            <small>Submit</small>
                                        </a>
                                    </div>
                                    <div class="col-6">
                                        <a href="/panel/modules/reports/daily.php" class="dropdown-item text-center py-3 rounded">
                                            <i class="bx bx-bar-chart-alt-2 fs-3 text-warning d-block mb-1"></i>
                                            <small>Reports</small>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notifications Dropdown -->
                        <div class="dropdown">
                            <a class="nav-link p-1 position-relative" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bx bx-bell fs-5 text-muted"></i>
                                <!-- Notification badge will be added when notification system is implemented -->
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 280px;">
                                <li class="dropdown-header fw-semibold">Notifications</li>
                                <li><hr class="dropdown-divider my-1"></li>
                                <li class="text-center py-4 text-muted">
                                    <i class="bx bx-bell-off fs-3 d-block mb-2"></i>
                                    <small>No new notifications</small>
                                </li>
                            </ul>
                        </div>
                        
                        <!-- User Menu Dropdown -->
                        <div class="dropdown">
                            <a class="nav-link p-0" href="#" data-bs-toggle="dropdown" aria-expanded="false">
                                <div class="avatar">
                                    <div class="avatar-initial rounded-circle bg-primary text-white fw-semibold" 
                                         style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                        <?= $user_initials ?>
                                    </div>
                                </div>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 240px;">
                                <li>
                                    <div class="dropdown-item-text">
                                        <div class="d-flex align-items-center py-2">
                                            <div class="avatar me-3">
                                                <div class="avatar-initial rounded-circle bg-primary text-white fw-semibold" 
                                                     style="width: 36px; height: 36px; display: flex; align-items: center; justify-content: center;">
                                                    <?= $user_initials ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="fw-semibold"><?= htmlspecialchars($user['name']) ?></div>
                                                <small class="text-muted"><?= ucfirst($user['level']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/panel/modules/users/profile.php">
                                    <i class="bx bx-user me-2"></i>My Profile
                                </a></li>
                                <li><a class="dropdown-item" href="/panel/modules/settings/index.php">
                                    <i class="bx bx-cog me-2"></i>Settings
                                </a></li>
                                <?php if ($user['level'] === 'admin' || $user['level'] === 'super_admin'): ?>
                                <li><a class="dropdown-item" href="/panel/activity_log.php">
                                    <i class="bx bx-time me-2"></i>Activity Log
                                </a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="/panel/logout.php">
                                    <i class="bx bx-power-off me-2"></i>Log Out
                                </a></li>
                            </ul>
                        </div>
                        
                    </div>
                </div>
            </nav>
            
            <!-- Content Container -->
            <div class="content-container">
                <?php 
                // Flash messages
                if (file_exists(__DIR__ . '/flash-messages.php')) {
                    require_once __DIR__ . '/flash-messages.php';
                }
                ?>
                
                <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
                <!-- Breadcrumbs -->
                <nav aria-label="breadcrumb" class="mb-3">
                    <ol class="breadcrumb breadcrumb-style1 mb-0">
                        <li class="breadcrumb-item">
                            <a href="/panel/dashboard.php"><i class="bx bx-home-alt"></i></a>
                        </li>
                        <?php foreach ($breadcrumbs as $index => $crumb): ?>
                            <li class="breadcrumb-item <?= $index === count($breadcrumbs) - 1 ? 'active' : '' ?>">
                                <?php if ($index === count($breadcrumbs) - 1): ?>
                                    <?= htmlspecialchars($crumb['title']) ?>
                                <?php else: ?>
                                    <a href="<?= htmlspecialchars($crumb['url']) ?>">
                                        <?= htmlspecialchars($crumb['title']) ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </nav>
                <?php endif; ?>
                
                <!-- PAGE CONTENT STARTS HERE -->