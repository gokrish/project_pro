<?php
/**
 * Create Candidate Handler
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;



// Check request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid request token');
}

try {
    // Validate input
    $validator = new Validator($_POST);
    
    if (!$validator->validate([
        'candidate_code' => 'required',
        'candidate_name' => 'required|min:2',
        'email' => 'required|email|unique:candidates,email',
        'phone' => 'required|phone',
        'work_authorization_status' => 'required',
        'skills' => 'required'
    ])) {
        $errors = $validator->errors();
        $firstError = reset($errors)[0] ?? 'Validation failed';
        redirectBack($firstError);
    }
    
    $data = $validator->validated();
    
    // Process skills array
    if (isset($data['skills']) && is_array($data['skills'])) {
        $data['skills'] = implode(', ', $data['skills']);
    }
    
    // Handle optional fields
    $data['phone_alternate'] = $data['phone_alternate'] ?? null;
    $data['current_location'] = $data['current_location'] ?? null;
    $data['preferred_location'] = $data['preferred_location'] ?? null;
    $data['linkedin_url'] = $data['linkedin_url'] ?? null;
    $data['current_position'] = $data['current_position'] ?? null;
    $data['current_company'] = $data['current_company'] ?? null;
    $data['total_experience'] = !empty($data['total_experience']) ? (float)$data['total_experience'] : 0;
    $data['relevant_experience'] = !empty($data['relevant_experience']) ? (float)$data['relevant_experience'] : 0;
    $data['notice_period'] = !empty($data['notice_period']) ? (int)$data['notice_period'] : null;
    $data['certifications'] = $data['certifications'] ?? null;
    $data['languages'] = $data['languages'] ?? null;
    $data['highest_degree'] = $data['highest_degree'] ?? null;
    $data['field_of_study'] = $data['field_of_study'] ?? null;
    $data['compensation_type'] = $data['compensation_type'] ?? 'salary';
    $data['current_compensation'] = !empty($data['current_compensation']) ? (float)$data['current_compensation'] : null;
    $data['expected_compensation'] = !empty($data['expected_compensation']) ? (float)$data['expected_compensation'] : null;
    $data['rating'] = !empty($data['rating']) ? (int)$data['rating'] : 0;
    $data['status'] = $data['status'] ?? 'active';
    $data['lead_type'] = $data['lead_type'] ?? 'warm';
    $data['assigned_to'] = !empty($data['assigned_to']) ? $data['assigned_to'] : null;
    $data['role_addressed'] = $data['role_addressed'] ?? null;
    $data['source'] = $data['source'] ?? null;
    $data['follow_up_date'] = !empty($data['follow_up_date']) ? $data['follow_up_date'] : null;
    $data['availability_date'] = !empty($data['availability_date']) ? $data['availability_date'] : null;
    $data['notes'] = $data['notes'] ?? null;
    
    // Insert candidate
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $sql = "
        INSERT INTO candidates (
            candidate_code, candidate_name, email, phone, phone_alternate,
            current_location, preferred_location, work_authorization_status, linkedin_url,
            current_position, current_company, total_experience, relevant_experience, notice_period,
            skills, certifications, languages, highest_degree, field_of_study,
            compensation_type, current_compensation, expected_compensation,
            rating, status, lead_type, assigned_to, role_addressed, source,
            follow_up_date, availability_date, notes, created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?, ?,
            ?, ?, ?,
            ?, ?, ?, ?, ?, ?,
            ?, ?, ?, NOW(), NOW()
        )
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssssddissssssddisssssss",
        $data['candidate_code'], $data['candidate_name'], $data['email'], $data['phone'], $data['phone_alternate'],
        $data['current_location'], $data['preferred_location'], $data['work_authorization_status'], $data['linkedin_url'],
        $data['current_position'], $data['current_company'], $data['total_experience'], $data['relevant_experience'], $data['notice_period'],
        $data['skills'], $data['certifications'], $data['languages'], $data['highest_degree'], $data['field_of_study'],
        $data['compensation_type'], $data['current_compensation'], $data['expected_compensation'],
        $data['rating'], $data['status'], $data['lead_type'], $data['assigned_to'], $data['role_addressed'], $data['source'],
        $data['follow_up_date'], $data['availability_date'], $data['notes']
    );
    
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'candidates',
        $data['candidate_code'],
        "Created candidate: {$data['candidate_name']}",
        ['email' => $data['email']]
    );
    
    redirectWithMessage(
        "/panel/modules/candidates/view.php?code={$data['candidate_code']}",
        'Candidate created successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Candidate create failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    redirectBack('Failed to create candidate: ' . $e->getMessage());
}