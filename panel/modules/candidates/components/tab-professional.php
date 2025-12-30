<?php
/**
 * Tab: Professional
 * Experience summary, skills, work history
 */
?>

<div class="row">
    <div class="col-lg-8">
        
        <!-- Professional Summary Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Professional Summary</h5>
                <?php if (Permission::can('candidates', 'edit')): ?>
                <a href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidateCode) ?>&section=professional" 
                   class="btn btn-sm btn-outline-primary">
                    <i class="bx bx-edit me-1"></i> Edit
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($candidate['experience_summary'])): ?>
                <div class="experience-summary">
                    <?= $candidate['experience_summary'] ?>
                </div>
                <?php else: ?>
                <div class="text-center py-4 text-muted">
                    <i class="bx bx-file-blank bx-lg mb-2"></i>
                    <p class="mb-0">No professional summary provided yet</p>
                    <?php if (Permission::can('candidates', 'edit')): ?>
                    <a href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidateCode) ?>&section=professional" 
                       class="btn btn-sm btn-primary mt-2">
                        <i class="bx bx-plus me-1"></i> Add Summary
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Current Employment Card -->
        <?php if (!empty($candidate['current_job_title']) || !empty($candidate['current_company'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Current Employment</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($candidate['current_job_title'])): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Position</label>
                        <h6 class="mb-0"><?= htmlspecialchars($candidate['current_job_title']) ?></h6>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($candidate['current_company'])): ?>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small mb-1">Company</label>
                        <h6 class="mb-0"><?= htmlspecialchars($candidate['current_company']) ?></h6>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($candidate['total_experience_years'])): ?>
                    <div class="col-md-6">
                        <label class="text-muted small mb-1">Total Experience</label>
                        <h6 class="mb-0"><?= number_format($candidate['total_experience_years'], 1) ?> years</h6>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
    
    <!-- Right Sidebar -->
    <div class="col-lg-4">
        
        <!-- Technical Skills Card -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Technical Skills</h5>
                <?php if (Permission::can('candidates', 'edit')): ?>
                <a href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidateCode) ?>&section=professional" 
                   class="text-primary small">
                    Edit
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (!empty($skills)): ?>
                <div class="d-flex flex-wrap gap-2">
                    <?php foreach ($skills as $skill): ?>
                    <span class="badge bg-primary"><?= htmlspecialchars($skill) ?></span>
                    <?php endforeach; ?>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="bx bx-info-circle me-1"></i> 
                    <?= count($skills) ?> skill<?= count($skills) !== 1 ? 's' : '' ?> listed
                </div>
                <?php else: ?>
                <div class="text-center py-3 text-muted">
                    <i class="bx bx-code-alt bx-lg mb-2"></i>
                    <p class="mb-0">No skills listed yet</p>
                    <?php if (Permission::can('candidates', 'edit')): ?>
                    <a href="/panel/modules/candidates/edit.php?code=<?= urlencode($candidateCode) ?>&section=professional" 
                       class="btn btn-sm btn-primary mt-2">
                        <i class="bx bx-plus me-1"></i> Add Skills
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Work Authorization Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Work Authorization</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($candidate['work_permit_status'])): ?>
                <div class="mb-3">
                    <label class="text-muted small mb-1">Status</label>
                    <div>
                        <span class="badge bg-label-success">
                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $candidate['work_permit_status']))) ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="mb-3">
                    <label class="text-muted small mb-1">Location</label>
                    <p class="mb-0">
                        <i class="bx bx-map me-1"></i>
                        <?= htmlspecialchars($candidate['location'] ?? 'Not specified') ?>
                    </p>
                </div>
                
                <div>
                    <label class="text-muted small mb-1">Relocation</label>
                    <p class="mb-0">
                        <?php if ($candidate['relocation_willing']): ?>
                        <span class="badge bg-label-success">
                            <i class="bx bx-check me-1"></i> Open to relocation
                        </span>
                        <?php else: ?>
                        <span class="badge bg-label-secondary">
                            <i class="bx bx-x me-1"></i> Not open to relocation
                        </span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>
        
    </div>
</div>
