<!-- Add Note Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-note"></i> Add Note
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="notesForm">
                <div class="modal-body">
                    <input type="hidden" name="can_code" id="notes_can_code">
                    <input type="hidden" name="job_id" id="notes_job_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Note Type</label>
                        <select name="note_type" class="form-control">
                            <option value="general">General Note</option>
                            <option value="screening">Screening</option>
                            <option value="interview">Interview</option>
                            <option value="client_interaction">Client Interaction</option>
                            <option value="follow_up">Follow-up</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Note <span class="text-danger">*</span></label>
                        <textarea name="note" class="form-control" rows="4" required
                                  placeholder="Enter your note here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-save"></i> Save Note
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#notesModal').on('show.bs.modal', function(e) {
    const canCode = $(this).data('can-code');
    const jobId = $(this).data('job-id');
    $('#notes_can_code').val(canCode);
    $('#notes_job_id').val(jobId);
});

$('#notesForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: '../candidates/handlers/note_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#notesModal').modal('hide');
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