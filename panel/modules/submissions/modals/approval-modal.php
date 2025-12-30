<!-- Approve Modal -->
<div class="modal fade" id="approveModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/approve.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            <input type="hidden" name="action" value="approve">
            
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-check-circle"></i> Approve Submission
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <strong>Candidate:</strong> <?= escape($submission['candidate_name']) ?><br>
                        <strong>Job:</strong> <?= escape($submission['job_title']) ?><br>
                        <strong>Client:</strong> <?= escape($submission['company_name']) ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Approval Notes</label>
                        <textarea name="approval_notes" class="form-control" rows="4" 
                                  placeholder="Add your comments (optional)..."></textarea>
                        <small class="text-muted">These notes will be visible to the recruiter</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check"></i> Approve
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Reject Modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/approve.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            <input type="hidden" name="action" value="reject">
            
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-x-circle"></i> Reject Submission
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        Are you sure you want to reject this submission?
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="approval_notes" class="form-control" rows="4" required
                                  placeholder="Please explain why this submission is being rejected..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="bx bx-x"></i> Reject
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>