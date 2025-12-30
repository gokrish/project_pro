<?php
/**
 * Create New Candidate
 * Multi-tab form with AI resume parsing
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
// Check permission

// Check permission
Permission::require('candidates', 'create');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Page configuration
$pageTitle = 'Add New Candidate';
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => 'Add New', 'url' => '']
];
$customJS = ['/panel/assets/js/modules/candidates-form.js'];
$customCSS = ['/panel/assets/css/modules/candidates-form.css'];

// Get all skills for dropdown
$skillsSql = "SELECT DISTINCT skill_name FROM skills ORDER BY skill_name";
$skills = $conn->query($skillsSql)->fetch_all(MYSQLI_ASSOC);

// Get all recruiters for assignment
$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $recruitersSql = "SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name";
    $recruiters = $conn->query($recruitersSql)->fetch_all(MYSQLI_ASSOC);
}

// Generate candidate code
$candidateCode = 'CAN' . date('Ymd') . strtoupper(substr(uniqid(), -6));

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bx bx-user-plus text-primary me-2"></i>
                        Add New Candidate
                    </h5>
                    <a href="/panel/modules/candidates/list.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bx bx-arrow-back"></i> Back to List
                    </a>
                </div>
            </div>
            
            <div class="card-body">
                <!-- AI Resume Parser Section -->
                <div class="alert alert-info border-info">
                    <div class="d-flex align-items-start">
                        <i class="bx bx-brain fs-3 me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-2">
                                <i class="bx bx-sparkles"></i> Extract Details
                            </h6>
                            <p class="mb-3">
                                Upload a resume and let AI extract candidate information automatically. 
                                You can review and edit all fields before saving.
                            </p>
                            
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <input type="file" 
                                           class="form-control" 
                                           id="resumeFile" 
                                           accept=".pdf,.doc,.docx,.txt"
                                           disabled>
                                    <small class="text-muted">
                                        Supported formats: PDF, DOC, DOCX, TXT (Max 5MB)
                                    </small>
                                </div>
                                <div class="col-md-4">
                                    <button type="button" class="btn btn-primary w-100" id="parseResumeBtn" disabled>
                                        <i class="bx bx-upload"></i> Parse Resume
                                    </button>
                                </div>
                            </div>
                            
                            <div id="parseStatus" class="mt-3 d-none">
                                <div class="alert alert-warning mb-0">
                                    <i class="bx bx-info-circle me-2"></i>
                                    <strong>AI parsing is currently disabled.</strong> Local AI service not configured.
                                    <br><small>You can still manually fill in the form below.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <ul class="nav nav-tabs nav-fill mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" 
                                id="tab-basic" 
                                data-bs-toggle="tab" 
                                data-bs-target="#basic-info" 
                                type="button" 
                                role="tab">
                            <i class="bx bx-user me-2"></i>
                            Basic Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" 
                                id="tab-professional" 
                                data-bs-toggle="tab" 
                                data-bs-target="#professional-info" 
                                type="button" 
                                role="tab">
                            <i class="bx bx-briefcase me-2"></i>
                            Professional Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" 
                                id="tab-additional" 
                                data-bs-toggle="tab" 
                                data-bs-target="#additional-info" 
                                type="button" 
                                role="tab">
                            <i class="bx bx-detail me-2"></i>
                            Additional Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" 
                                id="tab-documents" 
                                data-bs-toggle="tab" 
                                data-bs-target="#documents" 
                                type="button" 
                                role="tab">
                            <i class="bx bx-file me-2"></i>
                            Documents
                        </button>
                    </li>
                </ul>

                <!-- Candidate Form -->
                <form id="candidateForm" method="POST" action="/panel/modules/candidates/handlers/create.php" data-validate>
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode) ?>">
                    
                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Basic Information Tab -->
                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel">
                            <div class="row g-3">
                                <!-- Full Name -->
                                <div class="col-md-6">
                                    <label for="candidate_name" class="form-label">
                                        Candidate Name <span class="text-danger">*</span>
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="candidate_name" 
                                           name="candidate_name" 
                                           placeholder="Enter full name"
                                           required
                                           data-rules="required|min:2">
                                </div>
                                
                                <!-- Email -->
                                <div class="col-md-6">
                                    <label for="email" class="form-label">
                                        Email Address <span class="text-danger">*</span>
                                    </label>
                                    <input type="email" 
                                           class="form-control" 
                                           id="email" 
                                           name="email" 
                                           placeholder="email@example.com"
                                           required
                                           data-rules="required|email">
                                    <small class="form-text text-muted">
                                        We'll check for duplicates
                                    </small>
                                </div>
                                
                                <!-- Phone -->
                                <div class="col-md-6">
                                    <label for="phone" class="form-label">
                                        Phone Number <span class="text-danger">*</span>
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone" 
                                           name="phone" 
                                           placeholder="+32 123 45 67 89"
                                           required
                                           data-rules="required|phone">
                                </div>
                                
                                <!-- Alternative Phone -->
                                <div class="col-md-6">
                                    <label for="phone_alternate" class="form-label">
                                        Alternative Phone
                                    </label>
                                    <input type="tel" 
                                           class="form-control" 
                                           id="phone_alternate" 
                                           name="phone_alternate" 
                                           placeholder="+32 987 65 43 21"
                                           data-rules="phone">
                                </div>
                                
                                <!-- Current Location -->
                                <div class="col-md-6">
                                    <label for="current_location" class="form-label">
                                        Current Location
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="current_location" 
                                           name="current_location" 
                                           placeholder="Country">
                                </div>
                                

                                <!-- Work Authorization -->
                                <div class="col-md-6">
                                    <label for="work_authorization_status" class="form-label">
                                        Work Authorization <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" 
                                            id="work_authorization_status" 
                                            name="work_authorization_status"
                                            required
                                            data-rules="required">
                                        <option value="">Select status...</option>
                                        <option value="eu_citizen">EU Citizen</option>
                                        <option value="work_permit">Valid Work Permit</option>
                                        <option value="requires_sponsorship">Requires Sponsorship</option>
                                    </select>
                                </div>
                                
                                <!-- LinkedIn -->
                                <div class="col-md-6">
                                    <label for="linkedin_url" class="form-label">
                                        LinkedIn Profile URL
                                    </label>
                                    <input type="url" 
                                           class="form-control" 
                                           id="linkedin_url" 
                                           name="linkedin_url" 
                                           placeholder="https://linkedin.com/in/..."
                                           data-rules="url">
                                </div>
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="nextTab()">
                                    Next: Professional Details <i class="bx bx-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Professional Information Tab -->
                        <div class="tab-pane fade" id="professional-info" role="tabpanel">
                            <div class="row g-3">
                                <!-- Current Position -->
                                <div class="col-md-6">
                                    <label for="current_position" class="form-label">
                                        Current Position
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="current_position" 
                                           name="current_position" 
                                           placeholder="e.g., Senior Software Developer">
                                </div>
                                
                                <!-- Current Company -->
                                <div class="col-md-6">
                                    <label for="current_company" class="form-label">
                                        Current Company
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="current_company" 
                                           name="current_company" 
                                           placeholder="Company name">
                                </div>
                                
                                <!-- Total Experience -->
                                <div class="col-md-4">

                                
                                <!-- Notice Period -->
                                <div class="col-md-4">
                                    <label for="notice_period" class="form-label">
                                        Notice Period (Days)
                                    </label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="notice_period" 
                                           name="notice_period" 
                                           min="0"
                                           placeholder="30"
                                           data-rules="numeric">
                                </div>
                                
                                <!-- Skills -->
                                <div class="col-12">
                                    <label for="skills" class="form-label">
                                        Key Skills <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" 
                                            id="skills" 
                                            name="skills[]" 
                                            multiple
                                            required
                                            data-rules="required">
                                        <?php foreach ($skills as $skill): ?>
                                        <option value="<?= htmlspecialchars($skill['skill_name']) ?>">
                                            <?= htmlspecialchars($skill['skill_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Select multiple skills. Type to search or add new skills.
                                    </small>
                                </div>
                                
                                <!-- Certifications -->
                                <div class="col-md-6">
                                    <label for="certifications" class="form-label">
                                        Certifications
                                    </label>
                                    <textarea class="form-control" 
                                              id="certifications" 
                                              name="certifications" 
                                              rows="3"
                                              placeholder="List professional certifications (one per line)"></textarea>
                                </div>
                                
                                <!-- Languages -->
                                <div class="col-md-6">
                                    <label for="languages" class="form-label">
                                        Languages
                                    </label>
                                    <textarea class="form-control" 
                                              id="languages" 
                                              name="languages" 
                                              rows="3"
                                              placeholder="Language: Proficiency (e.g., English: Native, French: Professional)"></textarea>
                                </div>
                                

                            
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()">
                                    <i class="bx bx-chevron-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" onclick="nextTab()">
                                    Next: Additional Info <i class="bx bx-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Additional Information Tab -->
                        <div class="tab-pane fade" id="additional-info" role="tabpanel">
                            <div class="row g-3">
                                <!-- Compensation Type -->
                                <div class="col-md-3">
                                    <label for="compensation_type" class="form-label">
                                        Compensation Type
                                    </label>
                                    <select class="form-select" id="compensation_type" name="compensation_type">
                                        <option value="salary">Salary</option>
                                        <option value="hourly">Daily Rate</option>
                                    </select>
                                </div>
                                
                                <!-- Current Compensation -->
                                <div class="col-md-3">
                                    <label for="current_compensation" class="form-label">
                                        Current (<span id="compTypeLabel">Salary</span>)
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="current_compensation" 
                                               name="current_compensation"
                                               min="0"
                                               step="1000"
                                               placeholder="0"
                                               data-rules="numeric">
                                    </div>
                                </div>
                                
                                <!-- Expected Compensation -->
\
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" 
                                               class="form-control" 
                                               id="expected_compensation" 
                                               name="expected_compensation"
                                               min="0"
                                               step="1000"
                                               placeholder="0"
                                               data-rules="numeric">
                                    </div>
                                </div>
                                
                                <!-- Rating -->
                                <div class="col-md-3">
                                    <label for="rating" class="form-label">
                                        Candidate Rating
                                    </label>
                                    <select class="form-select" id="rating" name="rating">
                                        <option value="0">Not Rated</option>
                                        <option value="1">★ Poor</option>
                                        <option value="2">★★ Fair</option>
                                        <option value="3">★★★ Good</option>
                                        <option value="4">★★★★ Very Good</option>
                                        <option value="5">★★★★★ Excellent</option>
                                    </select>
                                </div>
                                
                                <!-- Status -->
                                <div class="col-md-4">
                                    <label for="status" class="form-label">
                                        Status
                                    </label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="active" selected>Active</option>
                                        <option value="placed">Placed</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                                
                                <!-- Lead Type -->
                                <div class="col-md-4">
                                    <label for="lead_type" class="form-label">
                                        Lead Type
                                    </label>
                                    <select class="form-select" id="lead_type" name="lead_type">
                                        <option value="cold">❄️ Cold - Initial contact</option>
                                        <option value="warm" selected>⏰ Warm - Engaged</option>
                                        <option value="hot">🔥 Hot - Ready to proceed</option>
                                        <option value="blacklist">🚫 Blacklist - Do not contact</option>
                                    </select>
                                </div>
                                
                                <!-- Assigned To -->
                                <?php if (!empty($recruiters)): ?>
                                <div class="col-md-4">
                                    <label for="assigned_to" class="form-label">
                                        Assign to Recruiter
                                    </label>
                                    <select class="form-select" id="assigned_to" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($recruiters as $recruiter): ?>
                                        <option value="<?= htmlspecialchars($recruiter['user_code']) ?>"
                                                <?= $user['user_code'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($recruiter['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Role Addressed -->
                                <div class="col-md-6">
                                    <label for="role_addressed" class="form-label">
                                        Role/Position Addressed For
                                    </label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="role_addressed" 
                                           name="role_addressed" 
                                           placeholder="Specific position this candidate is being considered for">
                                </div>
                                
                                <!-- Source -->
                                <div class="col-md-6">
                                    <label for="source" class="form-label">
                                        Source
                                    </label>
                                    <select class="form-select" id="source" name="source">
                                        <option value="">Select source...</option>
                                        <option value="linkedin">LinkedIn</option>
                                        <option value="job_board">Job Board</option>
                                        <option value="referral">Referral</option>
                                        <option value="direct_application">Direct Application</option>
                                        <option value="headhunting">Headhunting</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                
                                <!-- Follow-up Date -->
                                <div class="col-md-6">
                                    <label for="follow_up_date" class="form-label">
                                        Follow-up Date
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="follow_up_date" 
                                           name="follow_up_date">
                                </div>
                                
                                <!-- Availability -->
                                <div class="col-md-6">
                                    <label for="availability_date" class="form-label">
                                        Available From
                                    </label>
                                    <input type="date" 
                                           class="form-control" 
                                           id="availability_date" 
                                           name="availability_date">
                                </div>
                                
                                <!-- Notes -->
                                <div class="col-12">
                                    <label for="notes" class="form-label">
                                        Internal Notes
                                    </label>
                                    <textarea class="form-control" 
                                              id="notes" 
                                              name="notes" 
                                              rows="4"
                                              placeholder="Add any internal notes about this candidate..."></textarea>
                                </div>
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()">
                                    <i class="bx bx-chevron-left"></i> Previous
                                </button>
                                <button type="button" class="btn btn-primary" onclick="nextTab()">
                                    Next: Documents <i class="bx bx-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <div class="alert alert-info">
                                <i class="bx bx-info-circle me-2"></i>
                                You can upload documents after creating the candidate profile.
                            </div>
                            
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()">
                                    <i class="bx bx-chevron-left"></i> Previous
                                </button>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary me-2" id="saveDraft">
                                        <i class="bx bx-save"></i> Save as Draft
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="bx bx-check"></i> Create Candidate
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Tab navigation helpers
function nextTab() {
    const activeTab = document.querySelector('.nav-link.active');
    const nextTab = activeTab.closest('li').nextElementSibling?.querySelector('.nav-link');
    if (nextTab) {
        nextTab.click();
    }
}

function prevTab() {
    const activeTab = document.querySelector('.nav-link.active');
    const prevTab = activeTab.closest('li').previousElementSibling?.querySelector('.nav-link');
    if (prevTab) {
        prevTab.click();
    }
}

// Update compensation label
document.getElementById('compensation_type').addEventListener('change', function() {
    const label = this.value === 'daily' ? 'daily' : 'Salary';
    document.getElementById('compTypeLabel').textContent = label;
});

// Show AI parsing status
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('parseStatus').classList.remove('d-none');
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>