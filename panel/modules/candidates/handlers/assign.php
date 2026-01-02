<?php
/**
 * Assign Candidate to Recruiter
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('candidates', 'assign')) {
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
$input = json_decode(file_get_contents('php://input'), true);
$candidateCode = $input['candidate_code'] ?? '';
$recruiterCode = $input['recruiter_code'] ?? '';

// Validate input
if (empty($candidateCode)) {
    echo ApiResponse::validationError(['candidate_code' => 'Candidate code is required']);
    exit;
}

if (empty($recruiterCode)) {
    echo ApiResponse::validationError(['recruiter_code' => 'Recruiter is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify recruiter exists
    $stmt = $conn->prepare("SELECT user_code, name FROM users WHERE user_code = ? AND is_active = 1");
    $stmt->bind_param("s", $recruiterCode);
    $stmt->execute();
    $recruiter = $stmt->get_result()->fetch_assoc();
    
    if (!$recruiter) {
        echo ApiResponse::error('Invalid recruiter selected', 400);
        exit;
    }
    
    // Verify candidate exists and user has access
    $accessFilter = Permission::getAccessibleCandidates();
    $whereClause = $accessFilter ? "candidate_code = ? AND ({$accessFilter})" : "candidate_code = ?";
    
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE {$whereClause}");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        echo ApiResponse::error('Candidate not found or no access', 404);
        exit;
    }
    
    // Update assignment
    $stmt = $conn->prepare("UPDATE candidates SET assigned_to = ?, updated_at = NOW() WHERE candidate_code = ?");
    $stmt->bind_param("ss", $recruiterCode, $candidateCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'assign',
        'candidates',
        $candidateCode,
        "Assigned candidate to {$recruiter['name']}",
        [
            'recruiter_code' => $recruiterCode,
            'old_assigned_to' => $candidate['assigned_to']
        ]
    );
    
    echo ApiResponse::success([
        'recruiter' => $recruiter
    ], 'Candidate assigned successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Assign failed', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode
    ]);
    
    echo ApiResponse::error('Failed to assign candidate: ' . $e->getMessage(), 500);
}