<?php
/**
 * Add Note to CV Handler
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
    $cvId = (int)input('cv_id');
    $note = trim(input('note', ''));
    
    if (!$cvId || empty($note)) {
        echo ApiResponse::validationError(['note' => 'Note text is required']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Verify CV exists
    $stmt = $conn->prepare("SELECT id FROM cv_inbox WHERE id = ?");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo ApiResponse::error('CV not found', 404);
        exit;
    }
    
    // Insert note
    $stmt = $conn->prepare("
        INSERT INTO cv_notes (cv_id, note, created_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("iss", $cvId, $note, $user['user_code']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add note');
    }
    
    $noteId = $conn->insert_id;
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'cv_notes',
        $noteId,
        "Added note to CV application"
    );
    
    echo ApiResponse::success(['note_id' => $noteId], 'Note added successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Add note failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to add note', 500);
}