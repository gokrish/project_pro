<?php
/**
 * Submit Candidate to Job Handler
 * Creates submission record and notifies manager
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, Auth, CSRFToken, Logger, Permission};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('jobs', 'submit_candidates')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

// CSRF validation
if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $jobCode = $_POST['job_code'] ?? '';
    $candidateCode = $_POST['candidate_code'] ?? '';
    $notes = $_POST['submission_notes'] ?? '';
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
    $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(submission_code, 5) AS UNSIGNED)) as max_num FROM submissions");
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $nextNum = ($result['max_num'] ?? 0) + 1;
    $submissionCode = 'SUB-' . str_pad($nextNum, 5, '0', STR_PAD_LEFT);
    
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
        throw new \Exception('Failed to create submission');
    }
    
    // Update job submissions count
    $conn->query("UPDATE jobs SET submissions_count = submissions_count + 1 WHERE job_code = '$jobCode'");
    
    // Update candidate total submissions
    $conn->query("UPDATE candidates SET total_submissions = total_submissions + 1 WHERE candidate_code = '$candidateCode'");
    
    // Notify manager if requested
    if ($notifyManager) {
        // Get job and candidate details
        $stmt = $conn->prepare("
            SELECT j.job_title, j.created_by, c.candidate_name, c.email
            FROM jobs j, candidates c
            WHERE j.job_code = ? AND c.candidate_code = ?
        ");
        $stmt->bind_param("ss", $jobCode, $candidateCode);
        $stmt->execute();
        $details = $stmt->get_result()->fetch_assoc();
        
        // Send notification (implement your email/notification system)
        // notifyManager($details['created_by'], $submissionCode, $details);
    }
    
    // Log activity
    Logger::getInstance()->logActivity('submit_candidate', 'submissions', $submissionCode,
        "Submitted candidate $candidateCode to job $jobCode");
    
    echo json_encode([
        'success' => true,
        'message' => 'Candidate submitted successfully',
        'submission_code' => $submissionCode
    ]);
    
} catch (\Exception $e) {
    Logger::getInstance()->logError('submit_candidate_error', $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>