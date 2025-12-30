<?php
/**
 * Tab: Communications
 * HR Comments, Notes, and Call Logs in one unified view
 */
?>

<div class="row">
    <div class="col-lg-8">
        
        <!-- HR Comments Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-comment-dots me-2"></i> HR Comments
                </h5>
                <?php if (Permission::can('candidates', 'add_comment')): ?>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                    <i class="bx bx-plus me-1"></i> Add Comment
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php
                // Get HR comments - SQL INJECTION SAFE
                $stmt = $conn->prepare("
                    SELECT c.*, u.name as created_by_name 
                    FROM hr_comments c 
                    LEFT JOIN users u ON c.created_by = u.user_code 
                    WHERE c.can_code = ? 
                    ORDER BY c.created_at DESC
                ");
                $stmt->bind_param("s", $candidateCode);
                $stmt->execute();
                $hrComments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($hrComments)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bx bx-comment-dots" style="font-size: 48px;"></i>
                    <p class="mb-0 mt-2">No HR comments yet</p>
                </div>
                <?php else: ?>
                <div class="timeline timeline-simple ps-3">
                    <?php foreach ($hrComments as $comment): ?>
                    <div class="timeline-item mb-3">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($comment['created_by_name'] ?? 'Unknown') ?></strong>
                                    <span class="text-muted ms-2"><?= timeAgo($comment['created_at']) ?></span>
                                </div>
                                <span class="badge bg-label-primary">HR Comment</span>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- General Notes Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-note me-2"></i> Notes
                </h5>
                <?php if (Permission::can('candidates', 'add_note')): ?>
                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                    <i class="bx bx-plus me-1"></i> Add Note
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($notes)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bx bx-note" style="font-size: 48px;"></i>
                    <p class="mb-0 mt-2">No notes yet</p>
                </div>
                <?php else: ?>
                <div class="timeline timeline-simple ps-3">
                    <?php foreach ($notes as $note): ?>
                    <div class="timeline-item mb-3">
                        <div class="timeline-marker bg-success"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($note['created_by_name'] ?? 'Unknown') ?></strong>
                                    <span class="text-muted ms-2"><?= timeAgo($note['created_at']) ?></span>
                                </div>
                                <span class="badge bg-label-success">Note</span>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($note['note_text'] ?? $note['note'] ?? '')) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Call Logs Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bx bx-phone me-2"></i> Call Logs
                </h5>
                <?php if (Permission::can('candidates', 'log_call')): ?>
                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#logCallModal">
                    <i class="bx bx-plus me-1"></i> Log Call
                </button>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php
                // Get call logs - SQL INJECTION SAFE
                $stmt = $conn->prepare("
                    SELECT cl.*, u.name as logged_by_name 
                    FROM call_logs cl 
                    LEFT JOIN users u ON cl.logged_by = u.user_code 
                    WHERE cl.can_code = ? 
                    ORDER BY cl.call_date DESC, cl.created_at DESC
                ");
                $stmt->bind_param("s", $candidateCode);
                $stmt->execute();
                $callLogs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                ?>
                
                <?php if (empty($callLogs)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="bx bx-phone" style="font-size: 48px;"></i>
                    <p class="mb-0 mt-2">No calls logged yet</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Outcome</th>
                                <th>Notes</th>
                                <th>Logged By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($callLogs as $log): 
                                $outcomeColor = 'secondary';
                                if (strtolower($log['outcome']) === 'positive') $outcomeColor = 'success';
                                elseif (strtolower($log['outcome']) === 'negative') $outcomeColor = 'danger';
                                elseif (strtolower($log['outcome']) === 'neutral') $outcomeColor = 'warning';
                            ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($log['call_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $outcomeColor ?>">
                                        <?= htmlspecialchars($log['outcome']) ?>
                                    </span>
                                </td>
                                <td><?= nl2br(htmlspecialchars($log['notes'])) ?></td>
                                <td><?= htmlspecialchars($log['logged_by_name'] ?? 'Unknown') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </div>
    
    <!-- Right Sidebar: Quick Actions & Summary -->
    <div class="col-lg-4">
        
        <!-- Communication Summary -->
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-3">Communication Summary</h5>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-primary rounded p-2 me-3">
                        <i class="bx bx-comment-dots fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">HR Comments</small>
                        <h6 class="mb-0"><?= count($hrComments ?? []) ?></h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center mb-3">
                    <div class="badge bg-label-success rounded p-2 me-3">
                        <i class="bx bx-note fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Notes</small>
                        <h6 class="mb-0"><?= count($notes) ?></h6>
                    </div>
                </div>
                
                <div class="d-flex align-items-center">
                    <div class="badge bg-label-warning rounded p-2 me-3">
                        <i class="bx bx-phone fs-5"></i>
                    </div>
                    <div>
                        <small class="text-muted d-block">Call Logs</small>
                        <h6 class="mb-0"><?= count($callLogs ?? []) ?></h6>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (Permission::can('candidates', 'add_comment')): ?>
                    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#addCommentModal">
                        <i class="bx bx-comment-dots me-2"></i> Add HR Comment
                    </button>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('candidates', 'add_note')): ?>
                    <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                        <i class="bx bx-note me-2"></i> Add Note
                    </button>
                    <?php endif; ?>
                    
                    <?php if (Permission::can('candidates', 'log_call')): ?>
                    <button class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#logCallModal">
                        <i class="bx bx-phone me-2"></i> Log Call
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
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
</style>
