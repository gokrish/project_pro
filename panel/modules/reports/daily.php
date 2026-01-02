<?php
/**
 * Daily Activity Report
 * Shows today's activities across all recruitment functions
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth};

// Check permission
Permission::require('reports', 'view_dashboard');

$user = Auth::user();
$userLevel = $user['level'] ?? 'user';
$isAdmin = in_array($userLevel, ['admin', 'super_admin', 'manager']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Get date range (default: today)
$date = filter_input(INPUT_GET, 'date', FILTER_SANITIZE_STRING) ?: date('Y-m-d');
$displayDate = date('l, F j, Y', strtotime($date));

// Initialize metrics
$metrics = [
    'new_candidates' => 0,
    'new_contacts' => 0,
    'cv_received' => 0,
    'cv_converted' => 0,
    'contacted' => 0,
    'qualified' => 0,
    'submissions' => 0,
    'placements' => 0,
    'calls_logged' => 0,
    'emails_sent' => 0,
    'jobs_created' => 0,
    'clients_added' => 0
];

// ============================================================================
// GET TODAY'S METRICS
// ============================================================================

try {
    // Build access filter
    $accessFilter = '';
    $userCode = Auth::userCode();
    
    if (!$isAdmin) {
        $accessFilter = " AND c.created_by = '$userCode'";
    }
    
    // Get comprehensive metrics
    $stmt = $conn->prepare("
        SELECT 
            -- Candidates
            COUNT(DISTINCT CASE WHEN DATE(c.created_at) = ? THEN c.candidate_code END) as new_candidates,
            COUNT(DISTINCT CASE WHEN DATE(c.last_contacted_date) = ? THEN c.candidate_code END) as contacted,
            
            -- Status Changes
            COUNT(DISTINCT CASE 
                WHEN DATE(sl.changed_at) = ? 
                AND sl.new_status = 'qualified' 
                THEN sl.candidate_code 
            END) as qualified,
            
            COUNT(DISTINCT CASE 
                WHEN DATE(sl.changed_at) = ? 
                AND sl.new_status = 'placed' 
                THEN sl.candidate_code 
            END) as placements,
            
            -- Submissions
            COUNT(DISTINCT CASE 
                WHEN DATE(s.submitted_at) = ? 
                THEN s.submission_code 
            END) as submissions,
            
            -- Communications
            COUNT(DISTINCT CASE 
                WHEN DATE(comm.contacted_at) = ? 
                AND comm.communication_type = 'Call' 
                THEN comm.id 
            END) as calls_logged,
            
            COUNT(DISTINCT CASE 
                WHEN DATE(comm.contacted_at) = ? 
                AND comm.communication_type = 'Email' 
                THEN comm.id 
            END) as emails_sent
            
        FROM candidates c
        LEFT JOIN candidate_status_log sl ON c.candidate_code = sl.candidate_code
        LEFT JOIN submissions s ON c.candidate_code = s.candidate_code AND s.deleted_at IS NULL
        LEFT JOIN candidate_communications comm ON c.candidate_code = comm.candidate_code
        WHERE c.deleted_at IS NULL
    ");
    
    $stmt->bind_param("sssssss", $date, $date, $date, $date, $date, $date, $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result) {
        $metrics = array_merge($metrics, $result);
    }
    
    // Get contacts metrics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN DATE(created_at) = ? THEN contact_code END) as new_contacts,
            COUNT(DISTINCT CASE WHEN DATE(converted_at) = ? THEN contact_code END) as contacts_converted
        FROM contacts
        WHERE deleted_at IS NULL" . ($isAdmin ? '' : " AND assigned_to = ?")
    );
    
    if ($isAdmin) {
        $stmt->bind_param("ss", $date, $date);
    } else {
        $stmt->bind_param("sss", $date, $date, $userCode);
    }
    
    $stmt->execute();
    $contactMetrics = $stmt->get_result()->fetch_assoc();
    $metrics['new_contacts'] = $contactMetrics['new_contacts'];
    
    // Get CV Inbox metrics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN DATE(created_at) = ? THEN id END) as cv_received,
            COUNT(DISTINCT CASE WHEN DATE(converted_at) = ? THEN id END) as cv_converted
        FROM cv_inbox
        WHERE deleted_at IS NULL" . ($isAdmin ? '' : " AND assigned_to = ?")
    );
    
    if ($isAdmin) {
        $stmt->bind_param("ss", $date, $date);
    } else {
        $stmt->bind_param("sss", $date, $date, $userCode);
    }
    
    $stmt->execute();
    $cvMetrics = $stmt->get_result()->fetch_assoc();
    $metrics['cv_received'] = $cvMetrics['cv_received'];
    $metrics['cv_converted'] = $cvMetrics['cv_converted'];
    
    // Get Jobs & Clients
    $stmt = $conn->prepare("
        SELECT 
            COUNT(DISTINCT CASE WHEN DATE(j.created_at) = ? THEN j.job_code END) as jobs_created,
            COUNT(DISTINCT CASE WHEN DATE(cl.created_at) = ? THEN cl.client_code END) as clients_added
        FROM jobs j, clients cl
        WHERE j.deleted_at IS NULL AND cl.deleted_at IS NULL
    ");
    
    $stmt->bind_param("ss", $date, $date);
    $stmt->execute();
    $otherMetrics = $stmt->get_result()->fetch_assoc();
    $metrics = array_merge($metrics, $otherMetrics);
    
} catch (Exception $e) {
    // Log error but don't break page
    \ProConsultancy\Core\Logger::getInstance()->error('Daily report metrics failed', [
        'error' => $e->getMessage(),
        'date' => $date
    ]);
}

// ============================================================================
// GET RECRUITER BREAKDOWN (Admin Only)
// ============================================================================

$recruiterBreakdown = [];
if ($isAdmin) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                u.user_code,
                u.name as recruiter_name,
                
                -- Candidates
                COUNT(DISTINCT CASE WHEN DATE(c.created_at) = ? THEN c.candidate_code END) as candidates_added,
                
                -- Communications
                COUNT(DISTINCT CASE 
                    WHEN DATE(comm.contacted_at) = ? 
                    AND comm.communication_type = 'Call' 
                    THEN comm.id 
                END) as calls_logged,
                
                -- Submissions
                COUNT(DISTINCT CASE WHEN DATE(s.submitted_at) = ? THEN s.submission_code END) as submissions_created,
                
                -- Placements
                COUNT(DISTINCT CASE 
                    WHEN DATE(sl.changed_at) = ? 
                    AND sl.new_status = 'placed' 
                    THEN sl.candidate_code 
                END) as placements
                
            FROM users u
            LEFT JOIN candidates c ON u.user_code = c.created_by AND c.deleted_at IS NULL
            LEFT JOIN candidate_communications comm ON u.user_code = comm.contacted_by
            LEFT JOIN submissions s ON u.user_code = s.submitted_by AND s.deleted_at IS NULL
            LEFT JOIN candidate_status_log sl ON u.user_code = sl.changed_by
            WHERE u.level IN ('recruiter', 'senior_recruiter', 'manager')
            AND u.is_active = 1
            GROUP BY u.user_code, u.name
            HAVING candidates_added > 0 OR calls_logged > 0 OR submissions_created > 0
            ORDER BY placements DESC, submissions_created DESC, candidates_added DESC
        ");
        
        $stmt->bind_param("ssss", $date, $date, $date, $date);
        $stmt->execute();
        $recruiterBreakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
    } catch (Exception $e) {
        \ProConsultancy\Core\Logger::getInstance()->error('Recruiter breakdown failed', [
            'error' => $e->getMessage()
        ]);
    }
}

// Page config
$pageTitle = 'Daily Activity Report';
$breadcrumbs = [
    ['title' => 'Reports', 'url' => '/panel/modules/reports/'],
    ['title' => 'Daily Activity', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-calendar me-2"></i>
                Daily Activity Report
            </h4>
            <p class="text-muted mb-0"><?= $displayDate ?></p>
        </div>
        <div class="btn-group">
            <!-- Date Selector -->
            <form method="GET" class="d-flex gap-2">
                <input type="date" name="date" class="form-control" 
                       value="<?= $date ?>" 
                       max="<?= date('Y-m-d') ?>"
                       onchange="this.form.submit()">
                
                <!-- Quick links -->
                <a href="?date=<?= date('Y-m-d', strtotime('-1 day')) ?>" 
                   class="btn btn-outline-secondary" title="Yesterday">
                    <i class="bx bx-chevron-left"></i>
                </a>
                <a href="?date=<?= date('Y-m-d') ?>" 
                   class="btn btn-outline-primary" title="Today">
                    Today
                </a>
            </form>
            
            <!-- Actions -->
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bx bx-printer"></i>
            </button>
            <a href="/panel/modules/reports/handlers/export.php?report=daily&date=<?= $date ?>" 
               class="btn btn-outline-success">
                <i class="bx bx-download"></i> Export
            </a>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <!-- Lead Generation -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">
                        <i class="bx bx-user-plus"></i> Lead Generation
                    </h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0"><?= number_format($metrics['new_contacts']) ?></h4>
                                <small class="text-muted">New Contacts</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0"><?= number_format($metrics['cv_received']) ?></h4>
                                <small class="text-muted">CVs Received</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Candidate Pipeline -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">
                        <i class="bx bx-user"></i> Candidate Pipeline
                    </h6>
                    <div class="row g-2">
                        <div class="col-4">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-primary"><?= number_format($metrics['new_candidates']) ?></h4>
                                <small class="text-muted">Added</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-info"><?= number_format($metrics['contacted']) ?></h4>
                                <small class="text-muted">Contacted</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-success"><?= number_format($metrics['qualified']) ?></h4>
                                <small class="text-muted">Qualified</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Activity -->
        <div class="col-md-3">
            <div class="card h-100">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">
                        <i class="bx bx-message-square-dots"></i> Activity
                    </h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0"><?= number_format($metrics['calls_logged']) ?></h4>
                                <small class="text-muted">Calls</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0"><?= number_format($metrics['emails_sent']) ?></h4>
                                <small class="text-muted">Emails</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="col-md-3">
            <div class="card h-100 border-success">
                <div class="card-body">
                    <h6 class="card-subtitle mb-2 text-muted">
                        <i class="bx bx-check-circle"></i> Results
                    </h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="text-center p-2 border rounded">
                                <h4 class="mb-0 text-warning"><?= number_format($metrics['submissions']) ?></h4>
                                <small class="text-muted">Submissions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center p-2 border rounded bg-success-subtle">
                                <h4 class="mb-0 text-success"><?= number_format($metrics['placements']) ?></h4>
                                <small class="text-muted">Placements</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Breakdown -->
    <div class="row g-4">
        <!-- Recruiter Performance (Admin Only) -->
        <?php if ($isAdmin && !empty($recruiterBreakdown)): ?>
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-group"></i> Recruiter Activity Breakdown
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Recruiter</th>
                                    <th class="text-center">Candidates</th>
                                    <th class="text-center">Calls</th>
                                    <th class="text-center">Submissions</th>
                                    <th class="text-center">Placements</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recruiterBreakdown as $rec): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($rec['recruiter_name']) ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rec['candidates_added'] > 0): ?>
                                            <span class="badge bg-primary"><?= $rec['candidates_added'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rec['calls_logged'] > 0): ?>
                                            <span class="badge bg-info"><?= $rec['calls_logged'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rec['submissions_created'] > 0): ?>
                                            <span class="badge bg-warning"><?= $rec['submissions_created'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($rec['placements'] > 0): ?>
                                            <span class="badge bg-success"><?= $rec['placements'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>Total</th>
                                    <th class="text-center">
                                        <?= array_sum(array_column($recruiterBreakdown, 'candidates_added')) ?>
                                    </th>
                                    <th class="text-center">
                                        <?= array_sum(array_column($recruiterBreakdown, 'calls_logged')) ?>
                                    </th>
                                    <th class="text-center">
                                        <?= array_sum(array_column($recruiterBreakdown, 'submissions_created')) ?>
                                    </th>
                                    <th class="text-center">
                                        <?= array_sum(array_column($recruiterBreakdown, 'placements')) ?>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Key Highlights -->
        <div class="col-lg-<?= ($isAdmin && !empty($recruiterBreakdown)) ? '4' : '12' ?>">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-star"></i> Key Highlights
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <?php if ($metrics['placements'] > 0): ?>
                            <li class="mb-3">
                                <i class="bx bx-check-circle text-success me-2"></i>
                                <strong><?= $metrics['placements'] ?></strong> 
                                placement<?= $metrics['placements'] != 1 ? 's' : '' ?> made! 🎉
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($metrics['submissions'] > 0): ?>
                            <li class="mb-3">
                                <i class="bx bx-send text-warning me-2"></i>
                                <strong><?= $metrics['submissions'] ?></strong> 
                                candidate<?= $metrics['submissions'] != 1 ? 's' : '' ?> submitted to clients
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($metrics['new_candidates'] > 0): ?>
                            <li class="mb-3">
                                <i class="bx bx-user-plus text-primary me-2"></i>
                                <strong><?= $metrics['new_candidates'] ?></strong> 
                                new candidate<?= $metrics['new_candidates'] != 1 ? 's' : '' ?> added
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($metrics['calls_logged'] > 0): ?>
                            <li class="mb-3">
                                <i class="bx bx-phone text-info me-2"></i>
                                <strong><?= $metrics['calls_logged'] ?></strong> 
                                call<?= $metrics['calls_logged'] != 1 ? 's' : '' ?> logged
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($metrics['cv_converted'] > 0): ?>
                            <li class="mb-3">
                                <i class="bx bx-file-blank text-success me-2"></i>
                                <strong><?= $metrics['cv_converted'] ?></strong> 
                                CV<?= $metrics['cv_converted'] != 1 ? 's' : '' ?> converted to candidates
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        // If no activity
                        $totalActivity = $metrics['placements'] + $metrics['submissions'] + 
                                       $metrics['new_candidates'] + $metrics['calls_logged'] + 
                                       $metrics['cv_converted'];
                        
                        if ($totalActivity === 0): 
                        ?>
                            <li class="text-muted text-center py-3">
                                <i class="bx bx-info-circle"></i>
                                No significant activity recorded for this date
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">Other Reports</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/panel/modules/reports/pipeline.php" class="list-group-item list-group-item-action">
                        <i class="bx bx-trending-up me-2"></i> Pipeline Report
                    </a>
                    <a href="/panel/modules/reports/recruiter_performance.php" class="list-group-item list-group-item-action">
                        <i class="bx bx-user-check me-2"></i> Recruiter Performance
                    </a>
                    <a href="/panel/modules/reports/followup.php" class="list-group-item list-group-item-action">
                        <i class="bx bx-alarm me-2"></i> Follow-up Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
