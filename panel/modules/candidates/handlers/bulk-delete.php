<?php
/**
 * Bulk Delete Candidates
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
$candidates = $input['candidates'] ?? [];

// Validate input
if (empty($candidates) || !is_array($candidates)) {
    echo ApiResponse::validationError(['candidates' => 'No candidates selected']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    $deletedCount = 0;
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
        
        // Delete related records first (applications, documents, notes)
        $conn->query("DELETE FROM applications WHERE candidate_code = '{$candidateCode}'");
        $conn->query("DELETE FROM candidate_documents WHERE candidate_code = '{$candidateCode}'");
        $conn->query("DELETE FROM candidate_notes WHERE candidate_code = '{$candidateCode}'");
        $conn->query("DELETE FROM candidate_activity_log WHERE module = 'candidates' AND record_id = '{$candidateCode}'");
        
        // Delete candidate
        $stmt = $conn->prepare("DELETE FROM candidates WHERE candidate_code = ?");
        $stmt->bind_param("s", $candidateCode);
        
        if ($stmt->execute()) {
            $deletedCount++;
            
            // Log deletion
            Logger::getInstance()->logActivity(
                'delete',
                'candidates',
                $candidateCode,
                "Deleted candidate: {$candidate['candidate_name']}",
                ['candidate_name' => $candidate['candidate_name']]
            );
        } else {
            $errors[] = "Failed to delete {$candidate['candidate_name']}";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $message = "{$deletedCount} candidate(s) deleted successfully";
    
    if (!empty($errors)) {
        $message .= ". " . count($errors) . " error(s) occurred.";
    }
    
    Logger::getInstance()->warn('Bulk delete completed', [
        'deleted' => $deletedCount,
        'total' => count($candidates)
    ]);
    
    echo ApiResponse::success([
        'deleted_count' => $deletedCount,
        'errors' => $errors
    ], $message);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    Logger::getInstance()->error('Bulk delete failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo ApiResponse::error('Failed to delete candidates: ' . $e->getMessage(), 500);
}