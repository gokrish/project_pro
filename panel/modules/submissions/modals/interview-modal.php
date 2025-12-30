<div class="modal fade" id="interviewModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/record-interview.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-calendar"></i> Schedule Interview
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Interview Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="interview_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Notes</label>
                        <textarea name="interview_notes" class="form-control" rows="4" 
                                  placeholder="Add interview details, format, attendees, etc..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Interview Result (Optional)</label>
                        <select name="interview_result" class="form-select">
                            <option value="">Not completed yet</option>
                            <option value="positive">Positive</option>
                            <option value="neutral">Neutral</option>
                            <option value="negative">Negative</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-check"></i> Save Interview
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>