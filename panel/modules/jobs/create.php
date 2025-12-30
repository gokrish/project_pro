<!-- create.php - Keep it simple! -->

<?php
require_once __DIR__ . '/../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;

Permission::require('jobs', 'create');

$pageTitle = 'Create Job';
require_once __DIR__ . '/../../includes/header.php';

// Get clients for dropdown
$db = Database::getInstance();
$conn = $db->getConnection();
$clients = $conn->query("SELECT client_code, company_name FROM clients WHERE is_active = 1 ORDER BY company_name")->fetch_all(MYSQLI_ASSOC);
$recruiters = $conn->query("SELECT user_code, name FROM users WHERE level IN ('recruiter', 'manager') AND is_active = 1 ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Generate job code
$jobCode = 'JOB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Create New Job</h5>
    </div>
    <div class="card-body">
        <form method="POST" action="handlers/create.php" id="jobForm">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="job_code" value="<?= $jobCode ?>">
            
            <!-- Basic Information -->
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-primary">Basic Information</h6>
                    <hr>
                </div>
                
                <div class="col-md-8 mb-3">
                    <label class="form-label">Job Title *</label>
                    <input type="text" class="form-control" name="job_title" required 
                           placeholder="e.g., Senior Java Developer">
                </div>
                
                <div class="col-md-4 mb-3">
                    <label class="form-label">Job Code</label>
                    <input type="text" class="form-control" value="<?= $jobCode ?>" readonly>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client *</label>
                    <select class="form-select" name="client_code" required>
                        <option value="">Select Client...</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['client_code'] ?>">
                                <?= escape($client['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Assigned To *</label>
                    <select class="form-select" name="assigned_to" required>
                        <option value="<?= Auth::userCode() ?>" selected>Myself</option>
                        <?php foreach ($recruiters as $recruiter): ?>
                            <?php if ($recruiter['user_code'] !== Auth::userCode()): ?>
                                <option value="<?= $recruiter['user_code'] ?>">
                                    <?= escape($recruiter['name']) ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <!-- Job Description -->
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-primary">Job Description</h6>
                    <hr>
                </div>
                
                <div class="col-12 mb-3">
                    <label class="form-label">Description *</label>
                    <textarea id="description" name="description" class="form-control" rows="15" required></textarea>
                    <small class="form-text text-muted">
                        Use the editor above to format your job description
                    </small>
                </div>
            </div>
            
            <!-- Compensation -->
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-primary">Compensation (Optional)</h6>
                    <hr>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Salary Min (EUR)</label>
                    <input type="number" class="form-control" name="salary_min" step="0.01" placeholder="45000">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label class="form-label">Salary Max (EUR)</label>
                    <input type="number" class="form-control" name="salary_max" step="0.01" placeholder="65000">
                </div>
            </div>
            
            <!-- Internal Notes -->
            <div class="row mb-4">
                <div class="col-12">
                    <h6 class="text-primary">Internal Notes (Not shown to candidates)</h6>
                    <hr>
                </div>
                
                <div class="col-12 mb-3">
                    <textarea class="form-control" name="internal_notes" rows="3" 
                              placeholder="Add any internal notes, requirements, or comments..."></textarea>
                </div>
            </div>
            
            <!-- Form Actions -->
            <div class="row">
                <div class="col-12">
                    <hr>
                    <div class="d-flex justify-content-between">
                        <a href="list.php" class="btn btn-outline-secondary">Cancel</a>
                        
                        <div>
                            <button type="submit" name="action" value="save_draft" class="btn btn-secondary me-2">
                                <i class="bx bx-save"></i> Save as Draft
                            </button>
                            <button type="submit" name="action" value="publish" class="btn btn-success">
                                <i class="bx bx-check-circle"></i> Create & Publish
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- TinyMCE Initialization -->
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js"></script>
<script>
tinymce.init({
    selector: '#description',
    height: 500,
    menubar: false,
    plugins: [
        'lists', 'link', 'table', 'code', 'paste', 'searchreplace'
    ],
    toolbar: 'undo redo | formatselect | bold italic underline | ' +
             'alignleft aligncenter alignright | bullist numlist | ' +
             'link table | removeformat code',
    paste_as_text: true,
    content_style: 'body { font-family: Arial, sans-serif; font-size: 14px; }',
    
    // Clean pasted content from Word/email
    paste_preprocess: function(plugin, args) {
        args.content = args.content.replace(/<\/?span[^>]*>/g, "");
        args.content = args.content.replace(/style="[^"]*"/g, "");
    }
});

// Form validation
document.getElementById('jobForm').addEventListener('submit', function(e) {
    const title = document.querySelector('[name="job_title"]').value.trim();
    const client = document.querySelector('[name="client_code"]').value;
    const description = tinymce.get('description').getContent();
    
    if (!title) {
        alert('Please enter a job title');
        e.preventDefault();
        return false;
    }
    
    if (!client) {
        alert('Please select a client');
        e.preventDefault();
        return false;
    }
    
    if (description.length < 100) {
        alert('Job description must be at least 100 characters');
        e.preventDefault();
        return false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>