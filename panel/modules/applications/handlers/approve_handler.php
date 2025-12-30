<?php
/**
 * Approve Application for Client Submission
 * Only admins/managers can approve
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    // Check if user is admin or manager
    if (!in_array($user['level'], ['admin', 'manager'])) {
        throw new Exception('You do not have permission to approve applications');
    }
    
    $application_id = (int)$_POST['application_id'];
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
    }
    
    // Get current application details
    $checkStmt = $conn->prepare("SELECT status, can_code, job_id FROM job_applications WHERE application_id = ?");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    if (!$appData) {
        throw new Exception('Application not found');
    }
    
    // Update application
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = 'approved',
            approved_by = ?,
            approved_at = NOW()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $user['user_code'], $application_id);
    
    if ($stmt->execute()) {
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'approve_application', 'application', ?, ?)
        ");
        
        $description = $notes ? "Approved with notes: $notes" : "Approved for client submission";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note if provided
        if ($notes) {
            $noteStmt = $conn->prepare("
                INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
                VALUES (?, ?, ?, 'approval', ?)
            ");
            $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $notes, $user['user_code']);
            $noteStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Application approved successfully'
        ]);
    } else {
        throw new Exception('Failed to approve application: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Approve handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?><?php
/**
 * Approve Application for Client Submission
 * Only admins/managers can approve
 */

require_once '../../../includes/core/Auth.php';
require_once '../../../includes/config/config.php';
requireLogin();

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    // Check if user is admin or manager
    if (!in_array($user['level'], ['admin', 'manager'])) {
        throw new Exception('You do not have permission to approve applications');
    }
    
    $application_id = (int)$_POST['application_id'];
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
    }
    
    // Get current application details
    $checkStmt = $conn->prepare("SELECT status, can_code, job_id FROM job_applications WHERE application_id = ?");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    if (!$appData) {
        throw new Exception('Application not found');
    }
    
    // Update application
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = 'approved',
            approved_by = ?,
            approved_at = NOW()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $user['user_code'], $application_id);
    
    if ($stmt->execute()) {
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'approve_application', 'application', ?, ?)
        ");
        
        $description = $notes ? "Approved with notes: $notes" : "Approved for client submission";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note if provided
        if ($notes) {
            $noteStmt = $conn->prepare("
                INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
                VALUES (?, ?, ?, 'approval', ?)
            ");
            $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $notes, $user['user_code']);
            $noteStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Application approved successfully'
        ]);
    } else {
        throw new Exception('Failed to approve application: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Approve handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>