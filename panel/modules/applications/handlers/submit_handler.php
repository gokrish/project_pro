<?php
/**
 * Submit Application to Client
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $submission_notes = sanitize($_POST['submission_notes'] ?? '');
    $submission_method = sanitize($_POST['submission_method'] ?? 'email');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
    }
    
    // Get application details
    $checkStmt = $conn->prepare("
        SELECT ja.*, c.candidate_name, j.title as job_title 
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
    
    // Check if already submitted
    if ($appData['submitted_to_client'] == 1) {
        throw new Exception('Application already submitted to client');
    }
    
    // Update application
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = 'submitted',
            submitted_to_client = 1,
            submitted_date = NOW(),
            submitted_by = ?
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $user['user_code'], $application_id);
    
    if ($stmt->execute()) {
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'submit_to_client', 'application', ?, ?)
        ");
        
        $description = "Submitted {$appData['candidate_name']} for {$appData['job_title']} via $submission_method";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add submission note
        if ($submission_notes) {
            $noteStmt = $conn->prepare("
                INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
                VALUES (?, ?, ?, 'client_submission', ?)
            ");
            $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $submission_notes, $user['user_code']);
            $noteStmt->execute();
        }
        
        // TODO: Send email notification to client (implement in future)
        
        echo json_encode([
            'success' => true,
            'message' => 'Application submitted to client successfully'
        ]);
    } else {
        throw new Exception('Failed to submit application: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Submit handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>