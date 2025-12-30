<div class="modal fade" id="rejectByClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/update-status.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            <input type="hidden" name="client_status" value="rejected">
            
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-x-circle"></i> Mark as Rejected
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Rejected By</label>
                        <select name="rejected_by" class="form-select">
                            <option value="client">Client</option>
                            <option value="candidate">Candidate</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="status_notes" class="form-control" rows="4" required
                                  placeholder="Please explain why the submission was rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x"></i> Mark as Rejected
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>