<?php
/**
 * Tab: Job Activity
 * Complete recruitment journey: Submissions → Applications → Interviews → Placements
 * 
 * This tab shows:
 * 1. Which clients this candidate was submitted to (Submissions module)
 * 2. Job applications and their status (Applications module)
 * 3. Interview feedback and ratings (Applications module)
 * 4. Placement history (Applications module)
 */
?>

<?php if (empty($submissions) && empty($applications) && empty($interviews) && empty($placements)): ?>

<!-- Empty State -->
<div class="card">
    <div class="card-body">
        <div class="text-center py-5">
            <i class="bx bx-line-chart" style="font-size: 64px; color: #cbd5e0;"></i>
            <h5 class="mt-3 mb-2">No Job Activity Yet</h5>
            <p class="text-muted mb-4">This candidate hasn't been submitted to any jobs yet.</p>
            
            <?php if (Permission::can('submissions', 'create')): ?>
            <a href="/panel/modules/submissions/create.php?candidate=<?= urlencode($candidateCode) ?>" 
               class="btn btn-primary">
                <i class="bx bx-plus me-1"></i> Create Submission
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php else: ?>

<!-- ===================================================================== -->
<!-- SECTION 1: SUBMISSIONS HISTORY -->
<!-- ===================================================================== -->
<?php if (!empty($submissions)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bx bx-paper-plane me-2"></i> Submission History
            <span class="badge bg-label-primary ms-2"><?= count($submissions) ?></span>
        </h5>
        <?php if (Permission::can('submissions', 'create')): ?>
        <a href="/panel/modules/submissions/create.php?candidate=<?= urlencode($candidateCode) ?>" 
           class="btn btn-sm btn-primary">
            <i class="bx bx-plus me-1"></i> New Submission
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <i class="bx bx-info-circle me-1"></i>
            Shows which clients this candidate was proposed to and the approval status.
        </p>
        
        <div class="timeline timeline-simple ps-3">
            <?php foreach ($submissions as $submission): ?>
            <div class="timeline-item mb-4">
                <div class="timeline-marker bg-<?= getStatusBadgeColor($submission['status']) ?>"></div>
                <div class="timeline-content">
                    
                    <!-- Submission Header -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">
                                <a href="/panel/modules/submissions/view.php?code=<?= urlencode($submission['submission_code']) ?>" 
                                   class="text-dark">
                                    <?= htmlspecialchars($submission['job_title']) ?>
                                </a>
                            </h6>
                            <p class="text-muted small mb-0">
                                <i class="bx bx-buildings me-1"></i>
                                <?= htmlspecialchars($submission['client_name']) ?>
                            </p>
                        </div>
                        <span class="badge bg-<?= getStatusBadgeColor($submission['status']) ?>">
                            <?= ucfirst(str_replace('_', ' ', $submission['status'])) ?>
                        </span>
                    </div>
                    
                    <!-- Submission Details -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-4">
                            <small class="text-muted d-block">Submission Code</small>
                            <code class="text-primary"><?= htmlspecialchars($submission['submission_code']) ?></code>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Submitted By</small>
                            <span><?= htmlspecialchars($submission['submitted_by_name'] ?? 'Unknown') ?></span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Submitted On</small>
                            <span><?= date('M d, Y', strtotime($submission['submitted_at'])) ?></span>
                        </div>
                        
                        <?php if (!empty($submission['proposed_rate'])): ?>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Proposed Rate</small>
                            <span class="text-success fw-semibold">€<?= number_format($submission['proposed_rate'], 0) ?>/day</span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($submission['reviewed_by_name'])): ?>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Reviewed By</small>
                            <span><?= htmlspecialchars($submission['reviewed_by_name']) ?></span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Reviewed On</small>
                            <span><?= date('M d, Y', strtotime($submission['reviewed_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Fit Reason -->
                    <?php if (!empty($submission['fit_reason'])): ?>
                    <div class="alert alert-info mb-2">
                        <small class="text-muted d-block mb-1"><strong>Why Good Fit:</strong></small>
                        <?= nl2br(htmlspecialchars($submission['fit_reason'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Review Notes -->
                    <?php if (!empty($submission['review_notes'])): ?>
                    <div class="alert alert-warning mb-2">
                        <small class="text-muted d-block mb-1"><strong>Manager Review Notes:</strong></small>
                        <?= nl2br(htmlspecialchars($submission['review_notes'])) ?>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Conversion Status -->
                    <?php if ($submission['converted_to_application']): ?>
                    <div class="alert alert-success mb-0">
                        <i class="bx bx-check-circle me-1"></i>
                        <strong>Converted to Application</strong>
                        <?php if (!empty($submission['application_id'])): ?>
                        - <a href="/panel/modules/applications/view.php?id=<?= $submission['application_id'] ?>" class="alert-link">
                            View Application Details
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="mt-2">
                        <a href="/panel/modules/submissions/view.php?code=<?= urlencode($submission['submission_code']) ?>" 
                           class="btn btn-sm btn-outline-primary">
                            <i class="bx bx-show me-1"></i> View Details
                        </a>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- SECTION 2: JOB APPLICATIONS -->
<!-- ===================================================================== -->
<?php if (!empty($applications)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="bx bx-spreadsheet me-2"></i> Job Applications
            <span class="badge bg-label-success ms-2"><?= count($applications) ?></span>
        </h5>
        <?php if (Permission::can('applications', 'create')): ?>
        <a href="/panel/modules/applications/create.php?candidate=<?= urlencode($candidateCode) ?>" 
           class="btn btn-sm btn-success">
            <i class="bx bx-plus me-1"></i> New Application
        </a>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <i class="bx bx-info-circle me-1"></i>
            Active job applications and their current status in the recruitment pipeline.
        </p>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Job Title</th>
                        <th>Client</th>
                        <th>Applied Date</th>
                        <th>Current Status</th>
                        <th>Interviews</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($applications as $app): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($app['job_title']) ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="bx bx-map-pin me-1"></i><?= htmlspecialchars($app['job_location'] ?? 'Remote') ?>
                            </small>
                        </td>
                        <td><?= htmlspecialchars($app['client_name'] ?? '-') ?></td>
                        <td><?= date('M d, Y', strtotime($app['application_date'])) ?></td>
                        <td>
                            <span class="badge bg-<?= getStatusBadgeColor($app['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $app['status'])) ?>
                            </span>
                            <br>
                            <small class="text-muted">
                                Since <?= timeAgo($app['current_stage_since']) ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($app['interview_count'] > 0): ?>
                            <span class="badge bg-label-info">
                                <?= $app['interview_count'] ?> round<?= $app['interview_count'] > 1 ? 's' : '' ?>
                            </span>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/panel/modules/applications/view.php?id=<?= $app['application_id'] ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-show me-1"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- SECTION 3: INTERVIEW HISTORY -->
<!-- ===================================================================== -->
<?php if (!empty($interviews)): ?>
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bx bx-conversation me-2"></i> Interview History
            <span class="badge bg-label-warning ms-2"><?= count($interviews) ?></span>
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <i class="bx bx-info-circle me-1"></i>
            All interview rounds with feedback and ratings from clients.
        </p>
        
        <div class="timeline timeline-simple ps-3">
            <?php foreach ($interviews as $interview): ?>
            <div class="timeline-item mb-4">
                <div class="timeline-marker bg-warning"></div>
                <div class="timeline-content">
                    
                    <!-- Interview Header -->
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">
                                <?= htmlspecialchars($interview['job_title']) ?> - Round <?= $interview['interview_round'] ?>
                            </h6>
                            <p class="text-muted small mb-0">
                                <i class="bx bx-buildings me-1"></i>
                                <?= htmlspecialchars($interview['client_name']) ?>
                            </p>
                        </div>
                        <span class="badge bg-label-warning">
                            <?= ucfirst($interview['interview_type'] ?? 'General') ?>
                        </span>
                    </div>
                    
                    <!-- Interview Details -->
                    <div class="row g-3 mb-2">
                        <div class="col-md-3">
                            <small class="text-muted d-block">Interview Date</small>
                            <span><?= date('M d, Y', strtotime($interview['interview_date'])) ?></span>
                        </div>
                        <div class="col-md-4">
                            <small class="text-muted d-block">Interviewer</small>
                            <span><?= htmlspecialchars($interview['interviewer_name'] ?? 'Not specified') ?></span>
                        </div>
                        <div class="col-md-3">
                            <small class="text-muted d-block">Rating</small>
                            <span><?= getRatingStars($interview['rating']) ?></span>
                        </div>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Recommendation</small>
                            <?php
                            $recColor = 'secondary';
                            if ($interview['recommendation'] === 'hire') $recColor = 'success';
                            elseif ($interview['recommendation'] === 'reject') $recColor = 'danger';
                            elseif ($interview['recommendation'] === 'maybe') $recColor = 'warning';
                            ?>
                            <span class="badge bg-<?= $recColor ?>">
                                <?= ucfirst($interview['recommendation'] ?? 'Pending') ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Feedback -->
                    <?php if (!empty($interview['feedback'])): ?>
                    <div class="alert alert-light mb-0">
                        <small class="text-muted d-block mb-1"><strong>Interviewer Feedback:</strong></small>
                        <?= nl2br(htmlspecialchars($interview['feedback'])) ?>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== -->
<!-- SECTION 4: PLACEMENT HISTORY -->
<!-- ===================================================================== -->
<?php if (!empty($placements)): ?>
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bx bx-check-circle me-2"></i> Placement History
            <span class="badge bg-label-success ms-2"><?= count($placements) ?></span>
        </h5>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">
            <i class="bx bx-info-circle me-1"></i>
            Successful placements with client feedback and performance.
        </p>
        
        <?php foreach ($placements as $placement): ?>
        <div class="card border border-success mb-3">
            <div class="card-body">
                
                <!-- Placement Header -->
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="mb-1"><?= htmlspecialchars($placement['job_title']) ?></h5>
                        <p class="text-muted mb-0">
                            <i class="bx bx-buildings me-1"></i>
                            <?= htmlspecialchars($placement['client_name']) ?>
                        </p>
                    </div>
                    <span class="badge bg-<?= $placement['status'] === 'active' ? 'success' : 'secondary' ?> badge-lg">
                        <?= ucfirst($placement['status']) ?>
                    </span>
                </div>
                
                <!-- Placement Details -->
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Placement Date</small>
                        <strong><?= date('M d, Y', strtotime($placement['placement_date'])) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Start Date</small>
                        <strong><?= date('M d, Y', strtotime($placement['start_date'])) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Contract Type</small>
                        <span class="badge bg-label-info"><?= ucfirst($placement['contract_type']) ?></span>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Annual Salary</small>
                        <strong class="text-success">€<?= number_format($placement['annual_salary'], 0) ?></strong>
                    </div>
                    
                    <div class="col-md-3">
                        <small class="text-muted d-block">Placement Fee</small>
                        <strong class="text-success">€<?= number_format($placement['placement_fee'], 0) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Probation Period</small>
                        <span><?= $placement['probation_period_months'] ?> months</span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Client Rating</small>
                        <span><?= getRatingStars($placement['client_rating']) ?></span>
                    </div>
                </div>
                
                <!-- Client Feedback -->
                <?php if (!empty($placement['client_feedback'])): ?>
                <div class="alert alert-success mt-3 mb-0">
                    <small class="text-muted d-block mb-1"><strong>Client Feedback:</strong></small>
                    <?= nl2br(htmlspecialchars($placement['client_feedback'])) ?>
                </div>
                <?php endif; ?>
                
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php endif; ?>

<style>
/* Timeline Styles */
.timeline-simple {
    position: relative;
    list-style: none;
}

.timeline-simple::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #e9ecef;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
}

.timeline-marker {
    position: absolute;
    left: -5px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #e9ecef;
}

.timeline-content {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 3px solid #e9ecef;
}

/* Print Styles */
@media print {
    .timeline-simple::before {
        background: #000;
    }
    
    .btn {
        display: none;
    }
}
</style>
