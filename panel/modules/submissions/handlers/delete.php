<?php
/**
 * Delete Submission (Soft Delete)
 * File: panel/modules/submissions/handlers/delete.php
 */


require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Permission;
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('submissions', 'delete')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionCode = $input['submission_code'] ?? '';
    
    if (empty($submissionCode)) {
        throw new Exception('Submission code is required');
    }
    
    // Get submission
    $stmt = $conn->prepare("
        SELECT * FROM candidate_submissions 
        WHERE submission_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('s', $submissionCode);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    // Only allow deletion of drafts or own submissions
    if ($submission['status'] !== 'draft' && $submission['submitted_by'] !== $user['user_code'] && $user['level'] !== 'admin') {
        throw new Exception('You can only delete draft submissions or your own submissions');
    }
    
    // Soft delete
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET deleted_at = NOW(), updated_at = NOW()
        WHERE submission_code = ?
    ");
    $stmt->bind_param('s', $submissionCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete submission');
    }
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'delete',
            'submissions',
            $submissionCode,
            "Deleted submission by {$user['name']}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission deleted successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Deletion error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}