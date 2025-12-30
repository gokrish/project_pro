<?php
require_once __DIR__ . '/../_common.php';
$can_code = $id ?? null;
if (!$can_code) return;

try {
    $stmt = $conn->prepare("SELECT * FROM call_logs WHERE can_code = ? ORDER BY call_date DESC");
    $stmt->bind_param("s", $can_code);
    $stmt->execute();
    $logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading call logs: ' . $e->getMessage() . '</div>';
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'add_call') {
    $call_date = $_POST['call_date'];
    $outcome = $_POST['outcome'];
    $notes = $_POST['notes'];
    $logged_by = Auth::user()['user_code'];
    
    $stmt = $conn->prepare("INSERT INTO call_logs (can_code, call_date, outcome, notes, logged_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $can_code, $call_date, $outcome, $notes, $logged_by);
    $stmt->execute();
    
    // Log to timeline
    $stmt = $conn->prepare("INSERT INTO candidates_edit_info (can_code, edited_field, new_value, edited_by) VALUES (?, 'Call Logged', ?, ?)");
    $new_value = "Outcome: $outcome";
    $stmt->bind_param("sss", $can_code, $new_value, $logged_by);
    $stmt->execute();
    
    header('Location: ' . $_SERVER['REQUEST_URI']);
}
?>
<div class="card">
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Call Logs</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addCallModal"><i class="bx bx-plus"></i> Add Call</button>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
        <p class="text-muted text-center"><i class="bx bx-phone bx-lg"></i><br>No calls logged yet.</p>
        <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Outcome</th>
                    <th>Notes</th>
                    <th>By</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo $log['call_date']; ?></td>
                    <td><span class="badge bg-<?php echo strtolower($log['outcome']); ?>"><?php echo $log['outcome']; ?></span></td>
                    <td><?php echo nl2br(htmlspecialchars($log['notes'])); ?></td>
                    <td><?php echo htmlspecialchars($log['logged_by']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<!-- Add Call Modal -->
<div class="modal fade" id="addCallModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log New Call</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_call">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" name="call_date" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Outcome</label>
                        <select class="form-select" name="outcome">
                            <option>Positive</option>
                            <option>Negative</option>
                            <option>Neutral</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Log Call</button>
                </div>
            </form>
        </div>
    </div>
</div>