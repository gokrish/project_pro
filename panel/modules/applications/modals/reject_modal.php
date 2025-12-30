<!-- Reject Application Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bx bx-x-circle"></i> Reject Application
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="rejectForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="reject_application_id">
                    
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle"></i>
                        Please provide a reason for rejection. This helps track patterns and improve future selections.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Stage</label>
                        <select name="rejection_stage" class="form-control">
                            <option value="screening">Initial Screening</option>
                            <option value="client_review">Client Review</option>
                            <option value="interview">Interview</option>
                            <option value="final_selection">Final Selection</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <select name="rejection_reason_quick" class="form-control" id="quickReasonSelect">
                            <option value="">Select a reason or type custom below</option>
                            <option value="Skills mismatch">Skills mismatch</option>
                            <option value="Experience not sufficient">Experience not sufficient</option>
                            <option value="Salary expectations too high">Salary expectations too high</option>
                            <option value="Location constraints">Location constraints</option>
                            <option value="Not available on required dates">Not available on required dates</option>
                            <option value="Poor interview performance">Poor interview performance</option>
                            <option value="Client selected another candidate">Client selected another candidate</option>
                            <option value="Candidate declined offer">Candidate declined offer</option>
                            <option value="Other">Other (specify below)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Additional Details</label>
                        <textarea name="rejection_reason" class="form-control" rows="3" required
                                  placeholder="Provide specific details about the rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x-circle"></i> Reject Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#rejectModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#reject_application_id').val(applicationId);
});

$('#quickReasonSelect').on('change', function() {
    const selectedReason = $(this).val();
    if (selectedReason && selectedReason !== 'Other') {
        $('textarea[name="rejection_reason"]').val(selectedReason);
    }
});

$('#rejectForm').on('submit', function(e) {
    e.preventDefault();
    
    if (!confirm('Are you sure you want to reject this application? This action cannot be easily undone.')) {
        return;
    }
    
    $.ajax({
        url: 'handlers/reject_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#rejectModal').modal('hide');
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function() {
            alert('An error occurred. Please try again.');
        }
    });
});
</script>