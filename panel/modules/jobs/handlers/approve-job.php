<?php
/**
 * Approve Job Handler
 * Approves job and publishes it
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('jobs', 'approve');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $job_code = input('job_code');
    $approval_notes = input('approval_notes', '');
    $user = Auth::user();
    
    if (empty($job_code)) {
        throw new Exception('Job code is required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check job exists
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $job_code);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        throw new Exception('Job not found');
    }
    
    if ($job['approval_status'] !== 'pending_approval') {
        throw new Exception('This job is not pending approval');
    }
    
    // Generate job_refno if not exists
    if (empty($job['job_refno'])) {
        $stmt = $conn->prepare("SELECT MAX(id) as max_id FROM jobs");
        $stmt->execute();
        $maxId = $stmt->get_result()->fetch_assoc()['max_id'] + 1;
        $job_refno = 'REF' . str_pad($maxId, 6, '0', STR_PAD_LEFT);
    } else {
        $job_refno = $job['job_refno'];
    }
    
    // Approve job
    $stmt = $conn->prepare("
        UPDATE jobs
        SET approval_status = 'approved',
            status = 'open',
            approved_by = ?,
            approved_at = NOW(),
            is_published = 1,
            published_at = NOW(),
            job_refno = ?,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    
    $stmt->bind_param("sss", $user['user_code'], $job_refno, $job_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to approve job: ' . $conn->error);
    }
    
    // Add approval note if provided
    if (!empty($approval_notes)) {
        $stmt = $conn->prepare("
            INSERT INTO notes (entity_type, entity_code, note_type, content, created_by, created_at)
            VALUES ('job', ?, 'approval', ?, ?, NOW())
        ");
        $stmt->bind_param("sss", $job_code, $approval_notes, $user['user_code']);
        $stmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'approve',
        'jobs',
        $job_code,
        "Job approved and published: {$job['job_title']}",
        [
            'approved_by' => $user['user_code'],
            'job_refno' => $job_refno,
            'approval_notes' => $approval_notes
        ]
    );
    
    // Get job details
    $jobStmt = $conn->prepare("
        SELECT j.*, u.email as recruiter_email, u.name as recruiter_name
        FROM jobs j 
        LEFT JOIN users u ON j.created_by = u.user_code
        WHERE j.job_code = ?
    ");
    $jobStmt->bind_param("s", $jobCode);
    $jobStmt->execute();
    $job = $jobStmt->get_result()->fetch_assoc();

    // Send email to recruiter
    if (!empty($job['recruiter_email'])) {
        Mailer::send(
            $job['recruiter_email'],
            "Job Approved: {$job['job_title']}",
            'job_approved',
            [
                'recruiter_name' => $job['recruiter_name'],
                'job_title' => $job['job_title'],
                'job_code' => $job['job_code'],
                'approved_by' => Auth::user()['name'],
                'approved_at' => date('d/m/Y H:i'),
                'approval_notes' => $_POST['approval_notes'] ?? '',
                'job_url' => BASE_URL . '/panel/modules/jobs/view.php?code=' . $job['job_code']
            ]
        );
    }

    Logger::getInstance()->info('jobs', 'approve', $jobCode, "Job approved, recruiter notified");
    
    redirectWithMessage(
        "/panel/modules/jobs/?action=view&code={$job_code}",
        'Job approved and published successfully!',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Job approval failed', [
        'error' => $e->getMessage(),
        'job_code' => $job_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to approve job: ' . $e->getMessage());
}