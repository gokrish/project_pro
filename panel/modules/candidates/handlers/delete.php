<?php
/**
 * Delete Single Candidate
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('candidates', 'delete')) {
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

// Validate input
if (empty($candidateCode)) {
    echo ApiResponse::validationError(['candidate_code' => 'Candidate code is required']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
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
    
    // Start transaction
    $conn->begin_transaction();
    
    // Delete related records
    $conn->query("DELETE FROM applications WHERE candidate_code = '{$candidateCode}'");
    $conn->query("DELETE FROM candidate_documents WHERE candidate_code = '{$candidateCode}'");
    $conn->query("DELETE FROM candidate_notes WHERE candidate_code = '{$candidateCode}'");
    $conn->query("DELETE FROM candidate_activity_log WHERE module = 'candidates' AND record_id = '{$candidateCode}'");
    
    // Delete candidate
    $stmt = $conn->prepare("DELETE FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    Logger::getInstance()->warn('Candidate deleted', [
        'candidate_code' => $candidateCode,
        'candidate_name' => $candidate['candidate_name']
    ]);
    
    echo ApiResponse::success(null, 'Candidate deleted successfully');
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    Logger::getInstance()->error('Delete failed', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode
    ]);
    
    echo ApiResponse::error('Failed to delete candidate: ' . $e->getMessage(), 500);
}