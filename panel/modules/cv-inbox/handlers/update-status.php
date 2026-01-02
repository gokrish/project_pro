<?php
/**
 * Update CV Status Handler
 * Refactored to include quality_score and review_notes
 * 
 * @version 2.0 - Refactored
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\ApiResponse;

// Check permission
if (!Permission::can('cv_inbox', 'edit')) {
    ApiResponse::forbidden('You do not have permission to update CV status');
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['cv_id'])) {
    ApiResponse::error('CV ID is required');
}

if (empty($_POST['status'])) {
    ApiResponse::error('Status is required');
}

$cvId = (int)$_POST['cv_id'];
$newStatus = $_POST['status'];
$qualityScore = isset($_POST['quality_score']) ? (int)$_POST['quality_score'] : null;
$reviewNotes = trim($_POST['review_notes'] ?? '');
$rejectionReason = trim($_POST['rejection_reason'] ?? '');
$user = Auth::user();

// Validate status
$validStatuses = ['new', 'screening', 'shortlisted', 'converted', 'rejected', 'spam'];
if (!in_array($newStatus, $validStatuses)) {
    ApiResponse::error('Invalid status value');
}

// Validate quality score (1-5)
if ($qualityScore !== null && ($qualityScore < 1 || $qualityScore > 5)) {
    ApiResponse::error('Quality score must be between 1 and 5');
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Get current CV data
    $stmt = $conn->prepare("
        SELECT cv_code, applicant_name, status, reviewed_by, reviewed_at
        FROM cv_inbox 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $result = $stmt->get_result();
    $cv = $result->fetch_assoc();
    
    if (!$cv) {
        ApiResponse::notFound('CV not found');
    }
    
    $oldStatus = $cv['status'];
    
    // Prepare update fields
    $updateFields = ['status = ?'];
    $updateParams = [$newStatus];
    $updateTypes = 's';
    
    // If moving from 'new' to any other status, mark as reviewed
    if ($oldStatus === 'new' && $newStatus !== 'new') {
        $updateFields[] = 'reviewed_by = ?';
        $updateFields[] = 'reviewed_at = NOW()';
        $updateParams[] = $user['user_code'];
        $updateTypes .= 's';
    }
    
    // Add quality score if provided
    if ($qualityScore !== null) {
        $updateFields[] = 'quality_score = ?';
        $updateParams[] = $qualityScore;
        $updateTypes .= 'i';
    }
    
    // Add review notes if provided
    if (!empty($reviewNotes)) {
        $updateFields[] = 'review_notes = ?';
        $updateParams[] = $reviewNotes;
        $updateTypes .= 's';
    }
    
    // Add rejection reason if status is rejected
    if ($newStatus === 'rejected' && !empty($rejectionReason)) {
        $updateFields[] = 'rejection_reason = ?';
        $updateParams[] = $rejectionReason;
        $updateTypes .= 's';
    }
    
    // Update CV
    $sql = "UPDATE cv_inbox SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE id = ?";
    $updateParams[] = $cvId;
    $updateTypes .= 'i';
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($updateTypes, ...$updateParams);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update CV status');
    }
    
    // Log status change
    Logger::getInstance()->logActivity(
        'update',
        'cv_inbox',
        $cv['cv_code'],
        "Status changed: {$oldStatus} → {$newStatus}",
        [
            'cv_id' => $cvId,
            'applicant_name' => $cv['applicant_name'],
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'quality_score' => $qualityScore,
            'has_review_notes' => !empty($reviewNotes),
            'updated_by' => $user['user_code']
        ]
    );
    
    $db->commit();
    
    ApiResponse::success([
        'cv_id' => $cvId,
        'cv_code' => $cv['cv_code'],
        'old_status' => $oldStatus,
        'new_status' => $newStatus,
        'quality_score' => $qualityScore,
        'reviewed_by' => $user['user_code'],
        'updated_at' => date('Y-m-d H:i:s')
    ], 'CV status updated successfully');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Failed to update CV status', [
        'cv_id' => $cvId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to update status', [
        'error' => $e->getMessage()
    ]);
}