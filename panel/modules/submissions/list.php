<?php
/**
 * Submissions List
 * Main view with filters, statistics, and actions
 */
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Page config
$pageTitle = 'Submissions';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Submissions', 'url' => '']
];

// Check permission
$canViewAll = Permission::can('submissions', 'view_all');
$canViewOwn = Permission::can('submissions', 'view_own');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Filters
$filters = [
    'internal_status' => input('internal_status', ''),
    'client_status' => input('client_status', ''),
    'candidate' => input('candidate', ''),
    'job' => input('job', ''),
    'client' => input('client', ''),
    'date_from' => input('date_from', ''),
    'date_to' => input('date_to', ''),
    'submitted_by' => input('submitted_by', '')
];

// Build WHERE clause
$where = ['s.deleted_at IS NULL'];
$params = [];
$types = '';

if ($filters['internal_status']) {
    $where[] = "s.internal_status = ?";
    $params[] = $filters['internal_status'];
    $types .= 's';
}

if ($filters['client_status']) {
    $where[] = "s.client_status = ?";
    $params[] = $filters['client_status'];
    $types .= 's';
}

if ($filters['candidate']) {
    $where[] = "(c.candidate_name LIKE ? OR c.email LIKE ? OR c.candidate_code LIKE ?)";
    $searchTerm = "%{$filters['candidate']}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if ($filters['job']) {
    $where[] = "(j.job_title LIKE ? OR j.job_code LIKE ?)";
    $searchTerm = "%{$filters['job']}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

if ($filters['client']) {
    $where[] = "cl.company_name LIKE ?";
    $params[] = "%{$filters['client']}%";
    $types .= 's';
}

if ($filters['date_from']) {
    $where[] = "DATE(s.created_at) >= ?";
    $params[] = $filters['date_from'];
    $types .= 's';
}

if ($filters['date_to']) {
    $where[] = "DATE(s.created_at) <= ?";
    $params[] = $filters['date_to'];
    $types .= 's';
}

if ($filters['submitted_by']) {
    $where[] = "s.submitted_by = ?";
    $params[] = $filters['submitted_by'];
    $types .= 's';
}

// Permission-based filtering
if (!$canViewAll && $canViewOwn) {
    $where[] = "s.submitted_by = ?";
    $params[] = $user['user_code'];
    $types .= 's';
}

$whereSQL = implode(' AND ', $where);

// Pagination
$page = max(1, (int)input('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countSQL = "
    SELECT COUNT(*) as total
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    JOIN jobs j ON s.job_code = j.job_code
    JOIN clients cl ON j.client_code = cl.client_code
    LEFT JOIN users u ON s.submitted_by = u.user_code
    WHERE $whereSQL
";

$stmt = $conn->prepare($countSQL);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get submissions
$sql = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        c.current_position,
        j.job_title,
        j.job_code,
        j.client_code,
        cl.company_name,
        u.name as submitted_by_name,
        DATEDIFF(NOW(), s.created_at) as days_pending
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    JOIN jobs j ON s.job_code = j.job_code
    JOIN clients cl ON j.client_code = cl.client_code
    LEFT JOIN users u ON s.submitted_by = u.user_code
    WHERE $whereSQL
    ORDER BY s.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$limitParams = array_merge($params, [$perPage, $offset]);
$limitTypes = $types . 'ii';
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$statsWhere = $canViewAll ? '1=1' : "s.submitted_by = '{$user['user_code']}'";
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.internal_status = 'pending' THEN 1 ELSE 0 END) as pending_approval,
        SUM(CASE WHEN s.internal_status = 'approved' AND s.client_status = 'not_sent' THEN 1 ELSE 0 END) as ready_to_send,
        SUM(CASE WHEN s.client_status = 'submitted' THEN 1 ELSE 0 END) as with_client,
        SUM(CASE WHEN s.client_status = 'interviewing' THEN 1 ELSE 0 END) as interviewing,
        SUM(CASE WHEN s.client_status = 'offered' THEN 1 ELSE 0 END) as offered,
        SUM(CASE WHEN s.client_status = 'placed' THEN 1 ELSE 0 END) as placed,
        SUM(CASE WHEN s.client_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM submissions s
    WHERE $statsWhere AND s.deleted_at IS NULL
";
$stats = $conn->query($statsSQL)->fetch_assoc();

// Get users for filter
$usersSQL = "SELECT user_code, name FROM users WHERE is_active = 1 ORDER BY name";
$users = $conn->query($usersSQL)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.stat-card {
    border-left: 4px solid;
    transition: transform 0.2s, box-shadow 0.2s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.submission-row:hover {
    background-color: #f8f9fa;
}
.status-badge {
    font-size: 0.75rem;
    padding: 0.35em 0.65em;
    font-weight: 600;
}
.filter-section {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 1rem;
}
</style>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-warning">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Pending Approval</h6>
                        <h2 class="mb-0 text-warning"><?= number_format($stats['pending_approval']) ?></h2>
                        <small class="text-muted">Needs manager review</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-time-five display-4 text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-success">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Ready to Send</h6>
                        <h2 class="mb-0 text-success"><?= number_format($stats['ready_to_send']) ?></h2>
                        <small class="text-muted">Approved by manager</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-check-circle display-4 text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-info">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">With Client</h6>
                        <h2 class="mb-0 text-info"><?= number_format($stats['with_client'] + $stats['interviewing']) ?></h2>
                        <small class="text-muted">Submitted or interviewing</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-send display-4 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Placements</h6>
                        <h2 class="mb-0 text-primary"><?= number_format($stats['placed']) ?></h2>
                        <small class="text-muted">Successfully placed</small>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-trophy display-4 text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bx bx-filter"></i> Filters
            <?php if (array_filter($filters)): ?>
                <a href="list.php" class="btn btn-sm btn-outline-secondary float-end">
                    <i class="bx bx-x"></i> Clear
                </a>
            <?php endif; ?>
        </h5>
    </div>
    <div class="card-body filter-section">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label small">Internal Status</label>
                <select name="internal_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="pending" <?= $filters['internal_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="approved" <?= $filters['internal_status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="rejected" <?= $filters['internal_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    <option value="withdrawn" <?= $filters['internal_status'] === 'withdrawn' ? 'selected' : '' ?>>Withdrawn</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Client Status</label>
                <select name="client_status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="not_sent" <?= $filters['client_status'] === 'not_sent' ? 'selected' : '' ?>>Not Sent</option>
                    <option value="submitted" <?= $filters['client_status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="interviewing" <?= $filters['client_status'] === 'interviewing' ? 'selected' : '' ?>>Interviewing</option>
                    <option value="offered" <?= $filters['client_status'] === 'offered' ? 'selected' : '' ?>>Offered</option>
                    <option value="placed" <?= $filters['client_status'] === 'placed' ? 'selected' : '' ?>>Placed</option>
                    <option value="rejected" <?= $filters['client_status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Candidate</label>
                <input type="text" name="candidate" class="form-control form-control-sm" 
                       value="<?= escape($filters['candidate']) ?>" placeholder="Name or email...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Job</label>
                <input type="text" name="job" class="form-control form-control-sm" 
                       value="<?= escape($filters['job']) ?>" placeholder="Job title...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Client</label>
                <input type="text" name="client" class="form-control form-control-sm" 
                       value="<?= escape($filters['client']) ?>" placeholder="Company...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Date From</label>
                <input type="date" name="date_from" class="form-control form-control-sm" 
                       value="<?= escape($filters['date_from']) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Date To</label>
                <input type="date" name="date_to" class="form-control form-control-sm" 
                       value="<?= escape($filters['date_to']) ?>">
            </div>
            
            <?php if ($canViewAll): ?>
            <div class="col-md-2">
                <label class="form-label small">Submitted By</label>
                <select name="submitted_by" class="form-select form-select-sm">
                    <option value="">All Recruiters</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['user_code'] ?>" <?= $filters['submitted_by'] === $u['user_code'] ? 'selected' : '' ?>>
                            <?= escape($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <label class="form-label small">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bx bx-search"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Submissions Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            Submissions 
            <span class="badge bg-secondary"><?= number_format($totalCount) ?> total</span>
        </h5>
        <?php if (Permission::can('submissions', 'approve')): ?>
            <a href="approval-dashboard.php" class="btn btn-sm btn-warning">
                <i class="bx bx-check-circle"></i> 
                Pending Approvals 
                <span class="badge bg-white text-warning"><?= $stats['pending_approval'] ?></span>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($submissions)): ?>
            <div class="text-center py-5">
                <i class="bx bx-folder-open display-1 text-muted"></i>
                <p class="text-muted mt-3">No submissions found</p>
                <?php if (array_filter($filters)): ?>
                    <a href="list.php" class="btn btn-sm btn-outline-primary">Clear Filters</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Client</th>
                            <th class="text-center">Internal</th>
                            <th class="text-center">Client Status</th>
                            <th>Timeline</th>
                            <th>Recruiter</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr class="submission-row">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-2">
                                            <?= strtoupper(substr($sub['candidate_name'], 0, 1)) ?>
                                        </div>
                                        <div>
                                            <strong><?= escape($sub['candidate_name']) ?></strong><br>
                                            <small class="text-muted"><?= escape($sub['current_position'] ?: 'N/A') ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?= escape($sub['job_title']) ?></strong><br>
                                    <small class="text-muted"><?= escape($sub['job_code']) ?></small>
                                </td>
                                <td>
                                    <?= escape($sub['company_name']) ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $internalBadgeClass = [
                                        'pending' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger',
                                        'withdrawn' => 'secondary'
                                    ];
                                    $class = $internalBadgeClass[$sub['internal_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $class ?> status-badge">
                                        <?= ucfirst($sub['internal_status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $clientBadgeClass = [
                                        'not_sent' => 'secondary',
                                        'submitted' => 'info',
                                        'interviewing' => 'primary',
                                        'offered' => 'warning',
                                        'placed' => 'success',
                                        'rejected' => 'danger',
                                        'withdrawn' => 'secondary'
                                    ];
                                    $class = $clientBadgeClass[$sub['client_status']] ?? 'secondary';
                                    $displayStatus = str_replace('_', ' ', $sub['client_status']);
                                    ?>
                                    <span class="badge bg-<?= $class ?> status-badge">
                                        <?= ucfirst($displayStatus) ?>
                                    </span>
                                </td>
                                <td>
                                    <small>
                                        <strong>Created:</strong> <?= date('M d, Y', strtotime($sub['created_at'])) ?><br>
                                        <span class="text-muted"><?= $sub['days_pending'] ?> days ago</span>
                                    </small>
                                </td>
                                <td>
                                    <small><?= escape($sub['submitted_by_name']) ?></small>
                                </td>
                                <td class="text-center">
                                    <a href="view.php?code=<?= escape($sub['submission_code']) ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav aria-label="Page navigation">
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <p class="text-center text-muted small mt-2 mb-0">
                        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalCount)) ?> of <?= number_format($totalCount) ?> submissions
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>