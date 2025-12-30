<?php
/**
 * Record Placement Handler
 * Marks submission as placed and triggers status updates
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'update_status');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $submission_code = input('submission_code');
    $placement_date = input('placement_date');
    $placement_notes = input('placement_notes', '');
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get submission details
    $stmt = $conn->prepare("
        SELECT s.*, c.candidate_name, j.job_title
        FROM submissions s
        JOIN candidates c ON s.candidate_code = c.candidate_code
        JOIN jobs j ON s.job_code = j.job_code
        WHERE s.submission_code = ?
    ");
    $stmt->bind_param("s", $submission_code);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Validate can be placed
    if ($submission['client_status'] !== 'offered') {
        throw new Exception('Can only mark offered submissions as placed');
    }
    
    // Update submission (trigger will handle candidate/job updates)
    $stmt = $conn->prepare("
        UPDATE submissions
        SET client_status = 'placed',
            placement_date = ?,
            placement_notes = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("sss", $placement_date, $placement_notes, $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record placement: ' . $conn->error);
    }
    
    Logger::getInstance()->logActivity(
        'record_placement',
        'submissions',
        $submission_code,
        "🎉 PLACEMENT: {$submission['candidate_name']} placed at {$submission['job_title']}",
        [
            'placement_date' => $placement_date,
            'candidate' => $submission['candidate_name'],
            'job' => $submission['job_title']
        ]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        '🎉 Congratulations! Placement recorded successfully!',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Record placement failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    redirectBack('Failed to record placement: ' . $e->getMessage());
}