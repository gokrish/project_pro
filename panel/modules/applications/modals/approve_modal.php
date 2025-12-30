<!-- Approve Application Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bx bx-check-circle"></i> Approve Application
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="approveForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="approve_application_id">
                    
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle"></i>
                        This will approve the application for client submission.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approval Notes (Optional)</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Add any approval comments..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check-circle"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#approveModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#approve_application_id').val(applicationId);
});

$('#approveForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/approve_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#approveModal').modal('hide');
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