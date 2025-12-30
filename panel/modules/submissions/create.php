<?php
/**
 * Create Submission - Submit Candidate to Client
 * File: panel/modules/submissions/create.php
 */

if (!defined('INCLUDED_FROM_INDEX')) {
    define('INCLUDED_FROM_INDEX', true);
}

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Pre-fill from URL params
$candidateCode = input('candidate_code', '');
$jobCode = input('job_code', '');
$clientCode = input('client_code', '');

// Get candidate if pre-selected
$candidate = null;
if (!empty($candidateCode)) {
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param('s', $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
}

// Get job if pre-selected
$job = null;
if (!empty($jobCode)) {
    $stmt = $conn->prepare("
        SELECT j.*, cl.company_name as client_name 
        FROM jobs j
        LEFT JOIN clients cl ON j.client_code = cl.client_code
        WHERE j.job_code = ?
    ");
    $stmt->bind_param('s', $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    if ($job && empty($clientCode)) {
        $clientCode = $job['client_code'];
    }
}

// Get all candidates for dropdown
$candidatesQuery = "
    SELECT candidate_code, candidate_name, email, current_position, experience_years
    FROM candidates 
    WHERE status = 'active' 
    ORDER BY candidate_name
";
$candidates = $conn->query($candidatesQuery)->fetch_all(MYSQLI_ASSOC);

// Get all active jobs
$jobsQuery = "
    SELECT j.job_code, j.title, cl.company_name as client_name, j.client_code
    FROM jobs j
    LEFT JOIN clients cl ON j.client_code = cl.client_code
    WHERE j.status = 'open'
    ORDER BY j.created_at DESC
";
$jobs = $conn->query($jobsQuery)->fetch_all(MYSQLI_ASSOC);

// Get all active clients
$clientsQuery = "
    SELECT client_code, company_name, client_name, email
    FROM clients 
    WHERE status = 'active' 
    ORDER BY company_name
";
$clients = $conn->query($clientsQuery)->fetch_all(MYSQLI_ASSOC);

// Generate submission code
$submissionCode = 'SUB-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="bx bx-send text-primary me-2"></i>Submit Candidate to Client
            </h4>
            <p class="text-muted mb-0">Create internal submission for review</p>
        </div>
        <a href="?action=list" class="btn btn-secondary">
            <i class="bx bx-arrow-back me-1"></i> Back to List
        </a>
    </div>

    <div class="row">
        <!-- Main Form -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Submission Details</h5>
                </div>
                <div class="card-body">
                    <form id="submissionForm" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        <input type="hidden" name="submission_code" value="<?= htmlspecialchars($submissionCode) ?>">
                        
                        <!-- Section 1: Selection -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">1. Selection</h6>
                            
                            <div class="row g-3">
                                <!-- Candidate -->
                                <div class="col-md-6">
                                    <label class="form-label">
                                        Candidate <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="candidate_code" name="candidate_code" required>
                                        <option value="">Select Candidate...</option>
                                        <?php foreach ($candidates as $cand): ?>
                                        <option value="<?= htmlspecialchars($cand['candidate_code']) ?>"
                                                <?= $candidateCode === $cand['candidate_code'] ? 'selected' : '' ?>
                                                data-email="<?= htmlspecialchars($cand['email']) ?>"
                                                data-position="<?= htmlspecialchars($cand['current_position'] ?? '') ?>"
                                                data-experience="<?= $cand['experience_years'] ?? '' ?>">
                                            <?= htmlspecialchars($cand['candidate_name']) ?>
                                            <?php if ($cand['current_position']): ?>
                                                - <?= htmlspecialchars($cand['current_position']) ?>
                                            <?php endif; ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="candidateInfo" class="mt-2 small text-muted"></div>
                                </div>
                                
                                <!-- Job -->
                                <div class="col-md-6">
                                    <label class="form-label">
                                        Job <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="job_code" name="job_code" required>
                                        <option value="">Select Job...</option>
                                        <?php foreach ($jobs as $j): ?>
                                        <option value="<?= htmlspecialchars($j['job_code']) ?>"
                                                <?= $jobCode === $j['job_code'] ? 'selected' : '' ?>
                                                data-client="<?= htmlspecialchars($j['client_code']) ?>"
                                                data-client-name="<?= htmlspecialchars($j['client_name']) ?>">
                                            <?= htmlspecialchars($j['title']) ?>
                                            (<?= htmlspecialchars($j['client_name']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Client (auto-filled from job) -->
                                <div class="col-12">
                                    <label class="form-label">
                                        Client <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="client_code" name="client_code" required>
                                        <option value="">Select Client...</option>
                                        <?php foreach ($clients as $cl): ?>
                                        <option value="<?= htmlspecialchars($cl['client_code']) ?>"
                                                <?= $clientCode === $cl['client_code'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cl['company_name']) ?>
                                            (<?= htmlspecialchars($cl['client_name']) ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Auto-filled based on selected job</small>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 2: Proposal -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">2. Rate Proposal</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Proposed Rate <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="proposed_rate" required 
                                           placeholder="500" step="0.01">
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label">Rate Type</label>
                                    <select class="form-select" name="rate_type">
                                        <option value="daily" selected>Daily</option>
                                        <option value="annual">Annual</option>
                                    </select>
                                </div>
                                
                                
                                <div class="col-md-6">
                                    <label class="form-label">Availability Date</label>
                                    <input type="date" class="form-control" name="availability_date" 
                                           min="<?= date('Y-m-d') ?>">
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label">Contract Duration (months)</label>
                                    <input type="number" class="form-control" name="contract_duration" 
                                           placeholder="6" min="1" max="60">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Section 3: Assessment -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">3. Candidate Assessment</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">
                                    Why This Candidate? <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control" name="fit_reason" rows="4" required
                                          placeholder="Explain why this candidate is a great fit for this role and client..."></textarea>
                                <small class="text-muted">This will be shared with the client</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Key Strengths</label>
                                <textarea class="form-control" name="key_strengths" rows="3"
                                          placeholder="• 10+ years in DevOps&#10;• AWS & Azure certified&#10;• Led teams of 5+ engineers"></textarea>
                                <small class="text-muted">List key strengths (one per line)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Any Concerns?</label>
                                <textarea class="form-control" name="concerns" rows="2"
                                          placeholder="Internal notes about potential concerns..."></textarea>
                                <small class="text-muted">Internal only - not shared with client</small>
                            </div>
                        </div>
                        
                        <!-- Section 4: Documents -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">4. Documents (Optional)</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Attach CV/Resume</label>
                                <input type="file" class="form-control" name="cv_file" accept=".pdf,.doc,.docx">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Additional Documents</label>
                                <input type="file" class="form-control" name="additional_docs[]" multiple>
                                <small class="text-muted">Certificates, portfolio, etc.</small>
                            </div>
                        </div>
                        
                        <!-- Section 5: Submission Type -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">5. Submission Options</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Submission Type</label>
                                <select class="form-select" name="submission_type">
                                    <option value="client_submission" selected>Submit to Client (requires approval)</option>
                                    <option value="internal_review">Internal Review Only</option>
                                </select>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="save_as_draft" id="save_as_draft">
                                <label class="form-check-label" for="save_as_draft">
                                    Save as draft (can edit later)
                                </label>
                            </div>
                        </div>
                        
                        <!-- Submit Buttons -->
                        <div class="pt-3 border-top">
                            <button type="submit" class="btn btn-primary me-2" id="submitBtn">
                                <i class="bx bx-send me-1"></i> Submit for Review
                            </button>
                            <button type="button" class="btn btn-success me-2" id="saveDraftBtn">
                                <i class="bx bx-save me-1"></i> Save as Draft
                            </button>
                            <a href="?action=list" class="btn btn-label-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Help Sidebar -->
        <div class="col-lg-4">
            <div class="card bg-label-info mb-3">
                <div class="card-body">
                    <h6><i class="bx bx-info-circle me-2"></i>Submission Process</h6>
                    <ol class="mb-0 small ps-3">
                        <li class="mb-2">Select candidate and job</li>
                        <li class="mb-2">Propose rate and terms</li>
                        <li class="mb-2">Write compelling assessment</li>
                        <li class="mb-2">Submit for manager review</li>
                        <li class="mb-2">If approved → sent to client</li>
                        <li class="mb-2">Client feedback tracked</li>
                        <li>If interested → becomes application</li>
                    </ol>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h6>Tips for Success</h6>
                    <ul class="mb-0 small">
                        <li class="mb-2">Be specific about why this candidate fits</li>
                        <li class="mb-2">Highlight unique qualifications</li>
                        <li class="mb-2">Address any potential concerns proactively</li>
                        <li class="mb-2">Ensure rate is competitive</li>
                        <li>Attach updated CV if available</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Auto-fill client when job selected
    $('#job_code').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const clientCode = selectedOption.data('client');
        if (clientCode) {
            $('#client_code').val(clientCode);
        }
    });
    
    // Show candidate info when selected
    $('#candidate_code').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const email = selectedOption.data('email');
        const position = selectedOption.data('position');
        const experience = selectedOption.data('experience');
        
        let info = '';
        if (email) info += '<i class="bx bx-envelope"></i> ' + email + '<br>';
        if (position) info += '<i class="bx bx-briefcase"></i> ' + position + '<br>';
        if (experience) info += '<i class="bx bx-time"></i> ' + experience + ' years experience';
        
        $('#candidateInfo').html(info);
    });
    
    // Trigger on page load if pre-selected
    if ($('#candidate_code').val()) {
        $('#candidate_code').trigger('change');
    }
    
    // Form submission
    $('#submissionForm').on('submit', function(e) {
        e.preventDefault();
        submitForm(false);
    });
    
    $('#saveDraftBtn').on('click', function() {
        $('#save_as_draft').prop('checked', true);
        submitForm(true);
    });
    
    async function submitForm(isDraft) {
        const formData = new FormData(document.getElementById('submissionForm'));
        
        if (isDraft) {
            formData.set('save_as_draft', '1');
        }
        
        try {
            const btn = isDraft ? $('#saveDraftBtn') : $('#submitBtn');
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Saving...');
            
            const response = await fetch('handlers/create.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                window.location.href = '?action=view&code=' + result.submission_code;
            } else {
                alert('Error: ' + result.message);
                btn.prop('disabled', false).html(isDraft ? '<i class="bx bx-save me-1"></i> Save as Draft' : '<i class="bx bx-send me-1"></i> Submit for Review');
            }
        } catch (error) {
            alert('Network error. Please try again.');
            console.error(error);
        }
    }
});
</script>