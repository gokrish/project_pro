<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('submissions', 'edit');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $submissionCode = $_POST['submission_code'];
    
    // Fetch existing submission
    $stmt = $conn->prepare("SELECT * FROM candidate_submissions WHERE submission_code = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $submissionCode);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Check ownership for edit.own
    if (!Permission::can('submissions', 'edit.all')) {
        if (!Permission::can('submissions', 'edit.own') || $submission['submitted_by'] !== Auth::userCode()) {
            // throw new PermissionException('You can only edit your own submissions');
        }
    }
    
    // Extract form data
    $proposedRate = !empty($_POST['proposed_rate']) ? (float)$_POST['proposed_rate'] : null;
    $rateType = $_POST['rate_type'] ?? 'daily';
    $availabilityDate = !empty($_POST['availability_date']) ? $_POST['availability_date'] : null;
    $contractDuration = !empty($_POST['contract_duration_months']) ? (int)$_POST['contract_duration_months'] : null;
    $fitReason = trim($_POST['fit_reason'] ?? '');
    $keyStrengths = trim($_POST['key_strengths'] ?? '');
    $concerns = trim($_POST['concerns'] ?? '');
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET proposed_rate = ?,
            rate_type = ?,
            availability_date = ?,
            contract_duration_months = ?,
            fit_reason = ?,
            key_strengths = ?,
            concerns = ?,
            updated_at = NOW()
        WHERE submission_code = ?
    ");
    
    $stmt->bind_param(
        "dssiss",
        $proposedRate, 
        $rateType, 
        $availabilityDate, 
        $contractDuration,
        $fitReason, 
        $keyStrengths, 
        $concerns,
        $submissionCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update submission: ' . $stmt->error);
    }
    
    Logger::getInstance()->logActivity('update', 'submissions', $submissionCode, 'Updated submission details');
    
    FlashMessage::success('Submission updated successfully!');
    redirect(BASE_URL . '/panel/modules/submissions/view.php?submission_code=' . $submissionCode);
    
} catch (Exception $e) {
    FlashMessage::error('Failed to update submission: ' . $e->getMessage());
    $_SESSION['old'] = $_POST;
    back();
}
