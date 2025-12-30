<?php
/**
 * Contacts List - Lead Management
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Pagination;

Permission::require('contacts', 'view');

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get filters
$filters = [
    'search' => input('search', ''),
    'status' => input('status', ''),
    'priority' => input('priority', ''),
    'source' => input('source', ''),
    'assigned_to' => input('assigned_to', ''),
    'tab' => input('tab', 'active') // active, qualified, nurturing, all
];

// Build WHERE clause
$where = ['c.deleted_at IS NULL'];
$params = [];
$types = '';

// Permission-based filtering
if (!Permission::can('contacts', 'view_all')) {
    $where[] = "c.assigned_to = ?";
    $params[] = Auth::userCode();
    $types .= 's';
}

// Tab filtering
switch ($filters['tab']) {
    case 'new':
        $where[] = "c.status = 'new'";
        break;
    case 'contacted':
        $where[] = "c.status = 'contacted'";
        break;
    case 'qualified':
        $where[] = "c.status = 'qualified'";
        break;
    case 'nurturing':
        $where[] = "c.status = 'nurturing'";
        break;
    case 'converted':
        $where[] = "c.status = 'converted'";
        break;
    // 'active' shows new + contacted + qualified + nurturing
    case 'active':
        $where[] = "c.status IN ('new', 'contacted', 'qualified', 'nurturing')";
        break;
}

// Additional filters
if (!empty($filters['search'])) {
    $where[] = "(c.first_name LIKE ? OR c.last_name LIKE ? OR c.email LIKE ? OR c.current_company LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

if (!empty($filters['status'])) {
    $where[] = "c.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['priority'])) {
    $where[] = "c.priority = ?";
    $params[] = $filters['priority'];
    $types .= 's';
}

if (!empty($filters['source'])) {
    $where[] = "c.source = ?";
    $params[] = $filters['source'];
    $types .= 's';
}

if (!empty($filters['assigned_to'])) {
    if ($filters['assigned_to'] === 'me') {
        $where[] = "c.assigned_to = ?";
        $params[] = Auth::userCode();
        $types .= 's';
    } elseif ($filters['assigned_to'] === 'unassigned') {
        $where[] = "c.assigned_to IS NULL";
    } else {
        $where[] = "c.assigned_to = ?";
        $params[] = $filters['assigned_to'];
        $types .= 's';
    }
}

$whereClause = implode(' AND ', $where);

// Get statistics
$statsSQL = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'contacted' THEN 1 ELSE 0 END) as contacted,
        SUM(CASE WHEN status = 'qualified' THEN 1 ELSE 0 END) as qualified,
        SUM(CASE WHEN status = 'nurturing' THEN 1 ELSE 0 END) as nurturing,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN priority = 'high' THEN 1 ELSE 0 END) as high_priority,
        SUM(CASE WHEN next_follow_up = CURDATE() THEN 1 ELSE 0 END) as follow_ups_today
    FROM contacts c
    WHERE c.deleted_at IS NULL
";

// Add permission filter to stats
if (!Permission::can('contacts', 'view_all')) {
    $statsSQL .= " AND c.assigned_to = '" . Auth::userCode() . "'";
}

$stats = $conn->query($statsSQL)->fetch_assoc();

// Count filtered records
$countSQL = "SELECT COUNT(*) as total FROM contacts c WHERE {$whereClause}";
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

// Get contacts
$sql = "
    SELECT 
        c.*,
        u.name as assigned_to_name,
        cand.candidate_code as converted_candidate_code
    FROM contacts c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    LEFT JOIN candidates cand ON c.converted_to_candidate = cand.candidate_code
    WHERE {$whereClause}
    ORDER BY 
        CASE c.priority
            WHEN 'high' THEN 1
            WHEN 'medium' THEN 2
            WHEN 'low' THEN 3
        END,
        c.next_follow_up ASC,
        c.created_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get filter options
$recruiters = $conn->query("SELECT user_code, name FROM users WHERE is_active = 1 AND level IN ('recruiter', 'manager') ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Contacts';
$breadcrumbs = [
    ['title' => 'Contacts', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bx bx-user-circle text-primary me-2"></i>
                        Contacts & Leads
                    </h4>
                    <p class="text-muted mb-0">
                        Manage prospects before they become candidates
                    </p>
                </div>
                <div>
                    <?php if (Permission::can('contacts', 'create')): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bx bx-plus"></i> Add Contact
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <small class="text-muted d-block">Total</small>
                    <h4 class="mb-0"><?= number_format($stats['total']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-warning">
                <div class="card-body">
                    <small class="text-muted d-block">New</small>
                    <h4 class="mb-0 text-warning"><?= number_format($stats['new']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-info">
                <div class="card-body">
                    <small class="text-muted d-block">Qualified</small>
                    <h4 class="mb-0 text-info"><?= number_format($stats['qualified']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-primary">
                <div class="card-body">
                    <small class="text-muted d-block">Nurturing</small>
                    <h4 class="mb-0 text-primary"><?= number_format($stats['nurturing']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-success">
                <div class="card-body">
                    <small class="text-muted d-block">Converted</small>
                    <h4 class="mb-0 text-success"><?= number_format($stats['converted']) ?></h4>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-danger">
                <div class="card-body">
                    <small class="text-muted d-block">Follow-ups Today</small>
                    <h4 class="mb-0 text-danger"><?= number_format($stats['follow_ups_today']) ?></h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabs & Filters Card -->
    <div class="card">
        <div class="card-header">
            <!-- Tabs -->
            <ul class="nav nav-tabs card-header-tabs">
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'active' ? 'active' : '' ?>" 
                       href="?tab=active">
                        Active (<?= $stats['new'] + $stats['contacted'] + $stats['qualified'] + $stats['nurturing'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'new' ? 'active' : '' ?>" 
                       href="?tab=new">
                        New (<?= $stats['new'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'qualified' ? 'active' : '' ?>" 
                       href="?tab=qualified">
                        Qualified (<?= $stats['qualified'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'nurturing' ? 'active' : '' ?>" 
                       href="?tab=nurturing">
                        Nurturing (<?= $stats['nurturing'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'converted' ? 'active' : '' ?>" 
                       href="?tab=converted">
                        Converted (<?= $stats['converted'] ?>)
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $filters['tab'] === 'all' ? 'active' : '' ?>" 
                       href="?tab=all">
                        All (<?= $stats['total'] ?>)
                    </a>
                </li>
            </ul>
        </div>
        
        <!-- Filters -->
        <div class="card-body border-bottom">
            <form method="GET" class="row g-3">
                <input type="hidden" name="tab" value="<?= escape($filters['tab']) ?>">
                
                <div class="col-md-3">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search..." 
                           value="<?= escape($filters['search']) ?>">
                </div>
                
                <div class="col-md-2">
                    <select name="priority" class="form-select">
                        <option value="">All Priorities</option>
                        <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <select name="source" class="form-select">
                        <option value="">All Sources</option>
                        <option value="linkedin" <?= $filters['source'] === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                        <option value="referral" <?= $filters['source'] === 'referral' ? 'selected' : '' ?>>Referral</option>
                        <option value="networking" <?= $filters['source'] === 'networking' ? 'selected' : '' ?>>Networking</option>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <select name="assigned_to" class="form-select">
                        <option value="">All Recruiters</option>
                        <option value="me" <?= $filters['assigned_to'] === 'me' ? 'selected' : '' ?>>My Contacts</option>
                        <option value="unassigned" <?= $filters['assigned_to'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($recruiters as $rec): ?>
                            <option value="<?= $rec['user_code'] ?>" <?= $filters['assigned_to'] === $rec['user_code'] ? 'selected' : '' ?>>
                                <?= escape($rec['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bx bx-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Contacts Table -->
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Company / Title</th>
                        <th>Contact Info</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Source</th>
                        <th>Next Follow-up</th>
                        <th>Assigned To</th>
                        <th width="120">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($contacts)): ?>
                        <tr>
                            <td colspan="9" class="text-center py-5">
                                <i class="bx bx-user-circle bx-lg text-muted"></i>
                                <p class="text-muted mt-2">No contacts found</p>
                                <?php if (Permission::can('contacts', 'create')): ?>
                                    <a href="create.php" class="btn btn-sm btn-primary mt-2">
                                        <i class="bx bx-plus"></i> Add First Contact
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($contacts as $contact): ?>
                        <tr>
                            <td>
                                <a href="view.php?contact_code=<?= urlencode($contact['contact_code']) ?>" 
                                   class="fw-semibold">
                                    <?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($contact['current_company']): ?>
                                    <div><?= escape($contact['current_company']) ?></div>
                                    <small class="text-muted"><?= escape($contact['current_title']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div><?= escape($contact['email']) ?></div>
                                <?php if ($contact['phone']): ?>
                                    <small class="text-muted"><?= escape($contact['phone']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = [
                                    'new' => 'warning',
                                    'contacted' => 'info',
                                    'qualified' => 'primary',
                                    'nurturing' => 'secondary',
                                    'converted' => 'success',
                                    'not_interested' => 'danger',
                                    'unresponsive' => 'dark'
                                ];
                                $color = $statusColors[$contact['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $color ?>">
                                    <?= ucwords(str_replace('_', ' ', $contact['status'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $priorityColors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'info'];
                                $pColor = $priorityColors[$contact['priority']] ?? 'secondary';
                                ?>
                                <span class="badge bg-label-<?= $pColor ?>">
                                    <?= ucfirst($contact['priority']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= ucfirst(str_replace('_', ' ', $contact['source'])) ?></small>
                            </td>
                            <td>
                                <?php if ($contact['next_follow_up']): ?>
                                    <?php
                                    $isOverdue = $contact['next_follow_up'] < date('Y-m-d');
                                    $isToday = $contact['next_follow_up'] === date('Y-m-d');
                                    ?>
                                    <span class="text-<?= $isOverdue ? 'danger' : ($isToday ? 'warning' : 'muted') ?>">
                                        <?= formatDate($contact['next_follow_up'], 'M d, Y') ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?= escape($contact['assigned_to_name'] ?? 'Unassigned') ?></small>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" 
                                            data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <a class="dropdown-item" href="view.php?contact_code=<?= urlencode($contact['contact_code']) ?>">
                                            <i class="bx bx-show me-2"></i> View
                                        </a>
                                        
                                        <?php if (Permission::can('contacts', 'edit')): ?>
                                            <a class="dropdown-item" href="edit.php?contact_code=<?= urlencode($contact['contact_code']) ?>">
                                                <i class="bx bx-edit me-2"></i> Edit
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($contact['status'] !== 'converted' && Permission::can('contacts', 'convert')): ?>
                                            <div class="dropdown-divider"></div>
                                            <a class="dropdown-item text-success" 
                                               href="convert.php?contact_code=<?= urlencode($contact['contact_code']) ?>">
                                                <i class="bx bx-right-arrow-circle me-2"></i> Convert to Candidate
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
                <?= $pagination->render() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>