<?php
/**
 * Update Candidate Phone Number Handler
 * Handles quick phone number updates 
 * 
 * @package ProConsultancy\Modules\Candidates
 * @version 1.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Auth;

// Check permission
if (!Permission::can('candidates', 'edit.all') && !Permission::can('candidates', 'edit.own')) {
    ApiResponse::forbidden('You do not have permission to update candidate information');
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['candidate_code'])) {
    ApiResponse::error('Candidate code is required');
}

$candidateCode = $_POST['candidate_code'];
$phone = trim($_POST['phone'] ?? '');
$phoneAlternate = trim($_POST['phone_alternate'] ?? '');

// Validate phone format
if (!empty($phone) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phone)) {
    ApiResponse::validation(['phone' => ['Invalid phone number format']]);
}

if (!empty($phoneAlternate) && !preg_match('/^[\d\s\+\-\(\)]+$/', $phoneAlternate)) {
    ApiResponse::validation(['phone_alternate' => ['Invalid alternate phone number format']]);
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Get current candidate data
    $stmt = $conn->prepare("
        SELECT phone, phone_alternate, created_by, assigned_to 
        FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    if (!$candidate) {
        ApiResponse::notFound('Candidate not found');
    }
    
    // Check ownership for .own permission
    $currentUser = Auth::user();
    if (Permission::can('candidates', 'edit.own') && !Permission::can('candidates', 'edit.all')) {
        if ($candidate['created_by'] !== $currentUser['user_code'] && 
            $candidate['assigned_to'] !== $currentUser['user_code']) {
            ApiResponse::forbidden('You can only edit your own candidates');
        }
    }
    
    // Store old values for audit trail
    $oldPhone = $candidate['phone'];
    $oldPhoneAlternate = $candidate['phone_alternate'];
    
    // Update candidate
    $stmt = $conn->prepare("
        UPDATE candidates 
        SET phone = ?, 
            phone_alternate = ?,
            updated_at = NOW()
        WHERE candidate_code = ?
    ");
    $stmt->bind_param("sss", $phone, $phoneAlternate, $candidateCode);
    $stmt->execute();
    
    // Log changes to activity timeline
    $loggedBy = Auth::userCode();
    
    if ($oldPhone !== $phone) {
        $stmt = $conn->prepare("
            INSERT INTO candidate_activity_log (
                can_code, 
                activity_type, 
                field_name, 
                old_value, 
                new_value, 
                description,
                created_by, 
                created_at
            ) VALUES (?, 'field_update', 'phone', ?, ?, 'Phone number updated', ?, NOW())
        ");
        $description = "Phone number updated";
        $stmt->bind_param("ssss", $candidateCode, $oldPhone, $phone, $loggedBy);
        $stmt->execute();
    }
    
    if ($oldPhoneAlternate !== $phoneAlternate) {
        $stmt = $conn->prepare("
            INSERT INTO candidate_activity_log (
                can_code, 
                activity_type, 
                field_name, 
                old_value, 
                new_value, 
                description,
                created_by, 
                created_at
            ) VALUES (?, 'field_update', 'phone_alternate', ?, ?, 'Alternate phone updated', ?, NOW())
        ");
        $stmt->bind_param("ssss", $candidateCode, $oldPhoneAlternate, $phoneAlternate, $loggedBy);
        $stmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update',
        'candidates',
        $candidateCode,
        'Phone number updated',
        [
            'phone' => $phone,
            'phone_alternate' => $phoneAlternate,
            'updated_by' => $loggedBy
        ]
    );
    
    $db->commit();
    
    ApiResponse::success([
        'candidate_code' => $candidateCode,
        'phone' => $phone,
        'phone_alternate' => $phoneAlternate,
        'updated_at' => date('Y-m-d H:i:s')
    ], 'Contact information updated successfully');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Failed to update candidate phone', [
        'candidate_code' => $candidateCode,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to update contact information', [
        'error' => $e->getMessage()
    ]);
}
