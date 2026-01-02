<?php
/**
 * Candidates List Page
 * Main listing with filters, search, and bulk actions
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, Logger, Pagination};

// Check permission
if (!Permission::can('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit;
}

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Page configuration
$pageTitle = 'All Candidates';
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php']
];
$customJS = ['/panel/assets/js/modules/candidates-list.js'];

// Get filters
$filters = [
    'search' => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '',
    'status' => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: '',
    'lead_type' => filter_input(INPUT_GET, 'lead_type', FILTER_SANITIZE_STRING) ?: '',
    'assigned_to' => filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING) ?: '',
    'location' => filter_input(INPUT_GET, 'location', FILTER_SANITIZE_STRING) ?: '',
    'skills' => filter_input(INPUT_GET, 'skills', FILTER_SANITIZE_STRING) ?: '',
];

// Build WHERE clause
$whereConditions = ['c.deleted_at IS NULL'];
$params = [];
$types = '';

// Apply access control
if (!Permission::can('candidates', 'view_all')) {
    if (Permission::can('candidates', 'view_own')) {
        $whereConditions[] = 'c.created_by = ?';
        $params[] = Auth::userCode();
        $types .= 's';
    } else {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

// Search filter
if (!empty($filters['search'])) {
    $searchTerm = '%' . $filters['search'] . '%';
    $whereConditions[] = "(
        c.candidate_name LIKE ? OR 
        c.email LIKE ? OR 
        c.phone LIKE ? OR 
        c.current_position LIKE ? OR 
        c.current_employer LIKE ?
    )";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sssss';
}

// Status filter
if (!empty($filters['status'])) {
    $whereConditions[] = "c.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

// Lead type filter
if (!empty($filters['lead_type'])) {
    $whereConditions[] = "c.lead_type = ?";
    $params[] = $filters['lead_type'];
    $types .= 's';
}

// Assigned to filter
if (!empty($filters['assigned_to'])) {
    if ($filters['assigned_to'] === 'unassigned') {
        $whereConditions[] = "c.assigned_to IS NULL";
    } else {
        $whereConditions[] = "c.assigned_to = ?";
        $params[] = $filters['assigned_to'];
        $types .= 's';
    }
}

// Location filter
if (!empty($filters['location'])) {
    $whereConditions[] = "c.current_location = ?";
    $params[] = $filters['location'];
    $types .= 's';
}

// Skills filter
if (!empty($filters['skills'])) {
    $whereConditions[] = "EXISTS (
        SELECT 1 FROM candidate_skills cs 
        JOIN technical_skills ts ON cs.skill_id = ts.id 
        WHERE cs.candidate_code = c.candidate_code 
        AND ts.skill_name LIKE ?
    )";
    $skillSearch = '%' . $filters['skills'] . '%';
    $params[] = $skillSearch;
    $types .= 's';
}

// Build WHERE clause
$whereSQL = implode(' AND ', $whereConditions);

// Count total records
$countSQL = "SELECT COUNT(*) as total FROM candidates c WHERE {$whereSQL}";
$stmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];

// Pagination
$pagination = Pagination::fromRequest($totalRecords, 25);

// Fetch candidates
$sql = "
    SELECT 
        c.*,
        u.name as assigned_to_name,
        GROUP_CONCAT(DISTINCT ts.skill_name ORDER BY ts.skill_name SEPARATOR ', ') as skills
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    LEFT JOIN candidate_skills cs ON c.candidate_code = cs.candidate_code
    LEFT JOIN technical_skills ts ON cs.skill_id = ts.id
    WHERE {$whereSQL}
    GROUP BY c.candidate_code
    ORDER BY c.updated_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recruiters for assignment
$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin','user') AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $recruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><?= $pageTitle ?></h4>
            <p class="text-muted mb-0">Manage and track all candidates</p>
        </div>
        <div>
            <?php if (Permission::can('candidates', 'create')): ?>
                <a href="/panel/modules/candidates/create.php" class="btn btn-primary">
                    <i class="bx bx-plus"></i> Add Candidate
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="bx bx-filter"></i> Filters
                </h6>
                <button type="button" class="btn btn-sm btn-link" id="toggleFilters">
                    <i class="bx bx-chevron-down"></i>
                </button>
            </div>
        </div>
        <div class="card-body" id="filterPanel">
            <form method="GET" id="filterForm">
                <div class="row g-3">
                    <!-- Search -->
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= htmlspecialchars($filters['search']) ?>" 
                               placeholder="Name, email, phone...">
                    </div>

                    <!-- Status -->
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Statuses</option>
                            <?php foreach (CANDIDATE_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lead Type -->
                    <div class="col-md-2">
                        <label class="form-label">Lead Type</label>
                        <select class="form-select" name="lead_type">
                            <option value="">All Types</option>
                            <?php foreach (LEAD_TYPES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $filters['lead_type'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Location -->
                    <div class="col-md-2">
                        <label class="form-label">Location</label>
                        <select class="form-select" name="location">
                            <option value="">All Locations</option>
                            <?php foreach (CANDIDATE_LOCATIONS as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $filters['location'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Assigned To -->
                    <div class="col-md-2">
                        <label class="form-label">Assigned To</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">All</option>
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

                    <!-- Skills -->
                    <div class="col-md-3">
                        <label class="form-label">Skills</label>
                        <input type="text" class="form-control" name="skills" 
                               value="<?= htmlspecialchars($filters['skills']) ?>" 
                               placeholder="Java, Python, React...">
                    </div>

                    <!-- Buttons -->
                    <div class="col-md-9 d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-search"></i> Search
                        </button>
                        <a href="/panel/modules/candidates/list.php" class="btn btn-secondary">
                            <i class="bx bx-reset"></i> Clear
                        </a>
                        <?php if (Permission::can('candidates', 'export')): ?>
                            <button type="button" class="btn btn-success" id="exportCsv">
                                <i class="bx bx-download"></i> CSV
                            </button>
                            <button type="button" class="btn btn-success" id="exportExcel">
                                <i class="bx bx-file"></i> Excel
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Bulk Actions Bar (Hidden by default) -->
    <div class="alert alert-info d-none mb-3" id="bulkActionsBar">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <strong><span id="selectedCount">0</span> candidate(s) selected</strong>
            </div>
            <div class="btn-group">
                <?php if (Permission::can('candidates', 'assign')): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="bulkAssign">
                        <i class="bx bx-user-check"></i> Assign
                    </button>
                <?php endif; ?>
                <?php if (Permission::can('candidates', 'edit')): ?>
                    <button type="button" class="btn btn-sm btn-warning" id="bulkChangeStatus">
                        <i class="bx bx-sync"></i> Change Status
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="bulkChangeLeadType">
                        <i class="bx bx-target-lock"></i> Change Lead Type
                    </button>
                <?php endif; ?>
                <?php if (Permission::can('candidates', 'delete')): ?>
                    <button type="button" class="btn btn-sm btn-danger" id="bulkDelete">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Candidates Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($candidates)): ?>
                <div class="text-center py-5">
                    <i class="bx bx-user-x" style="font-size: 4rem; color: #ccc;"></i>
                    <h5 class="mt-3 text-muted">No candidates found</h5>
                    <p class="text-muted">Try adjusting your filters or add a new candidate</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="30">
                                    <input type="checkbox" class="form-check-input" id="selectAll">
                                </th>
                                <th>Candidate</th>
                                <th>Contact</th>
                                <th>Current Role</th>
                                <th>Skills</th>
                                <th>Status</th>
                                <th>Lead Type</th>
                                <th>Location</th>
                                <th>Assigned To</th>
                                <th>Updated</th>
                                <th width="100">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($candidates as $candidate): ?>
                                <tr>
                                    <td>
                                        <input type="checkbox" class="form-check-input candidate-checkbox" 
                                               value="<?= $candidate['candidate_code'] ?>">
                                    </td>
                                    <td>
                                        <a href="/panel/modules/candidates/view.php?code=<?= $candidate['candidate_code'] ?>" 
                                           class="text-decoration-none fw-bold">
                                            <?= htmlspecialchars($candidate['candidate_name']) ?>
                                        </a>
                                        <?php if (!empty($candidate['professional_summary'])): ?>
                                            <br>
                                            <small class="text-muted">
                                                <?= htmlspecialchars(substr($candidate['professional_summary'], 0, 60)) ?>...
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="bx bx-envelope"></i> <?= htmlspecialchars($candidate['email']) ?><br>
                                            <i class="bx bx-phone"></i> <?= htmlspecialchars($candidate['phone']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (!empty($candidate['current_position'])): ?>
                                            <strong><?= htmlspecialchars($candidate['current_position']) ?></strong><br>
                                        <?php endif; ?>
                                        <?php if (!empty($candidate['current_employer'])): ?>
                                            <small class="text-muted">
                                                <?= htmlspecialchars($candidate['current_employer']) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($candidate['skills'])): ?>
                                            <?php
                                            $skillsArray = explode(', ', $candidate['skills']);
                                            $displaySkills = array_slice($skillsArray, 0, 3);
                                            $remainingSkills = count($skillsArray) - 3;
                                            ?>
                                            <div class="d-flex flex-wrap gap-1">
                                                <?php foreach ($displaySkills as $skill): ?>
                                                    <span class="badge bg-secondary"><?= htmlspecialchars($skill) ?></span>
                                                <?php endforeach; ?>
                                                <?php if ($remainingSkills > 0): ?>
                                                    <span class="badge bg-light text-dark">+<?= $remainingSkills ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <small class="text-muted">No skills</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= getCandidateStatusBadge($candidate['status']) ?>
                                    </td>
                                    <td>
                                        <?= getLeadTypeBadge($candidate['lead_type']) ?>
                                    </td>
                                    <td>
                                        <small><?= htmlspecialchars($candidate['current_location'] ?? 'N/A') ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($candidate['assigned_to_name'])): ?>
                                            <small><?= htmlspecialchars($candidate['assigned_to_name']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Unassigned</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?= date('M d, Y', strtotime($candidate['updated_at'])) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/panel/modules/candidates/view.php?code=<?= $candidate['candidate_code'] ?>" 
                                               class="btn btn-outline-primary" title="View">
                                                <i class="bx bx-show"></i>
                                            </a>
                                            <?php if (Permission::can('candidates', 'edit')): ?>
                                                <a href="/panel/modules/candidates/edit.php?code=<?= $candidate['candidate_code'] ?>" 
                                                   class="btn btn-outline-secondary" title="Edit">
                                                    <i class="bx bx-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-3">
                    <?= $pagination->renderComplete('candidates') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bulk Assign Modal -->
<?php if (Permission::can('candidates', 'assign')): ?>
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bulk Assign Candidates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkAssignForm">
                    <p>Assign <strong><span id="bulkAssignCount">0</span> candidate(s)</strong> to:</p>
                    <select class="form-select" name="recruiter" required>
                        <option value="">Select recruiter...</option>
                        <?php foreach ($recruiters as $recruiter): ?>
                            <option value="<?= $recruiter['user_code'] ?>">
                                <?= htmlspecialchars($recruiter['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBulkAssign">Assign</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Make candidateCode available globally for JS
const candidateCode = null; // Not needed on list page
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>