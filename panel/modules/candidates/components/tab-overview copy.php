<?php
/**
 * Tab: Overview
 * Contact information, compensation, quick stats
 */
?>

<div class="row">
    <div class="col-lg-8">
        
        <!-- Contact Information Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Contact Information</h5>
                <?php if (Permission::can('candidates', 'edit')): ?>
                <button type="button" class="btn btn-sm btn-primary" id="editContactBtn">
                    <i class="bx bx-edit me-1"></i> Edit Contact
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                
                <!-- View Mode -->
                <div id="contactViewMode">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1">Email Address</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-envelope text-primary me-2 fs-5"></i>
                                <a href="mailto:<?= htmlspecialchars($candidate['email']) ?>" class="text-primary">
                                    <?= htmlspecialchars($candidate['email']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1">Phone Number</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-phone text-success me-2 fs-5"></i>
                                <a href="tel:<?= htmlspecialchars($candidate['phone']) ?>" class="text-primary">
                                    <?= htmlspecialchars($candidate['phone']) ?>
                                </a>
                            </div>
                        </div>
                        
                        <?php if (!empty($candidate['secondary_phone'])): ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1">Alternative Phone</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-phone-call text-info me-2 fs-5"></i>
                                <a href="tel:<?= htmlspecialchars($candidate['secondary_phone']) ?>">
                                    <?= htmlspecialchars($candidate['secondary_phone']) ?>
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1">Current Location</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-map text-warning me-2 fs-5"></i>
                                <span><?= htmlspecialchars($candidate['location'] ?? 'Not specified') ?></span>
                            </div>
                        </div>
                        
                        <?php if (!empty($candidate['nationality'])): ?>
                        <div class="col-md-6 mb-3">
                            <label class="text-muted small mb-1">Nationality</label>
                            <div class="d-flex align-items-center">
                                <i class="bx bx-globe text-info me-2 fs-5"></i>
                                <span><?= htmlspecialchars($candidate['nationality']) ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Edit Mode (Hidden by default) -->
                <div id="contactEditMode" style="display: none;">
                    <form id="updateContactForm">
                        <input type="hidden" name="candidate_code" value="<?= htmlspecialchars($candidateCode) ?>">
                        <input type="hidden" name="csrf_token" value="<?= \ProConsultancy\Core\CSRFToken::getToken() ?>">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" 
                                       value="<?= htmlspecialchars($candidate['email']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="edit_phone" name="phone" 
                                       value="<?= htmlspecialchars($candidate['phone']) ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_secondary_phone" class="form-label">Alternative Phone</label>
                                <input type="tel" class="form-control" id="edit_secondary_phone" name="secondary_phone" 
                                       value="<?= htmlspecialchars($candidate['secondary_phone'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_location" class="form-label">Current Location <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_location" name="location" required>
                                    <option value="">Select Location</option>
                                    <option value="Belgium" <?= $candidate['location'] === 'Belgium' ? 'selected' : '' ?>>Belgium</option>
                                    <option value="India" <?= $candidate['location'] === 'India' ? 'selected' : '' ?>>India</option>
                                    <option value="Netherlands" <?= $candidate['location'] === 'Netherlands' ? 'selected' : '' ?>>Netherlands</option>
                                    <option value="Luxembourg" <?= $candidate['location'] === 'Luxembourg' ? 'selected' : '' ?>>Luxembourg</option>
                                    <option value="France" <?= $candidate['location'] === 'France' ? 'selected' : '' ?>>France</option>
                                    <option value="Germany" <?= $candidate['location'] === 'Germany' ? 'selected' : '' ?>>Germany</option>
                                    <option value="UK" <?= $candidate['location'] === 'UK' ? 'selected' : '' ?>>United Kingdom</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_nationality" class="form-label">Nationality</label>
                                <input type="text" class="form-control" id="edit_nationality" name="nationality" 
                                       value="<?= htmlspecialchars($candidate['nationality'] ?? '') ?>">
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bx bx-save me-1"></i> Update Contact
                            </button>
                            <button type="button" class="btn btn-secondary" id="cancelEditBtn">
                                <i class="bx bx-x me-1"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>
                
            </div>
        </div>
        
        <!-- Compensation & Availability Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Compensation & Availability</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Current Salary</label>
                        <h5 class="mb-0 text-primary">
                            <?= formatCurrency($candidate['current_salary'] ?? 0) ?>
                        </h5>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Expected Salary</label>
                        <h5 class="mb-0 text-success">
                            <?= formatCurrency($candidate['expected_salary'] ?? 0) ?>
                        </h5>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Notice Period</label>
                        <p class="mb-0">
                            <?= !empty($candidate['notice_period_days']) 
                                ? $candidate['notice_period_days'] . ' days' 
                                : 'Not specified' ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Available From</label>
                        <p class="mb-0">
                            <?= !empty($candidate['availability_date']) 
                                ? date('M d, Y', strtotime($candidate['availability_date'])) 
                                : 'Immediately' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Work Authorization Card -->
        <?php if (!empty($candidate['work_permit_status']) || !empty($candidate['relocation_willing'])): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Work Authorization</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($candidate['work_permit_status'])): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Work Authorization Status</label>
                        <p class="mb-0">
                            <span class="badge bg-label-success">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $candidate['work_permit_status']))) ?>
                            </span>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Open to Relocation</label>
                        <p class="mb-0">
                            <?= $candidate['relocation_willing'] 
                                ? '<span class="badge bg-label-success">Yes</span>' 
                                : '<span class="badge bg-label-secondary">No</span>' ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Right Sidebar: Quick Stats -->
    <div class="col-lg-4">
        
        <!-- Quick Stats Card -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Quick Stats</h5>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-primary rounded p-2 me-3">
                        <i class="bx bx-time fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Total Experience</small>
                        <h6 class="mb-0">
                            <?= !empty($candidate['total_experience_years']) 
                                ? number_format($candidate['total_experience_years'], 1) . ' years' 
                                : 'Not specified' ?>
                        </h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-success rounded p-2 me-3">
                        <i class="bx bx-briefcase fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Current Position</small>
                        <h6 class="mb-0">
                            <?= htmlspecialchars($candidate['current_job_title'] ?? 'Not specified') ?>
                        </h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-warning rounded p-2 me-3">
                        <i class="bx bx-buildings fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Current Employer</small>
                        <h6 class="mb-0">
                            <?= htmlspecialchars($candidate['current_company'] ?? 'Not specified') ?>
                        </h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-info rounded p-2 me-3">
                        <i class="bx bx-calendar fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Last Updated</small>
                        <h6 class="mb-0"><?= timeAgo($candidate['updated_at']) ?></h6>
                    </div>
                </div>
                
                <hr class="my-3">
                
                <div class="d-flex align-items-center mb-2">
                    <div class="badge bg-label-primary rounded p-2 me-3">
                        <i class="bx bx-paper-plane fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Submissions</small>
                        <h6 class="mb-0"><?= count($submissions) ?></h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-2">
                    <div class="badge bg-label-success rounded p-2 me-3">
                        <i class="bx bx-spreadsheet fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Applications</small>
                        <h6 class="mb-0"><?= count($applications) ?></h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-2">
                    <div class="badge bg-label-warning rounded p-2 me-3">
                        <i class="bx bx-conversation fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Interviews</small>
                        <h6 class="mb-0"><?= count($interviews) ?></h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-success rounded p-2 me-3">
                        <i class="bx bx-check-circle fs-5"></i>
                    </div>
                    <div class="flex-grow-1">
                        <small class="text-muted d-block">Placements</small>
                        <h6 class="mb-0"><?= count($placements) ?></h6>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- LinkedIn Profile -->
        <?php if (!empty($candidate['linkedin_url']) && filter_var($candidate['linkedin_url'], FILTER_VALIDATE_URL)): ?>
        <div class="card">
            <div class="card-body text-center">
                <i class="bx bxl-linkedin-square text-primary" style="font-size: 48px;"></i>
                <h6 class="mt-2 mb-3">LinkedIn Profile</h6>
                <a href="<?= htmlspecialchars($candidate['linkedin_url']) ?>" 
                   target="_blank" 
                   class="btn btn-sm btn-primary">
                    <i class="bx bxl-linkedin me-1"></i> View Profile
                </a>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<script>
// Toggle edit mode
$('#editContactBtn').on('click', function() {
    $('#contactViewMode').hide();
    $('#contactEditMode').show();
    $(this).hide();
});

// Cancel editing
$('#cancelEditBtn').on('click', function() {
    $('#contactEditMode').hide();
    $('#contactViewMode').show();
    $('#editContactBtn').show();
});

// Update contact form
$('#updateContactForm').on('submit', function(e) {
    e.preventDefault();
    
    const formData = $(this).serialize();
    
    $.ajax({
        url: '/panel/modules/candidates/handlers/update-contact.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        beforeSend: function() {
            Swal.fire({
                title: 'Updating...',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
        },
        success: function(response) {
            if (response.success) {
                Swal.fire('Success', 'Contact information updated', 'success').then(() => {
                    location.reload();
                });
            } else {
                Swal.fire('Error', response.message || 'Failed to update contact', 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'Failed to update contact information', 'error');
        }
    });
});
</script>
