<?php
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;
use ProConsultancy\Core\ApiResponse;

Permission::require('candidates', 'edit');

// Validate transition
$validTransitions = CANDIDATE_STATUS_TRANSITIONS[$oldStatus] ?? [];
if (!in_array($newStatus, $validTransitions)) {
    ApiResponse::error('Invalid status transition', 400);
}

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $candidateCode = $_POST['candidate_code'] ?? null;
    $newStatus = $_POST['new_status'] ?? null;
    $notes = trim($_POST['notes'] ?? '');
    $nextFollowUp = $_POST['next_follow_up'] ?? null;
    
    if (!$candidateCode || !$newStatus) {
        throw new Exception('Missing required fields');
    }
    
    // Get current candidate data
    $stmt = $conn->prepare("
        SELECT status, candidate_name, email 
        FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    $oldStatus = $candidate['status'];
    
    // ═══════════════════════════════════════════════════════
    // STATUS TRANSITION VALIDATION (YOUR WORKFLOW)
    // ═══════════════════════════════════════════════════════
    
    // Validate: "contacted" requires notes
    if ($newStatus === 'contacted' && empty($notes)) {
        throw new Exception('Notes are required when marking as contacted. Please describe the conversation.');
    }
    
    // Validate: "contacted" requires follow-up date
    if ($newStatus === 'contacted' && empty($nextFollowUp)) {
        throw new Exception('Next follow-up date is required when marking as contacted.');
    }
    
    // Validate: "qualified" requires screening notes exist
    if ($newStatus === 'qualified') {
        $stmt = $conn->prepare("
            SELECT screening_notes FROM candidates WHERE candidate_code = ?
        ");
        $stmt->bind_param("s", $candidateCode);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (empty($result['screening_notes'])) {
            throw new Exception('Screening notes required before marking as qualified. Please add screening notes first.');
        }
    }
    
    // Validate: "submitted" requires at least one submission
    if ($newStatus === 'submitted') {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM candidate_submissions 
            WHERE candidate_code = ? AND deleted_at IS NULL
        ");
        $stmt->bind_param("s", $candidateCode);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] == 0) {
            throw new Exception('Cannot mark as submitted. No job submissions found. Please submit candidate to a job first.');
        }
    }
    
    // Validate: "rejected" requires reason
    if ($newStatus === 'rejected' && empty($notes)) {
        throw new Exception('Rejection reason is required.');
    }
    
    // Validate: "placed" requires notes about placement
    if ($newStatus === 'placed' && empty($notes)) {
        throw new Exception('Placement details are required (job, start date, etc.).');
    }
    
    // ═══════════════════════════════════════════════════════
    // UPDATE DATABASE
    // ═══════════════════════════════════════════════════════
    
    $conn->begin_transaction();
    
    // Update candidate status
    $updateFields = "status = ?, updated_at = NOW()";
    $params = [$newStatus];
    $types = 's';
    
    // Update last_contacted_date if moving to "contacted"
    if ($newStatus === 'contacted') {
        $updateFields .= ", last_contacted_date = CURDATE()";
    }
    
    // Update next_follow_up if provided
    if (!empty($nextFollowUp)) {
        $updateFields .= ", next_follow_up_date = ?";
        $params[] = $nextFollowUp;
        $types .= 's';
    }
    
    $params[] = $candidateCode;
    $types .= 's';
    
    $sql = "UPDATE candidates SET {$updateFields} WHERE candidate_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    
    // Insert into status change log
    $stmt = $conn->prepare("
        INSERT INTO candidate_status_log (
            candidate_code, old_status, new_status, 
            change_reason, notes, changed_by, changed_at
        ) VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $changeReason = "Status changed from '{$oldStatus}' to '{$newStatus}'";
    $changedBy = Auth::userCode();
    
    $stmt->bind_param("ssssss", 
        $candidateCode, $oldStatus, $newStatus, 
        $changeReason, $notes, $changedBy
    );
    $stmt->execute();
    
    // Log to activity log
    $description = "Status: {$oldStatus} → {$newStatus}";
    if ($notes) {
        $description .= " | Notes: " . substr($notes, 0, 100);
    }
    
    Logger::getInstance()->logActivity(
        'status_change',
        'candidates',
        $candidateCode,
        $description,
        [
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'notes' => $notes,
            'next_follow_up' => $nextFollowUp
        ]
    );
    
    $conn->commit();
    
    FlashMessage::success("Status updated to '{$newStatus}' successfully!");
    redirect(BASE_URL . '/panel/modules/candidates/view.php?code=' . $candidateCode);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    FlashMessage::error('Failed to update status: ' . $e->getMessage());
    back();
}