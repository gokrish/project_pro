<?php
/**
 * View Client Details
 * File: panel/modules/clients/view.php
 */
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

if (!defined('INCLUDED_FROM_INDEX')) {
    require_once __DIR__ . '/../_common.php';
    Permission::require('clients', 'view');
}

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get client
$clientCode = input('code', '');
$clientId = (int)input('id', 0);

if (empty($clientCode) && !$clientId) {
    throw new Exception('Client code or ID is required');
}

$sql = "
    SELECT c.*, 
           u.name as account_manager_name,
           creator.name as created_by_name
    FROM clients c
    LEFT JOIN users u ON c.account_manager = u.user_code
    LEFT JOIN users creator ON c.created_by = creator.user_code
    WHERE " . ($clientCode ? "c.client_code = ?" : "c.client_id = ?");

$stmt = $conn->prepare($sql);
if ($clientCode) {
    $stmt->bind_param('s', $clientCode);
} else {
    $stmt->bind_param('i', $clientId);
}
$stmt->execute();
$client = $stmt->get_result()->fetch_assoc();

if (!$client) {
    throw new Exception('Client not found');
}

// Get client jobs
$jobsQuery = "
    SELECT 
        j.*,
        (SELECT COUNT(*) FROM applications WHERE job_code = j.job_code) as applicants_count
    FROM jobs j
    WHERE j.client_code = ?
    AND (j.deleted_at IS NULL OR j.deleted_at = '0000-00-00 00:00:00')
    ORDER BY j.created_at DESC
";
$stmt = $conn->prepare($jobsQuery);
$stmt->bind_param('s', $client['client_code']);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get client notes
$notesQuery = "
    SELECT cn.*, u.name as created_by_name
    FROM client_notes cn
    LEFT JOIN users u ON cn.created_by = u.user_code
    WHERE cn.client_code = ?
    ORDER BY cn.created_at DESC
";
$stmt = $conn->prepare($notesQuery);
$stmt->bind_param('s', $client['client_code']);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-building text-primary me-2"></i>
                <?= htmlspecialchars($client['company_name']) ?>
            </h4>
            <p class="text-muted mb-0">
                <?= htmlspecialchars($client['client_code']) ?>
                <span class="badge bg-label-<?= $client['status'] === 'active' ? 'success' : 'secondary' ?> ms-2">
                    <?= ucfirst($client['status']) ?>
                </span>
            </p>
        </div>
        <div>
            <?php if (Permission::can('clients', 'edit')): ?>
            <a href="?action=edit&code=<?= urlencode($client['client_code']) ?>" class="btn btn-primary me-2">
                <i class="bx bx-edit me-1"></i> Edit
            </a>
            <?php endif; ?>
            <a href="../jobs/create.php?client_code=<?= urlencode($client['client_code']) ?>" class="btn btn-success me-2">
                <i class="bx bx-briefcase me-1"></i> Create Job
            </a>
            <a href="?action=list" class="btn btn-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back
            </a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-success">
                                <i class="bx bx-briefcase bx-sm"></i>
                            </span>
                        </div>
                        <div>
                            <p class="mb-0 text-muted small">Active Jobs</p>
                            <h4 class="mb-0"><?= $client['active_jobs'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-info">
                                <i class="bx bx-folder bx-sm"></i>
                            </span>
                        </div>
                        <div>
                            <p class="mb-0 text-muted small">Total Jobs</p>
                            <h4 class="mb-0"><?= $client['total_jobs'] ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-warning">
                                <i class="bx bx-note bx-sm"></i>
                            </span>
                        </div>
                        <div>
                            <p class="mb-0 text-muted small">Notes</p>
                            <h4 class="mb-0"><?= count($notes) ?></h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="avatar me-3">
                            <span class="avatar-initial rounded bg-label-primary">
                                <i class="bx bx-calendar bx-sm"></i>
                            </span>
                        </div>
                        <div>
                            <p class="mb-0 text-muted small">Client Since</p>
                            <h6 class="mb-0"><?= date('M Y', strtotime($client['created_at'])) ?></h6>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column: Client Details -->
        <div class="col-lg-4">
            <!-- Contact Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Contact Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Primary Contact:</dt>
                        <dd class="col-7"><?= htmlspecialchars($client['client_name']) ?></dd>
                        
                        <?php if (!empty($client['contact_person']) && $client['contact_person'] !== $client['client_name']): ?>
                        <dt class="col-5">Alt Contact:</dt>
                        <dd class="col-7"><?= htmlspecialchars($client['contact_person']) ?></dd>
                        <?php endif; ?>
                        
                        <dt class="col-5">Email:</dt>
                        <dd class="col-7">
                            <a href="mailto:<?= htmlspecialchars($client['email']) ?>">
                                <?= htmlspecialchars($client['email']) ?>
                            </a>
                        </dd>
                        
                        <?php if (!empty($client['phone'])): ?>
                        <dt class="col-5">Phone:</dt>
                        <dd class="col-7">
                            <a href="tel:<?= htmlspecialchars($client['phone']) ?>">
                                <?= htmlspecialchars($client['phone']) ?>
                            </a>
                        </dd>
                        <?php endif; ?>
                        
                        <?php if (!empty($client['industry'])): ?>
                        <dt class="col-5">Industry:</dt>
                        <dd class="col-7">
                            <span class="badge bg-label-info">
                                <?= htmlspecialchars($client['industry']) ?>
                            </span>
                        </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Address -->
            <?php if (!empty($client['address']) || !empty($client['city']) || !empty($client['country'])): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Address</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($client['address'])): ?>
                        <?= nl2br(htmlspecialchars($client['address'])) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($client['city'])): ?>
                        <?= htmlspecialchars($client['city']) ?><br>
                    <?php endif; ?>
                    <?php if (!empty($client['country'])): ?>
                        <?= htmlspecialchars($client['country']) ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Account Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Account Management</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">Account Manager:</dt>
                        <dd class="col-7"><?= htmlspecialchars($client['account_manager_name'] ?? 'Unassigned') ?></dd>
                        
                        <dt class="col-5">Created By:</dt>
                        <dd class="col-7"><?= htmlspecialchars($client['created_by_name'] ?? 'System') ?></dd>
                        
                        <dt class="col-5">Created:</dt>
                        <dd class="col-7"><?= date('M d, Y', strtotime($client['created_at'])) ?></dd>
                        
                        <dt class="col-5">Last Updated:</dt>
                        <dd class="col-7"><?= date('M d, Y H:i', strtotime($client['updated_at'])) ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Internal Notes -->
            <?php if (!empty($client['notes'])): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Internal Notes</h5>
                </div>
                <div class="card-body">
                    <?= nl2br(htmlspecialchars($client['notes'])) ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right Column: Jobs & Activity -->
        <div class="col-lg-8">
            <!-- Jobs List -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Jobs (<?= count($jobs) ?>)</h5>
                    <a href="../jobs/create.php?client_code=<?= urlencode($client['client_code']) ?>" class="btn btn-sm btn-primary">
                        <i class="bx bx-plus me-1"></i> New Job
                    </a>
                </div>
                <div class="card-body">
                    <?php if (empty($jobs)): ?>
                        <div class="text-center py-4">
                            <i class="bx bx-briefcase" style="font-size: 48px; color: #ddd;"></i>
                            <p class="text-muted mt-2 mb-3">No jobs created yet</p>
                            <a href="../jobs/create.php?client_code=<?= urlencode($client['client_code']) ?>" class="btn btn-sm btn-primary">
                                Create First Job
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Job Title</th>
                                        <th>Status</th>
                                        <th>Applicants</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($jobs as $job): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($job['title']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($job['job_code']) ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-label-<?= $job['status'] === 'open' ? 'success' : 'secondary' ?>">
                                                <?= ucfirst($job['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= $job['applicants_count'] ?></td>
                                        <td><?= date('M d, Y', strtotime($job['created_at'])) ?></td>
                                        <td>
                                            <a href="../jobs/view.php?code=<?= urlencode($job['job_code']) ?>" 
                                               class="btn btn-sm btn-icon">
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

            <!-- Activity Notes -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Activity Notes (<?= count($notes) ?>)</h5>
                </div>
                <div class="card-body">
                    <!-- Add Note Form -->
                    <form id="addNoteForm" class="mb-4">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        <input type="hidden" name="client_code" value="<?= htmlspecialchars($client['client_code']) ?>">
                        <div class="input-group">
                            <select name="note_type" class="form-select" style="max-width: 150px;">
                                <option value="general">General</option>
                                <option value="meeting">Meeting</option>
                                <option value="call">Call</option>
                                <option value="email">Email</option>
                            </select>
                            <input type="text" name="note" class="form-control" placeholder="Add a note..." required>
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-plus"></i> Add
                            </button>
                        </div>
                    </form>

                    <!-- Notes List -->
                    <?php if (empty($notes)): ?>
                        <p class="text-muted text-center py-3">No notes yet</p>
                    <?php else: ?>
                        <div class="activity-timeline">
                            <?php foreach ($notes as $note): ?>
                            <div class="timeline-item mb-3 pb-3 border-bottom" id="note-<?= $note['note_id'] ?>">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <span class="badge bg-label-<?= $note['note_type'] === 'meeting' ? 'primary' : ($note['note_type'] === 'call' ? 'success' : 'secondary') ?>">
                                            <?= ucfirst($note['note_type']) ?>
                                        </span>
                                        <strong class="ms-2"><?= htmlspecialchars($note['created_by_name']) ?></strong>
                                        <small class="text-muted">
                                            - <?= date('M d, Y H:i', strtotime($note['created_at'])) ?>
                                        </small>
                                    </div>
                                    <?php if ($note['created_by'] === $user['user_code'] || $user['level'] === 'admin'): ?>
                                    <button class="btn btn-sm btn-icon btn-text-secondary delete-note" 
                                            data-note-id="<?= $note['note_id'] ?>">
                                        <i class="bx bx-trash"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                                <p class="mb-0 mt-2"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Add note
    $('#addNoteForm').on('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        try {
            const response = await fetch('handlers/add-note.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            alert('Failed to add note');
        }
    });
    
    // Delete note (would need handler)
    $('.delete-note').on('click', function() {
        const noteId = $(this).data('note-id');
        if (confirm('Delete this note?')) {
            // Implement delete note handler
            $('#note-' + noteId).fadeOut();
        }
    });
});
</script>