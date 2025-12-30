<?php
/**
 * Change Application Status (Generic)
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $new_status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$application_id || !$new_status) {
        throw new Exception('Application ID and status are required');
    }
    
    // Validate status
    $validStatuses = [
        'applied', 'screening', 'screening_passed', 'pending_approval',
        'approved', 'submitted', 'shortlisted', 'interviewing',
        'interview_passed', 'offered', 'offer_accepted', 'placed',
        'rejected', 'withdrawn', 'on_hold'
    ];
    
    if (!in_array($new_status, $validStatuses)) {
        throw new Exception('Invalid status');
    }
    
    // Get application details
    $checkStmt = $conn->prepare("
        SELECT ja.can_code, ja.job_id, ja.status as old_status
        FROM job_applications ja
        WHERE ja.application_id = ?
    ");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    if (!$appData) {
        throw new Exception('Application not found');
    }
    
    // Update status
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = ?
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $new_status, $application_id);
    
    if ($stmt->execute()) {
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'change_status', 'application', ?, ?)
        ");
        
        $description = "Status changed from {$appData['old_status']} to $new_status";
        if ($notes) $description .= ": $notes";
        
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note if provided
        if ($notes) {
            $noteStmt = $conn->prepare("
                INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
                VALUES (?, ?, ?, 'status_change', ?)
            ");
            $noteText = "Status: $new_status\n$notes";
            $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $noteText, $user['user_code']);
            $noteStmt->execute();
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update status: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Status handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>