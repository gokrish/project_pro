// modules/candidates/handlers/update_lead_status.php
<?php
require_once __DIR__ . '/../../_common.php';

// Validate permissions
if (!in_array($user['level'], ['admin', 'recruiter'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$conn = Database::getInstance()->getConnection();

try {
    $candidate_code = $_POST['candidate_code'] ?? '';
    $lead_type = $_POST['lead_type'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    // Validate lead type
    $valid_types = ['Hot', 'Warm', 'Cold', 'Blacklist'];
    if (!in_array($lead_type, $valid_types)) {
        throw new Exception('Invalid lead type');
    }
    
    // Special handling for Blacklist
    if ($lead_type === 'Blacklist' && empty($notes)) {
        throw new Exception('Reason required for blacklisting candidate');
    }
    
    $conn->begin_transaction();
    
    // Update candidate
    $stmt = $conn->prepare("UPDATE candidates SET lead_type = ?, updated_at = NOW() WHERE candidate_code = ?");
    $stmt->bind_param("ss", $lead_type, $candidate_code);
    $stmt->execute();
    
    // Log the change
    $stmt = $conn->prepare("INSERT INTO activity_log (user_code, action, module, record_id, details) VALUES (?, 'lead_status_change', 'candidates', ?, ?)");
    $details = json_encode(['from' => $_POST['old_status'] ?? '', 'to' => $lead_type, 'notes' => $notes]);
    $stmt->bind_param("sss", $user['user_code'], $candidate_code, $details);
    $stmt->execute();
    
    // If blacklisted, send notification to admin
    if ($lead_type === 'Blacklist') {
        // Send email notification
        $mailer = new Mailer();
        $mailer->sendToAdmins('Candidate Blacklisted', "Candidate {$candidate_code} was blacklisted. Reason: {$notes}");
    }
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Lead status updated successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    Logger::getInstance()->error('Lead status update failed', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>