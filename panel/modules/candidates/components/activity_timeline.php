<?php
require_once __DIR__ . '/../_common.php';
$can_code = $id ?? null;
if (!$can_code) return;

try {
    $stmt = $conn->prepare("SELECT * FROM candidates_edit_info WHERE can_code = ? ORDER BY edited_at DESC");
    $stmt->bind_param("s", $can_code);
    $stmt->execute();
    $timeline = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading timeline: ' . $e->getMessage() . '</div>';
    return;
}
?>
<div class="card">
    <div class="card-header">Activity Timeline</div>
    <div class="card-body p-0">
        <div class="timeline">
            <?php if (empty($timeline)): ?>
            <p class="text-muted text-center py-3"><i class="bx bx-history bx-lg"></i><br>No activity yet.</p>
            <?php else: ?>
            <?php foreach ($timeline as $t): ?>
            <div class="timeline-item">
                <div class="timeline-time">
                    <span class="date"><?php echo date('d M Y', strtotime($t['edited_at'])); ?></span>
                    <span class="time"><?php echo date('H:i', strtotime($t['edited_at'])); ?></span>
                </div>
                <div class="timeline-icon">
                    <i class="bx bx-edit"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-content-inner">
                        <h6 class="mb-1"><?php echo htmlspecialchars($t['edited_field']); ?> updated</h6>
                        <p class="mb-1">
                            From: <span class="text-danger"><?php echo htmlspecialchars($t['old_value']); ?></span><br>
                            To: <span class="text-success"><?php echo htmlspecialchars($t['new_value']); ?></span>
                        </p>
                        <small class="text-muted">By <?php echo htmlspecialchars($t['edited_name']); ?></small>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>