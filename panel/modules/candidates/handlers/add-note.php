<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\{Permission, Database, CSRFToken, Logger, ApiResponse, Auth};

Permission::require('candidates', 'edit');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

$candidateCode = $_POST['candidate_code'] ?? '';
$note = trim($_POST['note'] ?? '');

if (empty($candidateCode) || empty($note)) {
    ApiResponse::error( 'Note is required');
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // If using hr_comments table for notes:
    $stmt = $conn->prepare("
        INSERT INTO hr_comments (can_code, comment, created_by, created_at)
        VALUES (?, ?, ?, NOW())
    ");
    $stmt->bind_param("sss", $candidateCode, $note, Auth::userCode());
    $stmt->execute();
    
    Logger::getInstance()->logActivity('add_note', 'candidates', $candidateCode, 'Note added');
    
    ApiResponse::success([], 'Note added successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Failed to add note', ['error' => $e->getMessage()]);
    ApiResponse::error('Failed to add note');
}