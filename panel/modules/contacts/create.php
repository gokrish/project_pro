<?php
/**
 * Create Contact Form
 * Lead/Prospect intake form
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('contacts', 'create');

$db = Database::getInstance();
$conn = $db->getConnection();

// Get recruiters for assignment dropdown
$recruitersQuery = "SELECT user_code, name FROM users WHERE is_active = 1 AND level IN ('recruiter', 'senior_recruiter', 'manager') ORDER BY name";
$recruiters = $conn->query($recruitersQuery);

// Get old input if validation failed
$old = $_SESSION['old'] ?? [];
unset($_SESSION['old']);

$pageTitle = 'Add New Contact';
$breadcrumbs = [
    ['title' => 'Contacts', 'url' => '/panel/modules/contacts/list.php'],
    ['title' => 'Add Contact', 'url' => '']
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
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">
                <i class="bx bx-user-plus text-primary me-2"></i>
                Add New Contact
            </h4>
            <p class="text-muted mb-0">Capture lead/prospect information before candidacy</p>
        </div>
        <a href="list.php" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Contacts
        </a>
    </div>

    <form id="contactForm" method="POST" action="handlers/create.php">
        <?= CSRFToken::field() ?>
        
        <!-- SECTION 1: BASIC INFORMATION -->
        <div class="form-section">
            <h5>
                <span class="section-number">1</span>
                Basic Information
            </h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="first_name" class="form-label">
                        First Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="first_name" name="first_name" 
                           value="<?= escape($old['first_name'] ?? '') ?>" required autofocus>
                </div>
                
                <div class="col-md-6">
                    <label for="last_name" class="form-label">
                        Last Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?= escape($old['last_name'] ?? '') ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">
                        Email <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= escape($old['email'] ?? '') ?>" required>
                    <div class="form-text">Primary contact email</div>
                </div>
                
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?= escape($old['phone'] ?? '') ?>" 
                           placeholder="+1 (555) 123-4567">
                </div>
                
                <div class="col-12">
                    <label for="linkedin_url" class="form-label">LinkedIn Profile</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bxl-linkedin"></i></span>
                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                               value="<?= escape($old['linkedin_url'] ?? '') ?>"
                               placeholder="https://linkedin.com/in/username">
                    </div>
                </div>
            </div>
        </div>
        
        <!-- SECTION 2: PROFESSIONAL INFORMATION -->
        <div class="form-section">
            <h5>
                <span class="section-number">2</span>
                Professional Information
            </h5>
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="current_company" class="form-label">Current Company</label>
                    <input type="text" class="form-control" id="current_company" name="current_company" 
                           value="<?= escape($old['current_company'] ?? '') ?>"
                           placeholder="e.g., Microsoft, Google">
                </div>
                
                <div class="col-md-6">
                    <label for="current_title" class="form-label">Current Job Title</label>
                    <input type="text" class="form-control" id="current_title" name="current_title" 
                           value="<?= escape($old['current_title'] ?? '') ?>"
                           placeholder="e.g., Senior Developer">
                </div>
                
                <div class="col-md-6">
                    <label for="years_of_experience" class="form-label">Years of Experience</label>
                    <input type="number" class="form-control" id="years_of_experience" name="years_of_experience" 
                           value="<?= $old['years_of_experience'] ?? '' ?>" 
                           min="0" max="50" step="0.5"
                           placeholder="e.g., 5">
                </div>
                
                <div class="col-md-6">
                    <label for="current_location" class="form-label">Current Location</label>
                    <input type="text" class="form-control" id="current_location" name="current_location" 
                           value="<?= escape($old['current_location'] ?? '') ?>"
                           placeholder="City, Country">
                </div>
                
                <div class="col-12">
                    <label for="skills" class="form-label">Key Skills</label>
                    <input type="text" class="form-control" id="skills" name="skills" 
                           value="<?= escape($old['skills'] ?? '') ?>"
                           placeholder="Java, Python, AWS, Project Management">
                    <div class="form-text">Separate skills with commas</div>
                </div>
                
                <div class="col-12">
                    <label for="summary" class="form-label">Brief Summary / Notes</label>
                    <textarea class="form-control" id="summary" name="summary" rows="3" 
                              placeholder="Quick notes about this contact..."><?= escape($old['summary'] ?? '') ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- SECTION 3: LEAD MANAGEMENT -->
        <div class="form-section">
            <h5>
                <span class="section-number">3</span>
                Lead Management
            </h5>
            
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="source" class="form-label">
                        Source <span class="text-danger">*</span>
                    </label>
                    <select class="form-select" id="source" name="source" required>
                        <option value="">Select Source</option>
                        <option value="linkedin" <?= ($old['source'] ?? '') === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                        <option value="referral" <?= ($old['source'] ?? '') === 'referral' ? 'selected' : '' ?>>Referral</option>
                        <option value="website" <?= ($old['source'] ?? '') === 'website' ? 'selected' : '' ?>>Website</option>
                        <option value="job_board" <?= ($old['source'] ?? '') === 'job_board' ? 'selected' : '' ?>>Job Board</option>
                        <option value="networking" <?= ($old['source'] ?? '') === 'networking' ? 'selected' : '' ?>>Networking Event</option>
                        <option value="social_media" <?= ($old['source'] ?? '') === 'social_media' ? 'selected' : '' ?>>Social Media</option>
                        <option value="cold_outreach" <?= ($old['source'] ?? '') === 'cold_outreach' ? 'selected' : '' ?>>Cold Outreach</option>
                        <option value="other" <?= ($old['source'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="medium" <?= ($old['priority'] ?? 'medium') === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="high" <?= ($old['priority'] ?? '') === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="low" <?= ($old['priority'] ?? '') === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="new" <?= ($old['status'] ?? 'new') === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= ($old['status'] ?? '') === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="qualified" <?= ($old['status'] ?? '') === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                        <option value="nurturing" <?= ($old['status'] ?? '') === 'nurturing' ? 'selected' : '' ?>>Nurturing</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="next_follow_up" class="form-label">Next Follow-up Date</label>
                    <input type="date" class="form-control" id="next_follow_up" name="next_follow_up"
                           value="<?= $old['next_follow_up'] ?? '' ?>"
                           min="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="assigned_to" class="form-label">Assign To</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="<?= Auth::userCode() ?>">Me (<?= Auth::user()['name'] ?>)</option>
                        <option value="">Unassigned</option>
                        <?php while ($recruiter = $recruiters->fetch_assoc()): ?>
                            <?php if ($recruiter['user_code'] !== Auth::userCode()): ?>
                                <option value="<?= $recruiter['user_code'] ?>" 
                                        <?= ($old['assigned_to'] ?? '') === $recruiter['user_code'] ? 'selected' : '' ?>>
                                    <?= escape($recruiter['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="source_details" class="form-label">Source Details (Optional)</label>
                    <input type="text" class="form-control" id="source_details" name="source_details" 
                           value="<?= escape($old['source_details'] ?? '') ?>"
                           placeholder="e.g., Referred by John Doe, Found in Software Engineers group">
                    <div class="form-text">Additional context about where you found this contact</div>
                </div>
            </div>
        </div>
        
        <!-- FORM ACTIONS -->
        <div class="d-flex gap-2 justify-content-end">
            <a href="list.php" class="btn btn-outline-secondary">
                <i class="bx bx-x me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bx bx-save me-1"></i> Create Contact
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
    const firstName = document.getElementById('first_name').value;
    const lastName = document.getElementById('last_name').value;
    const source = document.getElementById('source').value;
    
    if (!firstName || !lastName || !email || !source) {
        e.preventDefault();
        alert('Please fill in all required fields');
        return false;
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
        e.preventDefault();
        alert('Please enter a valid email address');
        document.getElementById('email').focus();
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>