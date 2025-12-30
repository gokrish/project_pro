<?php
/**
 * View Contact Details
 * Single contact view with actions
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;

Permission::require('contacts', 'view');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get contact code
$contactCode = $_GET['contact_code'] ?? $_GET['id'] ?? null;

if (!$contactCode) {
    FlashMessage::error('Contact code is required');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

// Fetch contact
$stmt = $conn->prepare("
    SELECT c.*, 
           u.name as assigned_to_name,
           creator.name as created_by_name,
           cand.candidate_code as converted_candidate_code,
           cand.first_name as candidate_first_name,
           cand.last_name as candidate_last_name
    FROM contacts c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    LEFT JOIN users creator ON c.created_by = creator.user_code
    LEFT JOIN candidates cand ON c.converted_to_candidate = cand.candidate_code
    WHERE c.contact_code = ? AND c.deleted_at IS NULL
");
$stmt->bind_param("s", $contactCode);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();

if (!$contact) {
    FlashMessage::error('Contact not found');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

// Check ownership for view.own
if (!Permission::can('contacts', 'view_all') && Permission::can('contacts', 'view_own')) {
    if ($contact['assigned_to'] !== Auth::userCode()) {
        throw new PermissionException('You can only view contacts assigned to you');
    }
}

// Parse skills
$skillsArray = json_decode($contact['skills'] ?? '[]', true);

// Get activity log
$activityStmt = $conn->prepare("
    SELECT al.*, u.name as user_name
    FROM activity_log al
    LEFT JOIN users u ON al.user_code = u.user_code
    WHERE al.module = 'contacts' AND al.record_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$activityStmt->bind_param("s", $contactCode);
$activityStmt->execute();
$activities = $activityStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = $contact['first_name'] . ' ' . $contact['last_name'];
$breadcrumbs = [
    ['title' => 'Contacts', 'url' => '/panel/modules/contacts/list.php'],
    ['title' => $contact['first_name'] . ' ' . $contact['last_name'], 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h4 class="mb-1">
                        <i class="bx bx-user-circle text-primary me-2"></i>
                        <?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?>
                    </h4>
                    <div class="d-flex gap-2 align-items-center">
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
                        $statusColor = $statusColors[$contact['status']] ?? 'secondary';
                        
                        $priorityColors = ['high' => 'danger', 'medium' => 'warning', 'low' => 'info'];
                        $priorityColor = $priorityColors[$contact['priority']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?= $statusColor ?>">
                            <?= ucwords(str_replace('_', ' ', $contact['status'])) ?>
                        </span>
                        <span class="badge bg-label-<?= $priorityColor ?>">
                            <?= ucfirst($contact['priority']) ?> Priority
                        </span>
                        <span class="badge bg-label-info">
                            <?= ucfirst(str_replace('_', ' ', $contact['source'])) ?>
                        </span>
                    </div>
                </div>
                <div>
                    <a href="list.php" class="btn btn-outline-secondary me-2">
                        <i class="bx bx-arrow-back"></i> Back
                    </a>
                    
                    <?php if (Permission::can('contacts', 'edit')): ?>
                        <a href="edit.php?contact_code=<?= urlencode($contactCode) ?>" class="btn btn-primary me-2">
                            <i class="bx bx-edit"></i> Edit
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($contact['status'] !== 'converted' && Permission::can('contacts', 'convert')): ?>
                        <a href="convert.php?contact_code=<?= urlencode($contactCode) ?>" class="btn btn-success">
                            <i class="bx bx-right-arrow-circle"></i> Convert to Candidate
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($contact['status'] === 'converted' && $contact['converted_candidate_code']): ?>
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <h6 class="alert-heading mb-1">
            <i class="bx bx-check-circle me-1"></i> Converted to Candidate
        </h6>
        <p class="mb-0">
            This contact was converted on <?= formatDate($contact['converted_at'], 'M d, Y') ?>. 
            <a href="/panel/modules/candidates/view.php?candidate_code=<?= urlencode($contact['converted_candidate_code']) ?>" 
               class="alert-link">
                View Candidate Profile →
            </a>
        </p>
    </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left Column: Contact Details -->
        <div class="col-lg-8">
            
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-info-circle me-2"></i>
                        Contact Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Email</label>
                            <div>
                                <a href="mailto:<?= escape($contact['email']) ?>">
                                    <?= escape($contact['email']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Phone</label>
                            <div>
                                <?php if ($contact['phone']): ?>
                                    <a href="tel:<?= escape($contact['phone']) ?>">
                                        <?= escape($contact['phone']) ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-12 mb-3">
                            <label class="text-muted small">LinkedIn</label>
                            <div>
                                <?php if ($contact['linkedin_url']): ?>
                                    <a href="<?= escape($contact['linkedin_url']) ?>" target="_blank">
                                        <?= escape($contact['linkedin_url']) ?>
                                        <i class="bx bx-link-external ms-1"></i>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Professional Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-briefcase me-2"></i>
                        Professional Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Current Company</label>
                            <div><?= escape($contact['current_company'] ?: '—') ?></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Current Title</label>
                            <div><?= escape($contact['current_title'] ?: '—') ?></div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Years of Experience</label>
                            <div>
                                <?= $contact['years_of_experience'] ? $contact['years_of_experience'] . ' years' : '—' ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small">Location</label>
                            <div><?= escape($contact['current_location'] ?: '—') ?></div>
                        </div>
                        
                        <?php if (!empty($skillsArray)): ?>
                        <div class="col-12 mb-3">
                            <label class="text-muted small">Skills</label>
                            <div class="mt-1">
                                <?php foreach ($skillsArray as $skill): ?>
                                    <span class="badge bg-label-primary me-1 mb-1">
                                        <?= escape($skill) ?>
                                    </span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($contact['summary']): ?>
                        <div class="col-12">
                            <label class="text-muted small">Summary / Notes</label>
                            <div class="mt-1"><?= nl2br(escape($contact['summary'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Activity Log -->
            <?php if (!empty($activities)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-history me-2"></i>
                        Recent Activity
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="timeline timeline-simple ps-3">
                        <?php foreach ($activities as $activity): ?>
                        <li class="timeline-item">
                            <div class="timeline-content">
                                <div class="d-flex justify-content-between">
                                    <strong><?= escape($activity['user_name'] ?? 'System') ?></strong>
                                    <small class="text-muted"><?= timeAgo($activity['created_at']) ?></small>
                                </div>
                                <p class="mb-0"><?= escape($activity['description']) ?></p>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <?php endif; ?>
            
        </div>
        
        <!-- Right Column: Management Info -->
        <div class="col-lg-4">
            
            <!-- Lead Management -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-target-lock me-2"></i>
                        Lead Management
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Assigned To</label>
                        <div>
                            <?= escape($contact['assigned_to_name'] ?? 'Unassigned') ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Next Follow-up</label>
                        <div>
                            <?php if ($contact['next_follow_up']): ?>
                                <?php
                                $isOverdue = $contact['next_follow_up'] < date('Y-m-d');
                                $isToday = $contact['next_follow_up'] === date('Y-m-d');
                                ?>
                                <span class="badge bg-<?= $isOverdue ? 'danger' : ($isToday ? 'warning' : 'info') ?>">
                                    <?= formatDate($contact['next_follow_up'], 'M d, Y') ?>
                                    <?= $isOverdue ? '(Overdue)' : ($isToday ? '(Today)' : '') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Not scheduled</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Source Details</label>
                        <div>
                            <?= escape($contact['source_details'] ?: '—') ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Metadata -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-file me-2"></i>
                        Record Details
                    </h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="text-muted small">Contact Code</label>
                        <div><code><?= escape($contact['contact_code']) ?></code></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Created By</label>
                        <div><?= escape($contact['created_by_name'] ?? 'Unknown') ?></div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="text-muted small">Created At</label>
                        <div><?= formatDate($contact['created_at'], 'M d, Y g:i A') ?></div>
                    </div>
                    
                    <?php if ($contact['updated_at'] !== $contact['created_at']): ?>
                    <div class="mb-3">
                        <label class="text-muted small">Last Updated</label>
                        <div><?= formatDate($contact['updated_at'], 'M d, Y g:i A') ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('contacts', 'delete')): ?>
                    <div class="mt-4">
                        <button type="button" class="btn btn-sm btn-outline-danger w-100" 
                                onclick="deleteContact('<?= escape($contactCode) ?>')">
                            <i class="bx bx-trash me-1"></i> Delete Contact
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
        </div>
    </div>
</div>

<?php if (Permission::can('contacts', 'delete')): ?>
<script>
function deleteContact(contactCode) {
    if (!confirm('Are you sure you want to delete this contact? This action cannot be undone.')) {
        return;
    }
    
    fetch('/panel/modules/contacts/handlers/delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            contact_code: contactCode,
            csrf_token: '<?= CSRFToken::generate() ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '/panel/modules/contacts/list.php';
        } else {
            alert('Error: ' + (data.message || 'Failed to delete contact'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the contact');
    });
}
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>