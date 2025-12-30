<div class="modal fade" id="sendToClientModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/send-to-client.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-send"></i> Send to Client
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <p><strong>You are about to send:</strong></p>
                        <p class="mb-1"><strong>Candidate:</strong> <?= escape($submission['candidate_name']) ?></p>
                        <p class="mb-1"><strong>To Client:</strong> <?= escape($submission['company_name']) ?></p>
                        <p class="mb-0"><strong>For Position:</strong> <?= escape($submission['job_title']) ?></p>
                    </div>
                    
                    <p>An email will be sent to:</p>
                    <p class="text-primary"><strong><?= escape($submission['client_email']) ?></strong></p>
                    
                    <p class="text-muted small">
                        The client will receive the candidate profile and contact information.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-send"></i> Send Now
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>