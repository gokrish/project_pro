<!-- Upload Document Modal -->
<div class="modal fade" id="uploadDocumentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bx bx-upload me-2"></i> Upload Document
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocumentForm" enctype="multipart/form-data">
                <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode) ?>">
                <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::getToken() ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="document_type" class="form-label">Document Type <span class="text-danger">*</span></label>
                        <select class="form-select" id="document_type" name="document_type" required>
                            <option value="">Select document type</option>
                            <option value="resume">Resume/CV</option>
                            <option value="cover_letter">Cover Letter</option>
                            <option value="certificate">Certificate</option>
                            <option value="portfolio">Portfolio</option>
                            <option value="reference">Reference Letter</option>
                            <option value="identification">ID Document</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_file" class="form-label">Choose File <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="document_file" name="document_file" 
                               accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.txt" required>
                        <div class="form-text">
                            Accepted formats: PDF, DOC, DOCX, JPG, PNG, TXT (Max: 10MB)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="document_notes" class="form-label">Notes (Optional)</label>
                        <textarea class="form-control" id="document_notes" name="notes" rows="3" 
                                  placeholder="Add any notes about this document..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bx bx-x me-1"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bx bx-upload me-1"></i> Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Upload Document Form Handler
$('#uploadDocumentForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Validate file size
    const fileInput = document.getElementById('document_file');
    if (fileInput.files[0] && fileInput.files[0].size > 10 * 1024 * 1024) {
        Swal.fire('Error', 'File size must be less than 10MB', 'error');
        return;
    }
    
    $.ajax({
        url: '/panel/modules/candidates/handlers/upload-document.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Uploading Document...',
                html: '<div class="progress"><div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%"></div></div>',
                allowOutsideClick: false,
                showConfirmButton: false
            });
        },
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', 'Document uploaded successfully', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to upload document', 'error');
            }
        },
        error: function(xhr) {
            const response = xhr.responseJSON;
            Swal.fire('Error', response?.message || 'Failed to upload document', 'error');
        }
    });
});

// Show file name when selected
$('#document_file').on('change', function() {
    const fileName = $(this).val().split('\\').pop();
    if (fileName) {
        $(this).next('.form-text').html('<i class="bx bx-check-circle text-success me-1"></i> ' + fileName);
    }
});
</script>
