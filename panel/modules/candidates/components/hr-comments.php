<?php
$can_code = $id ?? null;
if (!$can_code) return;

try {
    $stmt = $conn->prepare("SELECT * FROM hr_comments WHERE can_code = ? ORDER BY created_at DESC");
    $stmt->bind_param("s", $can_code);
    $stmt->execute();
    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading comments: ' . $e->getMessage() . '</div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_comment') {
    $comment = $_POST['comment'];
    $created_by = Auth::user()['user_code'];
    
    $stmt = $conn->prepare("INSERT INTO hr_comments (can_code, comment, created_by) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $can_code, $comment, $created_by);
    $stmt->execute();
    
    // Log to timeline
    $stmt = $conn->prepare("INSERT INTO candidates_edit_info (can_code, edited_field, new_value, edited_by) VALUES (?, 'HR Comment Added', ?, ?)");
    $stmt->bind_param("sss", $can_code, $comment, $created_by);
    $stmt->execute();
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
}
?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">HR Comments</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCommentModal"><i class="bx bx-plus"></i> Add Comment</button>
    </div>
    <div class="card-body">
        <?php if (empty($comments)): ?>
        <p class="text-muted text-center"><i class="bx bx-comment bx-lg"></i><br>No comments yet.</p>
        <?php else: ?>
        <div class="list-group">
            <?php foreach ($comments as $c): ?>
            <div class="list-group-item">
                <p><?php echo nl2br(htmlspecialchars($c['comment'])); ?></p>
                <small class="text-muted"><?php echo $c['created_by']; ?> • <?php echo $c['created_at']; ?></small>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Comment Modal -->
<div class="modal fade" id="addCommentModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Comment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_comment">
                <div class="modal-body">
                    <textarea class="form-control" name="comment" rows="5" required></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>