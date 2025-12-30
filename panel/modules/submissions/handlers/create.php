<?php
/**
 * Create Submission Handler
 * Validates and creates new submission
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid request token');
}

try {
    $candidate_code = input('candidate_code');
    $job_code = input('job_code');
    $notes = input('submission_notes', '');
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // === VALIDATION ===
    
    // 1. Validate candidate exists and is qualified
    $stmt = $conn->prepare("
        SELECT status, candidate_name FROM candidates 
        WHERE candidate_code = ?
    ");
    $stmt->bind_param("s", $candidate_code);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    if (!in_array($candidate['status'], ['qualified', 'active'])) {
        throw new Exception(
            'Only qualified candidates can be submitted. ' .
            'Current status: ' . $candidate['status'] . '. ' .
            'Please complete candidate screening first.'
        );
    }
    
    // 2. Validate job exists and is open
    $stmt = $conn->prepare("
        SELECT status, job_title, positions_total, positions_filled 
        FROM jobs 
        WHERE job_code = ?
    ");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    if (!in_array($job['status'], ['open', 'filling'])) {
        throw new Exception(
            'This job is not accepting submissions. ' .
            'Current status: ' . $job['status']
        );
    }
    
    if ($job['positions_filled'] >= $job['positions_total']) {
        throw new Exception('This job is already fully filled');
    }
    
    // 3. Check for duplicate submission
    $stmt = $conn->prepare("
        SELECT id FROM submissions 
        WHERE candidate_code = ? AND job_code = ?
    ");
    $stmt->bind_param("ss", $candidate_code, $job_code);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception(
            'This candidate has already been submitted to this job'
        );
    }
    
    // === CREATE SUBMISSION ===
    
    $submission_code = 'SUB' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    
    $stmt = $conn->prepare("
        INSERT INTO submissions (
            submission_code, 
            candidate_code, 
            job_code,
            submitted_by, 
            submission_notes,
            internal_status, 
            client_status,
            created_at
        ) VALUES (?, ?, ?, ?, ?, 'pending', 'not_sent', NOW())
    ");
    
    $stmt->bind_param("sssss",
        $submission_code, 
        $candidate_code, 
        $job_code,
        $user['user_code'], 
        $notes
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create submission: ' . $conn->error);
    }
    
    // Triggers will handle candidate/job status updates
    
    // === LOG ACTIVITY ===
    Logger::getInstance()->logActivity(
        'create',
        'submissions',
        $submission_code,
        "Submission created for approval: {$candidate['candidate_name']} → {$job['job_title']}",
        [
            'candidate' => $candidate_code,
            'job' => $job_code,
            'submitted_by' => $user['user_code']
        ]
    );
    
    // === TODO: NOTIFY MANAGER ===
    // Send email to manager for approval
    
    // === SUCCESS ===
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        'Submission created successfully and sent for manager approval',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Submission creation failed', [
        'error' => $e->getMessage(),
        'candidate' => $candidate_code ?? null,
        'job' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to create submission: ' . $e->getMessage());
}