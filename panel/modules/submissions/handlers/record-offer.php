<?php
/**
 * Record Offer Handler
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
    $offer_date = input('offer_date');
    $offer_notes = input('offer_notes', '');
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE submissions
        SET client_status = 'offered',
            offer_date = ?,
            offer_notes = ?
        WHERE submission_code = ?
    ");
    $stmt->bind_param("sss", $offer_date, $offer_notes, $submission_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to record offer: ' . $conn->error);
    }
    
    Logger::getInstance()->logActivity(
        'record_offer',
        'submissions',
        $submission_code,
        "Offer extended on " . date('M d, Y', strtotime($offer_date)),
        ['offer_date' => $offer_date]
    );
    
    redirectWithMessage(
        "/panel/modules/submissions/view.php?code={$submission_code}",
        'Offer details recorded successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Record offer failed', [
        'error' => $e->getMessage(),
        'submission' => $submission_code ?? null
    ]);
    redirectBack('Failed to record offer: ' . $e->getMessage());
}