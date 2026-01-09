<?php
/**
 * CV Submission Modal - Shared Component
 */
?>
<div class="modal fade" id="cvModal" tabindex="-1" aria-labelledby="cvModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cvModalLabel">Submit Your CV</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="cvSubmissionForm" action="/public/handlers/submit-cv.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <small>
                            <i class="bi bi-info-circle me-2"></i>
                            Submit your CV for general consideration. We'll contact you when suitable positions become available.
                        </small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cvName" class="form-label">Your Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="cvName" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cvMobile" class="form-label">Your Mobile No <span class="text-danger">*</span></label>
                        <input type="tel" class="form-control" id="cvMobile" name="mobile" 
                               placeholder="+32 XXX XXX XXX" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cvEmail" class="form-label">Your Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="cvEmail" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cvInterest" class="form-label">Your Interest in Job</label>
                        <input type="text" class="form-control" id="cvInterest" name="interest" 
                               placeholder="e.g., Java Developer, Project Manager">
                        <small class="text-muted">What type of role are you looking for?</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="cvFile" class="form-label">Upload Your CV (PDF or DOCX) <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="cvFile" name="cv_file" 
                               accept=".pdf,.docx" required>
                        <small class="text-muted">Max file size: 5MB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="cvSubmitBtn">
                        <i class="bi bi-upload me-2"></i> Submit CV
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// CV Form Submission Handler
document.getElementById('cvSubmissionForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('cvSubmitBtn');
    const originalHTML = submitBtn.innerHTML;
    
    // Disable button and show loading
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
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('cvModal'));
            modal.hide();
            
            // Show success alert
            alert('✓ Thank you! Your CV has been submitted successfully.\n\nWe will review your application and contact you if there\'s a suitable opportunity.');
            
            // Reset form
            this.reset();
        } else {
            alert('Error: ' + (result.message || 'Failed to submit CV. Please try again.'));
        }
    } catch (error) {
        alert('Error: ' + error.message);
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHTML;
    }
});
</script>