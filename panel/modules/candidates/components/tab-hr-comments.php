<?php
// HR Comments Tab (Manager+ Only)
if (!in_array($userLevel, ['manager', 'admin', 'super_admin'])) {
    echo '<div class="alert alert-warning">Access denied</div>';
    return;
}

// Fetch HR comments
$stmt = $conn->prepare("
    SELECT hrc.*, u.name as created_by_name
    FROM candidate_hr_comments hrc
    LEFT JOIN users u ON hrc.created_by = u.user_code
    WHERE hrc.candidate_code = ?
    ORDER BY hrc.created_at DESC
");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$hrComments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<div class="card">
    <div class="card-header bg-warning bg-opacity-10">
        <div class="d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="bx bx-lock-alt text-warning"></i>
                Confidential HR Comments
            </h6>
            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#addHRCommentModal">
                <i class="bx bx-plus"></i> Add Comment
            </button>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($hrComments)): ?>
            <p class="text-muted text-center py-3">No HR comments yet</p>
        <?php else: ?>
            <?php foreach ($hrComments as $comment): ?>
                <div class="border-bottom pb-3 mb-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge bg-warning"><?= htmlspecialchars($comment['comment_type']) ?></span>
                            <?php if ($comment['is_confidential']): ?>
                                <span class="badge bg-danger">Confidential</span>
                            <?php endif; ?>
                        </div>
                        <small class="text-muted">
                            <?= date('M d, Y H:i', strtotime($comment['created_at'])) ?>
                            by <?= htmlspecialchars($comment['created_by_name']) ?>
                        </small>
                    </div>
                    <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>