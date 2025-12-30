<?php
/**
 * Edit Job - Simplified Version
 * Matches the simplified create form structure
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;

// Check permission
if (!Permission::can('jobs', 'edit')) {
    throw new PermissionException('You cannot edit jobs.');
}

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$jobCode = $_GET['job_code'] ?? $_GET['id'] ?? null;

if (!$jobCode) {
    FlashMessage::error('Job code is required');
    redirect(BASE_URL . '/panel/modules/jobs/list.php');
}

// Fetch job data - updated query to match new structure
$stmt = $conn->prepare("
    SELECT j.*, c.company_name 
    FROM jobs j 
    LEFT JOIN clients c ON c.client_code = j.client_code 
    WHERE j.job_code = ? AND j.deleted_at IS NULL
");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    FlashMessage::error('Job not found');
    redirect(BASE_URL . '/panel/modules/jobs/list.php');
}

// Get clients for dropdown
$clientsQuery = "SELECT client_code, company_name FROM clients WHERE status = 'active' AND deleted_at IS NULL ORDER BY company_name";
$clients = $conn->query($clientsQuery);

// Get recruiters for assignment
$recruitersQuery = "SELECT user_code, name FROM users WHERE level IN ('recruiter', 'senior_recruiter', 'manager') AND is_active = 1 ORDER BY name";
$recruiters = $conn->query($recruitersQuery);

// Get admins for approval dropdown
$adminsQuery = "SELECT user_code, name FROM users WHERE level IN ('admin', 'super_admin') AND is_active = 1 ORDER BY name";
$admins = $conn->query($adminsQuery);

// Get internal notes
$notesStmt = $conn->prepare("SELECT note FROM job_notes WHERE job_code = ? AND is_internal = 1 ORDER BY created_at DESC LIMIT 1");
$notesStmt->bind_param("s", $jobCode);
$notesStmt->execute();
$notesResult = $notesStmt->get_result();
$internalNote = $notesResult->fetch_assoc();
$internalNotes = $internalNote['note'] ?? '';

// Page config
$pageTitle = 'Edit Job: ' . $job['job_title'];
$breadcrumbs = [
    ['title' => 'Jobs', 'url' => '/panel/modules/jobs/list.php'],
    ['title' => $job['job_title'], 'url' => '/panel/modules/jobs/view.php?job_code=' . $jobCode],
    ['title' => 'Edit', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<style>
.form-section {
    background: #fff;
    border: 1px solid #e7e7e7;
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.form-section h5 {
    font-size: 16px;
    font-weight: 600;
    color: #566a7f;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 2px solid #f0f0f0;
}

.section-number {
    display: inline-block;
    width: 28px;
    height: 28px;
    line-height: 28px;
    text-align: center;
    background: #696cff;
    color: white;
    border-radius: 50%;
    margin-right: 8px;
    font-size: 14px;
}

.form-label {
    font-weight: 500;
    color: #566a7f;
    margin-bottom: 6px;
}

.help-text {
    font-size: 12px;
    color: #a1acb8;
    margin-top: 4px;
}

.required-star {
    color: #ff3e1d;
    margin-left: 2px;
}

.internal-field {
    background-color: #f8f9fa;
    border-left: 3px solid #696cff;
    padding-left: 12px;
}

.internal-label {
    color: #696cff;
}
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Edit Job Posting</h4>
            <p class="text-muted mb-0"><?= escape($job['job_title']) ?></p>
        </div>
        <div class="d-flex gap-2">
            <a href="view.php?job_code=<?= $jobCode ?>" class="btn btn-outline-secondary">
                <i class="bx bx-show me-1"></i> View Job
            </a>
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bx bx-arrow-back me-1"></i> Back to List
            </a>
        </div>
    </div>

    <form id="jobForm" method="POST" action="handlers/update.php">
        <?= CSRFToken::field() ?>
        <input type="hidden" name="job_code" value="<?= escape($jobCode) ?>">
        
        <!-- ============================================================ -->
        <!-- SECTION 1: BASIC INFORMATION -->
        <!-- ============================================================ -->
        <div class="form-section">
            <h5>
                <span class="section-number">1</span>
                Basic Information
            </h5>
            
            <div class="row g-3">
                <!-- Job Title -->
                <div class="col-md-8">
                    <label for="job_title" class="form-label">
                        Job Title <span class="required-star">*</span>
                    </label>
                    <input type="text" 
                           class="form-control" 
                           id="job_title" 
                           name="job_title" 
                           value="<?= escape($job['job_title']) ?>"
                           required>
                </div>
                
                <!-- Job Code (Read-only) -->
                <div class="col-md-4">
                    <label class="form-label">Job ID</label>
                    <input type="text" 
                           class="form-control" 
                           value="<?= escape($job['job_code']) ?>" 
                           disabled
                           style="background-color: #f8f9fa;">
                </div>
                
                <!-- Client (Internal Only) -->
                <div class="col-md-6 internal-field">
                    <label for="client_code" class="form-label internal-label">
                        Client <span class="required-star">*</span>
                        <span class="badge bg-info ms-1">Internal</span>
                    </label>
                    <select class="form-select" id="client_code" name="client_code" required>
                        <option value="">Select Client</option>
                        <?php while ($client = $clients->fetch_assoc()): ?>
                            <option value="<?= $client['client_code'] ?>"
                                    <?= $client['client_code'] === $job['client_code'] ? 'selected' : '' ?>>
                                <?= escape($client['company_name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- SECTION 2: JOB DETAILS -->
        <!-- ============================================================ -->
        <div class="form-section">
            <h5>
                <span class="section-number">2</span>
                Job Details
            </h5>
            
            <div class="row g-3">
                <div class="col-12">
                    <label for="description" class="form-label">
                        Job Description <span class="required-star">*</span>
                    </label>
                    <textarea class="form-control" 
                              id="description" 
                              name="description" 
                              rows="8" 
                              required><?= escape($job['description']) ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- SECTION 3: SALARY/RATE -->
        <!-- ============================================================ -->
        <div class="form-section">
            <h5>
                <span class="section-number">3</span>
                Compensation
            </h5>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="salary" class="form-label">Salary/Rate</label>
                    <div class="input-group">
                        <span class="input-group-text"><?= $job['currency'] ?? '€' ?></span>
                        <input type="number" 
                               class="form-control" 
                               id="salary" 
                               name="salary" 
                               value="<?= $job['salary'] ?>"
                               step="0.01"
                               min="0">
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- SECTION 4: INTERNAL FIELDS -->
        <!-- ============================================================ -->
        <div class="form-section">
            <h5>
                <span class="section-number">4</span>
                Internal Information
            </h5>
            
            <div class="row g-3">
                <!-- Internal Notes -->
                <div class="col-12 internal-field">
                    <label for="internal_notes" class="form-label internal-label">
                        Internal Notes
                        <span class="badge bg-info ms-1">Internal</span>
                    </label>
                    <textarea class="form-control" 
                              id="internal_notes" 
                              name="internal_notes" 
                              rows="4"><?= escape($internalNotes) ?></textarea>
                    <div class="help-text">
                        <i class="bx bx-lock-alt me-1"></i>
                        Private notes - only visible to your team
                    </div>
                </div>
                
                <!-- Assigned To -->
                <div class="col-md-6 internal-field">
                    <label for="assigned_to" class="form-label internal-label">
                        Assigned To
                        <span class="badge bg-info ms-1">Internal</span>
                    </label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php 
                        $recruiters->data_seek(0); // Reset pointer
                        while ($recruiter = $recruiters->fetch_assoc()): 
                        ?>
                            <option value="<?= $recruiter['user_code'] ?>"
                                    <?= $recruiter['user_code'] === ($job['assigned_to'] ?? '') ? 'selected' : '' ?>>
                                <?= escape($recruiter['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <!-- Approved By -->
                <div class="col-md-6 internal-field">
                    <label for="approved_by" class="form-label internal-label">
                        Approved By (Admin)
                        <span class="badge bg-info ms-1">Internal</span>
                    </label>
                    <select class="form-select" id="approved_by" name="approved_by">
                        <option value="">Not Approved</option>
                        <?php while ($admin = $admins->fetch_assoc()): ?>
                            <option value="<?= $admin['user_code'] ?>"
                                    <?= $admin['user_code'] === ($job['approved_by'] ?? '') ? 'selected' : '' ?>>
                                <?= escape($admin['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- SECTION 5: PUBLISHING -->
        <!-- ============================================================ -->
        <div class="form-section">
            <h5>
                <span class="section-number">5</span>
                Publishing Options
            </h5>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">
                        Status <span class="required-star">*</span>
                    </label>
                    <select class="form-select" id="status" name="status" required>
                        <option value="draft" <?= $job['status'] === 'draft' ? 'selected' : '' ?>>Draft (Not visible)</option>
                        <option value="published" <?= $job['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                        <option value="archived" <?= $job['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                    </select>
                </div>
                
                <div class="col-md-8">
                    <label class="form-label">Career Page Visibility</label>
                    <div class="form-check form-switch">
                        <input class="form-check-input" 
                               type="checkbox" 
                               id="is_published" 
                               name="is_published" 
                               value="1"
                               <?= $job['is_published'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_published">
                            <strong>Publish on Public Career Page</strong>
                        </label>
                    </div>
                    <div class="help-text">
                        <?php if ($job['is_published'] && $job['published_at']): ?>
                            <span class="badge bg-success">Published</span>
                            Since <?= formatDateTime($job['published_at']) ?>
                        <?php else: ?>
                            <span class="badge bg-secondary">Not Published</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- ============================================================ -->
        <!-- FORM ACTIONS -->
        <!-- ============================================================ -->
        <div class="d-flex gap-2 justify-content-end mt-4">
            <a href="view.php?job_code=<?= $jobCode ?>" class="btn btn-outline-secondary">
                <i class="bx bx-x me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bx bx-save me-1"></i> Save Changes
            </button>
        </div>
        
    </form>
</div>

<!-- Simplified JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    document.getElementById('jobForm').addEventListener('submit', function(e) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        
        // Check required fields
        const requiredFields = this.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if (!field.value.trim()) {
                field.classList.add('is-invalid');
                isValid = false;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            const firstError = document.querySelector('.is-invalid');
            if (firstError) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                firstError.focus();
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>