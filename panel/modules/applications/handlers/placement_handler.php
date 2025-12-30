<?php
/**
 * Mark Application as Successfully Placed
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $placement_notes = sanitize($_POST['placement_notes'] ?? '');
    $actual_start_date = sanitize($_POST['actual_start_date'] ?? '');
    
    if (!$application_id) {
        throw new Exception('Invalid application ID');
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
    
    // Update application status to placed
    $stmt = $conn->prepare("
        UPDATE job_applications 
        SET status = 'placed',
            placement_date = NOW()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('i', $application_id);
    
    if ($stmt->execute()) {
        // Update offer if exists
        if ($actual_start_date) {
            $offerStmt = $conn->prepare("
                UPDATE offers 
                SET candidate_response = 'accepted',
                    response_date = CURDATE(),
                    start_date = ?
                WHERE application_id = ?
            ");
            $offerStmt->bind_param('si', $actual_start_date, $application_id);
            $offerStmt->execute();
        }
        
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'mark_placed', 'application', ?, ?)
        ");
        
        $description = "Successfully placed: {$appData['candidate_name']} for {$appData['job_title']}";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add placement note
        $noteText = "✅ Successfully Placed!";
        if ($actual_start_date) $noteText .= "\nStart Date: $actual_start_date";
        if ($placement_notes) $noteText .= "\n$placement_notes";
        
        $noteStmt = $conn->prepare("
            INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
            VALUES (?, ?, ?, 'placement', ?)
        ");
        $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $noteText, $user['user_code']);
        $noteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Application marked as successfully placed! 🎉'
        ]);
    } else {
        throw new Exception('Failed to update placement: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Placement handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>