<?php
/**
 * Edit Contact
 * Same form as create.php but pre-filled with existing data
 * 
 * @version 2.0 FINAL
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;

// Check permission
if (!Permission::can('contacts', 'edit.all') && !Permission::can('contacts', 'edit.own')) {
    throw new PermissionException('You cannot edit contacts.');
}

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get contact code
$contactCode = $_GET['contact_code'] ?? $_GET['id'] ?? null;

if (!$contactCode) {
    FlashMessage::error('Contact code is required');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

// Fetch contact
$stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_code = ? AND deleted_at IS NULL");
$stmt->bind_param("s", $contactCode);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();

if (!$contact) {
    FlashMessage::error('Contact not found');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

// Check ownership for edit.own
if (!Permission::can('contacts', 'edit.all')) {
    if ($contact['assigned_to'] !== Auth::userCode()) {
        throw new PermissionException('You can only edit contacts assigned to you');
    }
}

// Get recruiters for assignment
$recruitersQuery = "SELECT user_code, name FROM users WHERE is_active = 1 AND level IN ('recruiter', 'senior_recruiter', 'manager') ORDER BY name";
$recruiters = $conn->query($recruitersQuery);

// Parse skills from JSON
$skillsArray = json_decode($contact['skills'] ?? '[]', true);
$skillsString = implode(', ', $skillsArray);

// Page config
$pageTitle = 'Edit Contact: ' . $contact['first_name'] . ' ' . $contact['last_name'];
$breadcrumbs = [
    ['title' => 'Contacts', 'url' => '/panel/modules/contacts/list.php'],
    ['title' => $contact['first_name'] . ' ' . $contact['last_name'], 'url' => '/panel/modules/contacts/view.php?contact_code=' . $contactCode],
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
</style>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1">Edit Contact</h4>
            <p class="text-muted mb-0"><?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?></p>
        </div>
        <a href="view.php?contact_code=<?= $contactCode ?>" class="btn btn-outline-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to Contact
        </a>
    </div>

    <form id="contactForm" method="POST" action="handlers/update.php">
        <?= CSRFToken::field() ?>
        <input type="hidden" name="contact_code" value="<?= escape($contactCode) ?>">
        
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
                           value="<?= escape($contact['first_name']) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="last_name" class="form-label">
                        Last Name <span class="text-danger">*</span>
                    </label>
                    <input type="text" class="form-control" id="last_name" name="last_name" 
                           value="<?= escape($contact['last_name']) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">
                        Email <span class="text-danger">*</span>
                    </label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= escape($contact['email']) ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="phone" class="form-label">Phone</label>
                    <input type="tel" class="form-control" id="phone" name="phone" 
                           value="<?= escape($contact['phone']) ?>">
                </div>
                
                <div class="col-12">
                    <label for="linkedin_url" class="form-label">LinkedIn Profile</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bx bxl-linkedin"></i></span>
                        <input type="url" class="form-control" id="linkedin_url" name="linkedin_url" 
                               value="<?= escape($contact['linkedin_url']) ?>">
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
                           value="<?= escape($contact['current_company']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="current_title" class="form-label">Current Job Title</label>
                    <input type="text" class="form-control" id="current_title" name="current_title" 
                           value="<?= escape($contact['current_title']) ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="years_of_experience" class="form-label">Years of Experience</label>
                    <input type="number" class="form-control" id="years_of_experience" name="years_of_experience" 
                           value="<?= $contact['years_of_experience'] ?>" min="0" max="50" step="0.5">
                </div>
                
                <div class="col-md-6">
                    <label for="current_location" class="form-label">Current Location</label>
                    <input type="text" class="form-control" id="current_location" name="current_location" 
                           value="<?= escape($contact['current_location']) ?>">
                </div>
                
                <div class="col-12">
                    <label for="skills" class="form-label">Key Skills (comma-separated)</label>
                    <input type="text" class="form-control" id="skills" name="skills" 
                           value="<?= escape($skillsString) ?>">
                    <div class="form-text">Separate skills with commas</div>
                </div>
                
                <div class="col-12">
                    <label for="summary" class="form-label">Brief Summary / Notes</label>
                    <textarea class="form-control" id="summary" name="summary" rows="3"><?= escape($contact['summary']) ?></textarea>
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
                        <option value="linkedin" <?= $contact['source'] === 'linkedin' ? 'selected' : '' ?>>LinkedIn</option>
                        <option value="referral" <?= $contact['source'] === 'referral' ? 'selected' : '' ?>>Referral</option>
                        <option value="website" <?= $contact['source'] === 'website' ? 'selected' : '' ?>>Website</option>
                        <option value="job_board" <?= $contact['source'] === 'job_board' ? 'selected' : '' ?>>Job Board</option>
                        <option value="networking" <?= $contact['source'] === 'networking' ? 'selected' : '' ?>>Networking Event</option>
                        <option value="social_media" <?= $contact['source'] === 'social_media' ? 'selected' : '' ?>>Social Media</option>
                        <option value="cold_outreach" <?= $contact['source'] === 'cold_outreach' ? 'selected' : '' ?>>Cold Outreach</option>
                        <option value="other" <?= $contact['source'] === 'other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <option value="high" <?= $contact['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $contact['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $contact['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="new" <?= $contact['status'] === 'new' ? 'selected' : '' ?>>New</option>
                        <option value="contacted" <?= $contact['status'] === 'contacted' ? 'selected' : '' ?>>Contacted</option>
                        <option value="qualified" <?= $contact['status'] === 'qualified' ? 'selected' : '' ?>>Qualified</option>
                        <option value="nurturing" <?= $contact['status'] === 'nurturing' ? 'selected' : '' ?>>Nurturing</option>
                        <option value="not_interested" <?= $contact['status'] === 'not_interested' ? 'selected' : '' ?>>Not Interested</option>
                        <option value="unresponsive" <?= $contact['status'] === 'unresponsive' ? 'selected' : '' ?>>Unresponsive</option>
                        <option value="converted" <?= $contact['status'] === 'converted' ? 'selected' : '' ?>>Converted</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label for="next_follow_up" class="form-label">Next Follow-up Date</label>
                    <input type="date" class="form-control" id="next_follow_up" name="next_follow_up"
                           value="<?= $contact['next_follow_up'] ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="assigned_to" class="form-label">Assign To</label>
                    <select class="form-select" id="assigned_to" name="assigned_to">
                        <option value="">Unassigned</option>
                        <?php while ($recruiter = $recruiters->fetch_assoc()): ?>
                            <option value="<?= $recruiter['user_code'] ?>" 
                                    <?= $recruiter['user_code'] === $contact['assigned_to'] ? 'selected' : '' ?>>
                                <?= escape($recruiter['name']) ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="col-12">
                    <label for="source_details" class="form-label">Source Details (Optional)</label>
                    <input type="text" class="form-control" id="source_details" name="source_details" 
                           value="<?= escape($contact['source_details']) ?>">
                </div>
            </div>
        </div>
        
        <!-- FORM ACTIONS -->
        <div class="d-flex gap-2 justify-content-end">
            <a href="view.php?contact_code=<?= $contactCode ?>" class="btn btn-outline-secondary">
                <i class="bx bx-x me-1"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bx bx-save me-1"></i> Save Changes
            </button>
        </div>
    </form>
</div>

<script>
document.getElementById('contactForm').addEventListener('submit', function(e) {
    const email = document.getElementById('email').value;
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