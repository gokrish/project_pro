<?php
/**
 * ============================================================================
 * PRODUCTION DASHBOARD v5.0
 * ============================================================================
 * 
 * BUSINESS REQUIREMENTS:
 * - Everything visible in one screen (no scrolling for primary content)
 * - Compact, professional layout
 * - Remove unnecessary details
 * - Focus on actionable metrics
 * - Clean, modern UI matching the blue theme
 * 
 * @version 5.0 PRODUCTION
 * @date 2026-01-03
 */

require_once __DIR__ . '/modules/_common.php';

use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;

// Check permission
if (!Permission::can('reports', 'view_dashboard')) {
    header('Location: /panel/errors/403.php');
    exit;
}

$user = Auth::user();
$userCode = $user['user_code'];
$userLevel = $user['level'] ?? 'user';
$isAdmin = in_array($userLevel, ['admin', 'super_admin', 'manager']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize stats
$stats = [
    'active_jobs' => 0,
    'total_candidates' => 0,
    'pending_submissions' => 0,
    'total_clients' => 0,
    'this_month_placements' => 0,
    'cv_inbox_new' => 0
];

try {
    // Active Jobs
    $jobsQuery = $isAdmin 
        ? "SELECT COUNT(*) as count FROM jobs WHERE status IN ('open', 'filling') AND deleted_at IS NULL"
        : "SELECT COUNT(*) as count FROM jobs WHERE status IN ('open', 'filling') AND deleted_at IS NULL AND (created_by = ? OR assigned_recruiter = ?)";
    
    if ($isAdmin) {
        $stats['active_jobs'] = $conn->query($jobsQuery)->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare($jobsQuery);
        $stmt->bind_param("ss", $userCode, $userCode);
        $stmt->execute();
        $stats['active_jobs'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Total Candidates
    $candidatesQuery = $isAdmin
        ? "SELECT COUNT(*) as count FROM candidates WHERE deleted_at IS NULL"
        : "SELECT COUNT(*) as count FROM candidates WHERE deleted_at IS NULL AND created_by = ?";
    
    if ($isAdmin) {
        $stats['total_candidates'] = $conn->query($candidatesQuery)->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare($candidatesQuery);
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $stats['total_candidates'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Pending Submissions
    $submissionsQuery = $isAdmin
        ? "SELECT COUNT(*) as count FROM submissions WHERE internal_status = 'pending' AND deleted_at IS NULL"
        : "SELECT COUNT(*) as count FROM submissions WHERE internal_status = 'pending' AND deleted_at IS NULL AND submitted_by = ?";
    
    if ($isAdmin) {
        $stats['pending_submissions'] = $conn->query($submissionsQuery)->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare($submissionsQuery);
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $stats['pending_submissions'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // Total Clients
    $clientsQuery = $isAdmin
        ? "SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL"
        : "SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL AND (created_by = ? OR account_manager = ?)";
    
    if ($isAdmin) {
        $stats['total_clients'] = $conn->query($clientsQuery)->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare($clientsQuery);
        $stmt->bind_param("ss", $userCode, $userCode);
        $stmt->execute();
        $stats['total_clients'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // This Month Placements
    $thisMonth = date('Y-m-01');
    $placementsQuery = $isAdmin
        ? "SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL AND placement_date >= ?"
        : "SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL AND submitted_by = ? AND placement_date >= ?";
    
    $stmt = $conn->prepare($placementsQuery);
    if ($isAdmin) {
        $stmt->bind_param("s", $thisMonth);
    } else {
        $stmt->bind_param("ss", $userCode, $thisMonth);
    }
    $stmt->execute();
    $stats['this_month_placements'] = $stmt->get_result()->fetch_assoc()['count'];
    
    // CV Inbox New Count
    $cvInboxQuery = "SELECT COUNT(*) as count FROM cv_inbox WHERE status = 'new' AND deleted_at IS NULL";
    $stats['cv_inbox_new'] = $conn->query($cvInboxQuery)->fetch_assoc()['count'];
    
} catch (Exception $e) {
    Logger::getInstance()->error('Dashboard stats failed', [
        'error' => $e->getMessage(),
        'user' => $userCode
    ]);
}

// Get recent activity (limited to 5 for compact view)
$recentActivity = [];
try {
    if ($isAdmin) {
        $activitySQL = "SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 5";
        $recentActivity = $conn->query($activitySQL)->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt = $conn->prepare("SELECT * FROM activity_log WHERE user_code = ? ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (Exception $e) {
    Logger::getInstance()->error('Failed to fetch recent activity', ['error' => $e->getMessage()]);
}

// Helper functions
function escape($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $diff = time() - $time;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    
    return date('M j, Y', $time);
}

function getStatusBadge($action) {
    $badges = [
        'create' => 'success',
        'update' => 'info',
        'delete' => 'danger',
        'login' => 'primary',
        'logout' => 'secondary'
    ];
    return $badges[strtolower($action)] ?? 'secondary';
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="dashboard-welcome mb-4">
    <h2>Welcome back, <?= escape($user['name']) ?>! 👋</h2>
    <p>Here's your recruitment pipeline overview for today.</p>
</div>

<!-- Key Metrics - Compact Grid -->
<div class="stats-grid mb-4">
    <!-- Active Jobs -->
    <div class="stat-card primary">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-primary text-white">
                <i class='bx bx-briefcase'></i>
            </div>
        </div>
        <div class="stat-card-label">Active Jobs</div>
        <div class="stat-card-value"><?= number_format($stats['active_jobs']) ?></div>
    </div>
    
    <!-- Total Candidates -->
    <div class="stat-card success">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-success text-white">
                <i class='bx bx-user-check'></i>
            </div>
        </div>
        <div class="stat-card-label">Candidates</div>
        <div class="stat-card-value"><?= number_format($stats['total_candidates']) ?></div>
    </div>
    
    <!-- Pending Submissions -->
    <div class="stat-card warning">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-warning text-white">
                <i class='bx bx-send'></i>
            </div>
        </div>
        <div class="stat-card-label">Pending</div>
        <div class="stat-card-value"><?= number_format($stats['pending_submissions']) ?></div>
    </div>
    
    <!-- This Month Placements -->
    <div class="stat-card info">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-info text-white">
                <i class='bx bx-trophy'></i>
            </div>
        </div>
        <div class="stat-card-label">This Month</div>
        <div class="stat-card-value"><?= number_format($stats['this_month_placements']) ?></div>
    </div>
</div>

<!-- Two Column Layout -->
<div class="row">
    <!-- Left: Quick Actions & Recent Activity -->
    <div class="col-lg-7">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class='bx bx-zap'></i> Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php if (Permission::can('candidates', 'create')): ?>
                    <div class="col-md-6">
                        <a href="/panel/modules/candidates/create.php" class="btn btn-primary w-100">
                            <i class='bx bx-user-plus'></i> Add Candidate
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('jobs', 'create')): ?>
                    <div class="col-md-6">
                        <a href="/panel/modules/jobs/create.php" class="btn btn-success w-100">
                            <i class='bx bx-briefcase-alt'></i> Create Job
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <a href="/panel/modules/submissions/list.php" class="btn btn-outline-primary w-100">
                            <i class='bx bx-send'></i> View Submissions
                        </a>
                    </div>
                    
                    <div class="col-md-6">
                        <a href="/panel/modules/cv-inbox/list.php" class="btn btn-outline-primary w-100">
                            <i class='bx bx-inbox'></i> CV Inbox
                            <?php if ($stats['cv_inbox_new'] > 0): ?>
                                <span class="badge bg-danger"><?= $stats['cv_inbox_new'] ?></span>
                            <?php endif; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class='bx bx-history'></i> Recent Activity
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <div class="text-center text-muted py-3">
                        <i class='bx bx-info-circle' style="font-size: 32px;"></i>
                        <p class="mb-0 mt-2">No recent activity</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <i class='bx bx-check'></i>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <span class="badge badge-<?= getStatusBadge($activity['action']) ?>">
                                        <?= escape($activity['module']) ?>
                                    </span>
                                    <?= escape($activity['description'] ?? $activity['action']) ?>
                                </div>
                                <div class="activity-time">
                                    <?= timeAgo($activity['created_at']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right: Pipeline Stats & Metrics -->
    <div class="col-lg-5">
        <!-- Pipeline Overview -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class='bx bx-pie-chart-alt-2'></i> Pipeline Overview
                </h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <div class="text-muted" style="font-size: 12px;">TOTAL CLIENTS</div>
                        <div class="fw-700" style="font-size: 20px; color: var(--primary-color);">
                            <?= number_format($stats['total_clients']) ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="/panel/modules/clients/list.php" class="btn btn-sm btn-outline-primary">
                            View All
                        </a>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3 pb-3 border-bottom">
                    <div>
                        <div class="text-muted" style="font-size: 12px;">CV INBOX</div>
                        <div class="fw-700" style="font-size: 20px; color: var(--warning-color);">
                            <?= number_format($stats['cv_inbox_new']) ?>
                            <span style="font-size: 12px; font-weight: 400; color: var(--text-muted);">new</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="/panel/modules/cv-inbox/list.php" class="btn btn-sm btn-outline-primary">
                            Review
                        </a>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted" style="font-size: 12px;">ACTIVE PIPELINE</div>
                        <div class="fw-700" style="font-size: 20px; color: var(--success-color);">
                            <?= number_format($stats['pending_submissions']) ?>
                            <span style="font-size: 12px; font-weight: 400; color: var(--text-muted);">submissions</span>
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="/panel/modules/submissions/list.php" class="btn btn-sm btn-outline-primary">
                            Manage
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Reports Link -->
        <?php if (Permission::can('reports', 'view_dashboard')): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class='bx bx-bar-chart-alt-2'></i> Reports & Analytics
                </h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/panel/modules/reports/daily.php" class="btn btn-outline-primary">
                        <i class='bx bx-calendar'></i> Daily Report
                    </a>
                    <a href="/panel/modules/reports/pipeline.php" class="btn btn-outline-primary">
                        <i class='bx bx-trending-up'></i> Pipeline Analytics
                    </a>
                    <a href="/panel/modules/reports/recruiter_performance.php" class="btn btn-outline-primary">
                        <i class='bx bx-trophy'></i> Performance
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>