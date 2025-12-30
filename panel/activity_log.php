<?php
/**
 * Activity Log Viewer
 * Comprehensive audit trail with filtering, search, export
 * 
 * @version 5.0
 */

require_once __DIR__ . '/_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Pagination;

// Check permission (admin only)
Permission::require('activity_log', 'view');

$db = Database::getInstance();
$conn = $db->getConnection();

// ====================================================================
// FILTERS
// ====================================================================
$filters = [
    'user_code' => input('user_code', ''),
    'module' => input('module', ''),
    'action' => input('action', ''),
    'level' => input('level', ''),
    'search' => input('search', ''),
    'date_from' => input('date_from', ''),
    'date_to' => input('date_to', ''),
    'record_id' => input('record_id', '')
];

// ====================================================================
// BUILD WHERE CLAUSE
// ====================================================================
$whereConditions = ['1=1'];
$params = [];
$types = '';

// User filter
if (!empty($filters['user_code'])) {
    $whereConditions[] = "al.user_code = ?";
    $params[] = $filters['user_code'];
    $types .= 's';
}

// Module filter
if (!empty($filters['module'])) {
    $whereConditions[] = "al.module = ?";
    $params[] = $filters['module'];
    $types .= 's';
}

// Action filter
if (!empty($filters['action'])) {
    $whereConditions[] = "al.action = ?";
    $params[] = $filters['action'];
    $types .= 's';
}

// Level filter
if (!empty($filters['level'])) {
    $whereConditions[] = "al.level = ?";
    $params[] = $filters['level'];
    $types .= 's';
}

// Search in description
if (!empty($filters['search'])) {
    $whereConditions[] = "(al.description LIKE ? OR al.record_id LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

// Date range filter
if (!empty($filters['date_from'])) {
    $whereConditions[] = "DATE(al.created_at) >= ?";
    $params[] = $filters['date_from'];
    $types .= 's';
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "DATE(al.created_at) <= ?";
    $params[] = $filters['date_to'];
    $types .= 's';
}

// Record ID filter (view all logs for specific record)
if (!empty($filters['record_id'])) {
    $whereConditions[] = "al.record_id = ?";
    $params[] = $filters['record_id'];
    $types .= 's';
}

$whereClause = implode(' AND ', $whereConditions);

// ====================================================================
// COUNT TOTAL RECORDS
// ====================================================================
$countQuery = "SELECT COUNT(*) as total FROM activity_log al WHERE {$whereClause}";
$countStmt = $conn->prepare($countQuery);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// ====================================================================
// PAGINATION
// ====================================================================
$page = (int)input('page', 1);
$perPage = (int)input('per_page', 50);
$pagination = new Pagination($totalRecords, $perPage, $page);

// ====================================================================
// GET ACTIVITY LOGS
// ====================================================================
$query = "
    SELECT 
        al.*,
        u.name as user_display_name,
        u.email as user_email
    FROM activity_log al
    LEFT JOIN users u ON al.user_code = u.user_code
    WHERE {$whereClause}
    ORDER BY al.created_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ====================================================================
// GET FILTER OPTIONS (Cached for Performance)
// ====================================================================

// Get users (only active ones who have activity)
$users = $conn->query("
    SELECT DISTINCT al.user_code, al.user_name
    FROM activity_log al
    WHERE al.user_code IS NOT NULL
    ORDER BY al.user_name
    LIMIT 100
")->fetch_all(MYSQLI_ASSOC);

// Get modules
$modules = $conn->query("
    SELECT DISTINCT module 
    FROM activity_log 
    ORDER BY module
")->fetch_all(MYSQLI_ASSOC);

// Get actions (most common)
$actions = $conn->query("
    SELECT action, COUNT(*) as count
    FROM activity_log 
    GROUP BY action 
    ORDER BY count DESC
    LIMIT 50
")->fetch_all(MYSQLI_ASSOC);

// ====================================================================
// EXPORT FUNCTIONALITY
// ====================================================================
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    exportToCSV($logs, $filters);
    exit;
}

// Page configuration
$pageTitle = 'Activity Log';
$breadcrumbs = [
    ['title' => 'Activity Log', 'url' => '']
];

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-history me-2"></i>Activity Log
            </h4>
            <p class="text-muted mb-0">
                <?= number_format($totalRecords) ?> total records
                <?php if (count(array_filter($filters))): ?>
                    (<?= number_format(count($logs)) ?> filtered)
                <?php endif; ?>
            </p>
        </div>
        <div>
            <button class="btn btn-outline-primary" type="button" 
                    data-bs-toggle="collapse" data-bs-target="#filtersCard">
                <i class="bx bx-filter me-1"></i> Filters
                <?php if (count(array_filter($filters)) > 0): ?>
                    <span class="badge bg-primary ms-1">
                        <?= count(array_filter($filters)) ?>
                    </span>
                <?php endif; ?>
            </button>
            
            <?php if (!empty($logs)): ?>
            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" 
               class="btn btn-success ms-2">
                <i class="bx bx-download me-1"></i> Export CSV
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters Card -->
    <div class="collapse mb-4 <?= count(array_filter($filters)) > 0 ? 'show' : '' ?>" 
         id="filtersCard">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <!-- Search -->
                        <div class="col-md-4">
                            <label class="form-label">Search</label>
                            <input type="text" class="form-control" name="search" 
                                   value="<?= escape($filters['search']) ?>"
                                   placeholder="Search description or record ID...">
                        </div>
                        
                        <!-- User -->
                        <div class="col-md-4">
                            <label class="form-label">User</label>
                            <select class="form-select" name="user_code">
                                <option value="">All Users</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?= escape($u['user_code']) ?>" 
                                        <?= $filters['user_code'] === $u['user_code'] ? 'selected' : '' ?>>
                                    <?= escape($u['user_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Module -->
                        <div class="col-md-4">
                            <label class="form-label">Module</label>
                            <select class="form-select" name="module">
                                <option value="">All Modules</option>
                                <?php foreach ($modules as $m): ?>
                                <option value="<?= escape($m['module']) ?>" 
                                        <?= $filters['module'] === $m['module'] ? 'selected' : '' ?>>
                                    <?= escape(ucfirst(str_replace('_', ' ', $m['module']))) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Action -->
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <select class="form-select" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $a): ?>
                                <option value="<?= escape($a['action']) ?>" 
                                        <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>>
                                    <?= escape(ucwords(str_replace('_', ' ', $a['action']))) ?>
                                    <small>(<?= number_format($a['count']) ?>)</small>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Level -->
                        <div class="col-md-3">
                            <label class="form-label">Severity</label>
                            <select class="form-select" name="level">
                                <option value="">All Levels</option>
                                <option value="info" <?= $filters['level'] === 'info' ? 'selected' : '' ?>>
                                    Info
                                </option>
                                <option value="warning" <?= $filters['level'] === 'warning' ? 'selected' : '' ?>>
                                    Warning
                                </option>
                                <option value="error" <?= $filters['level'] === 'error' ? 'selected' : '' ?>>
                                    Error
                                </option>
                                <option value="critical" <?= $filters['level'] === 'critical' ? 'selected' : '' ?>>
                                    Critical
                                </option>
                            </select>
                        </div>
                        
                        <!-- Date From -->
                        <div class="col-md-3">
                            <label class="form-label">Date From</label>
                            <input type="date" class="form-control" name="date_from" 
                                   value="<?= escape($filters['date_from']) ?>">
                        </div>
                        
                        <!-- Date To -->
                        <div class="col-md-3">
                            <label class="form-label">Date To</label>
                            <input type="date" class="form-control" name="date_to" 
                                   value="<?= escape($filters['date_to']) ?>">
                        </div>
                        
                        <!-- Per Page -->
                        <div class="col-md-12">
                            <label class="form-label">Results per page</label>
                            <select class="form-select" name="per_page" style="width: 150px;">
                                <option value="25" <?= $perPage === 25 ? 'selected' : '' ?>>25</option>
                                <option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option>
                                <option value="100" <?= $perPage === 100 ? 'selected' : '' ?>>100</option>
                                <option value="200" <?= $perPage === 200 ? 'selected' : '' ?>>200</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-search me-1"></i> Apply Filters
                        </button>
                        <a href="activity_log.php" class="btn btn-outline-secondary">
                            <i class="bx bx-x me-1"></i> Clear All
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Activity Log Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="140">Timestamp</th>
                        <th width="150">User</th>
                        <th width="120">Module</th>
                        <th width="120">Action</th>
                        <th>Description</th>
                        <th width="100">Level</th>
                        <th width="120">IP Address</th>
                        <th width="60"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="bx bx-history bx-lg text-muted mb-3"></i>
                                <p class="text-muted mb-0">No activity logs found</p>
                                <?php if (count(array_filter($filters)) > 0): ?>
                                    <a href="activity_log.php" class="btn btn-sm btn-outline-primary mt-2">
                                        Clear Filters
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <!-- Timestamp -->
                            <td>
                                <small class="text-nowrap">
                                    <?= date('M d, Y', strtotime($log['created_at'])) ?><br>
                                    <span class="text-muted">
                                        <?= date('H:i:s', strtotime($log['created_at'])) ?>
                                    </span>
                                </small>
                            </td>
                            
                            <!-- User -->
                            <td>
                                <?php if (!empty($log['user_display_name'])): ?>
                                    <a href="?user_code=<?= urlencode($log['user_code']) ?>">
                                        <?= escape($log['user_display_name']) ?>
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <?= escape($log['user_code']) ?>
                                    </small>
                                <?php else: ?>
                                    <span class="text-muted">System</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Module -->
                            <td>
                                <a href="?module=<?= urlencode($log['module']) ?>">
                                    <span class="badge bg-label-primary">
                                        <?= escape(ucfirst(str_replace('_', ' ', $log['module']))) ?>
                                    </span>
                                </a>
                            </td>
                            
                            <!-- Action -->
                            <td>
                                <a href="?module=<?= urlencode($log['module']) ?>&action=<?= urlencode($log['action']) ?>">
                                    <span class="badge bg-label-info">
                                        <?= escape(ucwords(str_replace('_', ' ', $log['action']))) ?>
                                    </span>
                                </a>
                            </td>
                            
                            <!-- Description -->
                            <td>
                                <?= escape($log['description']) ?>
                                
                                <?php if (!empty($log['record_id'])): ?>
                                    <br>
                                    <small class="text-muted">
                                        Record: 
                                        <a href="?record_id=<?= urlencode($log['record_id']) ?>">
                                            <?= escape($log['record_id']) ?>
                                        </a>
                                    </small>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Level -->
                            <td>
                                <?php
                                $levelColors = [
                                    'info' => 'info',
                                    'warning' => 'warning',
                                    'error' => 'danger',
                                    'critical' => 'danger'
                                ];
                                $levelColor = $levelColors[$log['level'] ?? 'info'] ?? 'secondary';
                                ?>
                                <span class="badge bg-label-<?= $levelColor ?>">
                                    <?= escape(ucfirst($log['level'] ?? 'info')) ?>
                                </span>
                            </td>
                            
                            <!-- IP Address -->
                            <td>
                                <small class="text-muted">
                                    <?= escape($log['ip_address'] ?? '—') ?>
                                </small>
                            </td>
                            
                            <!-- Actions -->
                            <td>
                                <?php if (!empty($log['details'])): ?>
                                    <button type="button" 
                                            class="btn btn-sm btn-icon btn-outline-primary"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#detailsModal<?= $log['id'] ?>">
                                        <i class="bx bx-info-circle"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalRecords > $perPage): ?>
            <div class="card-footer">
                <?= $pagination->render() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Details Modals -->
<?php foreach ($logs as $log): ?>
    <?php if (!empty($log['details'])): ?>
    <div class="modal fade" id="detailsModal<?= $log['id'] ?>" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Activity Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Timestamp</dt>
                        <dd class="col-sm-9"><?= date('F j, Y g:i:s A', strtotime($log['created_at'])) ?></dd>
                        
                        <dt class="col-sm-3">User</dt>
                        <dd class="col-sm-9"><?= escape($log['user_display_name'] ?? 'System') ?></dd>
                        
                        <dt class="col-sm-3">Module</dt>
                        <dd class="col-sm-9"><?= escape($log['module']) ?></dd>
                        
                        <dt class="col-sm-3">Action</dt>
                        <dd class="col-sm-9"><?= escape($log['action']) ?></dd>
                        
                        <dt class="col-sm-3">Description</dt>
                        <dd class="col-sm-9"><?= escape($log['description']) ?></dd>
                        
                        <?php if (!empty($log['record_id'])): ?>
                        <dt class="col-sm-3">Record ID</dt>
                        <dd class="col-sm-9"><?= escape($log['record_id']) ?></dd>
                        <?php endif; ?>
                        
                        <dt class="col-sm-3">IP Address</dt>
                        <dd class="col-sm-9"><?= escape($log['ip_address'] ?? 'N/A') ?></dd>
                        
                        <?php if (!empty($log['user_agent'])): ?>
                        <dt class="col-sm-3">User Agent</dt>
                        <dd class="col-sm-9"><small><?= escape($log['user_agent']) ?></small></dd>
                        <?php endif; ?>
                        
                        <dt class="col-sm-3">Details</dt>
                        <dd class="col-sm-9">
                            <pre class="bg-light p-3 rounded"><code><?= htmlspecialchars(json_encode(json_decode($log['details']), JSON_PRETTY_PRINT)) ?></code></pre>
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endforeach; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Export logs to CSV
 */
function exportToCSV($logs, $filters) {
    $filename = 'activity_log_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Timestamp',
        'User',
        'User Code',
        'Module',
        'Action',
        'Description',
        'Record ID',
        'Level',
        'IP Address'
    ]);
    
    // CSV Data
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['created_at'],
            $log['user_name'] ?? 'System',
            $log['user_code'] ?? '',
            $log['module'],
            $log['action'],
            $log['description'],
            $log['record_id'] ?? '',
            $log['level'] ?? 'info',
            $log['ip_address'] ?? ''
        ]);
    }
    
    fclose($output);
}