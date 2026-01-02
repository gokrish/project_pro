<!-- Add HR Comment Modal (Admin/Manager+ Only) -->
<div class="modal fade" id="addHRCommentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="addHRCommentForm" method="POST">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode ?? '') ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                
                <div class="modal-header bg-warning bg-opacity-10">
                    <h5 class="modal-title">
                        <i class="bx bx-lock-alt text-warning me-2"></i>
                        Add HR Comment (Confidential)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>Confidential:</strong> This comment will only be visible to admins.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Comment Type <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" name="comment_type" required>
                            <option value="">Select type...</option>
                            <option value="Screening">🔍 Screening Notes</option>
                            <option value="Interview_Feedback">💬 Interview Feedback</option>
                            <option value="Manager_Review">👔 Admin Review</option>
                            <option value="Red_Flag">🚩 Red Flag</option>
                            <option value="Recommendation">⭐ Recommendation</option>
                            <option value="General">📝 General Comment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">
                            Comment <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control" 
                                  name="comment" 
                                  rows="5" 
                                  required
                                  placeholder="Enter confidential HR notes..."></textarea>
                        <small class="text-muted">
                            Include details about strengths, concerns, cultural fit, or recommendations.
                        </small>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="is_confidential" 
                               id="isConfidential" 
                               value="1" 
                               checked>
                        <label class="form-check-label" for="isConfidential">
                            <strong>Keep this comment confidential</strong>
                            <br>
                            <small class="text-muted">Only admins can view</small>
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-lock-alt me-1"></i>
                        Add HR Comment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('addHRCommentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    
    try {
        const response = await fetch('/panel/modules/candidates/handlers/add-hr-comment.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast('HR comment added successfully', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('addHRCommentModal')).hide();
            
            // Reload HR comments tab if visible
            if (document.querySelector('#hr-comments-tab.active')) {
                loadHRComments();
            }
            
            // Reset form
            form.reset();
        } else {
            showToast(result.message || 'Failed to add HR comment', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while adding HR comment', 'error');
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-lock-alt me-1"></i> Add HR Comment';
    }
});
</script>
