<?php
/**
 * Create New Candidate 
 * 
 * @version 6.0
 * 
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken};

// Check permission
Permission::require('candidates', 'create');

$user = Auth::user();
$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

// ============================================================================
// GET REFERENCE DATA
// ============================================================================

// Locations (Belgium-focused)
$locations = [
    'Belgium' => 'Belgium',
    'Netherlands' => 'Netherlands',
    'Luxembourg' => 'Luxembourg',
    'Germany' => 'Germany',
    'France' => 'France',
    'India' => 'India',
    'Other' => 'Other'
];
$willingtojoin = [
    'Yes' => 'Yes',
    'No' => 'No',
];

// Work authorizations
$stmt = $conn->prepare("SELECT id, status_name FROM work_authorization WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$workAuths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all recruiters for assignment
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

// Notice periods
$noticePeriods = [
    0 => 'Immediate (0 days)',
    7 => '1 week (7 days)',
    14 => '2 weeks (14 days)',
    30 => '1 month (30 days)',
    60 => '2 months (60 days)',
    90 => '3 months (90 days)'
];

// Languages (Belgium market)
$availableLanguages = ['English', 'French', 'Dutch', 'German'];

// Candidate sources
$candidateSources = [
    'email_cv' => 'CV Received via Email',
    'phone_inquiry' => 'Inquiry',
    'linkedin_sourcing' => 'LinkedIn',
    'referral' => 'Referral (Employee/Client)',
    'other' => 'Other'
];

// Page config
$pageTitle = 'Add New Candidate';
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => 'Add New', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="content-container">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><?= $pageTitle ?></h4>
            <p class="text-muted mb-0">Add a new candidate to the recruitment</p>
        </div>
        <a href="/panel/modules/candidates/list.php" class="btn btn-secondary">
            <i class="bx bx-arrow-back"></i> Back to List
        </a>
    </div>

    <form method="POST" action="/panel/modules/candidates/handlers/create.php" id="candidateForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
        <!-- Resume Parser Card -->
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="bx bx-file-blank"></i> Quick Resume Parser
                </h6>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <i class="bx bx-info-circle"></i>
                    <strong>Time Saver:</strong> Upload the candidate's resume (PDF or DOCX) and we'll automatically extract:
                    name, email, phone, LinkedIn, location, and skills. You can review and correct before saving.
                </div>
                
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Upload Resume (PDF or DOCX)</label>
                        <input type="file" class="form-control" id="resumeFile" 
                            accept=".pdf,.docx">
                        <small class="text-muted">Supported: PDF, DOCX (Max 5MB)</small>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-primary w-100" id="parseResumeBtn">
                            <i class="bx bx-search"></i> Parse Resume
                        </button>
                    </div>
                </div>
                
                <!-- Parsing Status -->
                <div id="parseStatus" class="mt-3" style="display: none;">
                    <div class="alert alert-warning">
                        <div class="spinner-border spinner-border-sm me-2" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        Parsing resume... Please wait.
                    </div>
                </div>
                
                <!-- Parse Results -->
                <div id="parseResults" class="mt-3" style="display: none;"></div>
            </div>
        </div>
        <!-- Basic Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-user"></i> Basic Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Candidate Name -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Candidate Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="candidate_name" required 
                               placeholder="John Doe">
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" name="email" required 
                               placeholder="john.doe@example.com">
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Phone <span class="text-danger">*</span>
                        </label>
                        <input type="tel" class="form-control" name="phone" required 
                               placeholder="+32 123 456 789">
                    </div>

                    <!-- Alternate Email -->
                    <div class="col-md-6">
                        <label class="form-label">Alternate Email</label>
                        <input type="email" class="form-control" name="alternate_email" 
                               placeholder="john.personal@example.com">
                    </div>

                    <!-- Alternate Phone -->
                    <div class="col-md-6">
                        <label class="form-label">Alternate Phone</label>
                        <input type="tel" class="form-control" name="phone_alternate" 
                               placeholder="+32 987 654 321">
                    </div>

                    <!-- LinkedIn URL -->
                    <div class="col-md-6">
                        <label class="form-label">LinkedIn Profile</label>
                        <input type="url" class="form-control" name="linkedin_url" 
                               placeholder="https://linkedin.com/in/johndoe">
                    </div>

                    <!-- Current Location -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Current Location <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="current_location" required>
                            <option value="">Select location...</option>
                            <?php foreach ($locations as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === 'Belgium' ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Professional Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-briefcase"></i> Professional Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Current Position -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Current Position</label>
                        <input type="text" class="form-control" name="current_position" 
                               placeholder="Senior Java Developer">
                    </div>

                    <!-- Current Employer -->
                    <div class="col-md-6">
                        <label class="form-label">Current Employer</label>
                        <input type="text" class="form-control" name="current_employer" 
                               placeholder="Microsoft Belgium">
                    </div>

                    <!-- Current Agency -->
                    <div class="col-md-6">
                        <label class="form-label">Current Agency</label>
                        <input type="text" class="form-control" name="current_agency" 
                               placeholder="If working through agency">
                    </div>

                    <!-- Working Status -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Working Status</label>
                        <select class="form-select" name="current_working_status">
                            <option value="">Select status...</option>
                            <option value="Employee">Employee (Permanent)</option>
                            <option value="Freelance_Self">Freelance (Self-employed)</option>
                            <option value="Freelance_Company">Freelance (Through Company)</option>
                            <option value="Unemployed">Unemployed</option>
                        </select>
                    </div>

                    <!-- Work Authorization -->
                    <div class="col-md-6">
                        <label class="form-label">Work Authorization</label>
                        <select class="form-select" name="work_authorization_id">
                            <option value="">Select authorization...</option>
                            <?php foreach ($workAuths as $auth): ?>
                                <option value="<?= $auth['id'] ?>">
                                    <?= htmlspecialchars($auth['status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                     <!-- Willing to Join -->
                    <div class="col-md-6">
                        <label class="form-label">Willing to Join</label>
                        <select class="form-select" name="willing_to_join">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div>                               
                    <!-- Professional Summary -->
                    <div class="col-12">
                        <label class="form-label">Professional Summary</label>
                        <textarea class="form-control" name="professional_summary" rows="4" 
                                  placeholder="Brief overview of candidate's professional background, key skills, and experience..."></textarea>
                        <small class="text-muted">This will be visible on the candidate's profile</small>
                    </div>
                </div>
            </div>
        </div>
<!-- Skills Selection -->
<div class="card mb-3">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="bx bx-code-alt"></i> Technical Skills
        </h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold">Select Skills</label>
                <select class="form-select select2-multiple" id="skillsSelect" name="skills[]" multiple="multiple" required>
                    <!-- Populated via AJAX from skill_taxonomy -->
                </select>
                <small class="text-muted">Type to search skills. Add missing skills using "Add Skill"</small>
            </div>
            
            <div class="col-12 mt-3" id="skillDetails">
                <!-- Dynamically populated with proficiency levels and years -->
            </div>
            
            <div class="col-12 mt-2">
                <button type="button" class="btn btn-sm btn-outline-primary" id="addCustomSkill">
                    <i class='bx bx-plus'></i> Add Skill
                </button>
            </div>
        </div>
    </div>
</div>


        <!-- Languages -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-globe"></i> Languages
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Languages Spoken</label>
                        <div class="row">
                            <?php foreach ($availableLanguages as $lang): ?>
                                <div class="col-md-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="languages[]" 
                                               value="<?= $lang ?>" id="lang_<?= $lang ?>">
                                        <label class="form-check-label" for="lang_<?= $lang ?>">
                                            <?= $lang ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Availability -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-calendar"></i> Availability
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Notice Period -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Notice Period <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="notice_period_days" required>
                            <option value="">Select notice period...</option>
                            <?php foreach ($noticePeriods as $days => $label): ?>
                                <option value="<?= $days ?>" <?= $days === 30 ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Notice periods</small>
                    </div>

                    <!-- Available From -->
                    <div class="col-md-6">
                        <label class="form-label">Available From</label>
                        <input type="date" class="form-control" name="available_from" 
                               min="<?= date('Y-m-d') ?>">
                        <small class="text-muted">When can they start?</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compensation -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-euro"></i> Compensation Expectations
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <div class="alert alert-info mb-3">
                            <small>
                                <i class="bx bx-info-circle"></i>
                                <strong>Note:</strong> Fill either Annual Salary OR Daily Rate, depending on employment type.
                            </small>
                        </div>
                    </div>

                    <!-- Current Salary -->
                    <div class="col-md-6">
                        <label class="form-label">Current Annual Salary (€)</label>
                        <input type="number" class="form-control" name="current_salary" 
                               placeholder="50000" min="0" step="1000">
                        <small class="text-muted">For permanent employees</small>
                    </div>

                    <!-- Expected Salary -->
                    <div class="col-md-6">
                        <label class="form-label">Expected Annual Salary (€)</label>
                        <input type="number" class="form-control" name="expected_salary" 
                               placeholder="60000" min="0" step="1000">
                    </div>

                    <!-- Current Daily Rate -->
                    <div class="col-md-6">
                        <label class="form-label">Current Daily Rate (€)</label>
                        <input type="number" class="form-control" name="current_daily_rate" 
                               placeholder="500" min="0" step="50">
                        <small class="text-muted">For freelancers/contractors</small>
                    </div>

                    <!-- Expected Daily Rate -->
                    <div class="col-md-6">
                        <label class="form-label">Expected Daily Rate (€)</label>
                        <input type="number" class="form-control" name="expected_daily_rate" 
                               placeholder="550" min="0" step="50">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Information -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-target-lock"></i> Lead Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Lead Type -->
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Lead Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="lead_type" required>
                            <option value="Cold">❄️ Cold Lead</option>
                            <option value="Warm" selected>⚡ Warm Lead</option>
                            <option value="Hot">🔥 Hot Lead</option>
                        </select>
                    </div>

                    <!-- Lead Type Role -->
                    <div class="col-md-6">
                        <label class="form-label">Lead Type Role</label>
                        <select class="form-select" name="lead_type_role">
                            <option value="">Select role...</option>
                            <option value="Payroll">Payroll</option>
                            <option value="Recruitment">Recruitment</option>
                            <option value="InProgress">In Progress</option>
                            <option value="WaitingConfirmation">Waiting Confirmation</option>
                        </select>
                    </div>

                    <!-- Assigned To -->
                    <?php if (!empty($recruiters)): ?>
                        <div class="col-md-6">
                            <label class="form-label">Assign To Recruiter</label>
                            <select class="form-select" name="assigned_to">
                                <option value="">Unassigned</option>
                                <option value="<?= $userCode ?>" selected>Assign to me</option>
                                <?php foreach ($recruiters as $recruiter): ?>
                                    <?php if ($recruiter['user_code'] !== $userCode): ?>
                                        <option value="<?= htmlspecialchars($recruiter['user_code']) ?>">
                                            <?= htmlspecialchars($recruiter['name']) ?> (<?= htmlspecialchars($recruiter['level']) ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- -- Candidate Source -->
        <div class="card mb-3 border-primary">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="bx bx-link"></i> Candidate Source
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            How did you receive this candidate? 
                        </label>
                        <select class="form-select" name="candidate_source" required>
                            <option value="">Select source...</option>
                            <?php foreach ($candidateSources as $value => $label): ?>
                                <option value="<?= $value ?>">
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    


        <!-- Documents -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-file"></i> Documents
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- CV Upload -->
                    <div class="col-md-6">
                        <label class="form-label">Upload CV/Resume</label>
                        <input type="file" class="form-control" name="candidate_cv" 
                               accept=".pdf,.doc,.docx">
                        <small class="text-muted">Supported: PDF, DOC, DOCX (Max 5MB)</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notes -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-note"></i> Internal Notes
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Internal Notes</label>
                        <textarea class="form-control" name="internal_notes" rows="4" 
                                  placeholder="Private notes about this candidate (not visible to candidate)..."></textarea>
                        <small class="text-muted">These notes are internal and confidential</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Extra Details</label>
                        <textarea class="form-control" name="extra_details" rows="3" 
                                  placeholder="Any additional information..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-end gap-2">
                    <a href="/panel/modules/candidates/list.php" class="btn btn-secondary">
                        <i class="bx bx-x"></i> Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i> Create Candidate
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Include Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
// Initialize Select2 for skills
$(document).ready(function() {
    $('#skillsSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Type to search skills...',
        allowClear: true,
        tags: true,
        ajax: {
            url: '/panel/modules/candidates/handlers/search-skills.php',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    q: params.term,
                    page: params.page || 1
                };
            },
            processResults: function(data) {
                return {
                    results: data.results.map(skill => ({
                        id: skill.skill_name,
                        text: skill.skill_name
                    }))
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });
    
    // When skills are selected, show proficiency inputs
    $('#skillsSelect').on('change', function() {
        const selectedSkills = $(this).select2('data');
        updateProficiencyInputs(selectedSkills);
    });
});

function updateProficiencyInputs(skills) {
    const container = document.getElementById('proficiencyLevels');
    const wrapper = document.getElementById('proficiencyContainer');
    
    if (skills.length === 0) {
        wrapper.style.display = 'none';
        container.innerHTML = '';
        return;
    }
    
    wrapper.style.display = 'block';
    
    let html = '';
    skills.forEach((skill, index) => {
        const skillName = skill.text;
        const safeName = skillName.replace(/[^a-zA-Z0-9]/g, '_');
        
        html += `
            <div class="row mb-3 align-items-center border-bottom pb-2">
                <div class="col-md-4">
                    <strong>${skillName}</strong>
                </div>
                <div class="col-md-2">
                    <input type="number" name="skill_years[${skillName}]" 
                           class="form-control form-control-sm" 
                           placeholder="Years" min="0" max="50">
                </div>
                <div class="col-md-3">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" 
                               name="skill_primary[${skillName}]" value="1"
                               id="primary_${safeName}">
                        <label class="form-check-label" for="primary_${safeName}">
                            Primary skill
                        </label>
                    </div>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Form validation
document.getElementById('candidateForm').addEventListener('submit', function(e) {
    const name = document.querySelector('[name="candidate_name"]').value;
    const email = document.querySelector('[name="email"]').value;
    const phone = document.querySelector('[name="phone"]').value;
    const source = document.querySelector('[name="candidate_source"]').value;
    
    if (!name || !email || !phone || !source) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    return true;
});
// Resume parsing
document.getElementById('parseResumeBtn')?.addEventListener('click', async function() {
    const fileInput = document.getElementById('resumeFile');
    const file = fileInput.files[0];
    
    if (!file) {
        alert('Please select a resume file first');
        return;
    }
    
    // Validate file type
    const validTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    const validExtensions = ['pdf', 'docx'];
    const fileExt = file.name.split('.').pop().toLowerCase();
    
    if (!validTypes.includes(file.type) && !validExtensions.includes(fileExt)) {
        alert('Please upload a PDF or DOCX file');
        return;
    }
    
    // Show loading
    document.getElementById('parseStatus').style.display = 'block';
    document.getElementById('parseResults').style.display = 'none';
    this.disabled = true;
    
    try {
        // Create form data
        const formData = new FormData();
        formData.append('resume', file);
        
        // Send to parser
        const response = await fetch('/panel/modules/candidates/handlers/parse_resume.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        // Hide loading
        document.getElementById('parseStatus').style.display = 'none';
        this.disabled = false;
        
        if (result.success) {
            // Show success message
            const resultsDiv = document.getElementById('parseResults');
            resultsDiv.innerHTML = `
                <div class="alert alert-success">
                    <i class="bx bx-check-circle"></i>
                    <strong>Success!</strong> ${result.message}
                    <br><small>Found ${result.data.skills.length} skills. Review the pre-filled fields below.</small>
                </div>
            `;
            resultsDiv.style.display = 'block';
            
            // Pre-fill form fields
            if (result.data.name) {
                document.querySelector('[name="candidate_name"]').value = result.data.name;
            }
            if (result.data.email) {
                document.querySelector('[name="email"]').value = result.data.email;
            }
            if (result.data.phone) {
                document.querySelector('[name="phone"]').value = result.data.phone;
            }
            if (result.data.linkedin_url) {
                document.querySelector('[name="linkedin_url"]').value = result.data.linkedin_url;
            }
            if (result.data.location) {
                document.querySelector('[name="current_location"]').value = result.data.location;
            }
            
            // Pre-populate skills
            if (result.data.skills.length > 0) {
                // Clear existing skills
                $('#skillsSelect').val(null).trigger('change');
                
                // Add parsed skills
                result.data.skills.forEach(skill => {
                    const option = new Option(skill.skill_name, skill.skill_name, true, true);
                    $('#skillsSelect').append(option);
                });
                
                $('#skillsSelect').trigger('change');
                
                // Scroll to skills section
                document.querySelector('#skillsSelect').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }
            
        } else {
            // Show error
            const resultsDiv = document.getElementById('parseResults');
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bx bx-error-circle"></i>
                    <strong>Error:</strong> ${result.message}
                    <br><small>You can still fill the form manually.</small>
                </div>
            `;
            resultsDiv.style.display = 'block';
        }
        
    } catch (error) {
        document.getElementById('parseStatus').style.display = 'none';
        this.disabled = false;
        
        alert('Error parsing resume: ' + error.message);
    }
});
// Initialize skills selection with AJAX
$(document).ready(function() {
    $('#skillsSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search skills...',
        minimumInputLength: 2,
        ajax: {
            url: '/api/skills/search',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return { q: params.term };
            },
            processResults: function(data) {
                return {
                    results: data.map(skill => ({
                        id: skill.skill_id,
                        text: skill.skill_name,
                        category: skill.category
                    }))
                };
            }
        }
    });
    
    // Show skill details when skills are selected
    $('#skillsSelect').on('change', function() {
        const selectedSkills = $(this).select2('data');
        updateSkillDetails(selectedSkills);
    });
});

function updateSkillDetails(skills) {
    if (skills.length === 0) {
        $('#skillDetails').html('');
        return;
    }
    
    let html = '<div class="row g-3">';
    skills.forEach(skill => {
        const skillId = skill.id;
        const skillName = skill.text;
        html += `
        <div class="col-md-6 border p-3 mb-2 rounded" id="skill-${skillId}-container">
            <h6 class="mb-3">${skillName}</h6>
            <div class="mb-3">
                <label class="form-label">Proficiency Level</label>
                <select class="form-select" name="skill_proficiency[${skillId}]">
                    <option value="beginner">Beginner</option>
                    <option value="intermediate" selected>Intermediate</option>
                    <option value="advanced">Advanced</option>
                    <option value="expert">Expert</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label">Years of Experience</label>
                <input type="number" class="form-control" name="skill_years[${skillId}]" 
                       min="0" max="30" step="0.5" value="2">
            </div>
            <div class="form-check">
                <input class="form-check-input" type="checkbox" 
                       name="skill_primary[${skillId}]" id="primary-${skillId}">
                <label class="form-check-label" for="primary-${skillId}">
                    Primary Skill
                </label>
            </div>
        </div>
        `;
    });
    html += '</div>';
    $('#skillDetails').html(html);
}

</script>
<?php
require_once __DIR__ . '/../../includes/footer.php';
?>