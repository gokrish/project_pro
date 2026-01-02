<?php
/**
 * Add HR Comment Handler
 * Confidential HR notes (Manager+ only)
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;

header('Content-Type: application/json');

// Check permission - Only managers and admins
$user = Auth::user();
if (!$user || !in_array($user['level'] ?? '', ['manager', 'admin', 'super_admin'])) {
    echo ApiResponse::forbidden('Only managers can add HR comments');
    exit;
}

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo ApiResponse::error('Invalid request method', 405);
    exit;
}

// Verify CSRF token
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

// Get input
$candidateCode = filter_input(INPUT_POST, 'candidate_code', FILTER_SANITIZE_STRING);
$commentType = filter_input(INPUT_POST, 'comment_type', FILTER_SANITIZE_STRING);
$comment = filter_input(INPUT_POST, 'comment', FILTER_SANITIZE_STRING);
$isConfidential = filter_input(INPUT_POST, 'is_confidential', FILTER_SANITIZE_NUMBER_INT) ?? 1;

// Validate required fields
$errors = [];

if (empty($candidateCode)) {
    $errors['candidate_code'] = 'Candidate code is required';
}

if (empty($commentType)) {
    $errors['comment_type'] = 'Comment type is required';
}

if (empty($comment)) {
    $errors['comment'] = 'Comment is required';
}

// Validate comment type
$validTypes = ['Screening', 'Interview_Feedback', 'Manager_Review', 'Red_Flag', 'Recommendation', 'General'];
if (!in_array($commentType, $validTypes)) {
    $errors['comment_type'] = 'Invalid comment type';
}

if (!empty($errors)) {
    echo ApiResponse::validationError($errors);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify candidate exists
    $stmt = $conn->prepare("
        SELECT candidate_code, candidate_name 
        FROM candidates 
        WHERE candidate_code = ? 
        AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        echo ApiResponse::error('Candidate not found', 404);
        exit;
    }
    
    // Insert HR comment
    $stmt = $conn->prepare("
        INSERT INTO candidate_hr_comments 
        (candidate_code, comment_type, comment, is_confidential, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    
    $userCode = Auth::userCode();
    $stmt->bind_param(
        "sssis",
        $candidateCode,
        $commentType,
        $comment,
        $isConfidential,
        $userCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add HR comment');
    }
    
    $commentId = $conn->insert_id;
    
    // Log activity
    Logger::getInstance()->info(
        'candidates',
        'add_hr_comment',
        $candidateCode,
        "Added HR comment: {$commentType}",
        [
            'comment_id' => $commentId,
            'comment_type' => $commentType,
            'is_confidential' => $isConfidential
        ]
    );
    
    echo ApiResponse::success([
        'comment_id' => $commentId,
        'comment_type' => $commentType
    ], 'HR comment added successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Failed to add HR comment', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode
    ]);
    
    echo ApiResponse::error('Failed to add HR comment: ' . $e->getMessage(), 500);
}
