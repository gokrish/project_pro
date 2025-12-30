<?php
/**
 * Main Dashboard
 * Unified role-based dashboard
 */

require_once __DIR__ . '/_common.php';

use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;

Permission::require('reports', 'view_dashboard');

$user = Auth::user();
$userCode = Auth::userCode();
$userLevel = $user['level'] ?? 'user';

// Role detection
$isRecruiter = in_array($userLevel, ['recruiter', 'coordinator', 'user']);
$isManagerOrAdmin = in_array($userLevel, ['admin', 'manager', 'super_admin']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Initialize stats
$stats = [
    'total_candidates' => 0,
    'follow_ups_today' => 0,
    'hot_leads' => 0,
    'active_jobs' => 0,
    // ... etc
];

// Get stats based on role
try {
    if ($isRecruiter) {
        // RECRUITER STATS - Only their data
        $stmt = $conn->prepare("SELECT COUNT(*) as count 
            FROM candidates 
            WHERE deleted_at IS NULL 
            AND (created_by = ? OR assigned_to = ?)");
        $stmt->bind_param("ss", $userCode, $userCode);
        $stmt->execute();
        $stats['total_candidates'] = $stmt->get_result()->fetch_assoc()['count'];
        
    } else {
        // ADMIN STATS - All data
        $result = $conn->query("SELECT COUNT(*) as count 
            FROM candidates 
            WHERE deleted_at IS NULL");
        $stats['total_candidates'] = $result->fetch_assoc()['count'];
    }
    
    // ... more stats queries (all using prepared statements)
    
} catch (Exception $e) {
    Logger::getInstance()->error('Dashboard stats failed', [
        'error' => $e->getMessage()
    ]);
}

require_once __DIR__ . '/includes/header.php';
?>
<div class="card">
    <div class="card-header">Pending Approvals (<?= $pendingCount ?>)</div>
    <div class="card-body">
        <?php foreach ($pendingSubmissions as $sub): ?>
            <div class="d-flex justify-content-between">
                <span><?= $sub['candidate_name'] ?> → <?= $sub['job_title'] ?></span>
                <small><?= timeAgo($sub['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<!-- Dashboard Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Stats Cards (Unified for both roles) -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <!-- Stats -->
            </div>
        </div>
        <!-- More cards -->
    </div>
    
    <div class="row">
        <!-- Left Column -->
        <div class="col-lg-8">
            
            <!-- Today's Follow-ups (Both Roles) -->
            <div class="card mb-4">
                <!-- Follow-ups -->
            </div>
            
            <?php if ($isManagerOrAdmin): ?>
                <!-- Pending Reviews (Managers Only) -->
                <div class="card mb-4">
                    <!-- Admin-specific content -->
                </div>
            <?php endif; ?>
            
            <!-- Recent Activity (Both Roles) -->
            <div class="card">
                <!-- Activity -->
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="col-lg-4">
            
            <!-- Quick Actions (Role-specific) -->
            <div class="card mb-4">
                <div class="card-body">
                    <?php if ($isRecruiter): ?>
                        <!-- Recruiter actions -->
                        <a href="/panel/modules/candidates/create.php">Add Candidate</a>
                        <a href="/panel/modules/cv-inbox/">CV Inbox</a>
                    <?php else: ?>
                        <!-- Manager actions -->
                        <a href="/panel/modules/submissions/">Review Submissions</a>
                        <a href="/panel/modules/reports/">Reports</a>
                    <?php endif; ?>
                </div>
            </div>
            
            <?php if ($isManagerOrAdmin): ?>
                <!-- Team Performance (Managers Only) -->
                <div class="card">
                    <!-- Team stats -->
                </div>
            <?php endif; ?>
            
        </div>
    </div>
    
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>