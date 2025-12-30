<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-refresh me-2"></i> Change Candidate Status
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changeStatusForm">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode) ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::getToken() ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Current Status</label>
                        <div>
                            <span class="badge bg-label-<?= getStatusBadgeColor($candidate['lead_status']) ?> badge-lg">
                                <?= ucfirst(str_replace('_', ' ', $candidate['lead_status'])) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="new_status" class="form-label">New Status <span class="text-danger">*</span></label>
                        <select class="form-select" id="new_status" name="status" required>
                            <option value="">Select new status</option>
                            <option value="new" <?= $candidate['lead_status'] === 'new' ? 'disabled' : '' ?>>New</option>
                            <option value="contacted" <?= $candidate['lead_status'] === 'contacted' ? 'disabled' : '' ?>>Contacted</option>
                            <option value="qualified" <?= $candidate['lead_status'] === 'qualified' ? 'disabled' : '' ?>>Qualified</option>
                            <option value="submitted" <?= $candidate['lead_status'] === 'submitted' ? 'disabled' : '' ?>>Submitted</option>
                            <option value="interviewing" <?= $candidate['lead_status'] === 'interviewing' ? 'disabled' : '' ?>>Interviewing</option>
                            <option value="offered" <?= $candidate['lead_status'] === 'offered' ? 'disabled' : '' ?>>Offered</option>
                            <option value="placed" <?= $candidate['lead_status'] === 'placed' ? 'disabled' : '' ?>>Placed</option>
                            <option value="rejected" <?= $candidate['lead_status'] === 'rejected' ? 'disabled' : '' ?>>Rejected</option>
                            <option value="archived" <?= $candidate['lead_status'] === 'archived' ? 'disabled' : '' ?>>Archived</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="lead_type" class="form-label">Lead Type</label>
                        <select class="form-select" id="lead_type" name="lead_type">
                            <option value="hot" <?= $candidate['lead_type'] === 'hot' ? 'selected' : '' ?>>🔥 Hot - Ready to Move</option>
                            <option value="warm" <?= $candidate['lead_type'] === 'warm' ? 'selected' : '' ?>>🌡️ Warm - Interested</option>
                            <option value="cold" <?= $candidate['lead_type'] === 'cold' ? 'selected' : '' ?>>❄️ Cold - Passive</option>
                            <option value="blacklist" <?= $candidate['lead_type'] === 'blacklist' ? 'selected' : '' ?>>⛔ Blacklist</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status_reason" class="form-label">Reason for Change</label>
                        <textarea class="form-control" id="status_reason" name="reason" rows="3" 
                                  placeholder="Why are you changing the status?"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-check me-1"></i> Change Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Change Status Form Handler
$('#changeStatusForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: '/panel/modules/candidates/handlers/change-status.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Updating Status...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
        },
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', 'Status updated successfully', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to update status', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to update status', 'error');
        }
    });
});
</script>
