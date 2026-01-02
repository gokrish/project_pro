<?php
/**
 * Candidate Pipeline Report
 * Visualizes candidate flow through recruitment stages
 * 
 * @version 5.0 - Production Ready
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken};

// Check permission
Permission::require('reports', 'view_dashboard');

$user = Auth::user();
$userLevel = $user['level'] ?? 'user';
$isAdmin = in_array($userLevel, ['admin', 'super_admin', 'manager']);

$db = Database::getInstance();
$conn = $db->getConnection();

// Get filter parameters
$assignedTo = filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING);
$leadType = filter_input(INPUT_GET, 'lead_type', FILTER_SANITIZE_STRING);

// ============================================================================
// GET PIPELINE DATA BY STATUS
// ============================================================================

$pipelineData = [];
$totalCandidates = 0;

try {
    // Build query with filters
    $sql = "
        SELECT 
            c.status,
            COUNT(*) as count,
            COUNT(CASE WHEN c.lead_type = 'Hot' THEN 1 END) as hot_leads,
            COUNT(CASE WHEN c.lead_type = 'Warm' THEN 1 END) as warm_leads,
            COUNT(CASE WHEN c.lead_type = 'Cold' THEN 1 END) as cold_leads,
            COUNT(CASE WHEN c.lead_type = 'Blacklist' THEN 1 END) as blacklist,
            AVG(DATEDIFF(CURDATE(), c.created_at)) as avg_days_in_status,
            COUNT(CASE WHEN c.assigned_to IS NULL THEN 1 END) as unassigned
        FROM candidates c
        WHERE c.deleted_at IS NULL
    ";
    
    // Add filters
    $params = [];
    $types = '';
    
    if (!$isAdmin) {
        $sql .= " AND c.created_by = ?";
        $params[] = Auth::userCode();
        $types .= 's';
    }
    
    if ($assignedTo) {
        $sql .= " AND c.assigned_to = ?";
        $params[] = $assignedTo;
        $types .= 's';
    }
    
    if ($leadType) {
        $sql .= " AND c.lead_type = ?";
        $params[] = $leadType;
        $types .= 's';
    }
    
    $sql .= " GROUP BY c.status";
    $sql .= " ORDER BY FIELD(c.status, 'new', 'screening', 'qualified', 'active', 'placed', 'rejected', 'archived')";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $pipelineData = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Calculate total
    foreach ($pipelineData as $stage) {
        $totalCandidates += $stage['count'];
    }
    
} catch (Exception $e) {
    \ProConsultancy\Core\Logger::getInstance()->error('Pipeline report failed', [
        'error' => $e->getMessage()
    ]);
}

// ============================================================================
// GET CONVERSION FUNNEL DATA
// ============================================================================

$funnelData = [];

try {
    $sql = "
        SELECT 
            c.status,
            COUNT(*) as count
        FROM candidates c
        WHERE c.deleted_at IS NULL
    ";
    
    $params = [];
    $types = '';
    
    if (!$isAdmin) {
        $sql .= " AND c.created_by = ?";
        $params[] = Auth::userCode();
        $types .= 's';
    }
    
    if ($assignedTo) {
        $sql .= " AND c.assigned_to = ?";
        $params[] = $assignedTo;
        $types .= 's';
    }
    
    $sql .= " GROUP BY c.status";
    
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $statusCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Build funnel
    $counts = [];
    foreach ($statusCounts as $row) {
        $counts[$row['status']] = $row['count'];
    }
    
    $funnelData = [
        [
            'stage' => 'Total Candidates',
            'count' => array_sum($counts),
            'conversion' => 100
        ],
        [
            'stage' => 'Screening',
            'count' => ($counts['screening'] ?? 0) + ($counts['qualified'] ?? 0) + ($counts['active'] ?? 0) + ($counts['placed'] ?? 0),
            'conversion' => array_sum($counts) > 0 ? round((($counts['screening'] ?? 0) + ($counts['qualified'] ?? 0) + ($counts['active'] ?? 0) + ($counts['placed'] ?? 0)) / array_sum($counts) * 100, 1) : 0
        ],
        [
            'stage' => 'Qualified',
            'count' => ($counts['qualified'] ?? 0) + ($counts['active'] ?? 0) + ($counts['placed'] ?? 0),
            'conversion' => array_sum($counts) > 0 ? round((($counts['qualified'] ?? 0) + ($counts['active'] ?? 0) + ($counts['placed'] ?? 0)) / array_sum($counts) * 100, 1) : 0
        ],
        [
            'stage' => 'Active',
            'count' => ($counts['active'] ?? 0) + ($counts['placed'] ?? 0),
            'conversion' => array_sum($counts) > 0 ? round((($counts['active'] ?? 0) + ($counts['placed'] ?? 0)) / array_sum($counts) * 100, 1) : 0
        ],
        [
            'stage' => 'Placed',
            'count' => $counts['placed'] ?? 0,
            'conversion' => array_sum($counts) > 0 ? round(($counts['placed'] ?? 0) / array_sum($counts) * 100, 1) : 0
        ]
    ];
    
} catch (Exception $e) {
    \ProConsultancy\Core\Logger::getInstance()->error('Funnel data failed', [
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
$pageTitle = 'Pipeline Report';
$breadcrumbs = [
    ['title' => 'Reports', 'url' => '/panel/modules/reports/'],
    ['title' => 'Pipeline', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-trending-up me-2"></i>
                Candidate Pipeline Report
            </h4>
            <p class="text-muted mb-0">Visualize candidate flow through stages</p>
        </div>
        <div class="btn-group">
            <button class="btn btn-outline-secondary" onclick="window.print()">
                <i class="bx bx-printer"></i>
            </button>
            <a href="/panel/modules/reports/handlers/export.php?report=pipeline<?= $assignedTo ? '&assigned_to=' . urlencode($assignedTo) : '' ?><?= $leadType ? '&lead_type=' . urlencode($leadType) : '' ?>" 
               class="btn btn-outline-success">
                <i class="bx bx-download"></i> Export
            </a>
        </div>
    </div>

    <!-- Filters -->
    <?php if ($isAdmin && !empty($recruiters)): ?>
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
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
                
                <div class="col-md-4">
                    <label class="form-label">Filter by Lead Type</label>
                    <select name="lead_type" class="form-select" onchange="this.form.submit()">
                        <option value="">All Lead Types</option>
                        <option value="Hot" <?= $leadType === 'Hot' ? 'selected' : '' ?>>Hot</option>
                        <option value="Warm" <?= $leadType === 'Warm' ? 'selected' : '' ?>>Warm</option>
                        <option value="Cold" <?= $leadType === 'Cold' ? 'selected' : '' ?>>Cold</option>
                        <option value="Blacklist" <?= $leadType === 'Blacklist' ? 'selected' : '' ?>>Blacklist</option>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <?php if ($assignedTo || $leadType): ?>
                        <a href="/panel/modules/reports/pipeline.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i> Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2 class="mb-0"><?= number_format($totalCandidates) ?></h2>
                    <p class="mb-0">Total Candidates</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <?php
                    $activeCount = 0;
                    foreach ($pipelineData as $stage) {
                        if (in_array($stage['status'], ['new', 'screening', 'qualified', 'active'])) {
                            $activeCount += $stage['count'];
                        }
                    }
                    ?>
                    <h2 class="mb-0"><?= number_format($activeCount) ?></h2>
                    <p class="mb-0">Active Pipeline</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <?php
                    $placedCount = 0;
                    foreach ($pipelineData as $stage) {
                        if ($stage['status'] === 'placed') {
                            $placedCount = $stage['count'];
                            break;
                        }
                    }
                    $placementRate = $totalCandidates > 0 ? round(($placedCount / $totalCandidates) * 100, 1) : 0;
                    ?>
                    <h2 class="mb-0"><?= $placementRate ?>%</h2>
                    <p class="mb-0">Placement Rate</p>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <?php
                    $unassignedCount = 0;
                    foreach ($pipelineData as $stage) {
                        $unassignedCount += $stage['unassigned'];
                    }
                    ?>
                    <h2 class="mb-0"><?= number_format($unassignedCount) ?></h2>
                    <p class="mb-0">Unassigned</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Pipeline Breakdown Table -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-list-ul"></i> Pipeline Breakdown by Status
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pipelineData)): ?>
                        <p class="text-muted text-center py-4">No candidates in pipeline</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Status</th>
                                        <th class="text-center">Count</th>
                                        <th class="text-center">Hot</th>
                                        <th class="text-center">Warm</th>
                                        <th class="text-center">Cold</th>
                                        <th class="text-center">Avg Days</th>
                                        <th class="text-center">Unassigned</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pipelineData as $stage): ?>
                                    <tr>
                                        <td>
                                            <strong><?= ucfirst($stage['status']) ?></strong>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-primary"><?= number_format($stage['count']) ?></span>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stage['hot_leads'] > 0): ?>
                                                <span class="badge bg-danger"><?= $stage['hot_leads'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stage['warm_leads'] > 0): ?>
                                                <span class="badge bg-warning"><?= $stage['warm_leads'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stage['cold_leads'] > 0): ?>
                                                <span class="badge bg-info"><?= $stage['cold_leads'] ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= round($stage['avg_days_in_status']) ?> days
                                        </td>
                                        <td class="text-center">
                                            <?php if ($stage['unassigned'] > 0): ?>
                                                <span class="badge bg-secondary"><?= $stage['unassigned'] ?></span>
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
                                        <th class="text-center"><?= number_format($totalCandidates) ?></th>
                                        <th class="text-center">
                                            <?= array_sum(array_column($pipelineData, 'hot_leads')) ?>
                                        </th>
                                        <th class="text-center">
                                            <?= array_sum(array_column($pipelineData, 'warm_leads')) ?>
                                        </th>
                                        <th class="text-center">
                                            <?= array_sum(array_column($pipelineData, 'cold_leads')) ?>
                                        </th>
                                        <th class="text-center">-</th>
                                        <th class="text-center"><?= $unassignedCount ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Conversion Funnel -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="bx bx-filter"></i> Conversion Funnel
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($funnelData)): ?>
                        <?php foreach ($funnelData as $index => $funnel): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <small class="text-muted"><?= $funnel['stage'] ?></small>
                                    <small>
                                        <strong><?= number_format($funnel['count']) ?></strong>
                                        <span class="text-muted">(<?= $funnel['conversion'] ?>%)</span>
                                    </small>
                                </div>
                                <div class="progress" style="height: 25px;">
                                    <div class="progress-bar <?= $index === 0 ? 'bg-primary' : ($index === count($funnelData) - 1 ? 'bg-success' : 'bg-info') ?>" 
                                         role="progressbar" 
                                         style="width: <?= $funnel['conversion'] ?>%"
                                         aria-valuenow="<?= $funnel['conversion'] ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= $funnel['conversion'] ?>%
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Other Reports</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/panel/modules/reports/daily.php" class="list-group-item list-group-item-action">
                        <i class="bx bx-calendar me-2"></i> Daily Report
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
