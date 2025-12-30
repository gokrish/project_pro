<?php
/**
 * Dashboard - Main Landing Page
 * 
 * @version 2.0
 */
// Add at top of dashboard.php temporarily:
var_dump(Auth::check());
var_dump(Auth::user());
var_dump($_SESSION);
die();

require_once __DIR__ . '/modules/_common.php';


use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;

/**
 * HARD LOOP BREAKER
 */
if (Session::get('_login_fresh') === true) {
    Session::remove('_login_fresh');
    // Allow dashboard to load ONCE without auth redirect
} else {
    if (!Auth::check()) {
        header('Location: /panel/login.php');
        exit;
    }
}



// Check permission
if (!Permission::can('reports', 'view_dashboard')) {
    header('Location: /panel/errors/403.php');
    exit;
}

$user = Auth::user();
$userCode = $user['user_code'];
$userLevel = $user['level'] ?? 'user';

// Determine if user is admin/manager
$isAdmin = in_array($userLevel, ['admin', 'super_admin', 'manager','user']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize stats array
$stats = [
    'total_clients' => 0,
    'active_jobs' => 0,
    'total_candidates' => 0,
    'pending_submissions' => 0,
    'total_placements' => 0,
    'this_month_placements' => 0
];

// Get recent activity
$recentActivity = [];

try {
    // ========================================================================
    // STATISTICS QUERIES
    // ========================================================================
    
    // 1. Total Clients
    if ($isAdmin) {
        $result = $conn->query("SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL");
        $stats['total_clients'] = $result->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM clients WHERE deleted_at IS NULL AND (created_by = ? OR account_manager = ?)");
        $stmt->bind_param("ss", $userCode, $userCode);
        $stmt->execute();
        $stats['total_clients'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // 2. Active Jobs
    if ($isAdmin) {
        $result = $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status IN ('open', 'filling') AND deleted_at IS NULL");
        $stats['active_jobs'] = $result->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM jobs WHERE status IN ('open', 'filling') AND deleted_at IS NULL AND (created_by = ? OR assigned_recruiter = ?)");
        $stmt->bind_param("ss", $userCode, $userCode);
        $stmt->execute();
        $stats['active_jobs'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // 3. Total Candidates
    if ($isAdmin) {
        $result = $conn->query("SELECT COUNT(*) as count FROM candidates WHERE deleted_at IS NULL");
        $stats['total_candidates'] = $result->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE deleted_at IS NULL AND created_by = ?");
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $stats['total_candidates'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // 4. Pending Submissions (for managers)
    if ($isAdmin) {
        $result = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE internal_status = 'pending' AND deleted_at IS NULL");
        $stats['pending_submissions'] = $result->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE internal_status = 'pending' AND deleted_at IS NULL AND submitted_by = ?");
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $stats['pending_submissions'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // 5. Total Placements
    if ($isAdmin) {
        $result = $conn->query("SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL");
        $stats['total_placements'] = $result->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL AND submitted_by = ?");
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $stats['total_placements'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // 6. This Month Placements
    $thisMonth = date('Y-m-01');
    if ($isAdmin) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL AND placement_date >= ?");
        $stmt->bind_param("s", $thisMonth);
        $stmt->execute();
        $stats['this_month_placements'] = $stmt->get_result()->fetch_assoc()['count'];
    } else {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM submissions WHERE client_status = 'placed' AND deleted_at IS NULL AND submitted_by = ? AND placement_date >= ?");
        $stmt->bind_param("ss", $userCode, $thisMonth);
        $stmt->execute();
        $stats['this_month_placements'] = $stmt->get_result()->fetch_assoc()['count'];
    }
    
    // ========================================================================
    // RECENT ACTIVITY (Last 10 actions)
    // ========================================================================
    if ($isAdmin) {
        $activitySQL = "
            SELECT * FROM activity_log 
            ORDER BY created_at DESC 
            LIMIT 10
        ";
        $recentActivity = $conn->query($activitySQL)->fetch_all(MYSQLI_ASSOC);
    } else {
        $stmt = $conn->prepare("
            SELECT * FROM activity_log 
            WHERE user_code = ?
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->bind_param("s", $userCode);
        $stmt->execute();
        $recentActivity = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Dashboard stats failed', [
        'error' => $e->getMessage(),
        'user' => $userCode
    ]);
    // Don't break the page, just show zeros
}

// Get pending submissions for approval (managers only)
$pendingSubmissions = [];
if ($isAdmin && Permission::can('submissions', 'approve')) {
    try {
        $pendingSQL = "
            SELECT 
                s.*,
                c.candidate_name,
                j.job_title,
                cl.company_name
            FROM submissions s
            JOIN candidates c ON s.candidate_code = c.candidate_code
            JOIN jobs j ON s.job_code = j.job_code
            JOIN clients cl ON j.client_code = cl.client_code
            WHERE s.internal_status = 'pending'
            AND s.deleted_at IS NULL
            ORDER BY s.created_at ASC
            LIMIT 5
        ";
        $pendingSubmissions = $conn->query($pendingSQL)->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        Logger::getInstance()->error('Failed to fetch pending submissions', ['error' => $e->getMessage()]);
    }
}

// Get pending job approvals (managers only)
$pendingJobs = [];
if ($isAdmin && Permission::can('jobs', 'approve')) {
    try {
        $pendingJobsSQL = "
            SELECT 
                j.*,
                c.company_name
            FROM jobs j
            JOIN clients c ON j.client_code = c.client_code
            WHERE j.approval_status = 'pending_approval'
            AND j.deleted_at IS NULL
            ORDER BY j.submitted_for_approval_at ASC
            LIMIT 5
        ";
        $pendingJobs = $conn->query($pendingJobsSQL)->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        Logger::getInstance()->error('Failed to fetch pending jobs', ['error' => $e->getMessage()]);
    }
}

$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
?>

<style>
.stat-card {
    transition: transform 0.2s, box-shadow 0.2s;
    border-left: 4px solid;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-card.clients { border-left-color: #0d6efd; }
.stat-card.jobs { border-left-color: #198754; }
.stat-card.candidates { border-left-color: #ffc107; }
.stat-card.submissions { border-left-color: #0dcaf0; }
.stat-card.placements { border-left-color: #20c997; }
</style>

<!-- Welcome Banner -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <h4 class="text-white mb-1">Welcome back, <?= escape($user['name']) ?>! 👋</h4>
                <p class="mb-0">Here's what's happening with your recruitment pipeline today.</p>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Clients -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card clients h-100">
            <div class="card-body text-center">
                <i class="bx bx-building display-4 text-primary mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['total_clients']) ?></h3>
                <small class="text-muted">Clients</small>
            </div>
        </div>
    </div>
    
    <!-- Active Jobs -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card jobs h-100">
            <div class="card-body text-center">
                <i class="bx bx-briefcase display-4 text-success mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['active_jobs']) ?></h3>
                <small class="text-muted">Active Jobs</small>
            </div>
        </div>
    </div>
    
    <!-- Total Candidates -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card candidates h-100">
            <div class="card-body text-center">
                <i class="bx bx-user-check display-4 text-warning mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['total_candidates']) ?></h3>
                <small class="text-muted">Candidates</small>
            </div>
        </div>
    </div>
    
    <!-- Pending Submissions -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card submissions h-100">
            <div class="card-body text-center">
                <i class="bx bx-send display-4 text-info mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['pending_submissions']) ?></h3>
                <small class="text-muted">Pending</small>
            </div>
        </div>
    </div>
    
    <!-- Total Placements -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card placements h-100">
            <div class="card-body text-center">
                <i class="bx bx-trophy display-4 text-success mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['total_placements']) ?></h3>
                <small class="text-muted">All-Time</small>
            </div>
        </div>
    </div>
    
    <!-- This Month -->
    <div class="col-md-4 col-lg-2">
        <div class="card stat-card placements h-100">
            <div class="card-body text-center">
                <i class="bx bx-calendar-check display-4 text-primary mb-2"></i>
                <h3 class="mb-0"><?= number_format($stats['this_month_placements']) ?></h3>
                <small class="text-muted">This Month</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="row">
    <!-- Left Column -->
    <div class="col-lg-8">
        
        <!-- Pending Job Approvals (Managers Only) -->
        <?php if ($isAdmin && !empty($pendingJobs)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-briefcase text-warning"></i> 
                    Pending Job Approvals 
                    <span class="badge bg-warning"><?= count($pendingJobs) ?></span>
                </h5>
                <a href="/panel/modules/jobs/?action=list&tab=pending" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <?php foreach ($pendingJobs as $job): ?>
                                <tr>
                                    <td>
                                        <strong><?= escape($job['job_title']) ?></strong><br>
                                        <small class="text-muted"><?= escape($job['company_name']) ?></small>
                                    </td>
                                    <td class="text-end">
                                        <small class="text-muted d-block"><?= timeAgo($job['submitted_for_approval_at']) ?></small>
                                        <a href="/panel/modules/jobs/?action=approve&code=<?= escape($job['job_code']) ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bx bx-check-circle"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Pending Submissions (Managers Only) -->
        <?php if ($isAdmin && !empty($pendingSubmissions)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-send text-warning"></i> 
                    Pending Submissions 
                    <span class="badge bg-warning"><?= count($pendingSubmissions) ?></span>
                </h5>
                <a href="/panel/modules/submissions/?action=list&tab=pending" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <tbody>
                            <?php foreach ($pendingSubmissions as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= escape($sub['candidate_name']) ?></strong>
                                        <i class="bx bx-right-arrow-alt mx-2"></i>
                                        <?= escape($sub['job_title']) ?><br>
                                        <small class="text-muted"><?= escape($sub['company_name']) ?></small>
                                    </td>
                                    <td class="text-end">
                                        <small class="text-muted d-block"><?= timeAgo($sub['created_at']) ?></small>
                                        <a href="/panel/modules/submissions/?action=view&code=<?= escape($sub['submission_code']) ?>" 
                                           class="btn btn-sm btn-warning">
                                            <i class="bx bx-check-circle"></i> Review
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-history"></i> Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivity)): ?>
                    <p class="text-muted text-center py-3">No recent activity</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="d-flex justify-content-between border-bottom pb-2 mb-2">
                                <div>
                                    <span class="badge bg-<?= getStatusBadge($activity['action']) ?>">
                                        <?= escape($activity['module']) ?>
                                    </span>
                                    <?= escape($activity['description'] ?? $activity['action']) ?>
                                </div>
                                <small class="text-muted">
                                    <?= timeAgo($activity['created_at']) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-zap"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (Permission::can('candidates', 'create')): ?>
                        <a href="/panel/modules/candidates/?action=create" class="btn btn-primary">
                            <i class="bx bx-user-plus"></i> Add Candidate
                        </a>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('jobs', 'create')): ?>
                        <a href="/panel/modules/jobs/?action=create" class="btn btn-success">
                            <i class="bx bx-briefcase-alt"></i> Create Job
                        </a>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('clients', 'create')): ?>
                        <a href="/panel/modules/clients/?action=create" class="btn btn-info">
                            <i class="bx bx-building"></i> Add Client
                        </a>
                    <?php endif; ?>
                    
                    <a href="/panel/modules/submissions/?action=list" class="btn btn-outline-primary">
                        <i class="bx bx-send"></i> View Submissions
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Quick Stats -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-bar-chart"></i> Quick Stats</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <span>Placement Rate:</span>
                    <strong>
                        <?php
                        $rate = $stats['total_candidates'] > 0 
                            ? round(($stats['total_placements'] / $stats['total_candidates']) * 100, 1) 
                            : 0;
                        echo $rate . '%';
                        ?>
                    </strong>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span>Active Pipeline:</span>
                    <strong><?= number_format($stats['pending_submissions']) ?> submissions</strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Jobs to Fill:</span>
                    <strong><?= number_format($stats['active_jobs']) ?> positions</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>