<!-- Submit to Job Modal -->
<div class="modal fade" id="submitJobModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="submitJobForm" method="POST">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode ?? '') ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-briefcase me-2"></i>
                        Submit <?= htmlspecialchars($candidate['candidate_name'] ?? 'Candidate') ?> to Job
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <!-- Job Selection -->
                    <div class="mb-3">
                        <label class="form-label">
                            Select Job <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="job_code" id="jobSelect" required>
                            <option value="">Choose job...</option>
                            <?php
                            // Get open jobs with skill matching
                            if (isset($candidateCode)) {
                                $stmt = $conn->prepare("
                                    SELECT DISTINCT j.*,
                                           cl.company_name,
                                           COUNT(DISTINCT js.skill_id) as matching_skills,
                                           COUNT(DISTINCT js2.skill_id) as total_required_skills
                                    FROM jobs j
                                    JOIN clients cl ON j.client_code = cl.client_code
                                    LEFT JOIN job_skills js ON j.job_code = js.job_code
                                    LEFT JOIN job_skills js2 ON j.job_code = js2.job_code AND js2.is_required = 1
                                    LEFT JOIN candidate_skills cs ON js.skill_id = cs.skill_id AND cs.candidate_code = ?
                                    WHERE j.status = 'open'
                                    AND j.deleted_at IS NULL
                                    GROUP BY j.job_code
                                    HAVING matching_skills > 0
                                    ORDER BY matching_skills DESC, j.created_at DESC
                                    LIMIT 50
                                ");
                                $stmt->bind_param("s", $candidateCode);
                                $stmt->execute();
                                $matchingJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                                
                                foreach ($matchingJobs as $job) {
                                    $match = $job['matching_skills'];
                                    $total = $job['total_required_skills'];
                                    $matchPercent = $total > 0 ? round(($match / $total) * 100) : 0;
                                    $matchIndicator = $matchPercent >= 80 ? '🟢' : ($matchPercent >= 50 ? '🟡' : '🔴');
                                    
                                    echo '<option value="' . htmlspecialchars($job['job_code']) . '" data-match="' . $match . '">';
                                    echo $matchIndicator . ' ';
                                    echo htmlspecialchars($job['job_title']) . ' - ';
                                    echo htmlspecialchars($job['company_name']);
                                    echo ' (' . $match . '/' . $total . ' skills match)';
                                    echo '</option>';
                                }
                            }
                            ?>
                        </select>
                        <small class="text-muted">
                            🟢 = Great match (80%+) | 🟡 = Good match (50%+) | 🔴 = Partial match
                        </small>
                    </div>
                    
                    <!-- Job Details (shown after selection) -->
                    <div id="jobDetails" class="d-none mb-3">
                        <div class="alert alert-info">
                            <div id="jobDetailsContent">
                                <!-- Populated via AJAX -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Why Good Fit -->
                    <div class="mb-3">
                        <label class="form-label">
                            Why is this candidate a good fit? <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  name="fit_reason" 
                                  rows="4" 
                                  required
                                  placeholder="Explain why this candidate matches the job requirements..."></textarea>
                        <small class="text-muted">
                            Highlight matching skills, experience, and relevant qualifications
                        </small>
                    </div>
                    
                    <!-- Expected Salary/Rate -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Expected Salary (Annual)
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" 
                                       class="form-control" 
                                       name="expected_salary" 
                                       step="1000"
                                       placeholder="Optional">
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">
                                Expected Daily Rate
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">€</span>
                                <input type="number" 
                                       class="form-control" 
                                       name="expected_daily_rate" 
                                       step="50"
                                       placeholder="Optional">
                                <span class="input-group-text">/day</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Availability -->
                    <div class="mb-3">
                        <label class="form-label">
                            Available From
                        </label>
                        <input type="date" 
                               class="form-control" 
                               name="available_from"
                               min="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <!-- Additional Notes -->
                    <div class="mb-3">
                        <label class="form-label">
                            Additional Notes
                        </label>
                        <textarea class="form-control" 
                                  name="submission_notes" 
                                  rows="3"
                                  placeholder="Any additional information for the client..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send me-1"></i>
                        Submit to Job
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Load job details when selected
document.getElementById('jobSelect').addEventListener('change', async function() {
    const jobCode = this.value;
    const detailsDiv = document.getElementById('jobDetails');
    const contentDiv = document.getElementById('jobDetailsContent');
    
    if (!jobCode) {
        detailsDiv.classList.add('d-none');
        return;
    }
    
    try {
        const response = await fetch(`/panel/modules/jobs/api/get-details.php?code=${jobCode}`);
        const result = await response.json();
        
        if (result.success && result.data) {
            const job = result.data;
            contentDiv.innerHTML = `
                <h6 class="mb-2">${job.job_title}</h6>
                <div class="row g-2 small">
                    <div class="col-md-6">
                        <strong>Client:</strong> ${job.company_name}<br>
                        <strong>Location:</strong> ${job.location || 'Not specified'}
                    </div>
                    <div class="col-md-6">
                        <strong>Type:</strong> ${job.job_type || 'N/A'}<br>
                        <strong>Required Skills:</strong> ${job.required_skills || 'See job description'}
                    </div>
                </div>
            `;
            detailsDiv.classList.remove('d-none');
        }
    } catch (error) {
        console.error('Error loading job details:', error);
    }
});

document.getElementById('submitJobForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';
    
    try {
        const response = await fetch('/panel/modules/candidates/handlers/submit_to_job.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('Candidate submitted to job successfully', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('submitJobModal')).hide();
            
            // Reload job activity tab if visible
            if (document.querySelector('#job-activity-tab.active')) {
                loadJobActivity();
            }
            
            // Reset form
            form.reset();
            document.getElementById('jobDetails').classList.add('d-none');
        } else {
            showToast(result.message || 'Failed to submit candidate', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while submitting candidate', 'error');
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-send me-1"></i> Submit to Job';
    }
});
</script>
