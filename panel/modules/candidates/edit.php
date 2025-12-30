<?php
/**
 * Edit Candidate
 * Same structure as create but with existing data
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('candidates', 'edit');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get candidate code
$candidateCode = input('code');
if (empty($candidateCode)) {
    redirectWithMessage('/panel/modules/candidates/list.php', 'Candidate not found', 'error');
}

// Get candidate with access check
$accessFilter = Permission::getAccessibleCandidates();
$whereClause = $accessFilter ? "candidate_code = ? AND ({$accessFilter})" : "candidate_code = ?";

$stmt = $conn->prepare("SELECT * FROM candidates WHERE {$whereClause}");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!$candidate) {
    redirectWithMessage('/panel/modules/candidates/list.php', 'Candidate not found or no access', 'error');
}

// Page configuration
$pageTitle = 'Edit Candidate - ' . $candidate['candidate_name'];
$breadcrumbs = [
    ['title' => 'Candidates', 'url' => '/panel/modules/candidates/list.php'],
    ['title' => $candidate['candidate_name'], 'url' => '/panel/modules/candidates/view.php?code=' . $candidateCode],
    ['title' => 'Edit', 'url' => '']
];
$customJS = ['/panel/assets/js/modules/candidates-form.js'];

// Get skills and recruiters (same as create.php)
$skillsSql = "SELECT DISTINCT skill_name FROM skills ORDER BY skill_name";
$skills = $conn->query($skillsSql)->fetch_all(MYSQLI_ASSOC);

$recruiters = [];
if (Permission::can('candidates', 'assign')) {
    $recruitersSql = "SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager', 'admin') AND is_active = 1 ORDER BY name";
    $recruiters = $conn->query($recruitersSql)->fetch_all(MYSQLI_ASSOC);
}

// Parse skills for multi-select
$candidateSkills = !empty($candidate['skills']) ? explode(',', $candidate['skills']) : [];
$candidateSkills = array_map('trim', $candidateSkills);

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Edit Form - Similar to create.php but with values -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bx bx-edit text-primary me-2"></i>
                        Edit Candidate: <?= htmlspecialchars($candidate['candidate_name']) ?>
                    </h5>
                    <div>
                        <a href="/panel/modules/candidates/view.php?code=<?= urlencode($candidateCode) ?>" 
                           class="btn btn-sm btn-outline-secondary me-2">
                            <i class="bx bx-show"></i> View Profile
                        </a>
                        <a href="/panel/modules/candidates/list.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bx bx-arrow-back"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Tabs (same as create.php) -->
                <ul class="nav nav-tabs nav-fill mb-4" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="tab-basic" data-bs-toggle="tab" data-bs-target="#basic-info" type="button" role="tab">
                            <i class="bx bx-user me-2"></i>Basic Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-professional" data-bs-toggle="tab" data-bs-target="#professional-info" type="button" role="tab">
                            <i class="bx bx-briefcase me-2"></i>Professional Details
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-additional" data-bs-toggle="tab" data-bs-target="#additional-info" type="button" role="tab">
                            <i class="bx bx-detail me-2"></i>Additional Information
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="tab-documents" data-bs-toggle="tab" data-bs-target="#documents" type="button" role="tab">
                            <i class="bx bx-file me-2"></i>Documents
                        </button>
                    </li>
                </ul>

                <form id="candidateForm" method="POST" action="/panel/modules/candidates/handlers/update.php" data-validate>
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode) ?>">
                    
                    <div class="tab-content">
                        <!-- Basic Info Tab (with values) -->
                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="candidate_name" 
                                           value="<?= htmlspecialchars($candidate['candidate_name']) ?>" 
                                           required data-rules="required|min:2">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?= htmlspecialchars($candidate['email']) ?>" 
                                           required data-rules="required|email">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" 
                                           value="<?= htmlspecialchars($candidate['phone']) ?>" 
                                           required data-rules="required|phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Alternative Phone</label>
                                    <input type="tel" class="form-control" name="phone_alternate" 
                                           value="<?= htmlspecialchars($candidate['phone_alternate'] ?? '') ?>" 
                                           data-rules="phone">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Location</label>
                                    <input type="text" class="form-control" name="current_location" 
                                           value="<?= htmlspecialchars($candidate['current_location'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Preferred Location</label>
                                    <input type="text" class="form-control" name="preferred_location" 
                                           value="<?= htmlspecialchars($candidate['preferred_location'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Work Authorization <span class="text-danger">*</span></label>
                                    <select class="form-select" name="work_authorization_status" required>
                                        <option value="">Select...</option>
                                        <option value="eu_citizen" <?= $candidate['work_authorization_status'] === 'eu_citizen' ? 'selected' : '' ?>>EU Citizen</option>
                                        <option value="work_permit" <?= $candidate['work_authorization_status'] === 'work_permit' ? 'selected' : '' ?>>Work Permit</option>
                                        <option value="requires_sponsorship" <?= $candidate['work_authorization_status'] === 'requires_sponsorship' ? 'selected' : '' ?>>Requires Sponsorship</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">LinkedIn URL</label>
                                    <input type="url" class="form-control" name="linkedin_url" 
                                           value="<?= htmlspecialchars($candidate['linkedin_url'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-end">
                                <button type="button" class="btn btn-primary" onclick="nextTab()">Next <i class="bx bx-chevron-right"></i></button>
                            </div>
                        </div>

                        <!-- Professional Tab (with values - abbreviated for space) -->
                        <div class="tab-pane fade" id="professional-info" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Current Position</label>
                                    <input type="text" class="form-control" name="current_position" 
                                           value="<?= htmlspecialchars($candidate['current_position'] ?? '') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Current Company</label>
                                    <input type="text" class="form-control" name="current_company" 
                                           value="<?= htmlspecialchars($candidate['current_company'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Total Experience (Years)</label>
                                    <input type="number" class="form-control" name="total_experience" 
                                           value="<?= htmlspecialchars($candidate['total_experience'] ?? '') ?>" 
                                           step="0.5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Relevant Experience (Years)</label>
                                    <input type="number" class="form-control" name="relevant_experience" 
                                           value="<?= htmlspecialchars($candidate['relevant_experience'] ?? '') ?>" 
                                           step="0.5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Notice Period (Days)</label>
                                    <input type="number" class="form-control" name="notice_period" 
                                           value="<?= htmlspecialchars($candidate['notice_period'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Skills <span class="text-danger">*</span></label>
                                    <select class="form-select" name="skills[]" multiple required>
                                        <?php foreach ($skills as $skill): ?>
                                        <option value="<?= htmlspecialchars($skill['skill_name']) ?>"
                                                <?= in_array($skill['skill_name'], $candidateSkills) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($skill['skill_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Additional professional fields... -->
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()"><i class="bx bx-chevron-left"></i> Previous</button>
                                <button type="button" class="btn btn-primary" onclick="nextTab()">Next <i class="bx bx-chevron-right"></i></button>
                            </div>
                        </div>

                        <!-- Additional Info Tab (with values) -->
                        <div class="tab-pane fade" id="additional-info" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label">Compensation Type</label>
                                    <select class="form-select" name="compensation_type">
                                        <option value="salary" <?= ($candidate['compensation_type'] ?? 'salary') === 'salary' ? 'selected' : '' ?>>Salary</option>
                                        <option value="hourly" <?= ($candidate['compensation_type'] ?? '') === 'hourly' ? 'selected' : '' ?>>Hourly</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Current</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" class="form-control" name="current_compensation" 
                                               value="<?= htmlspecialchars($candidate['current_compensation'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Expected</label>
                                    <div class="input-group">
                                        <span class="input-group-text">€</span>
                                        <input type="number" class="form-control" name="expected_compensation" 
                                               value="<?= htmlspecialchars($candidate['expected_compensation'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Rating</label>
                                    <select class="form-select" name="rating">
                                        <option value="0" <?= ($candidate['rating'] ?? 0) == 0 ? 'selected' : '' ?>>Not Rated</option>
                                        <option value="1" <?= ($candidate['rating'] ?? 0) == 1 ? 'selected' : '' ?>>★ Poor</option>
                                        <option value="2" <?= ($candidate['rating'] ?? 0) == 2 ? 'selected' : '' ?>>★★ Fair</option>
                                        <option value="3" <?= ($candidate['rating'] ?? 0) == 3 ? 'selected' : '' ?>>★★★ Good</option>
                                        <option value="4" <?= ($candidate['rating'] ?? 0) == 4 ? 'selected' : '' ?>>★★★★ Very Good</option>
                                        <option value="5" <?= ($candidate['rating'] ?? 0) == 5 ? 'selected' : '' ?>>★★★★★ Excellent</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="active" <?= $candidate['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="placed" <?= $candidate['status'] === 'placed' ? 'selected' : '' ?>>Placed</option>
                                        <option value="archived" <?= $candidate['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Lead Type</label>
                                    <select class="form-select" name="lead_type">
                                        <option value="cold" <?= $candidate['lead_type'] === 'cold' ? 'selected' : '' ?>>❄️ Cold</option>
                                        <option value="warm" <?= $candidate['lead_type'] === 'warm' ? 'selected' : '' ?>>⏰ Warm</option>
                                        <option value="hot" <?= $candidate['lead_type'] === 'hot' ? 'selected' : '' ?>>🔥 Hot</option>
                                        <option value="blacklist" <?= $candidate['lead_type'] === 'blacklist' ? 'selected' : '' ?>>🚫 Blacklist</option>
                                    </select>
                                </div>
                                <?php if (!empty($recruiters)): ?>
                                <div class="col-md-4">
                                    <label class="form-label">Assigned To</label>
                                    <select class="form-select" name="assigned_to">
                                        <option value="">Unassigned</option>
                                        <?php foreach ($recruiters as $recruiter): ?>
                                        <option value="<?= htmlspecialchars($recruiter['user_code']) ?>"
                                                <?= $candidate['assigned_to'] === $recruiter['user_code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($recruiter['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <?php endif; ?>
                                <div class="col-12">
                                    <label class="form-label">Internal Notes</label>
                                    <textarea class="form-control" name="notes" rows="4"><?= htmlspecialchars($candidate['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()"><i class="bx bx-chevron-left"></i> Previous</button>
                                <button type="button" class="btn btn-primary" onclick="nextTab()">Next <i class="bx bx-chevron-right"></i></button>
                            </div>
                        </div>

                        <!-- Documents Tab -->
                        <div class="tab-pane fade" id="documents" role="tabpanel">
                            <p class="text-muted">Document upload functionality available in View page.</p>
                            <div class="mt-4 d-flex justify-content-between">
                                <button type="button" class="btn btn-outline-secondary" onclick="prevTab()"><i class="bx bx-chevron-left"></i> Previous</button>
                                <button type="submit" class="btn btn-success">
                                    <i class="bx bx-save"></i> Update Candidate
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function nextTab() { document.querySelector('.nav-link.active').closest('li').nextElementSibling?.querySelector('.nav-link').click(); }
function prevTab() { document.querySelector('.nav-link.active').closest('li').previousElementSibling?.querySelector('.nav-link').click(); }
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>