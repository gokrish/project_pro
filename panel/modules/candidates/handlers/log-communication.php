<?php
/**
 * Log Communication Handler
 * Records candidate interactions (calls, emails, meetings, etc.)
 * 
 * @version 1.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;

header('Content-Type: application/json');

// Check permission
if (!Permission::canAction('candidates', 'edit')) {
    echo ApiResponse::forbidden();
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
$communicationType = filter_input(INPUT_POST, 'communication_type', FILTER_SANITIZE_STRING);
$direction = filter_input(INPUT_POST, 'direction', FILTER_SANITIZE_STRING);
$subject = filter_input(INPUT_POST, 'subject', FILTER_SANITIZE_STRING);
$notes = filter_input(INPUT_POST, 'notes', FILTER_SANITIZE_STRING);
$durationMinutes = filter_input(INPUT_POST, 'duration_minutes', FILTER_SANITIZE_NUMBER_INT);
$nextAction = filter_input(INPUT_POST, 'next_action', FILTER_SANITIZE_STRING);
$nextActionDate = filter_input(INPUT_POST, 'next_action_date', FILTER_SANITIZE_STRING);

// Validate required fields
$errors = [];

if (empty($candidateCode)) {
    $errors['candidate_code'] = 'Candidate code is required';
}

if (empty($communicationType)) {
    $errors['communication_type'] = 'Communication type is required';
}

if (empty($direction)) {
    $errors['direction'] = 'Direction is required';
}

if (empty($notes)) {
    $errors['notes'] = 'Notes are required';
}

if (!empty($errors)) {
    echo ApiResponse::validationError($errors);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Verify candidate exists and user has access
    $accessFilter = Permission::getAccessibleCandidates();
    $sql = $accessFilter['sql'] ?? '1=1';
    $params = $accessFilter['params'] ?? [];
    $types = $accessFilter['types'] ?? '';
    
    $stmt = $conn->prepare("
        SELECT candidate_code, candidate_name 
        FROM candidates 
        WHERE candidate_code = ? 
        AND ({$sql})
        AND deleted_at IS NULL
    ");
    
    $bindParams = array_merge([$candidateCode], $params);
    $bindTypes = 's' . $types;
    
    $stmt->bind_param($bindTypes, ...$bindParams);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        echo ApiResponse::error('Candidate not found or no access', 404);
        exit;
    }
    
    // Insert communication log
    $stmt = $conn->prepare("
        INSERT INTO candidate_communications 
        (candidate_code, communication_type, direction, subject, notes, 
         duration_minutes, next_action, next_action_date, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $userCode = Auth::userCode();
    $stmt->bind_param(
        "sssssisss",
        $candidateCode,
        $communicationType,
        $direction,
        $subject,
        $notes,
        $durationMinutes,
        $nextAction,
        $nextActionDate,
        $userCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to log communication');
    }
    
    $communicationId = $conn->insert_id;
    
    // Update candidate's last_contacted_date
    $stmt = $conn->prepare("
        UPDATE candidates 
        SET last_contacted_date = CURDATE(),
            updated_at = NOW()
        WHERE candidate_code = ?
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->info(
        'candidates',
        'log_communication',
        $candidateCode,
        "Logged {$communicationType} communication",
        [
            'communication_id' => $communicationId,
            'type' => $communicationType,
            'direction' => $direction,
            'subject' => $subject
        ]
    );
    
    echo ApiResponse::success([
        'communication_id' => $communicationId,
        'communication_type' => $communicationType
    ], 'Communication logged successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Failed to log communication', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode
    ]);
    
    echo ApiResponse::error('Failed to log communication: ' . $e->getMessage(), 500);
}
