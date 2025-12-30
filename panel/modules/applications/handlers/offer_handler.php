<?php
/**
 * Create Offer (Simple - No PDF generation in V1)
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $offered_salary = (float)$_POST['offered_salary'];
    $offered_currency = sanitize($_POST['offered_currency'] ?? 'EUR');
    $start_date = sanitize($_POST['start_date'] ?? '');
    $offer_notes = sanitize($_POST['offer_notes'] ?? '');
    
    if (!$application_id || !$offered_salary) {
        throw new Exception('Application ID and salary are required');
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
    
    // Insert offer
    $stmt = $conn->prepare("
        INSERT INTO offers 
        (application_id, job_id, can_code, offered_salary, offered_currency, 
         offered_date, start_date, negotiation_notes, created_by)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, ?)
    ");
    
    $stmt->bind_param(
        'iisdsss',
        $application_id,
        $appData['job_id'],
        $appData['can_code'],
        $offered_salary,
        $offered_currency,
        $start_date,
        $offer_notes,
        $user['user_code']
    );
    
    if ($stmt->execute()) {
        $offer_id = $stmt->insert_id;
        
        // Update application status
        $statusStmt = $conn->prepare("UPDATE job_applications SET status = 'offered' WHERE application_id = ?");
        $statusStmt->bind_param('i', $application_id);
        $statusStmt->execute();
        
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'create_offer', 'application', ?, ?)
        ");
        
        $description = "Offer created: $offered_currency " . number_format($offered_salary);
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        // Add note
        $noteText = "Offer extended:\nSalary: $offered_currency " . number_format($offered_salary);
        if ($start_date) $noteText .= "\nStart Date: $start_date";
        if ($offer_notes) $noteText .= "\nNotes: $offer_notes";
        
        $noteStmt = $conn->prepare("
            INSERT INTO candidate_notes (can_code, job_id, note, note_type, created_by)
            VALUES (?, ?, ?, 'offer', ?)
        ");
        $noteStmt->bind_param('siss', $appData['can_code'], $appData['job_id'], $noteText, $user['user_code']);
        $noteStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Offer created successfully',
            'offer_id' => $offer_id
        ]);
    } else {
        throw new Exception('Failed to create offer: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Offer handler error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>