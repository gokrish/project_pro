<?php
/**
 * Update Candidate Handler
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('candidates', 'edit');

// Check request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid request token');
}

$candidateCode = input('candidate_code');
if (empty($candidateCode)) {
    redirectBack('Candidate code is required');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Verify access
    $accessFilter = Permission::getAccessibleCandidates();
    $whereClause = $accessFilter ? "candidate_code = ? AND ({$accessFilter})" : "candidate_code = ?";
    
    $stmt = $conn->prepare("SELECT * FROM candidates WHERE {$whereClause}");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $existingCandidate = $stmt->get_result()->fetch_assoc();
    
    if (!$existingCandidate) {
        redirectBack('Candidate not found or no access');
    }
    
    // Validate input (exclude email unique check for same candidate)
    $validator = new Validator($_POST);
    
    if (!$validator->validate([
        'candidate_name' => 'required|min:2',
        'email' => 'required|email',
        'phone' => 'required|phone',
        'work_authorization_status' => 'required'
    ])) {
        $errors = $validator->errors();
        $firstError = reset($errors)[0] ?? 'Validation failed';
        redirectBack($firstError);
    }
    
    $data = $validator->validated();
    
    // Check email uniqueness (excluding current candidate)
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE email = ? AND candidate_code != ?");
    $stmt->bind_param("ss", $data['email'], $candidateCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        redirectBack('Email already exists for another candidate');
    }
    
    // Process skills
    if (isset($_POST['skills']) && is_array($_POST['skills'])) {
        $data['skills'] = implode(', ', $_POST['skills']);
    }
    
    // Update candidate (similar field processing as create)
    $sql = "
        UPDATE candidates SET
            candidate_name = ?, email = ?, phone = ?, phone_alternate = ?,
            current_location = ?, preferred_location = ?, work_authorization_status = ?, linkedin_url = ?,
            current_position = ?, current_company = ?, total_experience = ?, relevant_experience = ?, notice_period = ?,
            skills = ?, certifications = ?, languages = ?, highest_degree = ?, field_of_study = ?,
            compensation_type = ?, current_compensation = ?, expected_compensation = ?,
            rating = ?, status = ?, lead_type = ?, assigned_to = ?, role_addressed = ?, source = ?,
            follow_up_date = ?, availability_date = ?, notes = ?, updated_at = NOW()
        WHERE candidate_code = ?
    ";
    
    // [Field assignments similar to create handler]
    
    $stmt = $conn->prepare($sql);
    // [Bind parameters - abbreviated for space]
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update',
        'candidates',
        $candidateCode,
        "Updated candidate: {$data['candidate_name']}"
    );
    
    redirectWithMessage(
        "/panel/modules/candidates/view.php?code={$candidateCode}",
        'Candidate updated successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Candidate update failed', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode
    ]);
    
    redirectBack('Failed to update candidate: ' . $e->getMessage());
}