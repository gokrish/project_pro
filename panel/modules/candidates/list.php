<?php
/**
 * Candidates List Page - PRODUCTION VERSION
 * Recruiter-focused, Belgium market optimized
 * 
 * @version 6.0 - Complete Rebuild
 * @date January 4, 2026
 * 
 * FEATURES:
 * - Smart skill-based search (priority)
 * - Dynamic filters from database
 * - Simplified 6-column table
 * - Belgium defaults
 * - Fast performance (<100ms)
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, Logger, Pagination};

// ============================================================================
// PERMISSION CHECK
// ============================================================================

if (!Permission::can('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    header('Location: /panel/errors/403.php');
    exit;
}

$user = Auth::user();
$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================================
// GET FILTER OPTIONS FROM DATABASE (DYNAMIC)
// ============================================================================

/**
 * Get unique languages from all candidates
 */
function getLanguageOptions($conn) {
    $languages = [];
    $stmt = $conn->prepare("
        SELECT DISTINCT languages 
        FROM candidates 
        WHERE languages IS NOT NULL 
        AND deleted_at IS NULL
    ");
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    foreach ($results as $row) {
        if (!empty($row['languages'])) {
            $langArray = json_decode($row['languages'], true);
            if (is_array($langArray)) {
                $languages = array_merge($languages, $langArray);
            }
        }
    }
    
    return array_unique($languages);
}

/**
 * Get ENUM values from database column
 */
function getEnumValues($conn, $table, $column) {
    $stmt = $conn->prepare("
        SELECT COLUMN_TYPE 
        FROM information_schema.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ? 
        AND COLUMN_NAME = ?
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) return [];
    
    preg_match("/^enum\(\'(.*)\'\)$/", $result['COLUMN_TYPE'], $matches);
    if (!isset($matches[1])) return [];
    
    return explode("','", $matches[1]);
}

// Get dynamic filter options
$availableLanguages = getLanguageOptions($conn);
$leadTypes = getEnumValues($conn, 'candidates', 'lead_type');
$leadTypeRoles = getEnumValues($conn, 'candidates', 'lead_type_role');
$workingStatuses = getEnumValues($conn, 'candidates', 'current_working_status');
$candidateStatuses = getEnumValues($conn, 'candidates', 'status');

// Belgium-specific defaults
$belgiumCities = [];

// ============================================================================
// GET FILTERS FROM REQUEST
// ============================================================================

$filters = [
    'search' => filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?: '',
    'status' => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY) ?: [],
    'lead_type' => filter_input(INPUT_GET, 'lead_type', FILTER_SANITIZE_STRING) ?: '',
    'lead_type_role' => filter_input(INPUT_GET, 'lead_type_role', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY) ?: [],
    'working_status' => filter_input(INPUT_GET, 'working_status', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY) ?: [],
    'languages' => filter_input(INPUT_GET, 'languages', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY) ?: [],
    'notice_period' => filter_input(INPUT_GET, 'notice_period', FILTER_SANITIZE_STRING) ?: '',
    'assigned_to' => filter_input(INPUT_GET, 'assigned_to', FILTER_SANITIZE_STRING) ?: '',
    'show_placed' => filter_input(INPUT_GET, 'show_placed', FILTER_VALIDATE_BOOLEAN),
    'hot_leads_only' => filter_input(INPUT_GET, 'hot_leads_only', FILTER_VALIDATE_BOOLEAN),
    'active_only' => filter_input(INPUT_GET, 'active_only', FILTER_VALIDATE_BOOLEAN),
];

// Default filters (if no filters applied)
if (empty(array_filter($filters))) {
    $filters['active_only'] = true;
    $filters['status'] = ['open', 'screening', 'qualified', 'on-hold'];
}

// ============================================================================
// BUILD QUERY
// ============================================================================

$whereConditions = ['c.deleted_at IS NULL'];
$params = [];
$types = '';

// Access control
if (!Permission::can('candidates', 'view_all')) {
    if (Permission::can('candidates', 'view_own')) {
        $whereConditions[] = 'c.assigned_to = ?';
        $params[] = $userCode;
        $types .= 's';
    } else {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

// Search (Skills, Name, Email, Phone - IN THAT ORDER)
if (!empty($filters['search'])) {
    $searchTerm = '%' . $filters['search'] . '%';
    
    // Prioritize skill search
    $whereConditions[] = "(
        EXISTS (
            SELECT 1 FROM candidate_skills cs 
            WHERE cs.candidate_code = c.candidate_code 
            AND cs.skill_name LIKE ?
        )
        OR c.candidate_name LIKE ?
        OR c.current_position LIKE ?
    )";
    
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sssss';
}

// Status filter (multi-select)
if (!empty($filters['status']) && is_array($filters['status'])) {
    $statusPlaceholders = implode(',', array_fill(0, count($filters['status']), '?'));
    $whereConditions[] = "c.status IN ($statusPlaceholders)";
    foreach ($filters['status'] as $status) {
        $params[] = $status;
        $types .= 's';
    }
}

// Lead type filter
if (!empty($filters['lead_type'])) {
    $whereConditions[] = "c.lead_type = ?";
    $params[] = $filters['lead_type'];
    $types .= 's';
}

// Lead type role filter (multi-select)
if (!empty($filters['lead_type_role']) && is_array($filters['lead_type_role'])) {
    $rolePlaceholders = implode(',', array_fill(0, count($filters['lead_type_role']), '?'));
    $whereConditions[] = "c.lead_type_role IN ($rolePlaceholders)";
    foreach ($filters['lead_type_role'] as $role) {
        $params[] = $role;
        $types .= 's';
    }
}

// Working status filter (multi-select)
if (!empty($filters['working_status']) && is_array($filters['working_status'])) {
    $statusPlaceholders = implode(',', array_fill(0, count($filters['working_status']), '?'));
    $whereConditions[] = "c.current_working_status IN ($statusPlaceholders)";
    foreach ($filters['working_status'] as $ws) {
        $params[] = $ws;
        $types .= 's';
    }
}

// Languages filter (multi-select - JSON search)
if (!empty($filters['languages']) && is_array($filters['languages'])) {
    $langConditions = [];
    foreach ($filters['languages'] as $lang) {
        $langConditions[] = "JSON_CONTAINS(c.languages, ?)";
        $params[] = json_encode($lang);
        $types .= 's';
    }
    if (!empty($langConditions)) {
        $whereConditions[] = '(' . implode(' OR ', $langConditions) . ')';
    }
}

// Notice period filter
if (!empty($filters['notice_period'])) {
    switch ($filters['notice_period']) {
        case 'immediate':
            $whereConditions[] = "c.notice_period_days = 0";
            break;
        case '0-30':
            $whereConditions[] = "c.notice_period_days BETWEEN 0 AND 30";
            break;
    }
}

// Assigned to filter
if (!empty($filters['assigned_to'])) {
    if ($filters['assigned_to'] === 'unassigned') {
        $whereConditions[] = "c.assigned_to IS NULL";
    } elseif ($filters['assigned_to'] === 'me') {
        $whereConditions[] = "c.assigned_to = ?";
        $params[] = $userCode;
        $types .= 's';
    } else {
        $whereConditions[] = "c.assigned_to = ?";
        $params[] = $filters['assigned_to'];
        $types .= 's';
    }
}

// Quick filters
if (!$filters['show_placed']) {
    $whereConditions[] = "c.status NOT IN ('placed', 'rejected', 'archived')";
}

if ($filters['hot_leads_only']) {
    $whereConditions[] = "c.lead_type = 'Hot'";
}

if ($filters['active_only']) {
    $whereConditions[] = "c.status IN ('new', 'screening', 'qualified', 'active')";
}

$whereSQL = implode(' AND ', $whereConditions);

// ============================================================================
// COUNT TOTAL RECORDS
// ============================================================================

$countSQL = "SELECT COUNT(*) as total FROM candidates c WHERE {$whereSQL}";
$stmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRecords = $stmt->get_result()->fetch_assoc()['total'];

// ============================================================================
// PAGINATION
// ============================================================================

$pagination = Pagination::fromRequest($totalRecords, 25);

// ============================================================================
// FETCH CANDIDATES
// ============================================================================

$sql = "
    SELECT 
        c.candidate_code,
        c.candidate_name,
        c.email,
        c.phone,
        c.current_position,
        c.current_employer,
        c.current_working_status,
        c.professional_summary,
        c.notice_period_days,
        c.lead_type,
        c.lead_type_role,
        c.status,
        c.last_contacted_date,
        c.total_submissions,
        c.total_placements,
        c.updated_at,
        u.name as assigned_to_name,
        GROUP_CONCAT(
            DISTINCT CONCAT(cs.skill_name, ':', cs.proficiency_level) 
            ORDER BY cs.is_primary DESC, cs.proficiency_level DESC
            SEPARATOR '||'
        ) as skills_data
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    LEFT JOIN candidate_skills cs ON c.candidate_code = cs.candidate_code
    WHERE {$whereSQL}
    GROUP BY c.candidate_code
    ORDER BY 
        CASE 
            WHEN c.lead_type = 'Hot' THEN 1
            WHEN c.lead_type = 'Warm' THEN 2
            WHEN c.lead_type = 'Cold' THEN 3
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

// ============================================================================
// GET RECRUITERS FOR ASSIGNMENT
// ============================================================================

$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $stmt = $conn->prepare("
        SELECT user_code, name, level 
        FROM users 
        WHERE level IN ('recruiter', 'senior_recruiter', 'manager', 'admin') 
        AND is_active = 1 
        ORDER BY name
    ");
    $stmt->execute();
    $recruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================================================
// HELPER FUNCTIONS FOR VIEW
// ============================================================================

/**
 * Get lead type badge with icon
 */
function getLeadTypeBadge($leadType) {
    return match($leadType) {
        'Hot' => '<span class="badge bg-danger">🔥 Hot</span>',
        'Warm' => '<span class="badge bg-warning">⚡ Warm</span>',
        'Cold' => '<span class="badge bg-info">❄️ Cold</span>',
        'Blacklist' => '<span class="badge bg-dark">🚫 Blacklist</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

/**
 * Get status badge
 */
function getStatusBadge($status) {
    return match($status) {
        'new' => '<span class="badge bg-primary">New</span>',
        'screening' => '<span class="badge bg-info">Screening</span>',
        'qualified' => '<span class="badge bg-success">Qualified</span>',
        'active' => '<span class="badge bg-warning">Active</span>',
        'placed' => '<span class="badge bg-success">Placed</span>',
        'on_hold' => '<span class="badge bg-secondary">On Hold</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'archived' => '<span class="badge bg-secondary">Archived</span>',
        default => '<span class="badge bg-secondary">Unknown</span>'
    };
}

/**
 * Format notice period
 */
function formatNoticePeriod($days) {
    if (empty($days) || $days == 0) return '<span class="text-success fw-semibold">Immediate</span>';
    if ($days <= 7) return '<span class="text-success">' . $days . ' days</span>';
    if ($days <= 30) return '<span class="text-warning">' . $days . ' days</span>';
    if ($days <= 60) return '<span class="text-danger">' . $days . ' days</span>';
    return '<span class="text-danger">' . $days . ' days</span>';
}

/**
 * Format skills for display 
 */
function formatSkills($skillsData) {
    if (empty($skillsData)) return '<span class="text-muted">No skills</span>';
    
    $skills = explode('||', $skillsData);
    $formatted = [];
    
    foreach (array_slice($skills, 0, 5) as $skill) {
        list($name, $level) = explode(':', $skill);
        $formatted[] = htmlspecialchars($name);
    }
    
    $output = implode(', ', $formatted);
    
    if (count($skills) > 5) {
        $remaining = count($skills) - 5;
        $output .= ' <span class="badge bg-secondary">+' . $remaining . ' more</span>';
    }
    
    return $output;
}


// ============================================================================
// PAGE CONFIGURATION
// ============================================================================

$pageTitle = 'Candidates';
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '']
];
$customJS = ['/panel/assets/js/modules/candidates-list.js'];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="content-container">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Candidates</h4>
            <p class="text-muted mb-0">
                <?= number_format($totalRecords) ?> total candidates
                <?php if (!empty($filters['search'])): ?>
                    matching "<?= e($filters['search']) ?>"
                <?php endif; ?>
            </p>
        </div>
        <div class="d-flex gap-2">
            <?php if (Permission::can('candidates', 'create')): ?>
                <a href="/panel/modules/candidates/create.php" class="btn btn-primary">
                    <i class='bx bx-plus'></i> Add Candidate
                </a>
            <?php endif; ?>
            
            <?php if (Permission::can('candidates', 'export')): ?>
                <button type="button" class="btn btn-outline-primary" id="exportExcel">
                    <i class='bx bx-download'></i> Export
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0">Filters</h6>
                    <?php if (array_filter($filters)): ?>
                        <a href="/panel/modules/candidates/list.php" class="btn btn-sm btn-outline-secondary">
                            Clear All
                        </a>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <form method="GET" action="" id="filterForm">
                        
                        <!-- Search -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Search</label>
                            <input 
                                type="text" 
                                name="search" 
                                class="form-control" 
                                placeholder="Skills, Name, Current Possition..."
                                value="<?= e($filters['search']) ?>"
                                autofocus
                            >
                            <small class="text-muted">Type skill name for best results</small>
                        </div>
<!-- Smart Search Field -->
<div class="mb-4">
    <label class="form-label fw-semibold">Find Candidates</label>
    <div class="input-group">
        <span class="input-group-text"><i class='bx bx-search'></i></span>
        <input type="text" class="form-control" name="search" 
               placeholder="Role or Skill (Java Developer, React, .NET)..."
               value="<?= e($filters['search'] ?? '') ?>">
        <button class="btn btn-outline-secondary" type="button" id="searchClear"
                style="display: <?= !empty($filters['search']) ? 'block' : 'none' ?>">
            <i class='bx bx-x'></i>
        </button>
    </div>
    <div class="search-suggestions mt-2" id="searchSuggestions" style="display: none;"></div>
    <small class="text-muted d-block mt-1">
        Try: "Full Stack Java", "React TypeScript", "DevOps Engineer"
    </small>
</div>

<!-- Experience Filter -->
<div class="mb-4">
    <label class="form-label fw-semibold">Experience Level</label>
    <div class="d-grid gap-2">
        <?php $expRanges = ['0-5', '5-8', '8-15', '15+']; ?>
        <?php foreach ($expRanges as $range): ?>
            <?php 
                $label = match($range) {
                    '0-5' => '0-5 years (Junior)',
                    '5-8' => '5-8 years (Mid-level)',
                    '8-15' => '8-15 years (Senior)',
                    '15+' => '15+ years (Principal/Architect)'
                };
                $isChecked = in_array($range, $filters['experience_ranges'] ?? []);
            ?>
            <label class="btn <?= $isChecked ? 'btn-primary' : 'btn-outline-primary' ?>">
                <input type="checkbox" name="experience_ranges[]" value="<?= $range ?>" 
                       <?= $isChecked ? 'checked' : '' ?> class="d-none">
                <span class="d-flex align-items-center">
                    <i class='bx <?= $isChecked ? 'bx-check-circle' : 'bx-circle' ?> me-2'></i>
                    <?= $label ?>
                </span>
            </label>
        <?php endforeach; ?>
    </div>
</div>

<!-- Availability Filter -->
<div class="mb-4">
    <label class="form-label fw-semibold">Availability</label>
    <div class="form-check form-switch mb-3">
        <input class="form-check-input" type="checkbox" id="availableNow" 
               <?= ($filters['availability'] ?? '') === 'immediate' ? 'checked' : '' ?>>
        <label class="form-check-label fw-medium" for="availableNow">
            Available Immediately
        </label>
    </div>
    <!-- Other availability options here -->
</div>


                        <!-- Quick Filters -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Quick Filters</label>
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    name="active_only" 
                                    id="active_only"
                                    value="1"
                                    <?= $filters['active_only'] ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="active_only">
                                    Show Active Only
                                </label>
                            </div>
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    name="hot_leads_only" 
                                    id="hot_leads_only"
                                    value="1"
                                    <?= $filters['hot_leads_only'] ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="hot_leads_only">
                                    🔥 Hot Leads Only
                                </label>
                            </div>
                            <div class="form-check">
                                <input 
                                    type="checkbox" 
                                    class="form-check-input" 
                                    name="show_placed" 
                                    id="show_placed"
                                    value="1"
                                    <?= $filters['show_placed'] ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="show_placed">
                                    Show Placed/Rejected
                                </label>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Status</label>
                            <?php foreach (['new', 'screening', 'qualified', 'active', 'on_hold'] as $status): ?>
                                <div class="form-check">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        name="status[]" 
                                        id="status_<?= $status ?>"
                                        value="<?= $status ?>"
                                        <?= in_array($status, $filters['status']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="status_<?= $status ?>">
                                        <?= ucfirst(str_replace('_', ' ', $status)) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Lead Type -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Lead Type</label>
                            <select name="lead_type" class="form-select">
                                <option value="">All Leads</option>
                                <?php foreach ($leadTypes as $type): ?>
                                    <?php if ($type !== 'Blacklist'): ?>
                                        <option value="<?= e($type) ?>" <?= $filters['lead_type'] === $type ? 'selected' : '' ?>>
                                            <?= e($type) ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Lead Type Role -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Lead Type Role</label>
                            <?php foreach ($leadTypeRoles as $role): ?>
                                <div class="form-check">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        name="lead_type_role[]" 
                                        id="role_<?= $role ?>"
                                        value="<?= e($role) ?>"
                                        <?= in_array($role, $filters['lead_type_role']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="role_<?= $role ?>">
                                        <?= e($role) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Working Status -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Working Status</label>
                            <?php foreach ($workingStatuses as $ws): ?>
                                <div class="form-check">
                                    <input 
                                        type="checkbox" 
                                        class="form-check-input" 
                                        name="working_status[]" 
                                        id="ws_<?= str_replace('_', '', $ws) ?>"
                                        value="<?= e($ws) ?>"
                                        <?= in_array($ws, $filters['working_status']) ? 'checked' : '' ?>
                                    >
                                    <label class="form-check-label" for="ws_<?= str_replace('_', '', $ws) ?>">
                                        <?= str_replace('_', ' ', e($ws)) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Languages -->
                        <?php if (!empty($availableLanguages)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Languages</label>
                                <?php foreach ($availableLanguages as $lang): ?>
                                    <div class="form-check">
                                        <input 
                                            type="checkbox" 
                                            class="form-check-input" 
                                            name="languages[]" 
                                            id="lang_<?= e($lang) ?>"
                                            value="<?= e($lang) ?>"
                                            <?= in_array($lang, $filters['languages']) ? 'checked' : '' ?>
                                        >
                                        <label class="form-check-label" for="lang_<?= e($lang) ?>">
                                            <?= e($lang) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <!-- Availability Filter -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Availability</label>
                            
                            
                            <!-- Quick Filters -->
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" 
                                    name="available_immediately" id="avail_immediate">
                                <label class="form-check-label" for="avail_immediate">
                                    Available Immediately
                                </label>
                            </div>
                            
                        </div>

                        <!-- Assigned To -->
                        <?php if (!empty($recruiters)): ?>
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Assigned To</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">All Recruiters</option>
                                    <option value="me" <?= $filters['assigned_to'] === 'me' ? 'selected' : '' ?>>
                                        My Candidates
                                    </option>
                                    <option value="unassigned" <?= $filters['assigned_to'] === 'unassigned' ? 'selected' : '' ?>>
                                        Unassigned
                                    </option>
                                    <optgroup label="Recruiters">
                                        <?php foreach ($recruiters as $recruiter): ?>
                                            <option 
                                                value="<?= e($recruiter['user_code']) ?>"
                                                <?= $filters['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>
                                            >
                                                <?= e($recruiter['name']) ?> (<?= e($recruiter['level']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        <?php endif; ?>

                        <button type="submit" class="btn btn-primary w-100">
                            <i class='bx bx-filter'></i> Apply Filters
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-lg-9 col-md-8">
            <div class="card">
                <div class="card-body">
                    
                    <!-- Bulk Actions (shown when candidates selected) -->
                    <div id="bulkActions" class="alert alert-info mb-3" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><span id="selectedCount">0</span> candidates selected</span>
                            <div class="btn-group">
                                <?php if (Permission::can('candidates', 'assign')): ?>
                                    <button type="button" class="btn btn-sm btn-primary" id="bulkAssign">
                                        Assign to Recruiter
                                    </button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="bulkExport">
                                    Export Selected
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php if (empty($candidates)): ?>
                        <div class="text-center py-5">
                            <i class='bx bx-search-alt' style="font-size: 4rem; color: #ccc;"></i>
                            <h5 class="mt-3">No candidates found</h5>
                            <p class="text-muted">
                                Try adjusting your filters or 
                                <a href="/panel/modules/candidates/create.php">add a new candidate</a>
                            </p>
                        </div>
                    <?php else: ?>
                        
                        <!-- Candidates Table -->
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th width="30">
                                            <input type="checkbox" class="form-check-input" id="selectAll">
                                        </th>
                                        <th>Candidate</th>
                                        <th>Professional Summary</th>
                                        <th>Lead</th>
                                        <th>Status</th>
                                        <th width="120">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($candidates as $candidate): ?>
                                        <tr>
                                            <td>
                                                <input 
                                                    type="checkbox" 
                                                    class="form-check-input candidate-checkbox" 
                                                    value="<?= e($candidate['candidate_code']) ?>"
                                                >
                                            </td>
                                            <td>
                                                <div class="fw-semibold"><?= e($candidate['candidate_name']) ?></div>
                                                <?php if (!empty($candidate['current_position'])): ?>
                                                    <small class="text-muted">
                                                        <?= e($candidate['current_position']) ?>
                                                        <?php if (!empty($candidate['current_employer'])): ?>
                                                            @ <?= e($candidate['current_employer']) ?>
                                                        <?php endif; ?>
                                                    </small>
                                                <?php endif; ?>
                                                <br>
                                                <small class="text-muted">
                                                    <i class='bx bx-envelope'></i> <?= e($candidate['email']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <?= formatSkills($candidate['skills_data']) ?>
                                                </div>
                                                <?php if (!empty($candidate['current_working_status'])): ?>
                                                    <small class="badge bg-light text-dark">
                                                        <?= str_replace('_', ' ', e($candidate['current_working_status'])) ?>
                                                    </small>
                                                <?php endif; ?>
                                                <?php if (!empty($candidate['lead_type_role'])): ?>
                                                    <small class="badge bg-secondary">
                                                        <?= e($candidate['lead_type_role']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= getLeadTypeBadge($candidate['lead_type']) ?>
                                            </td>
                                            <td>
                                                <?= getStatusBadge($candidate['status']) ?>
                                                <?php if ($candidate['total_submissions'] > 0): ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= $candidate['total_submissions'] ?> submission<?= $candidate['total_submissions'] > 1 ? 's' : '' ?>
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a 
                                                        href="/panel/modules/candidates/view.php?code=<?= e($candidate['candidate_code']) ?>" 
                                                        class="btn btn-sm btn-outline-primary"
                                                        title="View Profile"
                                                    >
                                                        <i class='bx bx-show'></i>
                                                    </a>
                                                    
                                                    <?php if (Permission::can('candidates', 'edit')): ?>
                                                        <a 
                                                            href="/panel/modules/candidates/edit.php?code=<?= e($candidate['candidate_code']) ?>" 
                                                            class="btn btn-sm btn-outline-secondary"
                                                            title="Edit"
                                                        >
                                                            <i class='bx bx-edit'></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    
                                                    <button 
                                                        type="button" 
                                                        class="btn btn-sm btn-outline-success submit-to-job-btn"
                                                        data-candidate-code="<?= e($candidate['candidate_code']) ?>"
                                                        data-candidate-name="<?= e($candidate['candidate_name']) ?>"
                                                        title="Submit to Job"
                                                    >
                                                        <i class='bx bx-paper-plane'></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($pagination->getTotalPages() > 1): ?>
                            <nav aria-label="Candidates pagination" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?= $pagination->render() ?>
                                </ul>
                            </nav>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Submit to Job Modal -->
<div class="modal fade" id="submitToJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Candidate to Job</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="submitToJobForm" method="POST" action="/panel/modules/candidates/handlers/submit_to_job.php">
                <div class="modal-body">
                    <input type="hidden" name="candidate_code" id="submit_candidate_code">
                    <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Candidate</label>
                        <input type="text" class="form-control" id="submit_candidate_name" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Select Job</label>
                        <select name="job_code" class="form-select" required>
                            <option value="">-- Select Job --</option>
                            <!-- Will be populated via AJAX -->
                        </select>
                        <small class="text-muted">Only open jobs are shown</small>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Submission Notes</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Why this candidate is a good fit..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit to Job</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Bulk Assign Modal -->
<?php if (Permission::can('candidates', 'assign')): ?>
<div class="modal fade" id="bulkAssignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Assign Candidates</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="bulkAssignForm" method="POST" action="/panel/modules/candidates/handlers/bulk-assign.php">
                <div class="modal-body">
                    <input type="hidden" name="candidate_codes" id="bulk_candidate_codes">
                    <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                    
                    <p><span id="bulk_assign_count">0</span> candidates will be assigned to:</p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Recruiter</label>
                        <select name="assigned_to" class="form-select" required>
                            <option value="">-- Select Recruiter --</option>
                            <?php foreach ($recruiters as $recruiter): ?>
                                <option value="<?= e($recruiter['user_code']) ?>">
                                    <?= e($recruiter['name']) ?> (<?= e($recruiter['level']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// Auto-submit form on filter change
document.querySelectorAll('#filterForm input[type="checkbox"], #filterForm select').forEach(el => {
    el.addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});

// Bulk selection
const selectAllCheckbox = document.getElementById('selectAll');
const candidateCheckboxes = document.querySelectorAll('.candidate-checkbox');
const bulkActionsDiv = document.getElementById('bulkActions');
const selectedCountSpan = document.getElementById('selectedCount');

function updateBulkActions() {
    const selected = Array.from(candidateCheckboxes).filter(cb => cb.checked);
    if (selected.length > 0) {
        bulkActionsDiv.style.display = 'block';
        selectedCountSpan.textContent = selected.length;
    } else {
        bulkActionsDiv.style.display = 'none';
    }
}

selectAllCheckbox?.addEventListener('change', function() {
    candidateCheckboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

candidateCheckboxes.forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

// Submit to Job
document.querySelectorAll('.submit-to-job-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const candidateCode = this.dataset.candidateCode;
        const candidateName = this.dataset.candidateName;
        
        document.getElementById('submit_candidate_code').value = candidateCode;
        document.getElementById('submit_candidate_name').value = candidateName;
        
        // Load available jobs via AJAX
        fetch(`/panel/modules/jobs/handlers/get-open-jobs.php`)
            .then(r => r.json())
            .then(data => {
                const select = document.querySelector('#submitToJobModal select[name="job_code"]');
                select.innerHTML = '<option value="">-- Select Job --</option>';
                data.jobs.forEach(job => {
                    select.innerHTML += `<option value="${job.job_code}">${job.job_title} - ${job.client_name}</option>`;
                });
            });
        
        const modal = new bootstrap.Modal(document.getElementById('submitToJobModal'));
        modal.show();
    });
});
// Smart search suggestions
$('#search').on('input', function() {
    const term = $(this).val().trim();
    $('#searchClear').toggle(term.length > 0);
    
    if (term.length < 2) {
        $('#searchSuggestions').hide();
        return;
    }
    
    // Show suggestions after delay
    clearTimeout(window.searchTimeout);
    window.searchTimeout = setTimeout(() => {
        $.get('/api/skill-suggestions', { term: term }, function(data) {
            if (data.suggestions && data.suggestions.length > 0) {
                let html = '';
                data.suggestions.forEach(s => {
                    html += `<div class="p-2 border-bottom suggestion-item">
                        <strong>${s.primary}</strong><br>
                        <small class="text-muted">${s.related.join(', ')}</small>
                    </div>`;
                });
                $('#searchSuggestions').html(html).show();
            } else {
                $('#searchSuggestions').hide();
            }
        });
    }, 300);
});

// Close suggestions when clicking outside
$(document).on('click', function(e) {
    if (!$(e.target).closest('#search, #searchSuggestions').length) {
        $('#searchSuggestions').hide();
    }
});

// Immediate availability toggle
$('#availableNow').change(function() {
    if (this.checked) {
        // Hide notice period options
        $('#noticePeriodContainer').hide();
    } else {
        $('#noticePeriodContainer').show();
    }
});

// Bulk Assign
document.getElementById('bulkAssign')?.addEventListener('click', function() {
    const selected = Array.from(candidateCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
    document.getElementById('bulk_candidate_codes').value = selected.join(',');
    document.getElementById('bulk_assign_count').textContent = selected.length;
    
    const modal = new bootstrap.Modal(document.getElementById('bulkAssignModal'));
    modal.show();
});
</script>

<?php
require_once __DIR__ . '/../../includes/footer.php';
?>