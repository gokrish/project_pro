<?php
/**
 * Reject Application with Reason
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $rejection_reason = sanitize($_POST['rejection_reason']);
    $rejection_stage = sanitize($_POST['rejection_stage'] ?? 'screening');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
    }
    
    if (empty($rejection_reason)) {
        throw new Exception('Rejection reason is required');
    }
    
    // Get application details
    $checkStmt = $conn->prepare("
        SELECT ja.can_code, ja.job_id, c.candidate_name, j.title as job_title
        FROM job_applications ja
        JOIN candidates c ON ja.can_code = c.can_code
        JOIN jobs j ON ja.job_id = j.job_id
        WHERE ja.application_id = ?
    ");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    if (!$appData) {
        throw new Exception('Application not found');
    }
    
    // Update application
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = 'rejected',
            rejection_reason = ?,
            rejection_stage = ?,
            rejected_by = ?,
            rejected_at = NOW()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('sssi', $rejection_reason, $rejection_stage, $user['user_code'], $application_id);
    
    if ($stmt->execute()) {
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'reject_application', 'application', ?, ?)
        ");
        
        $description = "Rejected at $rejection_stage: $rejection_reason";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add rejection note
        $noteText = "❌ Application Rejected\nStage: $rejection_stage\nReason: $rejection_reason";
        
        $noteStmt = $conn->prepare("
            INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
            VALUES (?, ?, ?, 'rejection', ?)
        ");
        $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $noteText, $user['user_code']);
        $noteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Application rejected successfully'
        ]);
    } else {
        throw new Exception('Failed to reject application: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Reject handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>