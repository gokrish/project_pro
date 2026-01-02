<?php
/**
 * Recruiter Performance Report
 * Track individual recruiter productivity and metrics
 * 
 * @version 5.0 - Production Ready
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

// Get date range (default: this month)
$period = filter_input(INPUT_GET, 'period', FILTER_SANITIZE_STRING) ?: 'month';
$specificUser = filter_input(INPUT_GET, 'user_code', FILTER_SANITIZE_STRING);

// Calculate date range based on period
switch ($period) {
    case 'today':
        $dateFrom = date('Y-m-d');
        $dateTo = date('Y-m-d');
        $periodLabel = 'Today';
        break;
    case 'week':
        $dateFrom = date('Y-m-d', strtotime('monday this week'));
        $dateTo = date('Y-m-d', strtotime('sunday this week'));
        $periodLabel = 'This Week';
        break;
    case 'month':
    default:
        $dateFrom = date('Y-m-01');
        $dateTo = date('Y-m-t');
        $periodLabel = 'This Month';
        break;
    case 'quarter':
        $currentMonth = date('n');
        $quarterStartMonth = floor(($currentMonth - 1) / 3) * 3 + 1;
        $dateFrom = date('Y-' . str_pad($quarterStartMonth, 2, '0', STR_PAD_LEFT) . '-01');
        $dateTo = date('Y-m-t', strtotime($dateFrom . ' +2 months'));
        $periodLabel = 'This Quarter';
        break;
    case 'year':
        $dateFrom = date('Y-01-01');
        $dateTo = date('Y-12-31');
        $periodLabel = 'This Year';
        break;
}

// ============================================================================
// GET RECRUITER PERFORMANCE DATA
// ============================================================================

$performanceData = [];

try {
    $sql = "
        SELECT 
            u.user_code,
            u.name as recruiter_name,
            u.email,
            
            -- Candidate Metrics
            COUNT(DISTINCT c.candidate_code) as total_candidates,
            COUNT(DISTINCT CASE WHEN c.status = 'new' THEN c.candidate_code END) as new_candidates,
            COUNT(DISTINCT CASE WHEN c.status = 'screening' THEN c.candidate_code END) as screening,
            COUNT(DISTINCT CASE WHEN c.status = 'qualified' THEN c.candidate_code END) as qualified,
            COUNT(DISTINCT CASE WHEN c.status = 'active' THEN c.candidate_code END) as active,
            COUNT(DISTINCT CASE WHEN c.status = 'placed' THEN c.candidate_code END) as placed,
            
            -- Period Activity
            COUNT(DISTINCT CASE 
                WHEN DATE(c.created_at) BETWEEN ? AND ? 
                THEN c.candidate_code 
            END) as candidates_added_period,
            
            -- Submission Metrics
            COUNT(DISTINCT s.submission_code) as total_submissions,
            COUNT(DISTINCT CASE 
                WHEN DATE(s.submitted_at) BETWEEN ? AND ? 
                THEN s.submission_code 
            END) as submissions_period,
            
            -- Placement in Period
            COUNT(DISTINCT CASE 
                WHEN DATE(sl.changed_at) BETWEEN ? AND ? 
                AND sl.new_status = 'placed' 
                THEN sl.candidate_code 
            END) as placements_period,
            
            -- Communication Metrics
            COUNT(DISTINCT comm.id) as total_communications,
            COUNT(DISTINCT CASE 
                WHEN comm.communication_type = 'Call' 
                THEN comm.id 
            END) as calls_logged,
            COUNT(DISTINCT CASE 
                WHEN DATE(comm.contacted_at) BETWEEN ? AND ? 
                THEN comm.id 
            END) as communications_period,
            
            -- Contact Conversion
            COUNT(DISTINCT cont.contact_code) as contacts_managed,
            COUNT(DISTINCT CASE 
                WHEN cont.converted_to_candidate IS NOT NULL 
                THEN cont.contact_code 
            END) as contacts_converted,
            
            -- CV Inbox
            COUNT(DISTINCT cv.id) as cv_handled,
            COUNT(DISTINCT CASE 
                WHEN cv.converted_at IS NOT NULL 
                THEN cv.id 
            END) as cv_converted
            
        FROM users u
        LEFT JOIN candidates c ON u.user_code = c.assigned_to AND c.deleted_at IS NULL
        LEFT JOIN submissions s ON u.user_code = s.submitted_by AND s.deleted_at IS NULL
        LEFT JOIN candidate_status_log sl ON u.user_code = sl.changed_by
        LEFT JOIN candidate_communications comm ON u.user_code = comm.contacted_by
        LEFT JOIN contacts cont ON u.user_code = cont.assigned_to AND cont.deleted_at IS NULL
        LEFT JOIN cv_inbox cv ON u.user_code = cv.assigned_to AND cv.deleted_at IS NULL
        WHERE u.is_active = 1 
        AND u.level IN ('recruiter', 'senior_recruiter', 'manager')
    ";
    
    // Add user filter if specified
    if ($specificUser) {
        $sql .= " AND u.user_code = ?";
    }
    
    // Add admin filter
    if (!$isAdmin) {
        $sql .= " AND u.user_code = ?";
    }
    
    $sql .= " GROUP BY u.user_code, u.name, u.email";
    $sql .= " ORDER BY placements_period DESC, submissions_period DESC, candidates_added_period DESC";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters (date range used 4 times)
    if ($specificUser && !$isAdmin) {
        $stmt->bind_param("ssssssssss", 
            $dateFrom, $dateTo,    // candidates_added_period
            $dateFrom, $dateTo,    // submissions_period
            $dateFrom, $dateTo,    // placements_period
            $dateFrom, $dateTo,    // communications_period
            $specificUser,         // specific user filter
            Auth::userCode()       // admin filter
        );
    } elseif ($specificUser) {
        $stmt->bind_param("sssssssss", 
            $dateFrom, $dateTo,    // candidates_added_period
            $dateFrom, $dateTo,    // submissions_period
            $dateFrom, $dateTo,    // placements_period
            $dateFrom, $dateTo,    // communications_period
            $specificUser          // specific user filter
        );
    } elseif (!$isAdmin) {
        $stmt->bind_param("sssssssss", 
            $dateFrom, $dateTo,    // candidates_added_period
            $dateFrom, $dateTo,    // submissions_period
            $dateFrom, $dateTo,    // placements_period
            $dateFrom, $dateTo,    // communications_period
            Auth::userCode()       // admin filter
        );
    } else {
        $stmt->bind_param("ssssssss", 
            $dateFrom, $dateTo,    // candidates_added_period
            $dateFrom, $dateTo,    // submissions_period
            $dateFrom, $dateTo,    // placements_period
            $dateFrom, $dateTo     // communications_period
        );
    }
    
    $stmt->execute();
    $performanceData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    \ProConsultancy\Core\Logger::getInstance()->error('Performance report failed', [
        'error' => $e->getMessage()
    ]);
}

// Calculate totals
$totals = [
    'candidates_added_period' => 0,
    'submissions_period' => 0,
    'placements_period' => 0,
    'communications_period' => 0,
    'contacts_converted' => 0,
    'cv_converted' => 0
];

foreach ($performanceData as $rec) {
    $totals['candidates_added_period'] += $rec['candidates_added_period'];
    $totals['submissions_period'] += $rec['submissions_period'];
    $totals['placements_period'] += $rec['placements_period'];
    $totals['communications_period'] += $rec['communications_period'];
    $totals['contacts_converted'] += $rec['contacts_converted'];
    $totals['cv_converted'] += $rec['cv_converted'];
}

// ============================================================================
// GET ALL RECRUITERS FOR FILTER
// ============================================================================

$allRecruiters = [];
if ($isAdmin) {
    try {
        $stmt = $conn->prepare("
            SELECT user_code, name 
            FROM users 
            WHERE level IN ('recruiter', 'senior_recruiter', 'manager')
            AND is_active = 1
            ORDER BY name
        ");
        $stmt->execute();
        $allRecruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        // Silently fail
    }
}

// Page config
$pageTitle = 'Recruiter Performance';
$breadcrumbs = [
    ['title' => 'Reports', 'url' => '/panel/modules/reports/'],
    ['title' => 'Performance', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user-check me-2"></i>
                Recruiter Performance Report
            </h4>
            <p class="text-muted mb-0"><?= $periodLabel ?> - Individual Metrics</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bx bx-printer"></i>
            </button>
            <a href="/panel/modules/reports/handlers/export.php?report=performance&period=<?= urlencode($period) ?><?= $specificUser ? '&user_code=' . urlencode($specificUser) : '' ?>" 
               class="btn btn-outline-success">
                <i class="bx bx-download"></i> Export
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <!-- Period Filter -->
                <div class="col-md-3">
                    <label class="form-label">Period</label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>Today</option>
                        <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>This Month</option>
                        <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>This Quarter</option>
                        <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>This Year</option>
                    </select>
                </div>
                
                <!-- Recruiter Filter (Admin Only) -->
                <?php if ($isAdmin && !empty($allRecruiters)): ?>
                <div class="col-md-4">
                    <label class="form-label">Filter by Recruiter</label>
                    <select name="user_code" class="form-select" onchange="this.form.submit()">
                        <option value="">All Recruiters</option>
                        <?php foreach ($allRecruiters as $rec): ?>
                            <option value="<?= htmlspecialchars($rec['user_code']) ?>" 
                                    <?= $specificUser === $rec['user_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rec['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 d-flex align-items-end">
                    <?php if ($specificUser || $period !== 'month'): ?>
                        <a href="/panel/modules/reports/recruiter_performance.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= number_format($totals['candidates_added_period']) ?></h3>
                    <small class="text-muted">Candidates Added</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= number_format($totals['communications_period']) ?></h3>
                    <small class="text-muted">Communications</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= number_format($totals['submissions_period']) ?></h3>
                    <small class="text-muted">Submissions</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body text-center">
                    <h3 class="mb-0 text-success"><?= number_format($totals['placements_period']) ?></h3>
                    <small class="text-muted">Placements</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= number_format($totals['contacts_converted']) ?></h3>
                    <small class="text-muted">Contacts Converted</small>
                </div>
            </div>
        </div>
        
        <div class="col-md-2">
            <div class="card">
                <div class="card-body text-center">
                    <h3 class="mb-0"><?= number_format($totals['cv_converted']) ?></h3>
                    <small class="text-muted">CVs Converted</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Table -->
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bx bx-bar-chart"></i> Individual Performance Breakdown
            </h5>
        </div>
        <div class="card-body">
            <?php if (empty($performanceData)): ?>
                <p class="text-muted text-center py-4">No performance data available</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Recruiter</th>
                                <th class="text-center">Candidates<br><small class="text-muted">Added</small></th>
                                <th class="text-center">Calls<br><small class="text-muted">Logged</small></th>
                                <th class="text-center">Submissions<br><small class="text-muted">Created</small></th>
                                <th class="text-center">Placements<br><small class="text-muted">Made</small></th>
                                <th class="text-center">Contacts<br><small class="text-muted">Converted</small></th>
                                <th class="text-center">CVs<br><small class="text-muted">Processed</small></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($performanceData as $index => $rec): ?>
                            <tr>
                                <td>
                                    <?php if ($index === 0 && $rec['placements_period'] > 0): ?>
                                        <span class="badge bg-warning">🏆 #<?= $index + 1 ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">#<?= $index + 1 ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($rec['recruiter_name']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= $rec['total_candidates'] ?> total candidates
                                        </small>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['candidates_added_period'] > 0): ?>
                                        <span class="badge bg-primary"><?= $rec['candidates_added_period'] ?></span>
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
                                    <?php if ($rec['submissions_period'] > 0): ?>
                                        <span class="badge bg-warning"><?= $rec['submissions_period'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['placements_period'] > 0): ?>
                                        <span class="badge bg-success"><?= $rec['placements_period'] ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['contacts_converted'] > 0): ?>
                                        <?= $rec['contacts_converted'] ?>
                                        <small class="text-muted">/ <?= $rec['contacts_managed'] ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($rec['cv_converted'] > 0): ?>
                                        <?= $rec['cv_converted'] ?>
                                        <small class="text-muted">/ <?= $rec['cv_handled'] ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">Team Total</th>
                                <th class="text-center"><?= number_format($totals['candidates_added_period']) ?></th>
                                <th class="text-center">-</th>
                                <th class="text-center"><?= number_format($totals['submissions_period']) ?></th>
                                <th class="text-center"><?= number_format($totals['placements_period']) ?></th>
                                <th class="text-center"><?= number_format($totals['contacts_converted']) ?></th>
                                <th class="text-center"><?= number_format($totals['cv_converted']) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="card mt-3">
        <div class="card-header">
            <h6 class="mb-0">Other Reports</h6>
        </div>
        <div class="list-group list-group-flush">
            <a href="/panel/modules/reports/daily.php" class="list-group-item list-group-item-action">
                <i class="bx bx-calendar me-2"></i> Daily Report
            </a>
            <a href="/panel/modules/reports/pipeline.php" class="list-group-item list-group-item-action">
                <i class="bx bx-trending-up me-2"></i> Pipeline Report
            </a>
            <a href="/panel/modules/reports/followup.php" class="list-group-item list-group-item-action">
                <i class="bx bx-alarm me-2"></i> Follow-up Dashboard
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
