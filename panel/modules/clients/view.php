<?php
/**
 * Client Detail View
 * Shows complete client information with jobs and placements
 */
if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
}

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get client code
$client_code = input('code');
if (!$client_code) {
    redirectBack('Client not found');
}

// Check permission
$canViewAll = Permission::can('clients', 'view_all');
$canViewOwn = Permission::can('clients', 'view_own');

if (!$canViewAll && !$canViewOwn) {
    header('Location: /panel/errors/403.php');
    exit;
}

// Get client details
$sql = "
    SELECT 
        c.*,
        u.name as account_manager_name,
        u.email as account_manager_email,
        creator.name as created_by_name
    FROM clients c
    LEFT JOIN users u ON c.account_manager = u.user_code
    LEFT JOIN users creator ON c.created_by = creator.user_code
    WHERE c.client_code = ?
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $client_code);
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    redirectBack('Client not found');
}

// Permission check for own clients
if (!$canViewAll && $canViewOwn) {
    if ($client['account_manager'] !== $user['user_code']) {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

// Get client jobs with submission counts
$jobsSQL = "
    SELECT 
        j.*,
        (SELECT COUNT(*) FROM submissions WHERE job_code = j.job_code) as total_submissions,
        (SELECT COUNT(*) FROM submissions WHERE job_code = j.job_code AND client_status = 'placed') as total_placements,
        (SELECT COUNT(*) FROM submissions WHERE job_code = j.job_code AND internal_status = 'pending') as pending_submissions
    FROM jobs j
    WHERE j.client_code = ?
    AND j.deleted_at IS NULL
    ORDER BY 
        CASE j.status
            WHEN 'open' THEN 1
            WHEN 'filling' THEN 2
            WHEN 'filled' THEN 3
            WHEN 'closed' THEN 4
            ELSE 5
        END,
        j.created_at DESC
";

$stmt = $conn->prepare($jobsSQL);
$stmt->bind_param("s", $client_code);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get active placements (candidates currently working)
$placementsSQL = "
    SELECT 
        s.*,
        c.candidate_name,
        c.email as candidate_email,
        c.phone as candidate_phone,
        c.current_location,
        j.job_title,
        j.job_code,
        DATEDIFF(NOW(), s.placement_date) as days_placed
    FROM submissions s
    JOIN candidates c ON s.candidate_code = c.candidate_code
    JOIN jobs j ON s.job_code = j.job_code
    WHERE j.client_code = ?
    AND s.client_status = 'placed'
    ORDER BY s.placement_date DESC
";

$stmt = $conn->prepare($placementsSQL);
$stmt->bind_param("s", $client_code);
$stmt->execute();
$placements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get client notes
$notesSQL = "
    SELECT n.*, u.name as created_by_name
    FROM notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.entity_type = 'client' AND n.entity_code = ?
    ORDER BY n.created_at DESC
    LIMIT 10
";

$stmt = $conn->prepare($notesSQL);
$stmt->bind_param("s", $client_code);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate statistics
$activeJobs = array_filter($jobs, fn($j) => in_array($j['status'], ['open', 'filling']));
$totalPlacements = array_sum(array_column($jobs, 'total_placements'));

// Page config
$pageTitle = $client['company_name'];
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => '/panel/'],
    ['title' => 'Clients', 'url' => '/panel/modules/clients/?action=list'],
    ['title' => $client['company_name'], 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.info-card {
    border-left: 4px solid #0d6efd;
}
.job-row {
    transition: background-color 0.2s;
    cursor: pointer;
}
.job-row:hover {
    background-color: #f8f9fa;
}
.placement-card {
    border-left: 3px solid #198754;
    background: #f8f9fa;
}
</style>

<!-- Header Section -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card info-card">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3 class="mb-2">
                            <i class="bx bx-building text-primary"></i>
                            <?= escape($client['company_name']) ?>
                        </h3>
                        <p class="text-muted mb-0">
                            <span class="badge bg-<?= $client['status'] === 'active' ? 'success' : 'secondary' ?> me-2">
                                <?= ucfirst($client['status']) ?>
                            </span>
                            <strong>Code:</strong> <?= escape($client['client_code']) ?> |
                            <strong>Account Manager:</strong> <?= escape($client['account_manager_name'] ?: 'Unassigned') ?> |
                            <strong>Added:</strong> <?= date('M d, Y', strtotime($client['created_at'])) ?>
                        </p>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if (Permission::can('clients', 'edit')): ?>
                            <a href="?action=edit&code=<?= escape($client_code) ?>" class="btn btn-primary me-2">
                                <i class="bx bx-edit"></i> Edit Client
                            </a>
                        <?php endif; ?>
                        <a href="../jobs/?action=create&client_code=<?= escape($client_code) ?>" class="btn btn-success">
                            <i class="bx bx-plus"></i> Create Job
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="bx bx-briefcase display-4 text-info mb-2"></i>
                <h3 class="mb-0"><?= count($activeJobs) ?></h3>
                <small class="text-muted">Active Jobs</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="bx bx-file display-4 text-primary mb-2"></i>
                <h3 class="mb-0"><?= count($jobs) ?></h3>
                <small class="text-muted">Total Jobs</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="bx bx-trophy display-4 text-success mb-2"></i>
                <h3 class="mb-0"><?= $totalPlacements ?></h3>
                <small class="text-muted">Total Placements</small>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="bx bx-user-check display-4 text-warning mb-2"></i>
                <h3 class="mb-0"><?= count($placements) ?></h3>
                <small class="text-muted">Active Placements</small>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="row">
    <!-- Left Column: Jobs & Placements -->
    <div class="col-md-8">
        <!-- Jobs List -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-briefcase"></i> Jobs 
                    <span class="badge bg-secondary"><?= count($jobs) ?></span>
                </h5>
                <a href="../jobs/?action=create&client_code=<?= escape($client_code) ?>" class="btn btn-sm btn-success">
                    <i class="bx bx-plus"></i> New Job
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($jobs)): ?>
                    <div class="text-center py-5">
                        <i class="bx bx-briefcase display-1 text-muted"></i>
                        <p class="text-muted mt-3">No jobs created yet</p>
                        <a href="../jobs/?action=create&client_code=<?= escape($client_code) ?>" class="btn btn-primary">
                            <i class="bx bx-plus"></i> Create First Job
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Job Title</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Submissions</th>
                                    <th class="text-center">Placements</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $job): ?>
                                    <tr class="job-row" onclick="window.location='../jobs/?action=view&code=<?= escape($job['job_code']) ?>'">
                                        <td>
                                            <div>
                                                <strong><?= escape($job['job_title']) ?></strong><br>
                                                <small class="text-muted">
                                                    <?= escape($job['job_code']) ?> | 
                                                    <?= escape($job['location'] ?: 'Remote') ?> |
                                                    <?= ucfirst($job['employment_type']) ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <?php
                                            $statusBadge = [
                                                'draft' => 'secondary',
                                                'open' => 'info',
                                                'filling' => 'primary',
                                                'filled' => 'success',
                                                'closed' => 'dark'
                                            ];
                                            $badgeColor = $statusBadge[$job['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?= $badgeColor ?>">
                                                <?= ucfirst($job['status']) ?>
                                            </span>
                                            <?php if ($job['pending_submissions'] > 0): ?>
                                                <br><small class="text-warning"><?= $job['pending_submissions'] ?> pending</small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-info"><?= $job['total_submissions'] ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-success"><?= $job['total_placements'] ?></span>
                                        </td>
                                        <td class="text-center" onclick="event.stopPropagation()">
                                            <a href="../jobs/?action=view&code=<?= escape($job['job_code']) ?>" 
                                               class="btn btn-sm btn-outline-primary" title="View Job">
                                                <i class="bx bx-show"></i>
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

        <!-- Active Placements -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-user-check"></i> Active Placements 
                    <span class="badge bg-success"><?= count($placements) ?></span>
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($placements)): ?>
                    <div class="text-center py-4">
                        <i class="bx bx-user-x display-4 text-muted"></i>
                        <p class="text-muted mt-2">No active placements yet</p>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($placements as $placement): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card placement-card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bx bx-user"></i>
                                            <?= escape($placement['candidate_name']) ?>
                                        </h6>
                                        <p class="mb-2">
                                            <strong>Position:</strong> <?= escape($placement['job_title']) ?><br>
                                            <small class="text-muted">
                                                <i class="bx bx-calendar"></i>
                                                Placed <?= $placement['days_placed'] ?> days ago
                                                (<?= date('M d, Y', strtotime($placement['placement_date'])) ?>)
                                            </small>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <a href="../submissions/?action=view&code=<?= escape($placement['submission_code']) ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bx bx-show"></i> View Details
                                            </a>
                                            <a href="../candidates/?action=view&code=<?= escape($placement['candidate_code']) ?>" 
                                               class="btn btn-sm btn-outline-secondary">
                                                <i class="bx bx-user"></i> Candidate
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Details & Notes -->
    <div class="col-md-4">
        <!-- Client Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-info-circle"></i> Client Information</h5>
            </div>
            <div class="card-body">
                <?php if ($client['contact_person']): ?>
                    <p class="mb-2">
                        <strong><i class="bx bx-user"></i> Contact Person:</strong><br>
                        <?= escape($client['contact_person']) ?>
                    </p>
                <?php endif; ?>
                
                <?php if ($client['email']): ?>
                    <p class="mb-2">
                        <strong><i class="bx bx-envelope"></i> Email:</strong><br>
                        <a href="mailto:<?= escape($client['email']) ?>"><?= escape($client['email']) ?></a>
                    </p>
                <?php endif; ?>
                
                <?php if ($client['phone']): ?>
                    <p class="mb-2">
                        <strong><i class="bx bx-phone"></i> Phone:</strong><br>
                        <a href="tel:<?= escape($client['phone']) ?>"><?= escape($client['phone']) ?></a>
                    </p>
                <?php endif; ?>
                
                <hr>
                
                <p class="mb-2">
                    <strong><i class="bx bx-user-circle"></i> Account Manager:</strong><br>
                    <?= escape($client['account_manager_name'] ?: 'Unassigned') ?>
                    <?php if ($client['account_manager_email']): ?>
                        <br><small><a href="mailto:<?= escape($client['account_manager_email']) ?>"><?= escape($client['account_manager_email']) ?></a></small>
                    <?php endif; ?>
                </p>
                
                <p class="mb-2">
                    <strong><i class="bx bx-calendar"></i> Created:</strong><br>
                    <?= date('M d, Y', strtotime($client['created_at'])) ?>
                    <?php if ($client['created_by_name']): ?>
                        <br><small class="text-muted">by <?= escape($client['created_by_name']) ?></small>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <!-- Notes Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-note"></i> Notes</h5>
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="bx bx-plus"></i> Add
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($notes)): ?>
                    <p class="text-muted text-center py-3">No notes yet</p>
                <?php else: ?>
                    <div class="timeline">
                        <?php foreach ($notes as $note): ?>
                            <div class="mb-3 pb-3 border-bottom">
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted">
                                        <strong><?= escape($note['created_by_name']) ?></strong>
                                    </small>
                                    <small class="text-muted">
                                        <?= date('M d, Y g:i A', strtotime($note['created_at'])) ?>
                                    </small>
                                </div>
                                <p class="mb-0 mt-1"><?= nl2br(escape($note['content'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-cog"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="../jobs/?action=create&client_code=<?= escape($client_code) ?>" class="btn btn-success">
                        <i class="bx bx-plus"></i> Create New Job
                    </a>
                    <?php if (Permission::can('clients', 'edit')): ?>
                        <a href="?action=edit&code=<?= escape($client_code) ?>" class="btn btn-primary">
                            <i class="bx bx-edit"></i> Edit Client
                        </a>
                    <?php endif; ?>
                    <a href="?action=list" class="btn btn-outline-secondary">
                        <i class="bx bx-arrow-back"></i> Back to List
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/add-note.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="client_code" value="<?= escape($client_code) ?>">
            
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bx bx-note"></i> Add Note</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Note <span class="text-danger">*</span></label>
                        <textarea name="content" class="form-control" rows="5" required 
                                  placeholder="Add your note here..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="note_type" class="form-select">
                            <option value="general">General Note</option>
                            <option value="call">Phone Call</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                            <option value="followup">Follow-up</option>
                        </select>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_important" value="1" class="form-check-input" id="importantCheck">
                        <label class="form-check-label" for="importantCheck">
                            Mark as important
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i> Save Note
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>