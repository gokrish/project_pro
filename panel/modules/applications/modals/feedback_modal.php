<!-- Client Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-message-dots"></i> Add Client Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="feedbackForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="feedback_application_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Client Decision</label>
                        <select name="client_decision" class="form-control">
                            <option value="">Pending Decision</option>
                            <option value="shortlist">Shortlist for Interview</option>
                            <option value="reject">Reject</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Client Feedback <span class="text-danger">*</span></label>
                        <textarea name="client_feedback" class="form-control" rows="4" required
                                  placeholder="Enter feedback from the client..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i> Save Feedback
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#feedbackModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#feedback_application_id').val(applicationId);
});

$('#feedbackForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/feedback_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#feedbackModal').modal('hide');
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