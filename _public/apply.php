<?php
/**
 * Job Application Form
 * Allows candidates to apply for specific job
 */

require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\{Database, CSRFToken};

$db = Database::getInstance();
$conn = $db->getConnection();

// Get job code
$jobCode = filter_input(INPUT_GET, 'job', FILTER_SANITIZE_STRING);

if (!$jobCode) {
    header('Location: /public/jobs.php');
    exit;
}

// Get job details
$stmt = $conn->prepare("
    SELECT 
        j.job_code,
        j.job_refno,
        j.job_title,
        j.location,
        c.company_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.job_code = ?
    AND j.status = 'open'
    AND j.is_published = 1
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header('Location: /public/jobs.php');
    exit;
}

// Page config
$pageTitle = 'Apply for ' . htmlspecialchars($job['job_title']) . ' - Pro Consultancy';
$pageKeywords = 'Job Application, ' . htmlspecialchars($job['job_title']) . ', Career';
$pageDescription = 'Apply for the position of ' . htmlspecialchars($job['job_title']) . ' at Pro Consultancy';
$currentPage = 'jobs';
$pageHeader = true;
$headerTitle = 'Apply for Position';
$headerSubtitle = htmlspecialchars($job['job_title']);
$headerImage = 'job.png';

require_once '_includes/header.php';
?>

<!-- Application Form Start -->
<div class="container-fluid py-5 wow fadeInUp" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                
                <!-- Job Info Card -->
                <div class="card border-primary mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-briefcase me-2"></i>
                            Applying for: <?= htmlspecialchars($job['job_title']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Job Reference</small>
                                <p class="mb-2 fw-semibold"><?= htmlspecialchars($job['job_refno']) ?></p>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Location</small>
                                <p class="mb-2 fw-semibold"><?= htmlspecialchars($job['location']) ?></p>
                            </div>
                            <?php if (!empty($job['company_name'])): ?>
                                <div class="col-md-6">
                                    <small class="text-muted">Company</small>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($job['company_name']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Application Form -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Application Form</h5>
                    </div>
                    <div class="card-body">
                        <form id="applicationForm" action="/public/handlers/submit-application.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                            <input type="hidden" name="job_code" value="<?= htmlspecialchars($jobCode) ?>">
                            
                            <!-- Personal Information -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">Personal Information</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Email <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Phone </label>
                                        <input type="tel" class="form-control" name="phone" 
                                               placeholder="+32 XXX XXX XXX" >
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">LinkedIn Profile</label>
                                        <input type="url" class="form-control" name="linkedin" 
                                               placeholder="https://linkedin.com/in/yourprofile">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Current Location <span class="text-danger">*</span></label>
                                        <select class="form-select" name="location" required>
                                            <option value="">Select location...</option>
                                            <option value="Belgium">Belgium</option>
                                            <option value="Netherlands">Netherlands</option>
                                            <option value="Luxembourg">Luxembourg</option>
                                            <option value="Germany">Germany</option>
                                            <option value="France">France</option>
                                            <option value="India">India</option>
                                            <option value="Other">Other</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Notice Period</label>
                                        <select class="form-select" name="notice_period">
                                            <option value="">Select notice period...</option>
                                            <option value="0">Immediate</option>
                                            <option value="7">1 week</option>
                                            <option value="14">2 weeks</option>
                                            <option value="30">1 month</option>
                                            <option value="60">2 months</option>
                                            <option value="90">3 months</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">Professional Information</h6>
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Current Job Title</label>
                                        <input type="text" class="form-control" name="current_position" 
                                               placeholder="e.g., Senior Java Developer">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Years of Experience</label>
                                        <input type="number" class="form-control" name="experience" 
                                               min="0" max="50" placeholder="e.g., 5">
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Expected Salary (EUR)</label>
                                        <input type="number" class="form-control" name="expected_salary" 
                                               placeholder="e.g., 60000" min="0" step="1000">
                                        <small class="text-muted">Annual salary for permanent roles</small>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label">Expected Daily Rate (EUR)</label>
                                        <input type="number" class="form-control" name="expected_daily_rate" 
                                               placeholder="e.g., 500" min="0" step="50">
                                        <small class="text-muted">For freelance/contract roles</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Documents -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">Documents</h6>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Upload CV/Resume <span class="text-danger">*</span></label>
                                        <input type="file" class="form-control" name="cv_file" 
                                               accept=".pdf,.doc,.docx" required>
                                        <small class="text-muted">Supported formats: PDF, DOC, DOCX (Max 5MB)</small>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">Cover Letter</label>
                                        <textarea class="form-control" name="cover_letter" rows="6" 
                                                  placeholder="Tell us why you're a great fit for this position..."></textarea>
                                        <small class="text-muted">Minimum 50 characters recommended</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Additional Information -->
                            <div class="mb-4">
                                <h6 class="border-bottom pb-2 mb-3">Additional Information</h6>
                                
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="relocate" value="1" id="relocate">
                                            <label class="form-check-label" for="relocate">
                                                I am willing to relocate if required
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="remote" value="1" id="remote">
                                            <label class="form-check-label" for="remote">
                                                I am interested in remote work opportunities
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <label class="form-label">How did you hear about this position?</label>
                                        <select class="form-select" name="source">
                                            <option value="">Select source...</option>
                                            <option value="website">Company Website</option>
                                            <option value="linkedin">LinkedIn</option>
                                            <option value="job_board">Job Board</option>
                                            <option value="referral">Referral</option>
                                            <option value="social_media">Social Media</option>
                                            <option value="other">Other</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- GDPR Consent -->
                            <div class="mb-4">
                                <div class="alert alert-info">
                                    <h6 class="alert-heading">
                                        <i class="bi bi-shield-check me-2"></i>Data Protection Notice
                                    </h6>
                                    <p class="mb-2 small">
                                        By submitting this application, you consent to Pro Consultancy processing your personal data for recruitment purposes. Your data will be stored securely and used only for evaluating your application.
                                    </p>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="gdpr_consent" value="1" id="gdpr" required>
                                        <label class="form-check-label" for="gdpr">
                                            I agree to the processing of my personal data as described above <span class="text-danger">*</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="/public/job-detail.php?ref=<?= urlencode($job['job_refno']) ?>" 
                                   class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-2"></i>Back to Job Details
                                </a>
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-paper-plane me-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Application Form End -->

<script>
// Form submission handler
document.getElementById('applicationForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const originalHTML = submitBtn.innerHTML;
    
    // Validate CV file
    const cvFile = document.querySelector('[name="cv_file"]').files[0];
    if (!cvFile) {
        alert('Please upload your CV');
        return;
    }
    
    // Validate file size
    if (cvFile.size > 5 * 1024 * 1024) {
        alert('CV file size must be less than 5MB');
        return;
    }
    
    // Validate GDPR consent
    if (!document.getElementById('gdpr').checked) {
        alert('Please accept the data protection terms');
        return;
    }
    
    // Show loading
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
    
    try {
        const formData = new FormData(this);
        
        const response = await fetch(this.action, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Redirect to success page
            window.location.href = '/public/apply-success.php?ref=<?= urlencode($job['job_refno']) ?>';
        } else {
            alert('Error: ' + (result.message || 'Failed to submit application'));
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHTML;
        }
    } catch (error) {
        alert('Error: ' + error.message);
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});
</script>

<?php require_once '_includes/footer.php'; ?>