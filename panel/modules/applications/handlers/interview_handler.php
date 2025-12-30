<?php
/**
 * Schedule Interview
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $interview_date = sanitize($_POST['interview_date']);
    $interview_time = sanitize($_POST['interview_time']);
    $interview_type = sanitize($_POST['interview_type'] ?? 'phone');
    $location = sanitize($_POST['location'] ?? '');
    $interviewer_name = sanitize($_POST['interviewer_name'] ?? '');
    $notes = sanitize($_POST['notes'] ?? '');
    
    if (!$application_id || !$interview_date || !$interview_time) {
        throw new Exception('Application ID, date, and time are required');
    }
    
    // Get application details
    $checkStmt = $conn->prepare("SELECT can_code, job_id FROM job_applications WHERE application_id = ?");
    $checkStmt->bind_param('i', $application_id);
    $checkStmt->execute();
    $appData = $checkStmt->get_result()->fetch_assoc();
    
    if (!$appData) {
        throw new Exception('Application not found');
    }
    
    // Insert interview
    $stmt = $conn->prepare("
        INSERT INTO interviews 
        (application_id, can_code, job_id, interview_date, interview_time, 
         interview_type, location, interviewer_name, notes, status, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?)
    ");
    
    $stmt->bind_param(
        'isissssss',
        $application_id,
        $appData['can_code'],
        $appData['job_id'],
        $interview_date,
        $interview_time,
        $interview_type,
        $location,
        $interviewer_name,
        $notes,
        $user['user_code']
    );
    
    if ($stmt->execute()) {
        $interview_id = $stmt->insert_id;
        
        // Update application status
        $statusStmt = $conn->prepare("UPDATE job_applications SET status = 'interviewing' WHERE application_id = ?");
        $statusStmt->bind_param('i', $application_id);
        $statusStmt->execute();
        
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'schedule_interview', 'application', ?, ?)
        ");
        
        $description = "Interview scheduled for $interview_date at $interview_time";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note
        $noteText = "Interview scheduled:\nDate: $interview_date\nTime: $interview_time\nType: $interview_type";
        if ($location) $noteText .= "\nLocation: $location";
        if ($notes) $noteText .= "\nNotes: $notes";
        
        $noteStmt = $conn->prepare("
            INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
            VALUES (?, ?, ?, 'interview', ?)
        ");
        $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $noteText, $user['user_code']);
        $noteStmt->execute();
        
        // TODO: Send email notification to candidate (implement in future)
        
        echo json_encode([
            'success' => true,
            'message' => 'Interview scheduled successfully',
            'interview_id' => $interview_id
        ]);
    } else {
        throw new Exception('Failed to schedule interview: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Interview handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>