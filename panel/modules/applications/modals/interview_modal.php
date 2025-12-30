<!-- Schedule Interview Modal -->
<div class="modal fade" id="interviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-calendar"></i> Schedule Interview
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="interviewForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="interview_application_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interview Date <span class="text-danger">*</span></label>
                            <input type="date" name="interview_date" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interview Time <span class="text-danger">*</span></label>
                            <input type="time" name="interview_time" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interview Type</label>
                            <select name="interview_type" class="form-control">
                                <option value="phone">Phone</option>
                                <option value="video">Video Call</option>
                                <option value="in_person">In-Person</option>
                                <option value="technical">Technical Round</option>
                                <option value="hr">HR Round</option>
                                <option value="final">Final Round</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Interviewer Name</label>
                            <input type="text" name="interviewer_name" class="form-control" 
                                   placeholder="e.g., John Smith">
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Location / Meeting Link</label>
                        <input type="text" name="location" class="form-control" 
                               placeholder="Office address or Zoom/Teams link">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control" rows="3" 
                                  placeholder="Any special instructions or preparation notes..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-calendar-check"></i> Schedule Interview
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#interviewModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#interview_application_id').val(applicationId);
    
    // Set default date to tomorrow
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    $('input[name="interview_date"]').val(tomorrow.toISOString().split('T')[0]);
});

$('#interviewForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/interview_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#interviewModal').modal('hide');
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