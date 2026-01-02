<?php
/**
 * Create New Candidate
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken};

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

// Get work authorizations
$stmt = $conn->prepare("SELECT id, status_name FROM work_authorization WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$workAuths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all recruiters for assignment
$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $recruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Include Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- Page Content -->
<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1"><?= $pageTitle ?></h4>
            <p class="text-muted mb-0">Add a new candidate to the system</p>
        </div>
        <a href="/panel/modules/candidates/list.php" class="btn btn-secondary">
            <i class="bx bx-arrow-back"></i> Back to List
        </a>
    </div>

    <form method="POST" action="/panel/modules/candidates/handlers/create.php" id="candidateForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
        
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
                        <label class="form-label">
                            Full Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control" name="candidate_name" required 
                               placeholder="John Doe">
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" name="email" required 
                               placeholder="john.doe@example.com">
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label class="form-label">
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
                        <label class="form-label">
                            Current Location <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="current_location" required>
                            <option value="">Select location...</option>
                            <?php foreach (CANDIDATE_LOCATIONS as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
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
                    <!-- Current Employer -->
                    <div class="col-md-6">
                        <label class="form-label">Current Employer</label>
                        <input type="text" class="form-control" name="current_employer" 
                               placeholder="Company name">
                    </div>

                    <!-- Current Position -->
                    <div class="col-md-6">
                        <label class="form-label">Current Position</label>
                        <input type="text" class="form-control" name="current_position" 
                               placeholder="Senior Developer">
                    </div>

                    <!-- Current Agency -->
                    <div class="col-md-6">
                        <label class="form-label">Current Agency (if any)</label>
                        <input type="text" class="form-control" name="current_agency" 
                               placeholder="Agency name">
                    </div>

                    <!-- Current Working Status -->
                    <div class="col-md-6">
                        <label class="form-label">Current Working Status</label>
                        <select class="form-select" name="current_working_status">
                            <option value="">Select...</option>
                            <?php foreach (CURRENT_WORKING_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Work Authorization -->
                    <div class="col-md-6">
                        <label class="form-label">Work Authorization</label>
                        <select class="form-select" name="work_authorization_id">
                            <option value="">Select...</option>
                            <?php foreach ($workAuths as $auth): ?>
                                <option value="<?= $auth['id'] ?>">
                                    <?= htmlspecialchars($auth['status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Languages -->
                    <div class="col-md-6">
                        <label class="form-label">Languages (comma separated)</label>
                        <input type="text" class="form-control" name="languages" 
                               placeholder="English, French, Dutch">
                        <small class="text-muted">Separate multiple languages with commas</small>
                    </div>

                    <!-- Role Addressed -->
                    <div class="col-12">
                        <label class="form-label">Role Addressed / Applied For</label>
                        <textarea class="form-control" name="role_addressed" rows="2" 
                                  placeholder="Which role is this candidate applying for or being considered for?"></textarea>
                    </div>
                    <!-- Professional Summary -->
                    <div class="col-12">
                        <label class="form-label">Professional Summary</label>
                        <textarea class="form-control" name="professional_summary" rows="4" 
                                  placeholder="Brief overview of experience, skills, and expertise..."></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Skills -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-code-alt"></i> Technical Skills
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Select Skills</label>
                        <select class="form-select" name="skills[]" id="skillsSelect" multiple="multiple">
                        </select>
                        <small class="text-muted">Type to search and add skills. You can add multiple skills.</small>
                    </div>

                    <!-- Proficiency levels container (populated by JS) -->
                    <div class="col-12" id="proficiencyContainer" style="display: none;">
                        <label class="form-label">Set Proficiency Levels</label>
                        <div id="proficiencyLevels" class="border rounded p-3 bg-light">
                            <!-- Dynamically populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compensation -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-euro"></i> Compensation & Availability
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Current Salary -->
                    <div class="col-md-6">
                        <label class="form-label">Current Salary (Annual)</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="current_salary" 
                                   step="1000" placeholder="50000">
                        </div>
                        <small class="text-muted">For employees</small>
                    </div>

                    <!-- Expected Salary -->
                    <div class="col-md-6">
                        <label class="form-label">Expected Salary (Annual)</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="expected_salary" 
                                   step="1000" placeholder="60000">
                        </div>
                    </div>

                    <!-- Current Daily Rate -->
                    <div class="col-md-6">
                        <label class="form-label">Current Daily Rate</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="current_daily_rate" 
                                   step="50" placeholder="500">
                            <span class="input-group-text">/day</span>
                        </div>
                        <small class="text-muted">For freelancers</small>
                    </div>

                    <!-- Expected Daily Rate -->
                    <div class="col-md-6">
                        <label class="form-label">Expected Daily Rate</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="expected_daily_rate" 
                                   step="50" placeholder="600">
                            <span class="input-group-text">/day</span>
                        </div>
                    </div>

                    <!-- Notice Period -->
                    <div class="col-md-6">
                        <label class="form-label">Notice Period</label>
                        <select class="form-select" name="notice_period_days">
                            <option value="">Select...</option>
                            <?php foreach (NOTICE_PERIODS as $days => $label): ?>
                                <option value="<?= $days ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Available From -->
                    <div class="col-md-6">
                        <label class="form-label">Available From</label>
                        <input type="date" class="form-control" name="available_from" 
                               min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Lead Management -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-target-lock"></i> Lead Management
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Lead Type -->
                    <div class="col-md-6">
                        <label class="form-label">Lead Type</label>
                        <select class="form-select" name="lead_type">
                            <?php foreach (LEAD_TYPES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $value === 'Warm' ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Lead Type Role -->
                    <div class="col-md-6">
                        <label class="form-label">Lead Type Role</label>
                        <select class="form-select" name="lead_type_role">
                            <option value="">Select...</option>
                            <?php foreach (LEAD_TYPE_ROLES as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Follow-up Status -->
                    <div class="col-md-6">
                        <label class="form-label">Follow-up Status</label>
                        <select class="form-select" name="follow_up_status">
                            <?php foreach (FOLLOW_UP_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Follow-up Date -->
                    <div class="col-md-6">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" class="form-control" name="follow_up_date" 
                               min="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Assigned To -->
                    <?php if (Permission::can('candidates', 'assign') && !empty($recruiters)): ?>
                    <div class="col-md-6">
                        <label class="form-label">Assign To</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($recruiters as $recruiter): ?>
                                <option value="<?= $recruiter['user_code'] ?>" 
                                        <?= $recruiter['user_code'] === Auth::userCode() ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($recruiter['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- GDPR Consent -->
        <!-- <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="bx bx-shield-check"></i> GDPR Consent
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <!-- Consent Given -->
                    <div class="col-md-6">
                        <label class="form-label">Consent Given</label>
                        <select class="form-select" name="consent_given">
                            <option value="0">No</option>
                            <option value="1">Yes</option>
                        </select>
                    </div> -->

                    <!-- Consent Date -->
                    <div class="col-md-6">
                        <label class="form-label">Consent Date</label>
                        <input type="date" class="form-control" name="consent_date" 
                               max="<?= date('Y-m-d') ?>">
                    </div>

                    <!-- Consent Type -->
                    <div class="col-md-6">
                        <label class="form-label">Consent Type</label>
                        <select class="form-select" name="consent_type">
                            <option value="">Select...</option>
                            <?php foreach (CONSENT_TYPES as $value => $label): ?>
                                <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
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
                                  placeholder="Private notes about this candidate..."></textarea>
                        <small class="text-muted">These notes are internal and not visible to the candidate</small>
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
                        id: skill.id,
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
        html += `
            <div class="row mb-2 align-items-center">
                <div class="col-md-6">
                    <strong>${skill.text}</strong>
                </div>
                <div class="col-md-6">
                    <select name="proficiency[${skill.id}]" class="form-select form-select-sm" required>
                        <option value="">Select level...</option>
                        <?php foreach (SKILL_PROFICIENCY_LEVELS as $value => $label): ?>
                            <option value="<?= $value ?>" ${index === 0 && '<?= $value ?>' === 'Intermediate' ? 'selected' : ''}>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

// Form validation
document.getElementById('candidateForm').addEventListener('submit', function(e) {
    const skills = $('#skillsSelect').val();
    
    if (skills && skills.length > 0) {
        // Check if all proficiency levels are set
        const proficiencySelects = document.querySelectorAll('#proficiencyLevels select');
        let allSet = true;
        
        proficiencySelects.forEach(select => {
            if (!select.value) {
                allSet = false;
                select.classList.add('is-invalid');
            } else {
                select.classList.remove('is-invalid');
            }
        });
        
        if (!allSet) {
            e.preventDefault();
            alert('Please set proficiency level for all selected skills');
            return false;
        }
    }
});

// Languages input - convert to JSON array format
document.getElementById('candidateForm').addEventListener('submit', function(e) {
    const languagesInput = document.querySelector('input[name="languages"]');
    if (languagesInput && languagesInput.value) {
        const languages = languagesInput.value.split(',').map(l => l.trim()).filter(l => l);
        // Create a hidden input with JSON array
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'languages_json';
        hiddenInput.value = JSON.stringify(languages);
        this.appendChild(hiddenInput);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>