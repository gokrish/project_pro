<?php
/**
 * Bulk Update Candidate Status
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
if (!Permission::can('candidates', 'edit')) {
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
$status = $input['status'] ?? '';

// Validate input
if (empty($candidates) || !is_array($candidates)) {
    echo ApiResponse::validationError(['candidates' => 'No candidates selected']);
    exit;
}

$validStatuses = ['active', 'placed', 'archived'];
if (!in_array($status, $validStatuses)) {
    echo ApiResponse::validationError(['status' => 'Invalid status']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Start transaction
    $conn->begin_transaction();
    
    $updatedCount = 0;
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
        
        // Update status
        $stmt = $conn->prepare("UPDATE candidates SET status = ?, updated_at = NOW() WHERE candidate_code = ?");
        $stmt->bind_param("ss", $status, $candidateCode);
        
        if ($stmt->execute()) {
            $updatedCount++;
            
            // Log activity
            Logger::getInstance()->logActivity(
                'update',
                'candidates',
                $candidateCode,
                "Changed status to {$status}",
                ['old_status' => $candidate['status'] ?? null, 'new_status' => $status]
            );
        } else {
            $errors[] = "Failed to update {$candidate['candidate_name']}";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $message = "{$updatedCount} candidate(s) status updated to " . ucfirst($status);
    
    if (!empty($errors)) {
        $message .= ". " . count($errors) . " error(s) occurred.";
    }
    
    Logger::getInstance()->info('Bulk status update completed', [
        'updated' => $updatedCount,
        'total' => count($candidates),
        'status' => $status
    ]);
    
    echo ApiResponse::success([
        'updated_count' => $updatedCount,
        'errors' => $errors
    ], $message);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    Logger::getInstance()->error('Bulk status update failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo ApiResponse::error('Failed to update status: ' . $e->getMessage(), 500);
}