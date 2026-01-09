<?php
/**
 * Update Candidate Status - QUICK UPDATE
 * @version 6.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken, Logger};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('candidates', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

// CSRF validation
if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$candidateCode = $_POST['candidate_code'] ?? '';
$newStatus = $_POST['new_status'] ?? '';
$reason = $_POST['reason'] ?? '';
$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Get current status
    $stmt = $conn->prepare("SELECT status FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        throw new Exception('Candidate not found');
    }
    
    $oldStatus = $result['status'];
    
    // Validate transition
    $allowedTransitions = [
        'new' => ['screening', 'rejected', 'archived'],
        'screening' => ['qualified', 'rejected', 'archived'],
        'qualified' => ['active', 'on_hold', 'rejected', 'archived'],
        'active' => ['placed', 'on_hold', 'rejected', 'archived'],
        'on_hold' => ['active', 'rejected', 'archived'],
        'placed' => ['active', 'archived'],
        'rejected' => ['archived'],
        'archived' => ['active'],
    ];
    
    if (!in_array($newStatus, $allowedTransitions[$oldStatus] ?? [])) {
        throw new Exception('Invalid status transition');
    }
    
    // Update status
    $stmt = $conn->prepare("UPDATE candidates SET status = ?, updated_at = NOW() WHERE candidate_code = ?");
    $stmt->bind_param("ss", $newStatus, $candidateCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update status');
    }
    
    // Log status change
    $stmt = $conn->prepare("
        INSERT INTO candidate_status_history (
            candidate_code,
            old_status,
            new_status,
            reason,
            changed_by,
            changed_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("sssss",
        $candidateCode,
        $oldStatus,
        $newStatus,
        $reason,
        $userCode
    );
    
    $stmt->execute();
    
    Logger::getInstance()->logActivity('status_update', 'candidates', $candidateCode, 
        "Status changed from $oldStatus to $newStatus");
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'old_status' => $oldStatus,
        'new_status' => $newStatus
    ]);
    
} catch (Exception $e) {
    Logger::getInstance()->logError('status_update_error', $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>