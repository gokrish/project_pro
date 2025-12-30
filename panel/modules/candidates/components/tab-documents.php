<?php
/**
 * Tab: Documents
 * Document management with upload and download
 */
?>

<?php if (empty($documents)): ?>

<!-- Empty State -->
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bx bx-file" style="font-size: 64px; color: #cbd5e0;"></i>
            <h5 class="mt-3 mb-2">No Documents Found</h5>
            <p class="text-muted mb-4">This candidate doesn't have any documents uploaded yet.</p>
            
            <?php if (Permission::can('candidates', 'upload_document')): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
                <i class="bx bx-upload me-1"></i> Upload Document
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<!-- Documents Grid -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bx bx-file me-2"></i> Candidate Documents
            <span class="badge bg-label-primary ms-2"><?= count($documents) ?></span>
        </h5>
        <?php if (Permission::can('candidates', 'upload_document')): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocumentModal">
            <i class="bx bx-upload me-1"></i> Upload New
        </button>
        <?php endif; ?>
    </div>
    <div class="card-body">
        
        <div class="row g-3">
            <?php foreach ($documents as $doc): 
                $fileExt = strtolower(pathinfo($doc['file_name'], PATHINFO_EXTENSION));
                $isImage = in_array($fileExt, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                $isPDF = $fileExt === 'pdf';
                $isDoc = in_array($fileExt, ['doc', 'docx']);
                $isExcel = in_array($fileExt, ['xls', 'xlsx']);
                
                // Icon and color based on file type
                $iconClass = 'bx-file';
                $iconColor = 'primary';
                if ($isPDF) {
                    $iconClass = 'bxs-file-pdf';
                    $iconColor = 'danger';
                } elseif ($isDoc) {
                    $iconClass = 'bxs-file-doc';
                    $iconColor = 'info';
                } elseif ($isExcel) {
                    $iconClass = 'bxs-spreadsheet';
                    $iconColor = 'success';
                } elseif ($isImage) {
                    $iconClass = 'bxs-image';
                    $iconColor = 'warning';
                }
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card border h-100 document-card">
                    <div class="card-body">
                        
                        <div class="d-flex align-items-start mb-3">
                            <!-- File Icon -->
                            <div class="flex-shrink-0 me-3">
                                <div class="avatar avatar-lg">
                                    <span class="avatar-initial rounded bg-label-<?= $iconColor ?>">
                                        <i class="bx <?= $iconClass ?> fs-2"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- File Info -->
                            <div class="flex-grow-1 overflow-hidden">
                                <h6 class="mb-1 text-truncate" title="<?= htmlspecialchars($doc['file_name']) ?>">
                                    <?= htmlspecialchars($doc['file_name']) ?>
                                </h6>
                                <p class="text-muted mb-0 small">
                                    <?= ucfirst($doc['document_type'] ?? 'Document') ?>
                                </p>
                                <div class="mt-2">
                                    <span class="badge bg-label-secondary"><?= strtoupper($fileExt) ?></span>
                                    <?php if (!empty($doc['file_size'])): ?>
                                    <span class="text-muted ms-2 small">
                                        <?= formatFileSize($doc['file_size']) ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- File Actions -->
                        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                            <small class="text-muted">
                                <i class="bx bx-time me-1"></i>
                                <?= timeAgo($doc['uploaded_at']) ?>
                            </small>
                            
                            <div class="dropdown">
                                <button type="button" class="btn btn-sm btn-icon btn-text-secondary rounded-pill dropdown-toggle hide-arrow" 
                                        data-bs-toggle="dropdown">
                                    <i class="bx bx-dots-vertical-rounded"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="<?= htmlspecialchars($doc['file_path']) ?>" target="_blank">
                                            <i class="bx bx-show me-2"></i> View
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="<?= htmlspecialchars($doc['file_path']) ?>" download>
                                            <i class="bx bx-download me-2"></i> Download
                                        </a>
                                    </li>
                                    <?php if (Permission::can('candidates', 'delete_document')): ?>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item text-danger delete-document" 
                                           href="#" 
                                           data-document-id="<?= $doc['id'] ?>"
                                           data-document-name="<?= htmlspecialchars($doc['file_name']) ?>">
                                            <i class="bx bx-trash me-2"></i> Delete
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
    </div>
</div>

<?php endif; ?>

<script>
// Delete document
$(document).on('click', '.delete-document', function(e) {
    e.preventDefault();
    
    const documentId = $(this).data('document-id');
    const documentName = $(this).data('document-name');
    
    Swal.fire({
        title: 'Delete Document?',
        text: `Are you sure you want to delete "${documentName}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!'
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '/panel/modules/candidates/handlers/delete-document.php',
                type: 'POST',
                data: {
                    document_id: documentId,
                    candidate_code: candidateCode,
                    csrf_token: csrfToken
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        Swal.fire('Deleted!', 'Document has been deleted.', 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', response.message || 'Failed to delete document', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to delete document', 'error');
                }
            });
        }
    });
});
</script>

<style>
.document-card {
    transition: all 0.3s ease;
}

.document-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
