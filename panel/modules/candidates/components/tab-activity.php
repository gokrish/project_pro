<?php
/**
 * Tab: Activity Timeline
 * Shows field change history and system activities
 */
?>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bx bx-history me-2"></i> Activity Timeline
        </h5>
    </div>
    <div class="card-body">
        
        <?php if (empty($activityLogs)): ?>
        
        <div class="text-center py-5 text-muted">
            <i class="bx bx-history" style="font-size: 64px;"></i>
            <h5 class="mt-3 mb-2">No Activity Yet</h5>
            <p class="mb-0">Activity will appear here as changes are made to this candidate</p>
        </div>
        
        <?php else: ?>
        
        <div class="timeline timeline-detailed ps-3">
            <?php foreach ($activityLogs as $log): ?>
            <div class="timeline-item mb-4">
                <div class="timeline-marker bg-primary"></div>
                <div class="timeline-content">
                    
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <h6 class="mb-1">
                                <?php
                                $field = htmlspecialchars($log['edited_field'] ?? $log['field_name'] ?? 'Field');
                                echo ucfirst(str_replace('_', ' ', $field));
                                ?>
                                <?php if (!empty($log['action'])): ?>
                                - <?= ucfirst($log['action']) ?>
                                <?php endif; ?>
                            </h6>
                            <div class="text-muted small">
                                <i class="bx bx-user me-1"></i>
                                <?= htmlspecialchars($log['edited_name'] ?? $log['user_name'] ?? 'System') ?>
                                <span class="mx-2">•</span>
                                <i class="bx bx-time me-1"></i>
                                <?= timeAgo($log['edited_at'] ?? $log['created_at']) ?>
                            </div>
                        </div>
                        <span class="badge bg-label-primary">Update</span>
                    </div>
                    
                    <?php if (!empty($log['old_value']) || !empty($log['new_value'])): ?>
                    <div class="activity-changes">
                        <?php if (!empty($log['old_value'])): ?>
                        <div class="change-item mb-1">
                            <small class="text-muted">From:</small>
                            <span class="text-danger ms-2"><?= htmlspecialchars($log['old_value']) ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($log['new_value'])): ?>
                        <div class="change-item">
                            <small class="text-muted">To:</small>
                            <span class="text-success ms-2"><?= htmlspecialchars($log['new_value']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($log['description'])): ?>
                    <p class="mb-0 mt-2 text-muted small">
                        <?= htmlspecialchars($log['description']) ?>
                    </p>
                    <?php endif; ?>
                    
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (count($activityLogs) >= 100): ?>
        <div class="alert alert-info mb-0">
            <i class="bx bx-info-circle me-2"></i>
            Showing last 100 activities. Older activities are archived.
        </div>
        <?php endif; ?>
        
        <?php endif; ?>
        
    </div>
</div>

<style>
.timeline-detailed {
    position: relative;
    list-style: none;
}

.timeline-detailed::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background: linear-gradient(to bottom, #667eea 0%, #764ba2 100%);
}

.timeline-item {
    position: relative;
    padding-left: 35px;
}

.timeline-marker {
    position: absolute;
    left: -6px;
    top: 5px;
    width: 14px;
    height: 14px;
    border-radius: 50%;
    border: 3px solid #fff;
    box-shadow: 0 0 0 2px #667eea;
    z-index: 1;
}

.timeline-content {
    padding: 20px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e9ecef;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
}

.timeline-content:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.activity-changes {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    margin-top: 10px;
}

.change-item {
    font-family: 'Monaco', 'Menlo', 'Courier New', monospace;
    font-size: 0.85rem;
}
</style>
