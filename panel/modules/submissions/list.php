<?php
/**
 * Submissions List
 * File: panel/modules/submissions/list.php
 */

if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get filters
$filters = [
    'status' => input('status', ''),
    'client_code' => input('client_code', ''),
    'job_code' => input('job_code', ''),
    'submitted_by' => input('submitted_by', ''),
    'search' => input('search', '')
];

// Build WHERE clause
$whereConditions = ['s.deleted_at IS NULL'];
$params = [];
$types = '';

if (!empty($filters['status'])) {
    $whereConditions[] = "s.status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['client_code'])) {
    $whereConditions[] = "s.client_code = ?";
    $params[] = $filters['client_code'];
    $types .= 's';
}

if (!empty($filters['job_code'])) {
    $whereConditions[] = "s.job_code = ?";
    $params[] = $filters['job_code'];
    $types .= 's';
}

if (!empty($filters['submitted_by'])) {
    $whereConditions[] = "s.submitted_by = ?";
    $params[] = $filters['submitted_by'];
    $types .= 's';
}

if (!empty($filters['search'])) {
    $whereConditions[] = "(c.candidate_name LIKE ? OR j.title LIKE ? OR cl.company_name LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm]);
    $types .= 'sss';
}

$whereClause = implode(' AND ', $whereConditions);

// Get statistics
$statsQuery = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN s.status = 'draft' THEN 1 ELSE 0 END) as draft,
        SUM(CASE WHEN s.status = 'pending_review' THEN 1 ELSE 0 END) as pending_review,
        SUM(CASE WHEN s.status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN s.status = 'submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN s.client_response = 'interested' THEN 1 ELSE 0 END) as interested,
        SUM(CASE WHEN s.converted_to_application = 1 THEN 1 ELSE 0 END) as converted
    FROM candidate_submissions s
    WHERE s.deleted_at IS NULL
";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get submissions
$query = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        j.title as job_title,
        cl.company_name as client_name,
        u.name as submitted_by_name
    FROM candidate_submissions s
    LEFT JOIN candidates c ON s.candidate_code = c.candidate_code
    LEFT JOIN jobs j ON s.job_code = j.job_code
    LEFT JOIN clients cl ON s.client_code = cl.client_code
    LEFT JOIN users u ON s.submitted_by = u.user_code
    WHERE {$whereClause}
    ORDER BY s.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$submissions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get clients for filter
$clientsQuery = "SELECT client_code, company_name FROM clients WHERE status = 'active' ORDER BY company_name";
$clients = $conn->query($clientsQuery)->fetch_all(MYSQLI_ASSOC);

// Get recruiters for filter
$recruitersQuery = "
    SELECT DISTINCT u.user_code, u.name 
    FROM users u
    INNER JOIN candidate_submissions s ON u.user_code = s.submitted_by
    WHERE u.is_active = 1
    ORDER BY u.name
";
$recruiters = $conn->query($recruitersQuery)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-send text-primary me-2"></i>Candidate Submissions
            </h4>
            <p class="text-muted mb-0">Internal candidate → client submissions</p>
        </div>
        <div>
            <?php if (Permission::can('submissions', 'create')): ?>
            <a href="?action=create" class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> New Submission
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Total</h6>
                    <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Draft</h6>
                    <h3 class="mb-0"><?= number_format($stats['draft']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Pending Review</h6>
                    <h3 class="mb-0 text-warning"><?= number_format($stats['pending_review']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Submitted</h6>
                    <h3 class="mb-0 text-info"><?= number_format($stats['submitted']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Interested</h6>
                    <h3 class="mb-0 text-success"><?= number_format($stats['interested']) ?></h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Converted</h6>
                    <h3 class="mb-0 text-primary"><?= number_format($stats['converted']) ?></h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between">
            <h5 class="mb-0"><i class="bx bx-filter-alt me-2"></i>Filters</h5>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="toggleFilters">
                <i class="bx bx-chevron-down"></i>
            </button>
        </div>
        <div class="card-body" id="filterPanel" style="<?= !empty(array_filter($filters)) ? '' : 'display:none;' ?>">
            <form method="GET">
                <input type="hidden" name="action" value="list">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="draft" <?= $filters['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="pending_review" <?= $filters['status'] === 'pending_review' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="approved" <?= $filters['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="submitted" <?= $filters['status'] === 'submitted' ? 'selected' : '' ?>>Submitted</option>
                            <option value="accepted" <?= $filters['status'] === 'accepted' ? 'selected' : '' ?>>Accepted</option>
                            <option value="rejected" <?= $filters['status'] === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Client</label>
                        <select name="client_code" class="form-select">
                            <option value="">All Clients</option>
                            <?php foreach ($clients as $client): ?>
                            <option value="<?= htmlspecialchars($client['client_code']) ?>"
                                    <?= $filters['client_code'] === $client['client_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['company_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Recruiter</label>
                        <select name="submitted_by" class="form-select">
                            <option value="">All Recruiters</option>
                            <?php foreach ($recruiters as $recruiter): ?>
                            <option value="<?= htmlspecialchars($recruiter['user_code']) ?>"
                                    <?= $filters['submitted_by'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($recruiter['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" 
                               placeholder="Candidate, job, client..." 
                               value="<?= htmlspecialchars($filters['search']) ?>">
                    </div>
                </div>
                
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-search me-1"></i> Apply
                    </button>
                    <a href="?action=list" class="btn btn-label-secondary">
                        <i class="bx bx-reset me-1"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Submissions Table -->
    <div class="card">
        <div class="card-body">
            <?php if (empty($submissions)): ?>
                <div class="text-center py-5">
                    <i class="bx bx-inbox" style="font-size: 64px; color: #ddd;"></i>
                    <h5 class="mt-3">No Submissions Found</h5>
                    <p class="text-muted">
                        <?php if (!empty(array_filter($filters))): ?>
                            No submissions match your search criteria.
                        <?php else: ?>
                            Start submitting candidates to clients.
                        <?php endif; ?>
                    </p>
                    <?php if (Permission::can('submissions', 'create')): ?>
                    <a href="?action=create" class="btn btn-primary">
                        <i class="bx bx-plus me-1"></i> Create First Submission
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="submissionsTable">
                        <thead>
                            <tr>
                                <th>Submission Code</th>
                                <th>Candidate</th>
                                <th>Job</th>
                                <th>Client</th>
                                <th>Submitted By</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $sub): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($sub['submission_code']) ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?= htmlspecialchars($sub['candidate_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($sub['candidate_email']) ?></small>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($sub['job_title']) ?></td>
                                <td><?= htmlspecialchars($sub['client_name']) ?></td>
                                <td><?= htmlspecialchars($sub['submitted_by_name']) ?></td>
                                <td>
                                    <?php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'pending_review' => 'warning',
                                        'approved' => 'info',
                                        'submitted' => 'primary',
                                        'accepted' => 'success',
                                        'rejected' => 'danger'
                                    ];
                                    $color = $statusColors[$sub['status']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?= $color ?>">
                                        <?= ucwords(str_replace('_', ' ', $sub['status'])) ?>
                                    </span>
                                </td>
                                <td><?= date('M d, Y', strtotime($sub['created_at'])) ?></td>
                                <td>
                                    <a href="?action=view&code=<?= urlencode($sub['submission_code']) ?>" 
                                       class="btn btn-sm btn-info">
                                        <i class="bx bx-show"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    $('#submissionsTable').DataTable({
        pageLength: 25,
        order: [[6, 'desc']]
    });
    
    $('#toggleFilters').on('click', function() {
        $('#filterPanel').slideToggle();
        $(this).find('i').toggleClass('bx-chevron-down bx-chevron-up');
    });
});
</script>