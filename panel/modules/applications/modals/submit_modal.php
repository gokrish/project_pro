<!-- Submit to Client Modal -->
<div class="modal fade" id="submitModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bx bx-send"></i> Submit to Client
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="submitForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="submit_application_id">
                    
                    <div class="alert alert-warning">
                        <i class="bx bx-info-circle"></i>
                        This will mark the application as submitted to the client.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Submission Method</label>
                        <select name="submission_method" class="form-control">
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                            <option value="portal">Client Portal</option>
                            <option value="meeting">In-Person Meeting</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Submission Notes</label>
                        <textarea name="submission_notes" class="form-control" rows="3" 
                                  placeholder="Add details about the submission..."></textarea>
                        <small class="text-muted">
                            Example: "Sent CV and cover letter to hiring manager via email"
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send"></i> Submit to Client
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#submitModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#submit_application_id').val(applicationId);
});

$('#submitForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/submit_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#submitModal').modal('hide');
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