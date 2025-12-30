<?php
/**
 * Update Offer Response (Candidate's decision)
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    $user = Auth::user();
    
    $application_id = (int)$_POST['application_id'];
    $response = sanitize($_POST['response']); // accepted, rejected, negotiating
    
    if (!$application_id || !$response) {
        throw new Exception('Application ID and response are required');
    }
    
    // Validate response
    $validResponses = ['accepted', 'rejected', 'negotiating'];
    if (!in_array($response, $validResponses)) {
        throw new Exception('Invalid response');
    }
    
    // Update offer
    $stmt = $conn->prepare("
        UPDATE offers 
        SET candidate_response = ?,
            response_date = CURDATE()
        WHERE application_id = ?
    ");
    
    $stmt->bind_param('si', $response, $application_id);
    
    if ($stmt->execute()) {
        // Update application status
        if ($response === 'accepted') {
            $newStatus = 'offer_accepted';
        } elseif ($response === 'rejected') {
            $newStatus = 'rejected';
        } else {
            $newStatus = 'offered'; // Stay in offered status while negotiating
        }
        
        $statusStmt = $conn->prepare("UPDATE job_applications SET status = ? WHERE application_id = ?");
        $statusStmt->bind_param('si', $newStatus, $application_id);
        $statusStmt->execute();
        
        // Log activity
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (user_code, action, entity_type, entity_id, description)
            VALUES (?, 'offer_response', 'application', ?, ?)
        ");
        
        $description = "Candidate $response the offer";
        $activityStmt->bind_param('sis', $user['user_code'], $application_id, $description);
        $activityStmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Offer response updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update offer response: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log('Offer response error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>