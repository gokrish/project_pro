<?php
/**
 * Convert Contact to Candidate Handler
 * Creates candidate record and updates contact status
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('contacts', 'convert');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $contactCode = $_POST['contact_code'] ?? null;
    
    if (!$contactCode) {
        throw new Exception('Contact code is required');
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    // Fetch contact
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_code = ? AND deleted_at IS NULL FOR UPDATE");
    $stmt->bind_param("s", $contactCode);
    $stmt->execute();
    $contact = $stmt->get_result()->fetch_assoc();
    
    if (!$contact) {
        throw new Exception('Contact not found');
    }
    
    // Check if already converted
    if ($contact['converted_to_candidate']) {
        throw new Exception('Contact has already been converted to candidate');
    }
    
    // Check for duplicate candidate email
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM candidates WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $contact['email']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['count'] > 0) {
        throw new Exception('A candidate with this email already exists');
    }
    
    // Generate candidate code
    $candidateCode = 'CAN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create candidate from contact
    $stmt = $conn->prepare("
        INSERT INTO candidates (
            candidate_code,
            first_name,
            last_name,
            email,
            phone,
            current_company,
            current_title,
            years_of_experience,
            current_location,
            skills,
            summary,
            status,
            source,
            lead_type,
            assigned_to,
            created_by,
            created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 
            'new', 
            'contact_conversion',
            CASE WHEN ? = 'high' THEN 'hot'
                 WHEN ? = 'medium' THEN 'warm'
                 ELSE 'cold' END,
            ?, ?, NOW()
        )
    ");
    
    $stmt->bind_param(
        "sssssssdsssssss",
        $candidateCode,
        $contact['first_name'],
        $contact['last_name'],
        $contact['email'],
        $contact['phone'],
        $contact['current_company'],
        $contact['current_title'],
        $contact['years_of_experience'],
        $contact['current_location'],
        $contact['skills'],
        $contact['summary'],
        $contact['priority'], // for lead_type calculation
        $contact['priority'], // for lead_type calculation
        $contact['assigned_to'],
        Auth::userCode()
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create candidate: ' . $stmt->error);
    }
    
    // Update contact status
    $stmt = $conn->prepare("
        UPDATE contacts 
        SET status = 'converted',
            converted_to_candidate = ?,
            converted_at = NOW(),
            updated_at = NOW()
        WHERE contact_code = ?
    ");
    
    $stmt->bind_param("ss", $candidateCode, $contactCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update contact: ' . $stmt->error);
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'convert',
        'contacts',
        $contactCode,
        "Converted contact to candidate: {$contact['first_name']} {$contact['last_name']}",
        [
            'candidate_code' => $candidateCode,
            'contact_email' => $contact['email']
        ]
    );
    
    Logger::getInstance()->logActivity(
        'create',
        'candidates',
        $candidateCode,
        "Candidate created from contact conversion",
        [
            'source' => 'contact_conversion',
            'original_contact_code' => $contactCode
        ]
    );
    
    FlashMessage::success('Contact successfully converted to candidate!');
    redirect(BASE_URL . '/panel/modules/candidates/view.php?candidate_code=' . $candidateCode);
    
} catch (Exception $e) {
    $conn->rollback();
    FlashMessage::error('Conversion failed: ' . $e->getMessage());
    back();
}