<?php
require_once __DIR__ . '/../../_common.php';
header('Content-Type: application/json');

// Verify token
if (!Auth::verifyToken($_POST['token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit();
}

try {
    $conn = Database::getInstance()->getConnection();
    $user = Auth::user();
    
    $can_code = $_POST['can_code'] ?? '';
    $job_code = $_POST['job_code'] ?? '';
    
    if (!$can_code || !$job_code) {
        throw new Exception('Missing candidate or job code');
    }
    
    // Check if already applied
    $stmt = $conn->prepare("
        SELECT id FROM jobs 
        WHERE can_code = ? AND job_code = ?
    ");
    $stmt->bind_param("ss", $can_code, $job_code);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Candidate already applied to this job');
    }
    
    // Add to job
    $stmt = $conn->prepare("
        INSERT INTO jobs (can_code, job_code, applied_date, status, added_by)
        VALUES (?, ?, NOW(), 'Applied', ?)
    ");
    $stmt->bind_param("sss", $can_code, $job_code, $user['user_code']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add candidate to job: ' . $stmt->error);
    }
    
    echo json_encode(['success' => true, 'message' => 'Candidate added to job successfully']);
    
} catch (Exception $e) {
    error_log("Add candidate to job error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>