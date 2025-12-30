<?php
/**
 * Delete CV Note Handler
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('cv_inbox', 'view')) {
    echo ApiResponse::forbidden();
    exit;
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

try {
    $noteId = (int)input('note_id');
    
    if (!$noteId) {
        echo ApiResponse::error('Note ID is required', 400);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get note (verify ownership)
    $stmt = $conn->prepare("SELECT * FROM cv_notes WHERE id = ?");
    $stmt->bind_param("i", $noteId);
    $stmt->execute();
    $note = $stmt->get_result()->fetch_assoc();
    
    if (!$note) {
        echo ApiResponse::error('Note not found', 404);
        exit;
    }
    
    // Only creator can delete (unless admin)
    if ($note['created_by'] !== $user['user_code'] && $user['level'] !== 'admin') {
        echo ApiResponse::error('You can only delete your own notes', 403);
        exit;
    }
    
    // Delete note
    $stmt = $conn->prepare("DELETE FROM cv_notes WHERE id = ?");
    $stmt->bind_param("i", $noteId);
    $stmt->execute();
    
    echo ApiResponse::success(null, 'Note deleted successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Delete note failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to delete note', 500);
}