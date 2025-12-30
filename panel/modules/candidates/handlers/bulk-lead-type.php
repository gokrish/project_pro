<?php
/**
 * Bulk Update Lead Type
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
$leadType = $input['lead_type'] ?? '';

// Validate input
if (empty($candidates) || !is_array($candidates)) {
    echo ApiResponse::validationError(['candidates' => 'No candidates selected']);
    exit;
}

$validLeadTypes = ['hot', 'warm', 'cold', 'blacklist'];
if (!in_array($leadType, $validLeadTypes)) {
    echo ApiResponse::validationError(['lead_type' => 'Invalid lead type']);
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
        
        $stmt = $conn->prepare("SELECT candidate_code, candidate_name, lead_type FROM candidates WHERE {$whereClause}");
        $stmt->bind_param("s", $candidateCode);
        $stmt->execute();
        $candidate = $stmt->get_result()->fetch_assoc();
        
        if (!$candidate) {
            $errors[] = "Candidate {$candidateCode} not found or no access";
            continue;
        }
        
        // Update lead type
        $stmt = $conn->prepare("UPDATE candidates SET lead_type = ?, updated_at = NOW() WHERE candidate_code = ?");
        $stmt->bind_param("ss", $leadType, $candidateCode);
        
        if ($stmt->execute()) {
            $updatedCount++;
            
            // Log activity
            Logger::getInstance()->logActivity(
                'update',
                'candidates',
                $candidateCode,
                "Changed lead type to {$leadType}",
                ['old_lead_type' => $candidate['lead_type'], 'new_lead_type' => $leadType]
            );
        } else {
            $errors[] = "Failed to update {$candidate['candidate_name']}";
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    $message = "{$updatedCount} candidate(s) lead type updated to " . ucfirst($leadType);
    
    if (!empty($errors)) {
        $message .= ". " . count($errors) . " error(s) occurred.";
    }
    
    Logger::getInstance()->info('Bulk lead type update completed', [
        'updated' => $updatedCount,
        'total' => count($candidates),
        'lead_type' => $leadType
    ]);
    
    echo ApiResponse::success([
        'updated_count' => $updatedCount,
        'errors' => $errors
    ], $message);
    
} catch (Exception $e) {
    if (isset($conn)) {
        $conn->rollback();
    }
    
    Logger::getInstance()->error('Bulk lead type update failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo ApiResponse::error('Failed to update lead type: ' . $e->getMessage(), 500);
}