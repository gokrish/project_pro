<div class="modal fade" id="offerModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/record-offer.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title">
                        <i class="bx bx-gift"></i> Record Offer
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Offer Date <span class="text-danger">*</span></label>
                        <input type="date" name="offer_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Offer Details</label>
                        <textarea name="offer_notes" class="form-control" rows="5" 
                                  placeholder="Record offer details here (recruiter will manually enter salary, benefits, start date, etc.)..."></textarea>
                        <small class="text-muted">
                            Include: Salary/rate details, benefits, start date, any special terms
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="bx bx-check"></i> Save Offer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>