<?php
/**
 * Jobs List Page
 * Shows all jobs with filters, search, statistics, and bulk actions
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Pagination;

// Check permission
Permission::require('jobs', 'view');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Page configuration
$pageTitle = 'Jobs';
$breadcrumbs = [
    ['title' => 'Jobs', 'url' => '']
];

// Get filters
$filters = [
    'search' => input('search', ''),
    'status' => input('status', ''),
    'client_code' => input('client_code', ''),
    'assigned_to' => input('assigned_to', ''),
    'approval_status' => input('approval_status', ''),
    'tab' => input('tab', 'active') // active, draft, pending_approval, closed, all
];

// Build WHERE clause
$where = ['j.deleted_at IS NULL'];
$params = [];
$types = '';

// Tab-based filtering
switch ($filters['tab']) {
    case 'active':
        $where[] = "j.status = 'open' AND j.is_published = 1";
        break;
    case 'draft':
        $where[] = "j.status = 'draft'";
        break;
    case 'pending_approval':
        $where[] = "j.approval_status = 'pending_approval'";
        break;
    case 'closed':
        $where[] = "j.status = 'closed'";
        break;
    // 'all' shows everything
}

// Search filter
if (!empty($filters['search'])) {
    $where[] = "(j.job_title LIKE ? OR j.job_code LIKE ? OR j.job_refno LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// Additional filters
if (!empty($filters['status'])) {
    $where[] = "j.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['client_code'])) {
    $where[] = "j.client_code = ?";
    $params[] = $filters['client_code'];
    $types .= 's';
}

if (!empty($filters['assigned_to'])) {
    if ($filters['assigned_to'] === 'me') {
        $where[] = "j.assigned_to = ?";
        $params[] = Auth::userCode();
        $types .= 's';
    } elseif ($filters['assigned_to'] === 'unassigned') {
        $where[] = "j.assigned_to IS NULL";
    } else {
        $where[] = "j.assigned_to = ?";
        $params[] = $filters['assigned_to'];
        $types .= 's';
    }
}

if (!empty($filters['approval_status'])) {
    $where[] = "j.approval_status = ?";
    $params[] = $filters['approval_status'];
    $types .= 's';
}

$whereClause = implode(' AND ', $where);

// Get statistics
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'open' AND is_published = 1 THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN approval_status = 'pending_approval' THEN 1 ELSE 0 END) as pending_approval,
        SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) as closed
    FROM jobs j
    WHERE j.deleted_at IS NULL
";
$statsResult = $conn->query($statsSQL);
$stats = $statsResult->fetch_assoc();

// Count filtered records
$countSQL = "SELECT COUNT(*) as total FROM jobs j WHERE {$whereClause}";
$countStmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Pagination
$page = (int)input('page', 1);
$perPage = 25;
$pagination = new Pagination($totalRecords, $perPage, $page);

// Get jobs
$sql = "
    SELECT 
        j.*,
        c.company_name,
        u1.name as created_by_name,
        u2.name as assigned_to_name,
        u3.name as approved_by_name,
        (SELECT COUNT(*) FROM applications a WHERE a.job_code = j.job_code) as applications_count
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u1 ON j.created_by = u1.user_code
    LEFT JOIN users u2 ON j.assigned_to = u2.user_code
    LEFT JOIN users u3 ON j.approved_by = u3.user_code
    WHERE {$whereClause}
    ORDER BY 
        CASE j.approval_status
            WHEN 'pending_approval' THEN 1
            ELSE 2
        END,
        j.created_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get clients for filter
$clientsSQL = "SELECT client_code, company_name FROM clients WHERE is_active = 1 ORDER BY company_name";
$clients = $conn->query($clientsSQL)->fetch_all(MYSQLI_ASSOC);

// Get recruiters for filter
$recruitersSQL = "SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name";
$recruiters = $conn->query($recruitersSQL)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bx bx-briefcase text-primary me-2"></i>
                    Jobs
                </h4>
                <p class="text-muted mb-0">Manage job postings and recruitment</p>
            </div>
            <div>
                <?php if (Permission::can('jobs', 'create')): ?>
                <a href="create.php" class="btn btn-primary">
                    <i class="bx bx-plus"></i> Create New Job
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <small class="text-muted d-block">Active Jobs</small>
                        <h3 class="mb-0"><?= number_format($stats['active']) ?></h3>
                    </div>
                    <div class="avatar flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-check-circle"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <small class="text-muted d-block">Drafts</small>
                        <h3 class="mb-0"><?= number_format($stats['draft']) ?></h3>
                    </div>
                    <div class="avatar flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-secondary">
                            <i class="bx bx-file"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <small class="text-muted d-block">Pending Approval</small>
                        <h3 class="mb-0 text-warning"><?= number_format($stats['pending_approval']) ?></h3>
                    </div>
                    <div class="avatar flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="bx bx-time"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6 col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <small class="text-muted d-block">Closed</small>
                        <h3 class="mb-0"><?= number_format($stats['closed']) ?></h3>
                    </div>
                    <div class="avatar flex-shrink-0">
                        <span class="avatar-initial rounded bg-label-dark">
                            <i class="bx bx-x-circle"></i>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabs & Filters -->
<div class="card">
    <div class="card-header">
        <!-- Tabs -->
        <ul class="nav nav-tabs card-header-tabs" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'active' ? 'active' : '' ?>" 
                   href="?tab=active">
                    Active (<?= $stats['active'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'draft' ? 'active' : '' ?>" 
                   href="?tab=draft">
                    Drafts (<?= $stats['draft'] ?>)
                </a>
            </li>
            <?php if (Permission::can('jobs', 'approve')): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'pending_approval' ? 'active' : '' ?>" 
                   href="?tab=pending_approval">
                    Pending Approval (<?= $stats['pending_approval'] ?>)
                    <?php if ($stats['pending_approval'] > 0): ?>
                        <span class="badge rounded-pill badge-center h-px-20 w-px-20 bg-danger ms-1">
                            <?= $stats['pending_approval'] ?>
                        </span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'closed' ? 'active' : '' ?>" 
                   href="?tab=closed">
                    Closed (<?= $stats['closed'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'all' ? 'active' : '' ?>" 
                   href="?tab=all">
                    All Jobs (<?= $stats['total'] ?>)
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Filters -->
    <div class="card-body border-bottom">
        <form method="GET" class="row g-3">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filters['tab']) ?>">
            
            <div class="col-md-3">
                <input type="text" class="form-control" name="search" 
                       placeholder="Search jobs..." 
                       value="<?= htmlspecialchars($filters['search']) ?>">
            </div>
            
            <div class="col-md-2">
                <select name="client_code" class="form-select">
                    <option value="">All Clients</option>
                    <?php foreach ($clients as $client): ?>
                        <option value="<?= $client['client_code'] ?>" 
                                <?= $filters['client_code'] === $client['client_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($client['company_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <select name="assigned_to" class="form-select">
                    <option value="">All Recruiters</option>
                    <option value="me" <?= $filters['assigned_to'] === 'me' ? 'selected' : '' ?>>
                        My Jobs
                    </option>
                    <option value="unassigned" <?= $filters['assigned_to'] === 'unassigned' ? 'selected' : '' ?>>
                        Unassigned
                    </option>
                    <?php foreach ($recruiters as $recruiter): ?>
                        <option value="<?= $recruiter['user_code'] ?>" 
                                <?= $filters['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($recruiter['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <div class="btn-group w-100">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-filter-alt"></i> Filter
                    </button>
                    <a href="?tab=<?= $filters['tab'] ?>" class="btn btn-outline-secondary">
                        <i class="bx bx-x"></i> Clear
                    </a>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Jobs Table -->
    <div class="table-responsive">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th width="40">
                        <input type="checkbox" class="form-check-input" id="selectAll">
                    </th>
                    <th>Job Title</th>
                    <th>Client</th>
                    <th>Status</th>
                    <th>Assigned To</th>
                    <th>Applications</th>
                    <th>Created</th>
                    <th width="120">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bx bx-briefcase" style="font-size: 48px; color: #ddd;"></i>
                            <p class="text-muted mt-2">No jobs found</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input select-job" 
                                       value="<?= $job['job_code'] ?>">
                            </td>
                            <td>
                                <a href="view.php?code=<?= urlencode($job['job_code']) ?>" 
                                   class="fw-semibold text-dark">
                                    <?= htmlspecialchars($job['job_title']) ?>
                                </a>
                                <?php if ($job['job_refno']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($job['job_refno']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($job['company_name'] ?? 'N/A') ?></td>
                            <td>
                                <?php
                                $statusColors = [
                                    'draft' => 'secondary',
                                    'pending_approval' => 'warning',
                                    'approved' => 'info',
                                    'open' => 'success',
                                    'closed' => 'dark',
                                    'cancelled' => 'danger'
                                ];
                                $color = $statusColors[$job['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>">
                                    <?= ucwords(str_replace('_', ' ', $job['status'])) ?>
                                </span>
                                
                                <?php if ($job['approval_status'] === 'pending_approval'): ?>
                                    <br><small class="text-warning">
                                        <i class="bx bx-time"></i> Awaiting Approval
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= htmlspecialchars($job['assigned_to_name'] ?? 'Unassigned') ?>
                            </td>
                            <td>
                                <?= $job['applications_count'] ?>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?= date('M d, Y', strtotime($job['created_at'])) ?><br>
                                    by <?= htmlspecialchars($job['created_by_name']) ?>
                                </small>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" 
                                            data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="view.php?code=<?= urlencode($job['job_code']) ?>">
                                            <i class="bx bx-show me-2"></i> View
                                        </a>
                                        
                                        <?php if (Permission::can('jobs', 'edit')): ?>
                                            <a class="dropdown-item" href="edit.php?code=<?= urlencode($job['job_code']) ?>">
                                                <i class="bx bx-edit me-2"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($job['approval_status'] === 'pending_approval' && Permission::can('jobs', 'approve')): ?>
                                            <a class="dropdown-item text-warning" 
                                               href="approve.php?code=<?= urlencode($job['job_code']) ?>">
                                                <i class="bx bx-check-circle me-2"></i> Approve
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (Permission::can('jobs', 'delete')): ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-danger" href="#" 
                                               onclick="deleteJob('<?= htmlspecialchars($job['job_code']) ?>', '<?= htmlspecialchars($job['job_title']) ?>'); return false;">
                                                <i class="bx bx-trash me-2"></i> Delete
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
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
            <?php echo $pagination->render(); ?>
        </div>
    <?php endif; ?>
</div>

<script>
// Select all checkbox
document.getElementById('selectAll')?.addEventListener('change', function() {
    document.querySelectorAll('.select-job').forEach(cb => {
        cb.checked = this.checked;
    });
});

// Delete job
function deleteJob(jobCode, jobTitle) {
    if (!confirm(`Are you sure you want to delete "${jobTitle}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    fetch('handlers/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            job_code: jobCode,
            csrf_token: '<?= CSRFToken::generate() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to delete job');
    });
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>