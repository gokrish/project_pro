<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Change Application Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="changeStatusForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="status_application_id">
                    
                    <div class="mb-3">
                        <label class="form-label">New Status <span class="text-danger">*</span></label>
                        <select name="status" class="form-control" required>
                            <option value="">Select Status</option>
                            <option value="applied">Applied</option>
                            <option value="screening">Screening</option>
                            <option value="screening_passed">Screening Passed</option>
                            <option value="pending_approval">Pending Approval</option>
                            <option value="approved">Approved</option>
                            <option value="submitted">Submitted to Client</option>
                            <option value="shortlisted">Shortlisted by Client</option>
                            <option value="interviewing">Interviewing</option>
                            <option value="interview_passed">Interview Passed</option>
                            <option value="offered">Offer Extended</option>
                            <option value="offer_accepted">Offer Accepted</option>
                            <option value="placed">Successfully Placed</option>
                            <option value="rejected">Rejected</option>
                            <option value="withdrawn">Candidate Withdrawn</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" placeholder="Add any notes about this status change..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-check"></i> Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#changeStatusModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#status_application_id').val(applicationId);
});

$('#changeStatusForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/status_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#changeStatusModal').modal('hide');
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