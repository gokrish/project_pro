<div class="modal fade" id="placementModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="handlers/record-placement.php">
            <?= CSRFToken::field() ?>
            <input type="hidden" name="submission_code" value="<?= escape($submission['submission_code']) ?>">
            
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="bx bx-trophy"></i> Record Placement
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success">
                        <strong>🎉 Congratulations!</strong><br>
                        You're about to mark this as a successful placement!
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Placement/Start Date <span class="text-danger">*</span></label>
                        <input type="date" name="placement_date" class="form-control" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Placement Notes</label>
                        <textarea name="placement_notes" class="form-control" rows="5" 
                                  placeholder="Record final placement details here (salary, exact start date, reporting structure, etc.)..."></textarea>
                        <small class="text-muted">
                            Include any final details about the placement
                        </small>
                    </div>
                    
                    <div class="alert alert-warning">
                        <small><strong>Note:</strong> This will automatically update the candidate status to "Placed" and increment the job's placement count.</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-trophy"></i> Confirm Placement
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>