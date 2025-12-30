<?php
/**
 * Bulk Assign Candidates to Recruiter
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
$candidates = $input['candidates'] ?? [];
$recruiterCode = $input['recruiter_code'] ?? '';

// Validate input
if (empty($candidates) || !is_array($candidates)) {
    echo ApiResponse::validationError(['candidates' => 'No candidates selected']);
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
    
    // Start transaction
    $conn->begin_transaction();
    
    $assignedCount = 0;
    $errors = [];
    
    foreach ($candidates as $candidateCode) {
        // Verify candidate exists and user has access
        $accessFilter = Permission::getAccessibleCandidates();
        $whereClause = $accessFilter ? "candidate_code = ? AND ({$accessFilter})" : "candidate_code = ?";
        
        $stmt = $conn->prepare("SELECT candidate_code, candidate_name FROM candidates WHERE {$whereClause}");
        $stmt->bind_param("s", $candidateCode);
        $stmt->execute();
        $candidate = $stmt->get_result()->fetch_assoc();
        
        if (!$candidate) {
            $errors[] = "Candidate {$candidateCode} not found or no access";
            continue;
        }
        
        // Update assignment
        $stmt = $conn->prepare("UPDATE candidates SET assigned_to = ?, updated_at = NOW() WHERE candidate_code = ?");
        $stmt->bind_param("ss", $recruiterCode, $candidateCode);
        
        if ($stmt->execute()) {
            $assignedCount++;
            
            // Log activity
            Logger::getInstance()->logActivity(
                'assign',
                'candidates',
                $candidateCode,
                "Assigned candidate to {$recruiter['name']}",
                ['recruiter_code' => $recruiterCode]
            );
        } else {
            $errors[] = "Failed to assign {$candidate['candidate_name']}";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $message = "{$assignedCount} candidate(s) assigned to {$recruiter['name']}";
    
    if (!empty($errors)) {
        $message .= ". " . count($errors) . " error(s) occurred.";
    }
    
    Logger::getInstance()->info('Bulk assign completed', [
        'assigned' => $assignedCount,
        'total' => count($candidates),
        'recruiter' => $recruiterCode
    ]);
    
    echo ApiResponse::success([
        'assigned_count' => $assignedCount,
        'errors' => $errors
    ], $message);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    Logger::getInstance()->error('Bulk assign failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo ApiResponse::error('Failed to assign candidates: ' . $e->getMessage(), 500);
}