<?php
/**
 * Add Client Feedback to Application
 */

require_once __DIR__ . '/../_common.php';
header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $client_feedback = sanitize($_POST['client_feedback'] ?? '');
    $client_decision = sanitize($_POST['client_decision'] ?? '');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
    }
    
    if (empty($client_feedback)) {
        throw new Exception('Feedback is required');
    }
    
    // Get application details
    $checkStmt = $conn->prepare("SELECT can_code, job_id FROM job_applications WHERE application_id = ?");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    // Update application with feedback
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET client_feedback = ?,
            feedback_received_date = NOW()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $client_feedback, $application_id);
    
    if ($stmt->execute()) {
        // Update status based on decision
        if ($client_decision === 'shortlist') {
            $statusStmt = $conn->prepare("UPDATE job_applications SET status = 'shortlisted' WHERE application_id = ?");
            $statusStmt->bind_param('i', $application_id);
            $statusStmt->execute();
        } elseif ($client_decision === 'reject') {
            $statusStmt = $conn->prepare("UPDATE job_applications SET status = 'rejected' WHERE application_id = ?");
            $statusStmt->bind_param('i', $application_id);
            $statusStmt->execute();
        }
        
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'client_feedback', 'application', ?, ?)
        ");
        
        $description = "Client feedback received: " . ($client_decision ?: 'pending decision');
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note
        $noteStmt = $conn->prepare("
            INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
            VALUES (?, ?, ?, 'client_feedback', ?)
        ");
        $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $client_feedback, $user['user_code']);
        $noteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Client feedback recorded successfully'
        ]);
    } else {
        throw new Exception('Failed to save feedback: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Feedback handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>