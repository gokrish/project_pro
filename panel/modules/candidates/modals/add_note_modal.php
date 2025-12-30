<div class="modal fade" id="addNoteModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="handlers/candidate_note_handler.php">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="can_code" value="<?php echo $id; ?>">
                <div class="modal-body">
                    <textarea name="note" class="form-control" rows="5" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>