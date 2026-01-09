<?php
/**
 * Submit Candidate to Job Handler
 * Creates submission record
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, Auth, CSRFToken, Logger, Permission};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('submissions', 'create')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

// CSRF validation
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $jobCode = $_POST['job_code'] ?? '';
    $candidateCode = $_POST['candidate_code'] ?? '';
    $notes = trim($_POST['submission_notes'] ?? '');
    $notifyManager = isset($_POST['notify_manager']);
    
    if (empty($jobCode) || empty($candidateCode)) {
        throw new \Exception('Job and candidate are required');
    }
    
    // Check if already submitted
    $stmt = $conn->prepare("
        SELECT submission_code 
        FROM submissions 
        WHERE job_code = ? 
        AND candidate_code = ? 
        AND deleted_at IS NULL
    ");
    $stmt->bind_param("ss", $jobCode, $candidateCode);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new \Exception('This candidate has already been submitted to this job');
    }
    
    // Generate submission code
    $stmt = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING(submission_code, 5) AS UNSIGNED)) as max_num 
        FROM submissions
    ");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $nextNum = ($result['max_num'] ?? 0) + 1;
    $submissionCode = 'SUB-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    
    // Create submission
    $stmt = $conn->prepare("
        INSERT INTO submissions (
            submission_code,
            candidate_code,
            job_code,
            submitted_by,
            internal_status,
            client_status,
            submission_notes,
            created_at
        ) VALUES (?, ?, ?, ?, 'pending', 'not_sent', ?, NOW())
    ");
    
    $stmt->bind_param("sssss",
        $submissionCode,
        $candidateCode,
        $jobCode,
        $userCode,
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new \Exception('Failed to create submission: ' . $conn->error);
    }
    
    // Triggers will auto-update counters
    
    // Log activity
    Logger::getInstance()->logActivity('create_submission', 'submissions', $submissionCode,
        "Submitted candidate $candidateCode to job $jobCode");
    
    echo json_encode([
        'success' => true,
        'message' => 'Candidate submitted successfully',
        'submission_code' => $submissionCode
    ]);
    
} catch (\Exception $e) {
    Logger::getInstance()->Error('submit_candidate_error', $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>