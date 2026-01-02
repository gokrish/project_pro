<?php
/**
 * Follow-up Dashboard
 * Track upcoming and overdue follow-ups
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

// Get filter
$urgency = filter_input(INPUT_GET, 'urgency', FILTER_SANITIZE_STRING);
$assignedTo = filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING);

// ============================================================================
// GET FOLLOW-UP DATA
// ============================================================================

$followups = [
    'overdue' => [],
    'today' => [],
    'this_week' => [],
    'later' => []
];

$counts = [
    'overdue' => 0,
    'today' => 0,
    'this_week' => 0,
    'later' => 0,
    'total' => 0
];

try {
    $sql = "
        SELECT 
            c.candidate_code,
            c.candidate_name,
            c.email,
            c.phone,
            c.status,
            c.lead_type,
            c.follow_up_date,
            c.last_contacted_date,
            c.follow_up_status,
            DATEDIFF(c.follow_up_date, CURDATE()) as days_until_followup,
            u.name as assigned_to_name,
            u.user_code as assigned_to_code,
            
            CASE 
                WHEN c.follow_up_date < CURDATE() THEN 'overdue'
                WHEN c.follow_up_date = CURDATE() THEN 'today'
                WHEN c.follow_up_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'this_week'
                ELSE 'later'
            END as urgency_level
            
        FROM candidates c
        LEFT JOIN users u ON c.assigned_to = u.user_code
        WHERE c.follow_up_date IS NOT NULL
        AND c.status NOT IN ('rejected', 'placed', 'archived')
        AND c.deleted_at IS NULL
    ";
    
    // Add filters
    $params = [];
    $types = '';
    
    if (!$isAdmin) {
        $sql .= " AND c.assigned_to = ?";
        $params[] = Auth::userCode();
        $types .= 's';
    }
    
    if ($assignedTo) {
        $sql .= " AND c.assigned_to = ?";
        $params[] = $assignedTo;
        $types .= 's';
    }
    
    if ($urgency) {
        switch ($urgency) {
            case 'overdue':
                $sql .= " AND c.follow_up_date < CURDATE()";
                break;
            case 'today':
                $sql .= " AND c.follow_up_date = CURDATE()";
                break;
            case 'this_week':
                $sql .= " AND c.follow_up_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                break;
            case 'later':
                $sql .= " AND c.follow_up_date > DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                break;
        }
    }
    
    $sql .= " ORDER BY c.follow_up_date ASC, c.lead_type DESC";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Organize by urgency
    foreach ($result as $followup) {
        $urgencyLevel = $followup['urgency_level'];
        $followups[$urgencyLevel][] = $followup;
        $counts[$urgencyLevel]++;
        $counts['total']++;
    }
    
} catch (Exception $e) {
    \ProConsultancy\Core\Logger::getInstance()->error('Follow-up dashboard failed', [
        'error' => $e->getMessage()
    ]);
}

// ============================================================================
// GET RECRUITERS FOR FILTER
// ============================================================================

$recruiters = [];
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
        $recruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (Exception $e) {
        // Silently fail
    }
}

// Page config
$pageTitle = 'Follow-up Dashboard';
$breadcrumbs = [
    ['title' => 'Reports', 'url' => '/panel/modules/reports/'],
    ['title' => 'Follow-up', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-alarm me-2"></i>
                Follow-up Dashboard
            </h4>
            <p class="text-muted mb-0">Track upcoming and overdue follow-ups</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bx bx-printer"></i>
            </button>
            <a href="/panel/modules/reports/handlers/export.php?report=followup<?= $urgency ? '&urgency=' . urlencode($urgency) : '' ?><?= $assignedTo ? '&assigned_to=' . urlencode($assignedTo) : '' ?>" 
               class="btn btn-outline-success">
                <i class="bx bx-download"></i> Export
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Filter by Urgency</label>
                    <select name="urgency" class="form-select" onchange="this.form.submit()">
                        <option value="">All Follow-ups</option>
                        <option value="overdue" <?= $urgency === 'overdue' ? 'selected' : '' ?>>Overdue Only</option>
                        <option value="today" <?= $urgency === 'today' ? 'selected' : '' ?>>Due Today</option>
                        <option value="this_week" <?= $urgency === 'this_week' ? 'selected' : '' ?>>This Week</option>
                        <option value="later" <?= $urgency === 'later' ? 'selected' : '' ?>>Later</option>
                    </select>
                </div>
                
                <?php if ($isAdmin && !empty($recruiters)): ?>
                <div class="col-md-4">
                    <label class="form-label">Filter by Recruiter</label>
                    <select name="assigned_to" class="form-select" onchange="this.form.submit()">
                        <option value="">All Recruiters</option>
                        <?php foreach ($recruiters as $rec): ?>
                            <option value="<?= htmlspecialchars($rec['user_code']) ?>" 
                                    <?= $assignedTo === $rec['user_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rec['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-3 d-flex align-items-end">
                    <?php if ($urgency || $assignedTo): ?>
                        <a href="/panel/modules/reports/followup.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-danger">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <span class="badge badge-center rounded-pill bg-danger" style="width: 50px; height: 50px;">
                            <i class="bx bx-alarm-exclamation bx-md"></i>
                        </span>
                    </div>
                    <h2 class="mb-0 text-danger"><?= number_format($counts['overdue']) ?></h2>
                    <p class="mb-0">Overdue</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-warning">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <span class="badge badge-center rounded-pill bg-warning" style="width: 50px; height: 50px;">
                            <i class="bx bx-alarm bx-md"></i>
                        </span>
                    </div>
                    <h2 class="mb-0 text-warning"><?= number_format($counts['today']) ?></h2>
                    <p class="mb-0">Due Today</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card border-info">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <span class="badge badge-center rounded-pill bg-info" style="width: 50px; height: 50px;">
                            <i class="bx bx-calendar-check bx-md"></i>
                        </span>
                    </div>
                    <h2 class="mb-0 text-info"><?= number_format($counts['this_week']) ?></h2>
                    <p class="mb-0">This Week</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-center align-items-center mb-2">
                        <span class="badge badge-center rounded-pill bg-secondary" style="width: 50px; height: 50px;">
                            <i class="bx bx-calendar bx-md"></i>
                        </span>
                    </div>
                    <h2 class="mb-0"><?= number_format($counts['total']) ?></h2>
                    <p class="mb-0">Total</p>
                </div>
            </div>
        </div>
    </div>

    <?php if ($counts['total'] === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-check-circle bx-lg text-success mb-3" style="font-size: 4rem;"></i>
                <h5>All Caught Up! 🎉</h5>
                <p class="text-muted">No pending follow-ups at this time.</p>
            </div>
        </div>
    <?php else: ?>

    <!-- Overdue Follow-ups -->
    <?php if (!empty($followups['overdue'])): ?>
    <div class="card mb-4 border-danger">
        <div class="card-header bg-danger text-white">
            <h5 class="mb-0">
                <i class="bx bx-alarm-exclamation"></i> Overdue Follow-ups
                <span class="badge bg-white text-danger ms-2"><?= count($followups['overdue']) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Status</th>
                            <th>Lead Type</th>
                            <th>Due Date</th>
                            <th>Days Overdue</th>
                            <?php if ($isAdmin): ?><th>Assigned To</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups['overdue'] as $followup): ?>
                        <tr>
                            <td>
                                <div>
                                    <a href="/panel/modules/candidates/view.php?code=<?= htmlspecialchars($followup['candidate_code']) ?>" 
                                       class="text-decoration-none">
                                        <strong><?= htmlspecialchars($followup['candidate_name']) ?></strong>
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($followup['phone'] ?? 'No phone') ?>
                                    </small>
                                </div>
                            </td>
                            <td><?= getCandidateStatusBadge($followup['status']) ?></td>
                            <td><?= getLeadTypeBadge($followup['lead_type']) ?></td>
                            <td><?= date('M d, Y', strtotime($followup['follow_up_date'])) ?></td>
                            <td>
                                <span class="badge bg-danger">
                                    <?= abs($followup['days_until_followup']) ?> days
                                </span>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td><?= htmlspecialchars($followup['assigned_to_name'] ?? 'Unassigned') ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="tel:<?= htmlspecialchars($followup['phone']) ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Call">
                                        <i class="bx bx-phone"></i>
                                    </a>
                                    <a href="mailto:<?= htmlspecialchars($followup['email']) ?>" 
                                       class="btn btn-outline-secondary" 
                                       title="Email">
                                        <i class="bx bx-envelope"></i>
                                    </a>
                                    <a href="/panel/modules/candidates/view.php?code=<?= htmlspecialchars($followup['candidate_code']) ?>" 
                                       class="btn btn-outline-info" 
                                       title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Due Today -->
    <?php if (!empty($followups['today'])): ?>
    <div class="card mb-4 border-warning">
        <div class="card-header bg-warning">
            <h5 class="mb-0">
                <i class="bx bx-alarm"></i> Due Today
                <span class="badge bg-white text-warning ms-2"><?= count($followups['today']) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Status</th>
                            <th>Lead Type</th>
                            <th>Last Contact</th>
                            <?php if ($isAdmin): ?><th>Assigned To</th><?php endif; ?>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups['today'] as $followup): ?>
                        <tr>
                            <td>
                                <div>
                                    <a href="/panel/modules/candidates/view.php?code=<?= htmlspecialchars($followup['candidate_code']) ?>" 
                                       class="text-decoration-none">
                                        <strong><?= htmlspecialchars($followup['candidate_name']) ?></strong>
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($followup['phone'] ?? 'No phone') ?>
                                    </small>
                                </div>
                            </td>
                            <td><?= getCandidateStatusBadge($followup['status']) ?></td>
                            <td><?= getLeadTypeBadge($followup['lead_type']) ?></td>
                            <td>
                                <?php if ($followup['last_contacted_date']): ?>
                                    <?= date('M d', strtotime($followup['last_contacted_date'])) ?>
                                <?php else: ?>
                                    <span class="text-muted">Never</span>
                                <?php endif; ?>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td><?= htmlspecialchars($followup['assigned_to_name'] ?? 'Unassigned') ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="btn-group btn-group-sm">
                                    <a href="tel:<?= htmlspecialchars($followup['phone']) ?>" 
                                       class="btn btn-outline-primary" 
                                       title="Call">
                                        <i class="bx bx-phone"></i>
                                    </a>
                                    <a href="mailto:<?= htmlspecialchars($followup['email']) ?>" 
                                       class="btn btn-outline-secondary" 
                                       title="Email">
                                        <i class="bx bx-envelope"></i>
                                    </a>
                                    <a href="/panel/modules/candidates/view.php?code=<?= htmlspecialchars($followup['candidate_code']) ?>" 
                                       class="btn btn-outline-info" 
                                       title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- This Week -->
    <?php if (!empty($followups['this_week'])): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bx bx-calendar-check"></i> Due This Week
                <span class="badge bg-info ms-2"><?= count($followups['this_week']) ?></span>
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Status</th>
                            <th>Lead Type</th>
                            <th>Due Date</th>
                            <th>Days Until</th>
                            <?php if ($isAdmin): ?><th>Assigned To</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($followups['this_week'] as $followup): ?>
                        <tr>
                            <td>
                                <a href="/panel/modules/candidates/view.php?code=<?= htmlspecialchars($followup['candidate_code']) ?>" 
                                   class="text-decoration-none">
                                    <strong><?= htmlspecialchars($followup['candidate_name']) ?></strong>
                                </a>
                            </td>
                            <td><?= getCandidateStatusBadge($followup['status']) ?></td>
                            <td><?= getLeadTypeBadge($followup['lead_type']) ?></td>
                            <td><?= date('M d, Y', strtotime($followup['follow_up_date'])) ?></td>
                            <td>
                                <span class="badge bg-info">
                                    <?= $followup['days_until_followup'] ?> days
                                </span>
                            </td>
                            <?php if ($isAdmin): ?>
                            <td><?= htmlspecialchars($followup['assigned_to_name'] ?? 'Unassigned') ?></td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

    <!-- Quick Links -->
    <div class="card">
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
            <a href="/panel/modules/reports/recruiter_performance.php" class="list-group-item list-group-item-action">
                <i class="bx bx-user-check me-2"></i> Recruiter Performance
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
