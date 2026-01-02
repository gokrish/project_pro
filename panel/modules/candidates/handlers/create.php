<?php
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Permission, Database, CSRFToken, Logger, ApiResponse, Auth};

Permission::require('candidates', 'create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Invalid request method', 405);
}

if (!CSRFToken::verifyRequest()) {
    ApiResponse::error('Invalid security token', 403);
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Generate candidate code
    $candidateCode = 'CAN' . date('Ymd') . strtoupper(substr(uniqid(), -6));
    
    // Sanitize inputs
    $candidateName = filter_input(INPUT_POST, 'candidate_name', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
    $currentLocation = filter_input(INPUT_POST, 'current_location', FILTER_SANITIZE_STRING);
    $currentEmployer = filter_input(INPUT_POST, 'current_employer', FILTER_SANITIZE_STRING);
    $currentPosition = filter_input(INPUT_POST, 'current_position', FILTER_SANITIZE_STRING);
    $professionalSummary = filter_input(INPUT_POST, 'professional_summary', FILTER_SANITIZE_STRING);
    $workAuthId = filter_input(INPUT_POST, 'work_authorization_id', FILTER_SANITIZE_NUMBER_INT);
    $currentSalary = filter_input(INPUT_POST, 'current_salary', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $expectedSalary = filter_input(INPUT_POST, 'expected_salary', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    $noticePeriodDays = filter_input(INPUT_POST, 'notice_period_days', FILTER_SANITIZE_NUMBER_INT);
    $availableFrom = filter_input(INPUT_POST, 'available_from', FILTER_SANITIZE_STRING);
    
    // Validate required fields
    $errors = [];
    if (empty($candidateName)) $errors['candidate_name'] = 'Name is required';
    if (empty($email)) $errors['email'] = 'Email is required';
    if (empty($phone)) $errors['phone'] = 'Phone is required';
    
    if (!empty($errors)) {
        ApiResponse::validationError($errors);
    }
    
    // Check for duplicates
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE email = ? OR phone = ? LIMIT 1");
    $stmt->bind_param("ss", $email, $phone);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        ApiResponse::error('A candidate with this email or phone already exists', 409);
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    // Insert candidate
    $stmt = $conn->prepare("
        INSERT INTO candidates (
            candidate_code, candidate_name, email, phone, 
            current_location, current_employer, current_position,
            professional_summary, work_authorization_id,
            current_salary, expected_salary, notice_period_days, available_from,
            status, lead_type, created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', 'Warm', ?, NOW())
    ");
    
    $userCode = Auth::userCode();
    $stmt->bind_param(
        "ssssssssiddiss",
        $candidateCode, $candidateName, $email, $phone,
        $currentLocation, $currentEmployer, $currentPosition,
        $professionalSummary, $workAuthId,
        $currentSalary, $expectedSalary, $noticePeriodDays, $availableFrom,
        $userCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create candidate');
    }
    
    // Add skills if provided
    if (!empty($_POST['skills']) && is_array($_POST['skills'])) {
        $stmt = $conn->prepare("
            INSERT INTO candidate_skills (candidate_code, skill_id, proficiency_level, added_by)
            VALUES (?, ?, ?, ?)
        ");
        
        foreach ($_POST['skills'] as $skillId) {
            $proficiency = $_POST['proficiency'][$skillId] ?? 'Intermediate';
            $stmt->bind_param("siss", $candidateCode, $skillId, $proficiency, $userCode);
            $stmt->execute();
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    // Log activity
    Logger::getInstance()->info('candidates', 'create', $candidateCode, "Created candidate: {$candidateName}");
    
    ApiResponse::created([
        'candidate_code' => $candidateCode,
        'redirect' => "/panel/modules/candidates/view.php?code={$candidateCode}"
    ], 'Candidate created successfully');
    
} catch (Exception $e) {
    $conn->rollback();
    Logger::getInstance()->error('Failed to create candidate', ['error' => $e->getMessage()]);
    ApiResponse::serverError('Failed to create candidate: ' . $e->getMessage());
}