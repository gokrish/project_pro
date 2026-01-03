<?php
/**
 * Withdraw Submission Handler
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('submissions', 'withdraw');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $submission_code = input('submission_code');
    $withdrawal_reason = input('withdrawal_reason');
    $user = Auth::user();
    
    if (empty($withdrawal_reason)) {
        throw new Exception('Withdrawal reason is required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get submission
    $stmt = $conn->prepare("SELECT * FROM submissions WHERE submission_code = ?");
    $stmt->bind_param("s", $submission_code);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Can't withdraw if already placed or rejected
    if (in_array($submission['client_status'], ['placed', 'rejected'])) {
        throw new Exception('Cannot withdraw a submission that is already ' . $submission['client_status']);
    }
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE submissions
        SET internal_status = 'withdrawn',
            client_status = 'withdrawn',
            withdrawn_date = NOW(),
            withdrawal_reason = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("ss", $withdrawal_reason, $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to withdraw submission: ' . $conn->error);
    }
    
    Logger::getInstance()->info(
        'submissions',
        'withdraw',
        $submissionCode,
        "Submission withdrawn",
        ['reason' => $withdrawalReason]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/list.php",
        'Submission withdrawn successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Withdrawal failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    redirectBack('Failed to withdraw submission: ' . $e->getMessage());
}