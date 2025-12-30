<!-- Create Offer Modal -->
<div class="modal fade" id="offerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bx bx-gift"></i> Create Offer
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="offerForm">
                <div class="modal-body">
                    <input type="hidden" name="application_id" id="offer_application_id">
                    
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Offered Salary <span class="text-danger">*</span></label>
                            <input type="number" name="offered_salary" class="form-control" 
                                   step="0.01" required placeholder="e.g., 50000">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Currency</label>
                            <select name="offered_currency" class="form-control">
                                <option value="EUR">EUR (€)</option>
                                <option value="USD">USD ($)</option>
                                <option value="GBP">GBP (£)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Expected Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Offer Notes</label>
                        <textarea name="offer_notes" class="form-control" rows="3" 
                                  placeholder="Benefits, bonus, work arrangement, etc..."></textarea>
                        <small class="text-muted">
                            Note: In V1, offer letter will be created manually. Auto-generation in future version.
                        </small>
                    </div>
                    
                    <div class="alert alert-info mb-0">
                        <i class="bx bx-info-circle"></i>
                        <strong>Next Steps:</strong> After creating offer, manually prepare offer letter and send to candidate.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bx bx-check"></i> Create Offer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$('#offerModal').on('show.bs.modal', function(e) {
    const applicationId = $(this).data('application-id');
    $('#offer_application_id').val(applicationId);
    
    // Set default start date to 1 month from now
    const startDate = new Date();
    startDate.setMonth(startDate.getMonth() + 1);
    $('input[name="start_date"]').val(startDate.toISOString().split('T')[0]);
});

$('#offerForm').on('submit', function(e) {
    e.preventDefault();
    
    $.ajax({
        url: 'handlers/offer_handler.php',
        method: 'POST',
        data: $(this).serialize(),
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                $('#offerModal').modal('hide');
                alert('Offer created successfully! Please prepare and send the offer letter to the candidate.');
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