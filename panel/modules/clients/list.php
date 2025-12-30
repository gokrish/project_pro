<?php
/**
 * Clients List Page
 * Shows all clients with filters and statistics
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

// Page config
$pageTitle = 'Clients';
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Clients', 'url' => '']
];

// Check permissions
$canViewAll = Permission::can('clients', 'view_all');
$canViewOwn = Permission::can('clients', 'view_own');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Filters
$filters = [
    'search' => input('search', ''),
    'status' => input('status', ''),
    'account_manager' => input('account_manager', ''),
    'has_active_jobs' => input('has_active_jobs', '')
];

// Build WHERE clause
$where = ['c.deleted_at IS NULL'];
$params = [];
$types = '';

if ($filters['search']) {
    $where[] = "(c.company_name LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ? OR c.client_code LIKE ?)";
    $searchTerm = "%{$filters['search']}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

if ($filters['status']) {
    $where[] = "c.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if ($filters['account_manager']) {
    $where[] = "c.account_manager = ?";
    $params[] = $filters['account_manager'];
    $types .= 's';
}

// Permission-based filtering
if (!$canViewAll && $canViewOwn) {
    $where[] = "c.account_manager = ?";
    $params[] = $user['user_code'];
    $types .= 's';
}

$whereSQL = implode(' AND ', $where);

// Pagination
$page = max(1, (int)input('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Get total count
$countSQL = "SELECT COUNT(*) as total FROM clients c WHERE $whereSQL";
$stmt = $conn->prepare($countSQL);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalCount / $perPage);

// Get clients
$sql = "
    SELECT 
        c.*,
        u.name as account_manager_name,
        (SELECT COUNT(*) FROM jobs WHERE client_code = c.client_code AND status IN ('open', 'filling')) as active_jobs_count,
        (SELECT COUNT(*) FROM jobs WHERE client_code = c.client_code) as total_jobs
    FROM clients c
    LEFT JOIN users u ON c.account_manager = u.user_code
    WHERE $whereSQL
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$limitParams = array_merge($params, [$perPage, $offset]);
$limitTypes = $types . 'ii';
$stmt->bind_param($limitTypes, ...$limitParams);
$stmt->execute();
$clients = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get statistics
$statsWhere = $canViewAll ? '1=1' : "c.account_manager = '{$user['user_code']}'";
$statsSQL = "
    SELECT 
        COUNT(*) as total_clients,
        SUM(CASE WHEN c.status = 'active' THEN 1 ELSE 0 END) as active_clients,
        SUM(CASE WHEN c.status = 'inactive' THEN 1 ELSE 0 END) as inactive_clients,
        (SELECT COUNT(*) FROM jobs j WHERE j.client_code IN (SELECT client_code FROM clients WHERE $statsWhere) AND j.status IN ('open', 'filling')) as total_active_jobs
    FROM clients c
    WHERE $statsWhere AND c.deleted_at IS NULL
";
$stats = $conn->query($statsSQL)->fetch_assoc();

// Get account managers for filter
$managersSQL = "
    SELECT DISTINCT u.user_code, u.name 
    FROM users u
    JOIN clients c ON u.user_code = c.account_manager
    WHERE u.is_active = 1
    ORDER BY u.name
";
$managers = $conn->query($managersSQL)->fetch_all(MYSQLI_ASSOC);

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.client-card {
    transition: transform 0.2s, box-shadow 0.2s;
    cursor: pointer;
}
.client-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
.stat-card {
    border-left: 4px solid;
}
</style>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-primary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Total Clients</h6>
                        <h2 class="mb-0"><?= number_format($stats['total_clients']) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-building display-4 text-primary opacity-50"></i>
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
                        <h6 class="text-muted mb-1">Active Clients</h6>
                        <h2 class="mb-0 text-success"><?= number_format($stats['active_clients']) ?></h2>
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
                        <h6 class="text-muted mb-1">Active Jobs</h6>
                        <h2 class="mb-0 text-info"><?= number_format($stats['total_active_jobs']) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-briefcase display-4 text-info opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 col-sm-6 mb-3">
        <div class="card stat-card border-secondary">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Inactive</h6>
                        <h2 class="mb-0 text-secondary"><?= number_format($stats['inactive_clients']) ?></h2>
                    </div>
                    <div class="align-self-center">
                        <i class="bx bx-x-circle display-4 text-secondary opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filters & Actions -->
<div class="card mb-4">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0"><i class="bx bx-filter"></i> Filters</h5>
            </div>
            <div class="col-md-6 text-end">
                <?php if (Permission::can('clients', 'create')): ?>
                    <a href="?action=create" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Add New Client
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="action" value="list">
            
            <div class="col-md-3">
                <label class="form-label small">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" 
                       value="<?= escape($filters['search']) ?>" 
                       placeholder="Company name, email...">
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            
            <?php if ($canViewAll): ?>
            <div class="col-md-3">
                <label class="form-label small">Account Manager</label>
                <select name="account_manager" class="form-select form-select-sm">
                    <option value="">All Managers</option>
                    <?php foreach ($managers as $manager): ?>
                        <option value="<?= escape($manager['user_code']) ?>" 
                                <?= $filters['account_manager'] === $manager['user_code'] ? 'selected' : '' ?>>
                            <?= escape($manager['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <label class="form-label small">Has Active Jobs</label>
                <select name="has_active_jobs" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="yes" <?= $filters['has_active_jobs'] === 'yes' ? 'selected' : '' ?>>Yes</option>
                    <option value="no" <?= $filters['has_active_jobs'] === 'no' ? 'selected' : '' ?>>No</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label small">&nbsp;</label>
                <button type="submit" class="btn btn-primary btn-sm w-100">
                    <i class="bx bx-search"></i> Apply
                </button>
            </div>
            
            <?php if (array_filter($filters)): ?>
            <div class="col-md-12">
                <a href="?action=list" class="btn btn-sm btn-outline-secondary">
                    <i class="bx bx-x"></i> Clear Filters
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Clients List -->
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            Clients 
            <span class="badge bg-secondary"><?= number_format($totalCount) ?> total</span>
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="bx bx-building display-1 text-muted"></i>
                <p class="text-muted mt-3">No clients found</p>
                <?php if (Permission::can('clients', 'create')): ?>
                    <a href="?action=create" class="btn btn-primary mt-3">
                        <i class="bx bx-plus"></i> Add First Client
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Company</th>
                            <th>Contact</th>
                            <th>Account Manager</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Jobs</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                            <tr class="client-card" onclick="window.location='?action=view&code=<?= escape($client['client_code']) ?>'">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm rounded-circle bg-label-primary d-flex align-items-center justify-content-center me-3">
                                            <i class="bx bx-building fs-4"></i>
                                        </div>
                                        <div>
                                            <strong><?= escape($client['company_name']) ?></strong><br>
                                            <small class="text-muted"><?= escape($client['client_code']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($client['contact_person']): ?>
                                        <div><strong><?= escape($client['contact_person']) ?></strong></div>
                                    <?php endif; ?>
                                    <?php if ($client['email']): ?>
                                        <small class="text-muted"><?= escape($client['email']) ?></small><br>
                                    <?php endif; ?>
                                    <?php if ($client['phone']): ?>
                                        <small class="text-muted"><?= escape($client['phone']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small><?= escape($client['account_manager_name'] ?: 'Unassigned') ?></small>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-<?= $client['status'] === 'active' ? 'success' : 'secondary' ?>">
                                        <?= ucfirst($client['status']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info"><?= $client['active_jobs_count'] ?> active</span>
                                    <br>
                                    <small class="text-muted"><?= $client['total_jobs'] ?> total</small>
                                </td>
                                <td class="text-center" onclick="event.stopPropagation()">
                                    <a href="?action=view&code=<?= escape($client['client_code']) ?>" 
                                       class="btn btn-sm btn-outline-primary" title="View Details">
                                        <i class="bx bx-show"></i>
                                    </a>
                                    <?php if (Permission::can('clients', 'edit')): ?>
                                        <a href="?action=edit&code=<?= escape($client['client_code']) ?>" 
                                           class="btn btn-sm btn-outline-secondary" title="Edit">
                                            <i class="bx bx-edit"></i>
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
                                <a class="page-link" href="?action=list&page=<?= $page - 1 ?>&<?= http_build_query($filters) ?>">Previous</a>
                            </li>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?action=list&page=<?= $i ?>&<?= http_build_query($filters) ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                                <a class="page-link" href="?action=list&page=<?= $page + 1 ?>&<?= http_build_query($filters) ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                    <p class="text-center text-muted small mt-2 mb-0">
                        Showing <?= number_format($offset + 1) ?> to <?= number_format(min($offset + $perPage, $totalCount)) ?> of <?= number_format($totalCount) ?> clients
                    </p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>