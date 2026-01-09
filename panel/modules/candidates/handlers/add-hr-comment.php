<?php
/**
 * Add HR Comment Handler
 * @version 6.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth, CSRFToken, Logger};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('candidates', 'view')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

// CSRF validation
if (!CSRFToken::validate($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$candidateCode = $_POST['candidate_code'] ?? '';
$commentType = $_POST['comment_type'] ?? 'general';
$comment = $_POST['comment'] ?? '';
$isPrivate = isset($_POST['is_private']) ? 1 : 0;
$userCode = Auth::userCode();
$db = Database::getInstance();
$conn = $db->getConnection();

try {
    if (empty($comment)) {
        throw new Exception('Comment cannot be empty');
    }
    
    $stmt = $conn->prepare("
        INSERT INTO candidate_hr_comments (
            candidate_code,
            comment_type,
            comment,
            is_private,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("sssis",
        $candidateCode,
        $commentType,
        $comment,
        $isPrivate,
        $userCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add comment');
    }
    
    Logger::getInstance()->logActivity('add_hr_comment', 'candidates', $candidateCode, 
        "Added $commentType comment");
    
    echo json_encode([
        'success' => true,
        'message' => 'Comment added successfully'
    ]);
    
} catch (Exception $e) {
    Logger::getInstance()->logError('add_hr_comment_error', $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>