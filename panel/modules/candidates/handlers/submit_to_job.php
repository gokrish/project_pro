<?php
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('submissions', 'create');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $candidateCode = $_POST['candidate_code'] ?? null;
    $jobCode = $_POST['job_code'] ?? null;
    $fitReason = trim($_POST['fit_reason'] ?? '');
    $proposedRate = trim($_POST['proposed_rate'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    
    if (!$candidateCode || !$jobCode) {
        throw new Exception('Candidate and job are required');
    }
    
    if (empty($fitReason)) {
        throw new Exception('Please explain why this candidate is a good fit');
    }
    
    // Verify candidate exists and is qualified
    $stmt = $conn->prepare("
        SELECT candidate_name, status, email
        FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    // Check if candidate is ready for submission
    if (!in_array($candidate['status'], ['qualified', 'contacted'])) {
        throw new Exception('Candidate must be qualified before submission. Current status: ' . $candidate['status']);
    }
    
    // Verify job exists
    $stmt = $conn->prepare("
        SELECT j.job_title, j.client_code, c.company_name
        FROM jobs j
        LEFT JOIN clients c ON j.client_code = c.client_code
        WHERE j.job_code = ? AND j.deleted_at IS NULL
    ");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    // Check for duplicate submission
    $stmt = $conn->prepare("
        SELECT submission_code 
        FROM candidate_submissions 
        WHERE candidate_code = ? AND job_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("ss", $candidateCode, $jobCode);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This candidate has already been submitted to this job');
    }
    
    $conn->begin_transaction();
    
    // Generate submission code
    $submissionCode = 'SUB-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create submission
    $stmt = $conn->prepare("
        INSERT INTO candidate_submissions (
            submission_code,
            candidate_code,
            job_code,
            client_code,
            fit_reason,
            proposed_rate,
            notes,
            status,
            submitted_by,
            submitted_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending_review', ?, NOW())
    ");
    
    $submittedBy = Auth::userCode();
    
    $stmt->bind_param("ssssssss",
        $submissionCode,
        $candidateCode,
        $jobCode,
        $job['client_code'],
        $fitReason,
        $proposedRate,
        $notes,
        $submittedBy
    );
    $stmt->execute();
    
    // Update candidate status to "submitted"
    $stmt = $conn->prepare("
        UPDATE candidates 
        SET status = 'submitted', updated_at = NOW()
        WHERE candidate_code = ?
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'submit_to_job',
        'candidates',
        $candidateCode,
        "Submitted to job: {$job['job_title']} at {$job['company_name']}",
        [
            'submission_code' => $submissionCode,
            'job_code' => $jobCode,
            'job_title' => $job['job_title'],
            'client' => $job['company_name'],
            'fit_reason' => substr($fitReason, 0, 100)
        ]
    );
    
    $conn->commit();
    
    FlashMessage::success("Candidate submitted to {$job['job_title']} successfully!");
    redirect(BASE_URL . '/panel/modules/candidates/view.php?code=' . $candidateCode . '&tab=submissions');
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    FlashMessage::error('Failed to submit candidate: ' . $e->getMessage());
    back();
}