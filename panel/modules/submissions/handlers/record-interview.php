<?php
/**
 * Record Interview Handler
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
    $interview_date = input('interview_date');
    $interview_notes = input('interview_notes', '');
    $interview_result = input('interview_result', null);
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE submissions
        SET client_status = 'interviewing',
            interview_date = ?,
            interview_notes = ?,
            interview_result = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("ssss", $interview_date, $interview_notes, $interview_result, $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record interview: ' . $conn->error);
    }
    
    Logger::getInstance()->logActivity(
        'record_interview',
        'submissions',
        $submission_code,
        "Interview scheduled for " . date('M d, Y', strtotime($interview_date)),
        ['interview_date' => $interview_date, 'result' => $interview_result]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        'Interview details recorded successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Record interview failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    redirectBack('Failed to record interview: ' . $e->getMessage());
}