<?php
/**
 * Edit Candidate
 * Update existing candidate with all fields
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken};

// Check permission
if (!Permission::can('candidates', 'edit')) {
    header('Location: /panel/errors/403.php');
    exit;
}

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get candidate code
$candidateCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
if (empty($candidateCode)) {
    header('Location: /panel/modules/candidates/list.php');
    exit;
}

// Load existing candidate with ownership check
$accessCheck = '';
if (!Permission::can('candidates', 'edit_all')) {
    if (Permission::can('candidates', 'edit_own')) {
        $accessCheck = ' AND c.created_by = ?';
    } else {
        header('Location: /panel/errors/403.php');
        exit;
    }
}

$sql = "SELECT c.* FROM candidates c WHERE c.candidate_code = ? AND c.deleted_at IS NULL" . $accessCheck;
$stmt = $conn->prepare($sql);

if ($accessCheck) {
    $stmt->bind_param("ss", $candidateCode, Auth::userCode());
} else {
    $stmt->bind_param("s", $candidateCode);
}

$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!$candidate) {
    header('Location: /panel/modules/candidates/list.php?error=' . urlencode('Candidate not found'));
    exit;
}

// Load existing skills
$stmt = $conn->prepare("
    SELECT cs.skill_id, ts.skill_name, cs.proficiency_level
    FROM candidate_skills cs
    JOIN technical_skills ts ON cs.skill_id = ts.id
    WHERE cs.candidate_code = ?
    ORDER BY ts.skill_name
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$existingSkills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Parse languages from JSON
$languages = '';
if (!empty($candidate['languages'])) {
    $langArray = json_decode($candidate['languages'], true);
    if (is_array($langArray)) {
        $languages = implode(', ', $langArray);
    }
}

// Get work authorizations
$stmt = $conn->prepare("SELECT id, status_name FROM work_authorization WHERE is_active = 1 ORDER BY display_order");
$stmt->execute();
$workAuths = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recruiters for assignment
$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name");
    $stmt->execute();
    $recruiters = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Page configuration
$pageTitle = 'Edit Candidate - ' . $candidate['candidate_name'];
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => $candidate['candidate_name'], 'url' => '/panel/modules/candidates/view.php?code=' . $candidateCode],
    ['title' => 'Edit', 'url' => '']
];

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
            <p class="text-muted mb-0">Update candidate information</p>
        </div>
        <div class="btn-group">
            <a href="/panel/modules/candidates/view.php?code=<?= $candidateCode ?>" class="btn btn-secondary">
                <i class="bx bx-arrow-back"></i> Back to Profile
            </a>
            <a href="/panel/modules/candidates/list.php" class="btn btn-outline-secondary">
                <i class="bx bx-list-ul"></i> All Candidates
            </a>
        </div>
    </div>

    <form method="POST" action="/panel/modules/candidates/handlers/update.php" id="candidateForm" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
        <input type="hidden" name="candidate_code" value="<?= $candidateCode ?>">
        
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
                               value="<?= htmlspecialchars($candidate['candidate_name']) ?>">
                    </div>

                    <!-- Email -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Email <span class="text-danger">*</span>
                        </label>
                        <input type="email" class="form-control" name="email" required 
                               value="<?= htmlspecialchars($candidate['email']) ?>">
                    </div>

                    <!-- Phone -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Phone <span class="text-danger">*</span>
                        </label>
                        <input type="tel" class="form-control" name="phone" required 
                               value="<?= htmlspecialchars($candidate['phone']) ?>">
                    </div>

                    <!-- Alternate Email -->
                    <div class="col-md-6">
                        <label class="form-label">Alternate Email</label>
                        <input type="email" class="form-control" name="alternate_email" 
                               value="<?= htmlspecialchars($candidate['alternate_email'] ?? '') ?>">
                    </div>

                    <!-- Alternate Phone -->
                    <div class="col-md-6">
                        <label class="form-label">Alternate Phone</label>
                        <input type="tel" class="form-control" name="phone_alternate" 
                               value="<?= htmlspecialchars($candidate['phone_alternate'] ?? '') ?>">
                    </div>

                    <!-- LinkedIn URL -->
                    <div class="col-md-6">
                        <label class="form-label">LinkedIn Profile</label>
                        <input type="url" class="form-control" name="linkedin_url" 
                               value="<?= htmlspecialchars($candidate['linkedin_url'] ?? '') ?>">
                    </div>

                    <!-- Current Location -->
                    <div class="col-md-6">
                        <label class="form-label">
                            Current Location <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="current_location" required>
                            <option value="">Select location...</option>
                            <?php foreach (CANDIDATE_LOCATIONS as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $candidate['current_location'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Willing to Join -->
                    <div class="col-md-6">
                        <label class="form-label">Willing to Join Immediately</label>
                        <select class="form-select" name="willing_to_join">
                            <option value="0" <?= $candidate['willing_to_join'] == 0 ? 'selected' : '' ?>>No</option>
                            <option value="1" <?= $candidate['willing_to_join'] == 1 ? 'selected' : '' ?>>Yes</option>
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
                    <div class="col-md-6">
                        <label class="form-label">Current Employer</label>
                        <input type="text" class="form-control" name="current_employer" 
                               value="<?= htmlspecialchars($candidate['current_employer'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Current Position</label>
                        <input type="text" class="form-control" name="current_position" 
                               value="<?= htmlspecialchars($candidate['current_position'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Current Agency (if any)</label>
                        <input type="text" class="form-control" name="current_agency" 
                               value="<?= htmlspecialchars($candidate['current_agency'] ?? '') ?>">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Current Working Status</label>
                        <select class="form-select" name="current_working_status">
                            <option value="">Select...</option>
                            <?php foreach (CURRENT_WORKING_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $candidate['current_working_status'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Work Authorization</label>
                        <select class="form-select" name="work_authorization_id">
                            <option value="">Select...</option>
                            <?php foreach ($workAuths as $auth): ?>
                                <option value="<?= $auth['id'] ?>" <?= $candidate['work_authorization_id'] == $auth['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($auth['status_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Languages (comma separated)</label>
                        <input type="text" class="form-control" name="languages" 
                               value="<?= htmlspecialchars($languages) ?>">
                        <small class="text-muted">Separate multiple languages with commas</small>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Professional Summary</label>
                        <textarea class="form-control" name="professional_summary" rows="4"><?= htmlspecialchars($candidate['professional_summary'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Role Addressed / Applied For</label>
                        <textarea class="form-control" name="role_addressed" rows="2"><?= htmlspecialchars($candidate['role_addressed'] ?? '') ?></textarea>
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
                            <?php foreach ($existingSkills as $skill): ?>
                                <option value="<?= $skill['skill_id'] ?>" selected>
                                    <?= htmlspecialchars($skill['skill_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Type to search and add more skills</small>
                    </div>

                    <div class="col-12" id="proficiencyContainer">
                        <label class="form-label">Set Proficiency Levels</label>
                        <div id="proficiencyLevels" class="border rounded p-3 bg-light">
                            <?php foreach ($existingSkills as $skill): ?>
                                <div class="row mb-2 align-items-center" data-skill-id="<?= $skill['skill_id'] ?>">
                                    <div class="col-md-6">
                                        <strong><?= htmlspecialchars($skill['skill_name']) ?></strong>
                                    </div>
                                    <div class="col-md-6">
                                        <select name="proficiency[<?= $skill['skill_id'] ?>]" class="form-select form-select-sm" required>
                                            <?php foreach (SKILL_PROFICIENCY_LEVELS as $value => $label): ?>
                                                <option value="<?= $value ?>" <?= $skill['proficiency_level'] === $value ? 'selected' : '' ?>>
                                                    <?= $label ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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
                    <div class="col-md-6">
                        <label class="form-label">Current Salary (Annual)</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="current_salary" step="1000"
                                   value="<?= $candidate['current_salary'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Expected Salary (Annual)</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="expected_salary" step="1000"
                                   value="<?= $candidate['expected_salary'] ?? '' ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Current Daily Rate</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="current_daily_rate" step="50"
                                   value="<?= $candidate['current_daily_rate'] ?? '' ?>">
                            <span class="input-group-text">/day</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Expected Daily Rate</label>
                        <div class="input-group">
                            <span class="input-group-text">€</span>
                            <input type="number" class="form-control" name="expected_daily_rate" step="50"
                                   value="<?= $candidate['expected_daily_rate'] ?? '' ?>">
                            <span class="input-group-text">/day</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Notice Period</label>
                        <select class="form-select" name="notice_period_days">
                            <option value="">Select...</option>
                            <?php foreach (NOTICE_PERIODS as $days => $label): ?>
                                <option value="<?= $days ?>" <?= $candidate['notice_period_days'] == $days ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Available From</label>
                        <input type="date" class="form-control" name="available_from" 
                               value="<?= $candidate['available_from'] ?? '' ?>">
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
                    <div class="col-md-6">
                        <label class="form-label">Lead Type</label>
                        <select class="form-select" name="lead_type">
                            <?php foreach (LEAD_TYPES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $candidate['lead_type'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Lead Type Role</label>
                        <select class="form-select" name="lead_type_role">
                            <option value="">Select...</option>
                            <?php foreach (LEAD_TYPE_ROLES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $candidate['lead_type_role'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Follow-up Status</label>
                        <select class="form-select" name="follow_up_status">
                            <?php foreach (FOLLOW_UP_STATUSES as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $candidate['follow_up_status'] === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Follow-up Date</label>
                        <input type="date" class="form-control" name="follow_up_date" 
                               value="<?= $candidate['follow_up_date'] ?? '' ?>">
                    </div>

                    <?php if (Permission::can('candidates', 'assign') && !empty($recruiters)): ?>
                    <div class="col-md-6">
                        <label class="form-label">Assign To</label>
                        <select class="form-select" name="assigned_to">
                            <option value="">Unassigned</option>
                            <?php foreach ($recruiters as $recruiter): ?>
                                <option value="<?= $recruiter['user_code'] ?>" 
                                        <?= $candidate['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($recruiter['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>
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
                    <?php if (!empty($candidate['candidate_cv'])): ?>
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="bx bx-file-blank"></i>
                                Current CV: <strong><?= basename($candidate['candidate_cv']) ?></strong>
                                <small class="text-muted">(uploaded <?= date('M d, Y', strtotime($candidate['cv_last_updated'] ?? $candidate['created_at'])) ?>)</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="form-label">Upload New CV/Resume</label>
                        <input type="file" class="form-control" name="candidate_cv" 
                               accept=".pdf,.doc,.docx">
                        <small class="text-muted">Upload to replace existing CV</small>
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
                        <textarea class="form-control" name="internal_notes" rows="4"><?= htmlspecialchars($candidate['internal_notes'] ?? '') ?></textarea>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Extra Details</label>
                        <textarea class="form-control" name="extra_details" rows="3"><?= htmlspecialchars($candidate['extra_details'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submit Buttons -->
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <small class="text-muted">
                            Last updated: <?= date('M d, Y H:i', strtotime($candidate['updated_at'])) ?>
                        </small>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="/panel/modules/candidates/view.php?code=<?= $candidateCode ?>" class="btn btn-secondary">
                            <i class="bx bx-x"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bx bx-save"></i> Update Candidate
                        </button>
                    </div>
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
    
    // When skills change, update proficiency inputs
    $('#skillsSelect').on('change', function() {
        const selectedSkills = $(this).select2('data');
        updateProficiencyInputs(selectedSkills);
    });
});

function updateProficiencyInputs(skills) {
    const container = document.getElementById('proficiencyLevels');
    
    // Build HTML for all selected skills
    let html = '';
    skills.forEach((skill) => {
        // Check if this skill already has a row
        const existingRow = container.querySelector(`[data-skill-id="${skill.id}"]`);
        const currentProficiency = existingRow ? 
            existingRow.querySelector('select').value : 
            'Intermediate';
        
        html += `
            <div class="row mb-2 align-items-center" data-skill-id="${skill.id}">
                <div class="col-md-6">
                    <strong>${skill.text}</strong>
                </div>
                <div class="col-md-6">
                    <select name="proficiency[${skill.id}]" class="form-select form-select-sm" required>
                        <?php foreach (SKILL_PROFICIENCY_LEVELS as $value => $label): ?>
                            <option value="<?= $value ?>" ${currentProficiency === '<?= $value ?>' ? 'selected' : ''}>
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

// Languages conversion
document.getElementById('candidateForm').addEventListener('submit', function(e) {
    const languagesInput = document.querySelector('input[name="languages"]');
    if (languagesInput && languagesInput.value) {
        const languages = languagesInput.value.split(',').map(l => l.trim()).filter(l => l);
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'languages_json';
        hiddenInput.value = JSON.stringify(languages);
        this.appendChild(hiddenInput);
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>