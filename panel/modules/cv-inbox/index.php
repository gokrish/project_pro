<?php
/**
 * CV Inbox - Application Management
 * Manual entry for email/LinkedIn applications
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
Permission::require('cv_inbox', 'view');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Page configuration
$pageTitle = 'CV Inbox';
$breadcrumbs = [
    ['title' => 'CV Inbox', 'url' => '/panel/modules/cv-inbox/index.php']
];
$customJS = ['/panel/assets/js/modules/cv-inbox.js'];

// Get filters
$filters = [
    'search' => input('search', ''),
    'status' => input('status', ''),
    'job_code' => input('job_code', ''),
    'source' => input('source', ''),
    'assigned_to' => input('assigned_to', ''),
    'date_from' => input('date_from', ''),
    'date_to' => input('date_to', ''),
    'tab' => input('tab', 'new') // new, reviewed, converted, rejected, all
];

// Build WHERE clause
$whereConditions = ['1=1'];
$params = [];
$types = '';

// Tab-based filtering
switch ($filters['tab']) {
    case 'new':
        $whereConditions[] = "cv.status = 'new'";
        break;
    case 'reviewed':
        $whereConditions[] = "cv.status = 'reviewed'";
        break;
    case 'converted':
        $whereConditions[] = "cv.status = 'converted'";
        break;
    case 'rejected':
        $whereConditions[] = "cv.status = 'rejected'";
        break;
    // 'all' shows everything
}

// Search filter
if (!empty($filters['search'])) {
    $whereConditions[] = "(cv.candidate_name LIKE ? OR cv.email LIKE ? OR cv.phone LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

// Status filter (additional to tab)
if (!empty($filters['status']) && $filters['tab'] === 'all') {
    $whereConditions[] = "cv.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

// Job filter
if (!empty($filters['job_code'])) {
    $whereConditions[] = "cv.job_code = ?";
    $params[] = $filters['job_code'];
    $types .= 's';
}

// Source filter
if (!empty($filters['source'])) {
    $whereConditions[] = "cv.source = ?";
    $params[] = $filters['source'];
    $types .= 's';
}

// Assigned to filter
if (!empty($filters['assigned_to'])) {
    $whereConditions[] = "cv.assigned_to = ?";
    $params[] = $filters['assigned_to'];
    $types .= 's';
}

// Date range
if (!empty($filters['date_from'])) {
    $whereConditions[] = "DATE(cv.received_at) >= ?";
    $params[] = $filters['date_from'];
    $types .= 's';
}

if (!empty($filters['date_to'])) {
    $whereConditions[] = "DATE(cv.received_at) <= ?";
    $params[] = $filters['date_to'];
    $types .= 's';
}

$whereClause = implode(' AND ', $whereConditions);

// Get statistics
$statsSql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END) as new,
        SUM(CASE WHEN status = 'reviewed' THEN 1 ELSE 0 END) as reviewed,
        SUM(CASE WHEN status = 'converted' THEN 1 ELSE 0 END) as converted,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM cv_inbox cv
    WHERE 1=1
";

$statsResult = $conn->query($statsSql);
$stats = $statsResult->fetch_assoc();

// Count filtered records
$countSql = "SELECT COUNT(*) as total FROM cv_inbox cv WHERE {$whereClause}";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Pagination
$page = (int)input('page', 1);
$perPage = 25;
$pagination = new Pagination($totalRecords, $perPage, $page);

// Get CV applications
$sql = "
    SELECT 
        cv.*,
        j.job_title,
        u.name as assigned_to_name,
        c.candidate_name as converted_candidate_name
    FROM cv_inbox cv
    LEFT JOIN jobs j ON cv.job_code = j.job_code
    LEFT JOIN users u ON cv.assigned_to = u.user_code
    LEFT JOIN candidates c ON cv.converted_to_candidate = c.candidate_code
    WHERE {$whereClause}
    ORDER BY 
        CASE 
            WHEN cv.status = 'new' THEN 1
            WHEN cv.status = 'reviewed' THEN 2
            WHEN cv.status = 'converted' THEN 3
            WHEN cv.status = 'rejected' THEN 4
            ELSE 5
        END,
        cv.received_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cvApplications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get open jobs for filter
$jobsSql = "SELECT job_code, job_title FROM jobs WHERE status = 'open' ORDER BY job_title";
$openJobs = $conn->query($jobsSql)->fetch_all(MYSQLI_ASSOC);

// Get recruiters for filter
$recruitersSql = "SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name";
$recruiters = $conn->query($recruitersSql)->fetch_all(MYSQLI_ASSOC);

Logger::getInstance()->logActivity('view', 'cv_inbox', null, 'Viewed CV inbox', [
    'filters' => $filters,
    'count' => count($cvApplications)
]);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bx bx-inbox text-primary me-2"></i>
                    CV Inbox
                </h4>
                <p class="text-muted mb-0">
                    Manage applications received via email, LinkedIn, and other channels
                </p>
            </div>
            <div>
                <?php if (Permission::can('cv_inbox', 'create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApplicationModal">
                    <i class="bx bx-plus"></i> Add Manual Entry
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Total</div>
                        <div class="stat-card-value"><?= number_format($stats['total']) ?></div>
                        <small class="text-muted">All applications</small>
                    </div>
                    <div class="stat-card-icon bg-label-primary">
                        <i class="bx bx-inbox"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">New</div>
                        <div class="stat-card-value text-warning"><?= number_format($stats['new']) ?></div>
                        <small class="text-muted">Awaiting review</small>
                    </div>
                    <div class="stat-card-icon bg-label-warning">
                        <i class="bx bx-time"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Reviewed</div>
                        <div class="stat-card-value text-info"><?= number_format($stats['reviewed']) ?></div>
                        <small class="text-muted">In progress</small>
                    </div>
                    <div class="stat-card-icon bg-label-info">
                        <i class="bx bx-show"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-success h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Converted</div>
                        <div class="stat-card-value text-success"><?= number_format($stats['converted']) ?></div>
                        <small class="text-muted">To candidates</small>
                    </div>
                    <div class="stat-card-icon bg-label-success">
                        <i class="bx bx-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Rejected</div>
                        <div class="stat-card-value text-danger"><?= number_format($stats['rejected']) ?></div>
                        <small class="text-muted">Not qualified</small>
                    </div>
                    <div class="stat-card-icon bg-label-danger">
                        <i class="bx bx-x-circle"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="row mb-3">
    <div class="col-12">
        <ul class="nav nav-pills" role="tablist">
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'new' ? 'active' : '' ?>" href="?tab=new">
                    <i class="bx bx-time"></i> New (<?= $stats['new'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'reviewed' ? 'active' : '' ?>" href="?tab=reviewed">
                    <i class="bx bx-show"></i> Reviewed (<?= $stats['reviewed'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'converted' ? 'active' : '' ?>" href="?tab=converted">
                    <i class="bx bx-check"></i> Converted (<?= $stats['converted'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'rejected' ? 'active' : '' ?>" href="?tab=rejected">
                    <i class="bx bx-x"></i> Rejected (<?= $stats['rejected'] ?>)
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $filters['tab'] === 'all' ? 'active' : '' ?>" href="?tab=all">
                    <i class="bx bx-list-ul"></i> All (<?= $stats['total'] ?>)
                </a>
            </li>
        </ul>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-header">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="bx bx-filter-alt me-2"></i>Search & Filter
            </h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                <i class="bx bx-chevron-down"></i>
            </button>
        </div>
    </div>
    <div class="card-body" id="filterPanel" style="display: none;">
        <form method="GET" action="">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($filters['tab']) ?>">
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Name, email, phone..."
                           value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Job</label>
                    <select name="job_code" class="form-select">
                        <option value="">All Jobs</option>
                        <?php foreach ($openJobs as $job): ?>
                        <option value="<?= htmlspecialchars($job['job_code']) ?>" 
                                <?= $filters['job_code'] === $job['job_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($job['job_title']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select">
                        <option value="">All Sources</option>
                        <option value="website" <?= $filters['source'] === 'website' ? 'selected' : '' ?>>Website</option>
                        <option value="email" <?= $filters['source'] === 'email' ? 'selected' : '' ?>>Email</option>
                        <option value="linkedin" <?= $filters['source'] === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                        <option value="referral" <?= $filters['source'] === 'referral' ? 'selected' : '' ?>>Referral</option>
                        <option value="other" <?= $filters['source'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">All Recruiters</option>
                        <?php foreach ($recruiters as $recruiter): ?>
                        <option value="<?= htmlspecialchars($recruiter['user_code']) ?>" 
                                <?= $filters['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($recruiter['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_from']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" 
                           value="<?= htmlspecialchars($filters['date_to']) ?>">
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-search"></i> Apply Filters
                    </button>
                    <a href="?tab=<?= htmlspecialchars($filters['tab']) ?>" class="btn btn-outline-secondary">
                        <i class="bx bx-reset"></i> Clear
                    </a>
                    <span class="text-muted ms-3">
                        Showing <?= number_format($totalRecords) ?> application(s)
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Applications Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($cvApplications)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bx bx-inbox"></i>
                </div>
                <h5 class="empty-state-title">No Applications Found</h5>
                <p class="empty-state-description">
                    <?php if ($filters['tab'] === 'new'): ?>
                        No new applications at the moment.
                    <?php elseif (!empty($filters['search'])): ?>
                        No applications match your search criteria.
                    <?php else: ?>
                        Start by adding manual entries for email or LinkedIn applications.
                    <?php endif; ?>
                </p>
                <?php if (Permission::can('cv_inbox', 'create')): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addApplicationModal">
                    <i class="bx bx-plus"></i> Add First Application
                </button>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Contact</th>
                            <th>Job Applied For</th>
                            <th>Source</th>
                            <th>Received</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th width="150">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cvApplications as $cv): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-sm me-2">
                                        <div class="avatar-initial rounded-circle bg-label-primary">
                                            <?= strtoupper(substr($cv['candidate_name'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="/panel/modules/cv-inbox/view.php?id=<?= $cv['id'] ?>" 
                                           class="fw-semibold text-decoration-none">
                                            <?= htmlspecialchars($cv['candidate_name']) ?>
                                        </a>
                                        <?php if ($cv['resume_path']): ?>
                                        <br><small class="text-success">
                                            <i class="bx bx-file"></i> Has Resume
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <small>
                                    <i class="bx bx-envelope"></i> <?= htmlspecialchars($cv['email']) ?>
                                    <?php if (!empty($cv['phone'])): ?>
                                    <br><i class="bx bx-phone"></i> <?= htmlspecialchars($cv['phone']) ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <?php if (!empty($cv['job_title'])): ?>
                                    <span class="badge bg-label-info">
                                        <?= htmlspecialchars($cv['job_title']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">General Application</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $sourceIcons = [
                                    'website' => 'bx-globe',
                                    'email' => 'bx-envelope',
                                    'linkedin' => 'bxl-linkedin',
                                    'referral' => 'bx-user-plus',
                                    'other' => 'bx-dots-horizontal'
                                ];
                                $icon = $sourceIcons[$cv['source']] ?? 'bx-circle';
                                ?>
                                <span class="badge bg-label-secondary">
                                    <i class="bx <?= $icon ?>"></i> <?= ucfirst($cv['source']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= formatDate($cv['received_at'], 'M d, Y') ?></small>
                            </td>
                            <td>
                                <?php
                                $statusClasses = [
                                    'new' => 'warning',
                                    'reviewed' => 'info',
                                    'converted' => 'success',
                                    'rejected' => 'danger',
                                    'spam' => 'dark'
                                ];
                                $statusClass = $statusClasses[$cv['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusClass ?>">
                                    <?= ucfirst($cv['status']) ?>
                                </span>
                                <?php if ($cv['status'] === 'converted' && $cv['converted_candidate_name']): ?>
                                <br><small class="text-muted">
                                    → <?= htmlspecialchars($cv['converted_candidate_name']) ?>
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($cv['assigned_to_name'])): ?>
                                <small><?= htmlspecialchars($cv['assigned_to_name']) ?></small>
                                <?php else: ?>
                                <span class="text-muted">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" class="btn btn-sm btn-icon btn-outline-secondary" 
                                            data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" href="/panel/modules/cv-inbox/view.php?id=<?= $cv['id'] ?>">
                                                <i class="bx bx-show me-2"></i> View Details
                                            </a>
                                        </li>
                                        <?php if ($cv['status'] !== 'converted'): ?>
                                        <li>
                                            <a class="dropdown-item" href="/panel/modules/cv-inbox/convert.php?id=<?= $cv['id'] ?>">
                                                <i class="bx bx-transfer me-2"></i> Convert to Candidate
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <?php if ($cv['status'] !== 'rejected'): ?>
                                        <li>
                                            <a class="dropdown-item text-danger reject-application" 
                                               href="#" data-id="<?= $cv['id'] ?>">
                                                <i class="bx bx-x me-2"></i> Reject
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item text-danger delete-application" 
                                               href="#" data-id="<?= $cv['id'] ?>">
                                                <i class="bx bx-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalRecords > $perPage): ?>
            <div class="mt-4">
                <?= $pagination->render('/panel/modules/cv-inbox/index.php', $_GET) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Manual Application Modal -->
<div class="modal fade" id="addApplicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Manual Application Entry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="addApplicationForm" enctype="multipart/form-data">
                    <?= CSRFToken::field() ?>
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        Use this form to manually enter applications received via email, LinkedIn, or other channels.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="candidate_name" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Job Applied For</label>
                            <select name="job_code" class="form-select">
                                <option value="">General Application (no specific job)</option>
                                <?php foreach ($openJobs as $job): ?>
                                <option value="<?= htmlspecialchars($job['job_code']) ?>">
                                    <?= htmlspecialchars($job['job_title']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Source <span class="text-danger">*</span></label>
                            <select name="source" class="form-select" required>
                                <option value="">Select source...</option>
                                <option value="email">Email Application</option>
                                <option value="linkedin">LinkedIn</option>
                                <option value="referral">Referral</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Resume/CV <span class="text-danger">*</span></label>
                            <input type="file" name="resume" class="form-control" 
                                   accept=".pdf,.doc,.docx" required>
                            <small class="text-muted">PDF, DOC, or DOCX (Max 5MB)</small>
                        </div>
                        
                        <div class="col-12">
                            <label class="form-label">Initial Notes</label>
                            <textarea name="notes" class="form-control" rows="3" 
                                      placeholder="Add any initial observations or context..."></textarea>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitApplication">
                    <i class="bx bx-save"></i> Add to Inbox
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>