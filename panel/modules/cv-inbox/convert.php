<?php
/**
 * Convert CV to Candidate
 * Enhanced version with auto-population
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('candidates', 'create');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get CV ID
$cvId = (int)input('id');
if (!$cvId) {
    redirectWithMessage('/panel/modules/cv-inbox/index.php', 'Invalid CV application', 'error');
}

// Get CV details
$stmt = $conn->prepare("
    SELECT cv.*, j.job_title, j.job_code
    FROM cv_inbox cv
    LEFT JOIN jobs j ON cv.job_code = j.job_code
    WHERE cv.id = ?
");
$stmt->bind_param("i", $cvId);
$stmt->execute();
$cv = $stmt->get_result()->fetch_assoc();

if (!$cv) {
    redirectWithMessage('/panel/modules/cv-inbox/index.php', 'CV not found', 'error');
}

// Check if already converted
if ($cv['status'] === 'converted') {
    redirectWithMessage(
        "/panel/modules/candidates/view.php?code={$cv['converted_to_candidate']}", 
        'This application has already been converted', 
        'info'
    );
}

// Generate candidate code
$candidateCode = 'CAN-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);

// Get open jobs for dropdown
$jobs = $conn->query("SELECT job_code, job_title FROM jobs WHERE status = 'open' ORDER BY job_title")->fetch_all(MYSQLI_ASSOC);

// duplicate email check before showing form
$stmt = $conn->prepare("
    SELECT candidate_code, candidate_name 
    FROM candidates 
    WHERE email = ? 
    AND deleted_at IS NULL
");
$stmt->bind_param("s", $cv['email']);
$stmt->execute();
$existingCandidate = $stmt->get_result()->fetch_assoc();

if ($existingCandidate) {
    echo '<div class="alert alert-warning">';
    echo '<strong>Email Already Exists!</strong><br>';
    echo 'A candidate with this email already exists: ';
    echo '<a href="/panel/modules/candidates/view.php?code=' . $existingCandidate['candidate_code'] . '">';
    echo htmlspecialchars($existingCandidate['candidate_name']) . '</a>';
    echo '<br><br>You can still proceed to link this CV to the existing candidate.';
    echo '</div>';
}
// Page configuration
$pageTitle = 'Convert to Candidate - ' . $cv['candidate_name'];
$breadcrumbs = [
    ['title' => 'CV Inbox', 'url' => '/panel/modules/cv-inbox/index.php'],
    ['title' => $cv['candidate_name'], 'url' => '/panel/modules/cv-inbox/view.php?id=' . $cvId],
    ['title' => 'Convert', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-transfer text-success me-2"></i>
                    Convert CV to Candidate
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <i class="bx bx-info-circle me-2"></i>
                    <strong>Converting Application:</strong> <?= htmlspecialchars($cv['candidate_name']) ?>
                    <br>This will create a new candidate record and optionally link to the job application.
                </div>
                
                <form id="convertForm" method="POST" action="/panel/modules/cv-inbox/handlers/convert.php">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="cv_id" value="<?= $cvId ?>">
                    
                    <!-- Basic Information (Auto-populated) -->
                    <div class="form-section mb-4">
                        <h5 class="section-title">Basic Information</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Candidate Code</label>
                                <input type="text" 
                                       name="candidate_code" 
                                       class="form-control" 
                                       value="<?= $candidateCode ?>" 
                                       readonly 
                                       style="background-color: #f0f0f0;">
                            </div>
                            
                            <div class="col-md-8">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" 
                                       name="candidate_name" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($cv['candidate_name']) ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" 
                                       name="email" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($cv['email']) ?>" 
                                       required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone</label>
                                <input type="tel" 
                                       name="phone" 
                                       class="form-control" 
                                       value="<?= htmlspecialchars($cv['phone'] ?? '') ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Professional Details -->
                    <div class="form-section mb-4">
                        <h5 class="section-title">Professional Details</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Current Position</label>
                                <input type="text" name="current_position" class="form-control">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Current Company</label>
                                <input type="text" name="current_company" class="form-control">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Total Experience (years)</label>
                                <input type="number" 
                                       name="total_experience" 
                                       class="form-control" 
                                       min="0" 
                                       step="0.5" 
                                       placeholder="e.g., 5">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Current Location</label>
                                <input type="text" name="current_location" class="form-control">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Skills (comma-separated)</label>
                                <textarea name="skills" 
                                          class="form-control" 
                                          rows="2" 
                                          placeholder="PHP, Laravel, MySQL, JavaScript..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Compensation & Availability -->
                    <div class="form-section mb-4">
                        <h5 class="section-title">Compensation & Availability</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Expected Daily Rate (EUR)</label>
                                <div class="input-group">
                                    <span class="input-group-text">€</span>
                                    <input type="number" 
                                           name="expected_compensation" 
                                           class="form-control" 
                                           min="0" 
                                           step="10" 
                                           placeholder="450">
                                    <span class="input-group-text">/day</span>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Available From</label>
                                <input type="date" 
                                       name="availability" 
                                       class="form-control" 
                                       min="<?= date('Y-m-d') ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Work Authorization</label>
                                <select name="work_authorization" class="form-select">
                                    <option value="">Select...</option>
                                    <option value="eu_citizen">EU Citizen</option>
                                    <option value="work_permit">Work Permit</option>
                                    <option value="requires_sponsorship">Requires Sponsorship</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Lead Type</label>
                                <select name="lead_type" class="form-select">
                                    <option value="warm" selected>🟡 Warm</option>
                                    <option value="hot">🔴 Hot</option>
                                    <option value="cold">🔵 Cold</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Job Application Link -->
                    <div class="form-section mb-4">
                        <h5 class="section-title">Link to Job Application</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Job Applied For</label>
                                <select name="job_code" class="form-select" id="jobSelect">
                                    <option value="">No job (create as general candidate)</option>
                                    <?php foreach ($jobs as $job): ?>
                                    <option value="<?= htmlspecialchars($job['job_code']) ?>" 
                                            <?= $cv['job_code'] === $job['job_code'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($job['job_title']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    If selected, an application record will be created
                                </small>
                            </div>
                            
                            <div class="col-md-4" id="applicationStatusDiv" style="display: none;">
                                <label class="form-label">Application Status</label>
                                <select name="application_status" class="form-select">
                                    <option value="applied">Applied</option>
                                    <option value="screening" selected>Screening</option>
                                    <option value="interview">Interview</option>
                                    <option value="shortlisted">Shortlisted</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Internal Notes -->
                    <div class="form-section mb-4">
                        <h5 class="section-title">Internal Notes</h5>
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Screening Notes</label>
                                <textarea name="notes" 
                                          class="form-control" 
                                          rows="4" 
                                          placeholder="Add initial screening notes, observations, or feedback..."></textarea>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           name="send_welcome_email" 
                                           id="sendWelcome" 
                                           value="1">
                                    <label class="form-check-label" for="sendWelcome">
                                        Send welcome email to candidate
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-end gap-2 pt-4 border-top">
                        <a href="/panel/modules/cv-inbox/view.php?id=<?= $cvId ?>" class="btn btn-outline-secondary">
                            <i class="bx bx-x"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-success">
                            <i class="bx bx-check"></i> Convert to Candidate
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.form-section { padding: 20px; background: #f8f9fa; border-radius: 8px; }
.section-title { color: #28a745; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #28a745; font-size: 1.1rem; }
</style>

<script>
// Show/hide application status based on job selection
document.getElementById('jobSelect').addEventListener('change', function() {
    const statusDiv = document.getElementById('applicationStatusDiv');
    if (this.value) {
        statusDiv.style.display = 'block';
    } else {
        statusDiv.style.display = 'none';
    }
});

// Trigger on page load
if (document.getElementById('jobSelect').value) {
    document.getElementById('applicationStatusDiv').style.display = 'block';
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>