<!-- Log Communication Modal -->
<div class="modal fade" id="logCommunicationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="logCommunicationForm" method="POST">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode ?? '') ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::generate() ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bx bx-phone me-2"></i>
                        Log Communication
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="row g-3">
                        <!-- Communication Type -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="communication_type" required>
                                <option value="">Select type...</option>
                                <option value="Call">📞 Phone Call</option>
                                <option value="Email">✉️ Email</option>
                                <option value="Meeting">🤝 Meeting</option>
                                <option value="WhatsApp">💬 WhatsApp</option>
                                <option value="LinkedIn">🔗 LinkedIn</option>
                                <option value="Other">📝 Other</option>
                            </select>
                        </div>
                        
                        <!-- Direction -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Direction <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" name="direction" required>
                                <option value="Outbound" selected>Outbound (You contacted)</option>
                                <option value="Inbound">Inbound (They contacted)</option>
                            </select>
                        </div>
                        
                        <!-- Subject/Title -->
                        <div class="col-12">
                            <label class="form-label">
                                Subject/Title
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="subject" 
                                   placeholder="E.g., Initial screening call, Follow-up on Java position">
                        </div>
                        
                        <!-- Duration (for calls/meetings) -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Duration (minutes)
                            </label>
                            <input type="number" 
                                   class="form-control" 
                                   name="duration_minutes" 
                                   min="1" 
                                   max="480"
                                   placeholder="Optional">
                            <small class="text-muted">For calls and meetings</small>
                        </div>
                        
                        <!-- Communication Date -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Date & Time
                            </label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   name="communication_date"
                                   value="<?= date('Y-m-d\TH:i') ?>">
                        </div>
                        
                        <!-- Notes -->
                        <div class="col-12">
                            <label class="form-label">
                                Notes <span class="text-danger">*</span>
                            </label>
                            <textarea class="form-control" 
                                      name="notes" 
                                      rows="4" 
                                      required
                                      placeholder="What was discussed? Key points from the conversation..."></textarea>
                            <small class="text-muted">Detailed notes help track candidate engagement</small>
                        </div>
                        
                        <!-- Next Action -->
                        <div class="col-md-8">
                            <label class="form-label">
                                Next Action
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   name="next_action" 
                                   placeholder="E.g., Send job description, Schedule interview">
                        </div>
                        
                        <!-- Next Action Date -->
                        <div class="col-md-4">
                            <label class="form-label">
                                Action Date
                            </label>
                            <input type="date" 
                                   class="form-control" 
                                   name="next_action_date"
                                   min="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save me-1"></i>
                        Log Communication
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('logCommunicationForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const form = e.target;
    const formData = new FormData(form);
    const submitBtn = form.querySelector('button[type="submit"]');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';
    
    try {
        const response = await fetch('/panel/modules/candidates/handlers/log-communication.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show success message
            showToast('Communication logged successfully', 'success');
            
            // Close modal
            bootstrap.Modal.getInstance(document.getElementById('logCommunicationModal')).hide();
            
            // Reload communications tab if visible
            if (document.querySelector('#communications-tab.active')) {
                loadCommunications();
            }
            
            // Reset form
            form.reset();
        } else {
            showToast(result.message || 'Failed to log communication', 'error');
        }
    } catch (error) {
        console.error('Error:', error);
        showToast('An error occurred while logging communication', 'error');
    } finally {
        // Re-enable button
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bx bx-save me-1"></i> Log Communication';
    }
});
</script>
