<?php
/**
 * Candidates List
 * Main candidates listing with recruiter-focused filters
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Pagination;

// Check permission
if (!Permission::require('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit();
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

// Get filters - sanitized inputs
$filters = [
    'search' => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '',
    'status' => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING) ?: '',
    'lead_type' => filter_input(INPUT_GET, 'lead_type', FILTER_SANITIZE_STRING) ?: '',
    'assigned_to' => filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING) ?: '',
    'work_authorization' => filter_input(INPUT_GET, 'work_authorization', FILTER_SANITIZE_STRING) ?: '',
    'skills' => filter_input(INPUT_GET, 'skills', FILTER_SANITIZE_STRING) ?: '',
    'experience_min' => filter_input(INPUT_GET, 'experience_min', FILTER_SANITIZE_NUMBER_INT) ?: '',
    'experience_max' => filter_input(INPUT_GET, 'experience_max', FILTER_SANITIZE_NUMBER_INT) ?: '',
    'location' => filter_input(INPUT_GET, 'location', FILTER_SANITIZE_STRING) ?: '',
    'rating_min' => filter_input(INPUT_GET, 'rating_min', FILTER_SANITIZE_NUMBER_INT) ?: '',
    'languages' => filter_input(INPUT_GET, 'languages', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?: [],
    'view' => filter_input(INPUT_GET, 'view', FILTER_SANITIZE_STRING) ?: 'all' // all, my, unassigned, hot, warm
];

// Build WHERE clause - START WITH PARAMETERIZED APPROACH
$whereConditions = [];
$params = [];
$types = '';

// Row-level security - FIXED SQL INJECTION
$accessFilter = Permission::getAccessibleCandidates();
if ($accessFilter) {
    // This should be a safe condition string from permissions system
    $whereConditions[] = $accessFilter;
}

// Search
if (!empty($filters['search'])) {
    $searchTerm = '%' . $filters['search'] . '%';
    $whereConditions[] = "(
        c.candidate_name LIKE ? OR 
        c.email LIKE ? OR 
        c.phone LIKE ? OR 
        c.current_position LIKE ? OR 
        c.current_company LIKE ?
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

// Work authorization filter
if (!empty($filters['work_authorization'])) {
    $whereConditions[] = "c.work_authorization_status = ?";
    $params[] = $filters['work_authorization'];
    $types .= 's';
}

// Skills filter
if (!empty($filters['skills'])) {
    $whereConditions[] = "c.skills LIKE ?";
    $params[] = '%' . $filters['skills'] . '%';
    $types .= 's';
}

// Experience range filter
if (!empty($filters['experience_min'])) {
    $whereConditions[] = "c.total_experience >= ?";
    $params[] = (int)$filters['experience_min'];
    $types .= 'i';
}
if (!empty($filters['experience_max'])) {
    $whereConditions[] = "c.total_experience <= ?";
    $params[] = (int)$filters['experience_max'];
    $types .= 'i';
}

// Location filter
if (!empty($filters['location'])) {
    $locationTerm = '%' . $filters['location'] . '%';
    $whereConditions[] = "(c.current_location LIKE ? OR c.preferred_location LIKE ?)";
    $params = array_merge($params, [$locationTerm, $locationTerm]);
    $types .= 'ss';
}

// Rating filter
if (!empty($filters['rating_min'])) {
    $whereConditions[] = "c.rating >= ?";
    $params[] = (int)$filters['rating_min'];
    $types .= 'i';
}

// Languages filter
$allowedLanguages = ['english', 'french', 'dutch', 'german'];
$selectedLanguages = array_intersect($filters['languages'], $allowedLanguages);

if (!empty($selectedLanguages)) {
    $langConditions = [];
    foreach ($selectedLanguages as $lang) {
        $langConditions[] = "FIND_IN_SET(?, c.languages_known) > 0";
        $params[] = $lang;
        $types .= 's';
    }
    $whereConditions[] = '(' . implode(' OR ', $langConditions) . ')';
}

// Quick view filters
switch ($filters['view']) {
    case 'my':
        if ($user['level'] === 'recruiter') {
            $whereConditions[] = "c.assigned_to = ?";
            $params[] = $user['user_code'];
            $types .= 's';
        }
        break;
    case 'unassigned':
        $whereConditions[] = "c.assigned_to IS NULL";
        break;
    case 'hot':
        $whereConditions[] = "c.lead_type = 'hot'";
        break;
    case 'warm':
        $whereConditions[] = "c.lead_type = 'warm'";
        break;
}

// Build WHERE clause safely
$whereClause = !empty($whereConditions) ? implode(' AND ', $whereConditions) : '1=1';

// Get statistics for dashboard cards
$statsSql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN lead_type = 'hot' THEN 1 ELSE 0 END) as hot_leads,
        SUM(CASE WHEN lead_type = 'warm' THEN 1 ELSE 0 END) as warm_leads,
        SUM(CASE WHEN lead_type = 'cold' THEN 1 ELSE 0 END) as cold_leads,
        SUM(CASE WHEN lead_type = 'blacklist' THEN 1 ELSE 0 END) as blacklisted,
        SUM(CASE WHEN assigned_to IS NULL THEN 1 ELSE 0 END) as unassigned,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active
    FROM candidates c
    WHERE {$whereClause}
";

// Prepare and execute stats query with parameters
$statsStmt = $conn->prepare($statsSql);
if (!empty($params)) {
    $statsStmt->bind_param($types, ...$params);
}
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();

// Count filtered records
$countSql = "SELECT COUNT(*) as total FROM candidates c WHERE {$whereClause}";
$countStmt = $conn->prepare($countSql);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Pagination
$page = (int)($_GET['page'] ?? 1);
$perPage = 25;
$pagination = new Pagination($totalRecords, $perPage, $page);

// Get candidates
$sql = "
    SELECT 
        c.*,
        u.name as assigned_to_name,
        (SELECT COUNT(*) FROM applications WHERE candidate_code = c.candidate_code) as application_count,
        (SELECT COUNT(*) FROM candidate_documents WHERE candidate_code = c.candidate_code) as document_count,
        (SELECT MAX(created_at) FROM activity_log WHERE module = 'candidates' AND record_id = c.candidate_code) as last_activity
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    WHERE {$whereClause}
    ORDER BY 
        CASE 
            WHEN c.lead_type = 'hot' THEN 1
            WHEN c.lead_type = 'warm' THEN 2
            WHEN c.lead_type = 'cold' THEN 3
            ELSE 4
        END,
        c.updated_at DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all recruiters for assignment dropdown
$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $recruiterSql = "SELECT user_code, name, email FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name";
    $recruiterResult = $conn->query($recruiterSql);
    $recruiters = $recruiterResult->fetch_all(MYSQLI_ASSOC);
}

// Get all unique skills for filter
$skillsSql = "SELECT DISTINCT skills FROM candidates WHERE skills IS NOT NULL AND skills != ''";
$allSkills = [];
$skillsResult = $conn->query($skillsSql);
if ($skillsResult) {
    while ($row = $skillsResult->fetch_assoc()) {
        if (!empty($row['skills'])) {
            $skills = explode(',', $row['skills']);
            foreach ($skills as $skill) {
                $skill = trim($skill);
                if (!empty($skill) && !in_array($skill, $allSkills)) {
                    $allSkills[] = $skill;
                }
            }
        }
    }
    sort($allSkills);
}

// Get all languages for filter (hardcoded for security)
$availableLanguages = [
    'english' => 'English',
    'french' => 'French',
    'dutch' => 'Dutch',
    'german' => 'German',
    'spanish' => 'Spanish',
    'italian' => 'Italian',
    'portuguese' => 'Portuguese'
];

Logger::getInstance()->logActivity('view', 'candidates', null, 'Viewed candidates list', [
    'filters' => $filters,
    'count' => count($candidates)
]);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="bx bx-user text-primary me-2"></i>
                    Candidates Database
                </h4>
                <p class="text-muted mb-0">
                    Manage your talent pipeline effectively
                </p>
            </div>
            <div class="d-flex gap-2">
                <?php if (Permission::can('candidates', 'create')): ?>
                <a href="/panel/modules/candidates/create.php" class="btn btn-primary">
                    <i class="bx bx-plus"></i> Add Candidate
                </a>
                <?php endif; ?>
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bx bx-download"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" id="exportCsv"><i class="bx bx-file me-2"></i>Export to CSV</a></li>
                        <li><a class="dropdown-item" href="#" id="exportExcel"><i class="bx bx-file-blank me-2"></i>Export to Excel</a></li>
                    </ul>
                </div>
                <?php if (Permission::can('candidates', 'create') && $user['level'] !== 'user'): ?>
                <a href="/panel/modules/candidates/import.php" class="btn btn-outline-secondary">
                    <i class="bx bx-upload"></i> Import
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row g-3 mb-4">
    <!-- Total Candidates -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-primary h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Total</div>
                        <div class="stat-card-value"><?= number_format($stats['total'] ?? 0) ?></div>
                        <small class="text-success">
                            <i class="bx bx-check-circle"></i> <?= number_format($stats['active'] ?? 0) ?> active
                        </small>
                    </div>
                    <div class="stat-card-icon bg-label-primary">
                        <i class="bx bx-user"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hot Leads -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-danger h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Hot Leads</div>
                        <div class="stat-card-value text-danger"><?= number_format($stats['hot_leads'] ?? 0) ?></div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-danger" style="width: <?= $stats['total'] > 0 ? round(($stats['hot_leads'] / $stats['total']) * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-card-icon bg-label-danger">
                        <i class="bx bxs-hot"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Warm Leads -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-warning h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Warm Leads</div>
                        <div class="stat-card-value text-warning"><?= number_format($stats['warm_leads'] ?? 0) ?></div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: <?= $stats['total'] > 0 ? round(($stats['warm_leads'] / $stats['total']) * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-card-icon bg-label-warning">
                        <i class="bx bx-time"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Cold Leads -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-info h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Cold Leads</div>
                        <div class="stat-card-value text-info"><?= number_format($stats['cold_leads'] ?? 0) ?></div>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-info" style="width: <?= $stats['total'] > 0 ? round(($stats['cold_leads'] / $stats['total']) * 100) : 0 ?>%"></div>
                        </div>
                    </div>
                    <div class="stat-card-icon bg-label-info">
                        <i class="bx bx-snow"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Unassigned -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-secondary h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Unassigned</div>
                        <div class="stat-card-value"><?= number_format($stats['unassigned'] ?? 0) ?></div>
                        <small class="text-muted">Need assignment</small>
                    </div>
                    <div class="stat-card-icon bg-label-secondary">
                        <i class="bx bx-user-x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Blacklisted -->
    <div class="col-sm-6 col-lg-2">
        <div class="card stat-card stat-card-dark h-100">
            <div class="card-body">
                <div class="d-flex align-items-start justify-content-between">
                    <div>
                        <div class="stat-card-label">Blacklisted</div>
                        <div class="stat-card-value"><?= number_format($stats['blacklisted'] ?? 0) ?></div>
                        <small class="text-muted">Do not contact</small>
                    </div>
                    <div class="stat-card-icon bg-label-dark">
                        <i class="bx bx-block"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick View Filters -->
<div class="row mb-3">
    <div class="col-12">
        <div class="btn-group" role="group">
            <a href="?view=all" class="btn btn-sm <?= $filters['view'] === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                All Candidates
            </a>
            <?php if ($user['level'] === 'recruiter'): ?>
            <a href="?view=my" class="btn btn-sm <?= $filters['view'] === 'my' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bx bx-user-pin"></i> My Candidates
            </a>
            <?php endif; ?>
            <a href="?view=unassigned" class="btn btn-sm <?= $filters['view'] === 'unassigned' ? 'btn-primary' : 'btn-outline-primary' ?>">
                <i class="bx bx-user-x"></i> Unassigned
            </a>
            <a href="?view=hot" class="btn btn-sm <?= $filters['view'] === 'hot' ? 'btn-danger' : 'btn-outline-danger' ?>">
                <i class="bx bxs-hot"></i> Hot Leads
            </a>
            <a href="?view=warm" class="btn btn-sm <?= $filters['view'] === 'warm' ? 'btn-warning' : 'btn-outline-warning' ?>">
                <i class="bx bx-time"></i> Warm Leads
            </a>
        </div>
    </div>
</div>

<!-- Advanced Filters -->
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
    <div class="card-body" id="filterPanel">
        <form method="GET" action="" id="filterForm">
            <div class="row g-3">
                <!-- Search -->
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Name, email, phone, position..."
                           value="<?= htmlspecialchars($filters['search']) ?>">
                </div>
                
                <!-- Status -->
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="active" <?= $filters['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="placed" <?= $filters['status'] === 'placed' ? 'selected' : '' ?>>Placed</option>
                        <option value="archived" <?= $filters['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                
                <!-- Lead Type -->
                <div class="col-md-2">
                    <label class="form-label">Lead Type</label>
                    <select name="lead_type" class="form-select">
                        <option value="">All Types</option>
                        <option value="hot" <?= $filters['lead_type'] === 'hot' ? 'selected' : '' ?>>🔥 Hot</option>
                        <option value="warm" <?= $filters['lead_type'] === 'warm' ? 'selected' : '' ?>>⏰ Warm</option>
                        <option value="cold" <?= $filters['lead_type'] === 'cold' ? 'selected' : '' ?>>❄️ Cold</option>
                        <option value="blacklist" <?= $filters['lead_type'] === 'blacklist' ? 'selected' : '' ?>>🚫 Blacklist</option>
                    </select>
                </div>
                
                <!-- Assigned To -->
                <div class="col-md-2">
                    <label class="form-label">Assigned To</label>
                    <select name="assigned_to" class="form-select">
                        <option value="">All Recruiters</option>
                        <option value="unassigned" <?= $filters['assigned_to'] === 'unassigned' ? 'selected' : '' ?>>Unassigned</option>
                        <?php foreach ($recruiters as $recruiter): ?>
                        <option value="<?= htmlspecialchars($recruiter['user_code']) ?>" 
                                <?= $filters['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($recruiter['name']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Rating -->
                <div class="col-md-2">
                    <label class="form-label">Min Rating</label>
                    <select name="rating_min" class="form-select">
                        <option value="">Any Rating</option>
                        <option value="5" <?= $filters['rating_min'] === '5' ? 'selected' : '' ?>>★★★★★ 5 Stars</option>
                        <option value="4" <?= $filters['rating_min'] === '4' ? 'selected' : '' ?>>★★★★ 4+ Stars</option>
                        <option value="3" <?= $filters['rating_min'] === '3' ? 'selected' : '' ?>>★★★ 3+ Stars</option>
                    </select>
                </div>
                
                <!-- Work Authorization -->
                <div class="col-md-3">
                    <label class="form-label">Work Authorization</label>
                    <select name="work_authorization" class="form-select">
                        <option value="">All Types</option>
                        <option value="eu_citizen" <?= $filters['work_authorization'] === 'eu_citizen' ? 'selected' : '' ?>>EU Citizen</option>
                        <option value="work_permit" <?= $filters['work_authorization'] === 'work_permit' ? 'selected' : '' ?>>Work Permit</option>
                        <option value="requires_sponsorship" <?= $filters['work_authorization'] === 'requires_sponsorship' ? 'selected' : '' ?>>Requires Sponsorship</option>
                    </select>
                </div>
                
                <!-- Languages Known - NEW MULTI-SELECT -->
                <div class="col-md-3">
                    <label class="form-label">Languages Known</label>
                    <select name="languages[]" class="form-select" multiple>
                        <?php foreach ($availableLanguages as $langCode => $langName): ?>
                        <option value="<?= htmlspecialchars($langCode) ?>" 
                                <?= in_array($langCode, $filters['languages']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($langName) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Hold Ctrl/Cmd to select multiple</small>
                </div>
                
                <!-- Skills -->
                <div class="col-md-3">
                    <label class="form-label">Skills</label>
                    <input type="text" 
                           name="skills" 
                           class="form-control" 
                           placeholder="e.g., PHP, React, Python"
                           value="<?= htmlspecialchars($filters['skills']) ?>"
                           list="skillsList">
                    <datalist id="skillsList">
                        <?php foreach ($allSkills as $skill): ?>
                        <option value="<?= htmlspecialchars($skill) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                
                <!-- Experience Range -->
                <div class="col-md-3">
                    <label class="form-label">Experience (Years)</label>
                    <div class="input-group">
                        <input type="number" 
                               name="experience_min" 
                               class="form-control" 
                               placeholder="Min"
                               min="0"
                               value="<?= htmlspecialchars($filters['experience_min']) ?>">
                        <span class="input-group-text">to</span>
                        <input type="number" 
                               name="experience_max" 
                               class="form-control" 
                               placeholder="Max"
                               min="0"
                               value="<?= htmlspecialchars($filters['experience_max']) ?>">
                    </div>
                </div>
                
                <!-- Location -->
                <div class="col-md-3">
                    <label class="form-label">Location</label>
                    <input type="text" 
                           name="location" 
                           class="form-control" 
                           placeholder="City or country"
                           value="<?= htmlspecialchars($filters['location']) ?>">
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-search"></i> Apply Filters
                    </button>
                    <a href="/panel/modules/candidates/list.php" class="btn btn-outline-secondary">
                        <i class="bx bx-reset"></i> Clear All
                    </a>
                    <span class="text-muted ms-3">
                        Showing <?= number_format($totalRecords) ?> candidate(s)
                    </span>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Candidates Table -->
<div class="card">
    <div class="card-body">
        <?php if (empty($candidates)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="bx bx-user-x"></i>
                </div>
                <h5 class="empty-state-title">No Candidates Found</h5>
                <p class="empty-state-description">
                    <?php if (!empty($filters['search']) || !empty($filters['status'])): ?>
                        No candidates match your current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        Get started by adding your first candidate to the database.
                    <?php endif; ?>
                </p>
                <?php if (Permission::can('candidates', 'create')): ?>
                <a href="/panel/modules/candidates/create.php" class="btn btn-primary">
                    <i class="bx bx-plus"></i> Add First Candidate
                </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <!-- Bulk Actions Bar -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="selectAll">
                    <label class="form-check-label" for="selectAll">
                        Select All
                    </label>
                </div>
                
                <div id="bulkActionsBar" class="d-none">
                    <span class="me-3">
                        <strong id="selectedCount">0</strong> selected
                    </span>
                    <?php if (Permission::can('candidates', 'assign')): ?>
                    <button type="button" class="btn btn-sm btn-primary" id="bulkAssign">
                        <i class="bx bx-user-plus"></i> Assign
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-warning" id="bulkChangeStatus">
                        <i class="bx bx-edit"></i> Change Status
                    </button>
                    <button type="button" class="btn btn-sm btn-info" id="bulkChangeLeadType">
                        <i class="bx bxs-hot"></i> Change Lead Type
                    </button>
                    <?php if (Permission::can('candidates', 'delete')): ?>
                    <button type="button" class="btn btn-sm btn-danger" id="bulkDelete">
                        <i class="bx bx-trash"></i> Delete
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Candidates Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th width="30"></th>
                            <th>Candidate</th>
                            <th>Position / Company</th>
                            <th>Experience</th>
                            <th>Location</th>
                            <th>Lead</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Languages</th>
                            <th>Rating</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($candidates as $candidate): ?>
                        <tr>
                            <td>
                                <input type="checkbox" 
                                       class="form-check-input candidate-checkbox" 
                                       value="<?= htmlspecialchars($candidate['candidate_code']) ?>">
                            </td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar me-3">
                                        <div class="avatar-initial rounded-circle bg-label-primary">
                                            <?= strtoupper(substr($candidate['candidate_name'], 0, 2)) ?>
                                        </div>
                                    </div>
                                    <div>
                                        <a href="/panel/modules/candidates/view.php?code=<?= urlencode($candidate['candidate_code']) ?>" 
                                           class="fw-semibold text-decoration-none">
                                            <?= htmlspecialchars($candidate['candidate_name']) ?>
                                        </a>
                                        <div class="small text-muted">
                                            <i class="bx bx-envelope"></i> <?= htmlspecialchars($candidate['email']) ?>
                                        </div>
                                        <?php if (!empty($candidate['phone'])): ?>
                                        <div class="small text-muted">
                                            <i class="bx bx-phone"></i> <?= htmlspecialchars($candidate['phone']) ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="fw-medium"><?= htmlspecialchars($candidate['current_position'] ?: '-') ?></div>
                                <div class="small text-muted"><?= htmlspecialchars($candidate['current_company'] ?: '-') ?></div>
                            </td>
                            <td>
                                <span class="badge bg-label-secondary">
                                    <?= (int)$candidate['total_experience'] ?> years
                                </span>
                            </td>
                            <td>
                                <div class="small">
                                    <?php if (!empty($candidate['current_location'])): ?>
                                    <i class="bx bx-map"></i> <?= htmlspecialchars($candidate['current_location']) ?>
                                    <?php else: ?>
                                    <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php
                                $leadClass = [
                                    'hot' => 'danger',
                                    'warm' => 'warning',
                                    'cold' => 'info',
                                    'blacklist' => 'dark'
                                ][$candidate['lead_type']] ?? 'secondary';
                                
                                $leadIcon = [
                                    'hot' => 'bxs-hot',
                                    'warm' => 'bx-time',
                                    'cold' => 'bx-snow',
                                    'blacklist' => 'bx-block'
                                ][$candidate['lead_type']] ?? 'bx-circle';
                                ?>
                                <span class="badge bg-<?= $leadClass ?>">
                                    <i class="bx <?= $leadIcon ?>"></i> <?= ucfirst($candidate['lead_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $statusClass = [
                                    'active' => 'success',
                                    'placed' => 'primary',
                                    'archived' => 'secondary'
                                ][$candidate['status']] ?? 'secondary';
                                ?>
                                <span class="badge bg-<?= $statusClass ?>">
                                    <?= ucfirst($candidate['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($candidate['assigned_to_name'])): ?>
                                <div class="d-flex align-items-center">
                                    <div class="avatar avatar-xs me-2">
                                        <div class="avatar-initial rounded-circle bg-label-info">
                                            <?= strtoupper(substr($candidate['assigned_to_name'], 0, 1)) ?>
                                        </div>
                                    </div>
                                    <span class="small"><?= htmlspecialchars($candidate['assigned_to_name']) ?></span>
                                </div>
                                <?php else: ?>
                                <span class="badge bg-label-secondary">
                                    <i class="bx bx-user-x"></i> Unassigned
                                </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php 
                                    $languages = !empty($candidate['languages_known']) 
                                        ? explode(',', $candidate['languages_known']) 
                                        : [];
                                    foreach ($languages as $lang): 
                                        $lang = trim($lang);
                                        if (isset($availableLanguages[$lang])): ?>
                                    <span class="badge bg-label-primary"><?= htmlspecialchars($availableLanguages[$lang]) ?></span>
                                    <?php endif; endforeach; ?>
                                </div>
                            </td>
                            <td>
                                <div class="rating">
                                    <?php
                                    $rating = (int)($candidate['rating'] ?? 0);
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= $rating):
                                    ?>
                                    <i class="bx bxs-star text-warning"></i>
                                    <?php else: ?>
                                    <i class="bx bx-star text-muted"></i>
                                    <?php endif; endfor; ?>
                                </div>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button type="button" 
                                            class="btn btn-sm btn-icon btn-outline-secondary" 
                                            data-bs-toggle="dropdown">
                                        <i class="bx bx-dots-vertical-rounded"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item" 
                                               href="/panel/modules/candidates/view.php?code=<?= urlencode($candidate['candidate_code']) ?>">
                                                <i class="bx bx-show me-2"></i> View Full Profile
                                            </a>
                                        </li>
                                        <?php if (Permission::can('candidates', 'edit')): ?>
                                        <li>
                                            <a class="dropdown-item" 
                                               href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidate['candidate_code']) ?>">
                                                <i class="bx bx-edit me-2"></i> Edit
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="mailto:<?= htmlspecialchars($candidate['email']) ?>">
                                                <i class="bx bx-envelope me-2"></i> Send Email
                                            </a>
                                        </li>
                                        <?php if (!empty($candidate['phone'])): ?>
                                        <li>
                                            <a class="dropdown-item" href="tel:<?= htmlspecialchars($candidate['phone']) ?>">
                                                <i class="bx bx-phone me-2"></i> Call
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (Permission::can('candidates', 'assign')): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item assign-candidate" 
                                               href="#"
                                               data-code="<?= htmlspecialchars($candidate['candidate_code']) ?>"
                                               data-name="<?= htmlspecialchars($candidate['candidate_name']) ?>">
                                                <i class="bx bx-user-plus me-2"></i> Assign to Recruiter
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if (Permission::can('candidates', 'delete')): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger delete-candidate" 
                                               href="#"
                                               data-code="<?= htmlspecialchars($candidate['candidate_code']) ?>"
                                               data-name="<?= htmlspecialchars($candidate['candidate_name']) ?>">
                                                <i class="bx bx-trash me-2"></i> Delete
                                            </a>
                                        </li>
                                        <?php endif; ?>
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
                <?= $pagination->render('/panel/modules/candidates/list.php', $_GET) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Bulk Assign Modal -->
<?php if (Permission::can('candidates', 'assign')): ?>
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Candidates to Recruiter</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkAssignForm">
                    <div class="mb-3">
                        <label class="form-label">Select Recruiter</label>
                        <select name="recruiter" class="form-select" required>
                            <option value="">Choose recruiter...</option>
                            <?php foreach ($recruiters as $recruiter): ?>
                            <option value="<?= htmlspecialchars($recruiter['user_code']) ?>">
                                <?= htmlspecialchars($recruiter['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <span id="bulkAssignCount">0</span> candidate(s) will be assigned.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmBulkAssign">
                    <i class="bx bx-check"></i> Assign
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>