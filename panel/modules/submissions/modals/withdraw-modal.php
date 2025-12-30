<div class="modal fade" id="withdrawModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/withdraw.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-secondary text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-x-circle"></i> Withdraw Submission
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning:</strong> This action will withdraw the submission. The candidate will no longer be considered for this position.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Withdrawal Reason <span class="text-danger">*</span></label>
                        <textarea name="withdrawal_reason" class="form-control" rows="4" required
                                  placeholder="Please explain why you're withdrawing this submission..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-x-circle"></i> Withdraw
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>