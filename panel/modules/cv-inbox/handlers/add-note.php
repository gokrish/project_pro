<?php
/**
 * Add Note Handler
 * Uses new cv_inbox_notes table
 * 
 * @version 2.0
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
    ApiResponse::forbidden('You do not have permission to add notes');
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['cv_id'])) {
    ApiResponse::error('CV ID is required');
}

if (empty($_POST['note'])) {
    ApiResponse::error('Note text is required');
}

$cvId = (int)$_POST['cv_id'];
$noteText = trim($_POST['note']);
$noteType = $_POST['note_type'] ?? 'general';
$user = Auth::user();

// Validate note type
$validTypes = ['general', 'screening', 'call', 'email', 'meeting'];
if (!in_array($noteType, $validTypes)) {
    $noteType = 'general';
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Verify CV exists
    $stmt = $conn->prepare("
        SELECT cv_code, applicant_name 
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
    
    // Insert note
    $stmt = $conn->prepare("
        INSERT INTO cv_inbox_notes (cv_id, note_type, note, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("isss", $cvId, $noteType, $noteText, $user['user_code']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add note');
    }
    
    $noteId = $conn->insert_id;
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'cv_inbox',
        $cv['cv_code'],
        "Added note to CV application",
        [
            'cv_id' => $cvId,
            'note_type' => $noteType,
            'created_by' => $user['user_code']
        ]
    );
    
    ApiResponse::success([
        'note_id' => $noteId,
        'cv_id' => $cvId,
        'note_type' => $noteType,
        'note' => $noteText,
        'created_by' => $user['name'],
        'created_at' => date('Y-m-d H:i:s')
    ], 'Note added successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Failed to add CV note', [
        'cv_id' => $cvId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to add note', [
        'error' => $e->getMessage()
    ]);
}