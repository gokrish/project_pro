<?php
/**
 * Jobs List Page with Tabs
 * - All Jobs
 * - My Jobs
 * - Pending Approval (managers only)
 * - Drafts
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Check permissions
$canViewAll = Permission::can('jobs', 'view_all');
$canViewOwn = Permission::can('jobs', 'view_own');
$canApprove = Permission::can('jobs', 'approve');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Get tab
$tab = input('tab', 'all');

// Page config
$pageTitle = 'Jobs';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Jobs', 'url' => '']
];

// Build WHERE based on tab
$where = ['j.deleted_at IS NULL'];
$params = [];
$types = '';

switch ($tab) {
    case 'my':
        $where[] = "(j.created_by = ? OR j.assigned_recruiter = ?)";
        $params[] = $user['user_code'];
        $params[] = $user['user_code'];
        $types .= 'ss';
        $tabTitle = 'My Jobs';
        break;
        
    case 'pending':
        if (!$canApprove) {
            header('Location: ?action=list&tab=all');
            exit;
        }
        $where[] = "j.approval_status = 'pending_approval'";
        $tabTitle = 'Pending Approval';
        break;
        
    case 'drafts':
        $where[] = "j.status = 'draft'";
        if (!$canViewAll) {
            $where[] = "j.created_by = ?";
            $params[] = $user['user_code'];
            $types .= 's';
        }
        $tabTitle = 'Drafts';
        break;
        
    case 'open':
        $where[] = "j.status IN ('open', 'filling')";
        $tabTitle = 'Open Jobs';
        break;
        
    case 'closed':
        $where[] = "j.status IN ('filled', 'closed')";
        $tabTitle = 'Closed Jobs';
        break;
        
    default: // 'all'
        if (!$canViewAll) {
            $where[] = "(j.created_by = ? OR j.assigned_recruiter = ?)";
            $params[] = $user['user_code'];
            $params[] = $user['user_code'];
            $types .= 'ss';
        }
        $tabTitle = 'All Jobs';
        break;
}

$whereSQL = implode(' AND ', $where);

// Get statistics for tabs
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts,
        SUM(CASE WHEN status IN ('open', 'filling') THEN 1 ELSE 0 END) as open_jobs,
        SUM(CASE WHEN approval_status = 'pending_approval' THEN 1 ELSE 0 END) as pending_approval,
        SUM(CASE WHEN created_by = '{$user['user_code']}' OR assigned_recruiter = '{$user['user_code']}' THEN 1 ELSE 0 END) as my_jobs,
        SUM(CASE WHEN status IN ('filled', 'closed') THEN 1 ELSE 0 END) as closed
    FROM jobs j
    WHERE j.deleted_at IS NULL
";

if (!$canViewAll) {
    $statsSQL .= " AND (j.created_by = '{$user['user_code']}' OR j.assigned_recruiter = '{$user['user_code']}')";
}

$stats = $conn->query($statsSQL)->fetch_assoc();

// Pagination
$page = max(1, (int)input('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countSQL = "SELECT COUNT(*) as total FROM jobs j WHERE $whereSQL";
$stmt = $conn->prepare($countSQL);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get jobs
$sql = "
    SELECT 
        j.*,
        c.company_name,
        u_created.name as created_by_name,
        u_assigned.name as assigned_recruiter_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u_created ON j.created_by = u_created.user_code
    LEFT JOIN users u_assigned ON j.assigned_recruiter = u_assigned.user_code
    WHERE $whereSQL
    ORDER BY 
        CASE j.approval_status
            WHEN 'pending_approval' THEN 1
            ELSE 2
        END,
        j.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$limitParams = array_merge($params, [$perPage, $offset]);
$limitTypes = $types . 'ii';
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.job-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
    border-left: 4px solid transparent;
}
.job-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.job-card.draft { border-left-color: #6c757d; }
.job-card.pending { border-left-color: #ffc107; }
.job-card.open { border-left-color: #0dcaf0; }
.job-card.filling { border-left-color: #0d6efd; }
.job-card.filled { border-left-color: #198754; }
.job-card.closed { border-left-color: #6c757d; }
</style>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'all' ? 'active' : '' ?>" href="?action=list&tab=all">
            All Jobs <span class="badge bg-secondary"><?= $stats['total'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'my' ? 'active' : '' ?>" href="?action=list&tab=my">
            My Jobs <span class="badge bg-primary"><?= $stats['my_jobs'] ?></span>
        </a>
    </li>
    <?php if ($canApprove): ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'pending' ? 'active' : '' ?>" href="?action=list&tab=pending">
            Pending Approval <span class="badge bg-warning"><?= $stats['pending_approval'] ?></span>
        </a>
    </li>
    <?php endif; ?>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'drafts' ? 'active' : '' ?>" href="?action=list&tab=drafts">
            Drafts <span class="badge bg-secondary"><?= $stats['drafts'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'open' ? 'active' : '' ?>" href="?action=list&tab=open">
            Open <span class="badge bg-info"><?= $stats['open_jobs'] ?></span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'closed' ? 'active' : '' ?>" href="?action=list&tab=closed">
            Closed <span class="badge bg-secondary"><?= $stats['closed'] ?></span>
        </a>
    </li>
</ul>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4><?= $tabTitle ?></h4>
    <?php if (Permission::can('jobs', 'create')): ?>
        <a href="?action=create" class="btn btn-primary">
            <i class="bx bx-plus"></i> Create New Job
        </a>
    <?php endif; ?>
</div>

<!-- Jobs List -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($jobs)): ?>
            <div class="text-center py-5">
                <i class="bx bx-briefcase display-1 text-muted"></i>
                <p class="text-muted mt-3">No jobs found</p>
                <?php if (Permission::can('jobs', 'create')): ?>
                    <a href="?action=create" class="btn btn-primary mt-3">
                        <i class="bx bx-plus"></i> Create First Job
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Job Title</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Approval</th>
                            <th>Positions</th>
                            <th>Assigned To</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobs as $job): ?>
                            <tr class="job-card <?= $job['status'] ?>" 
                                onclick="window.location='?action=view&code=<?= escape($job['job_code']) ?>'">
                                <td>
                                    <strong><?= escape($job['job_title']) ?></strong><br>
                                    <small class="text-muted">
                                        <?= escape($job['job_code']) ?>
                                        <?php if ($job['job_refno']): ?>
                                            | Ref: <?= escape($job['job_refno']) ?>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <?= escape($job['company_name']) ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = [
                                        'draft' => 'secondary',
                                        'pending_approval' => 'warning',
                                        'open' => 'info',
                                        'filling' => 'primary',
                                        'filled' => 'success',
                                        'closed' => 'dark',
                                        'cancelled' => 'danger'
                                    ];
                                    $badgeColor = $statusBadge[$job['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $badgeColor ?>">
                                        <?= ucfirst(str_replace('_', ' ', $job['status'])) ?>
                                    </span>
                                    <?php if ($job['is_published']): ?>
                                        <br><small class="text-success">Published</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $approvalBadge = [
                                        'draft' => 'secondary',
                                        'pending_approval' => 'warning',
                                        'approved' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $appBadgeColor = $approvalBadge[$job['approval_status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $appBadgeColor ?>">
                                        <?= ucfirst(str_replace('_', ' ', $job['approval_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $job['positions_filled'] ?> / <?= $job['positions_total'] ?>
                                    <br><small class="text-muted"><?= $job['total_submissions'] ?> submissions</small>
                                </td>
                                <td>
                                    <small><?= escape($job['assigned_recruiter_name'] ?: 'Unassigned') ?></small>
                                </td>
                                <td onclick="event.stopPropagation()">
                                    <a href="?action=view&code=<?= escape($job['job_code']) ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <?php if ($canApprove && $job['approval_status'] === 'pending_approval'): ?>
                                        <a href="?action=approve&code=<?= escape($job['job_code']) ?>" 
                                           class="btn btn-sm btn-warning" title="Review">
                                            <i class="bx bx-check-circle"></i>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                <a class="page-link" href="?action=list&tab=<?= $tab ?>&page=<?= $page - 1 ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=list&tab=<?= $tab ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?action=list&tab=<?= $tab ?>&page=<?= $page + 1 ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <p class="text-center text-muted small mt-2 mb-0">
                        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalCount)) ?> of <?= number_format($totalCount) ?> jobs
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>