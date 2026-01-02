<?php
/**
 * Convert CV to Candidate Handler
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\Auth;

// Check permission
Permission::require('candidates', 'create');

// Check request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid request token');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get CV ID
    $cvId = (int)input('cv_id');
    if (!$cvId) {
        redirectBack('CV ID is required');
    }
    
    // Get CV details (using NEW field names!)
    $stmt = $conn->prepare("
        SELECT cv.*, j.job_code 
        FROM cv_inbox cv
        LEFT JOIN jobs j ON cv.job_id = j.id
        WHERE cv.id = ? AND cv.deleted_at IS NULL
    ");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    
    if (!$cv) {
        redirectBack('CV not found');
    }
    
    // Check if already converted
    if ($cv['status'] === 'converted') {
        redirectWithMessage(
            "/panel/modules/candidates/view.php?code={$cv['converted_to_candidate_code']}",
            'This CV has already been converted',
            'info'
        );
    }
    
    // Validate input
    $validator = new Validator($_POST);
    if (!$validator->validate([
        'candidate_name' => 'required|min:2',
        'lead_type' => 'required'
    ])) {
        redirectBack(reset($validator->errors())[0] ?? 'Validation failed');
    }
    
    $data = $validator->validated();
    
    // Generate candidate code
    $candidateCode = 'CAN' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if candidate code already exists
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $candidateCode = 'CAN' . date('YmdHis') . rand(100, 999);
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $existingCandidate = $stmt->get_result()->fetch_assoc();
    
    if ($existingCandidate) {
        // Email exists - link to existing candidate
        $candidateCode = $existingCandidate['candidate_code'];
        $isNewCandidate = false;
    } else {
        $isNewCandidate = true;
    }
    
    // Get optional fields
    $phone = input('phone', '' );
    $phoneAlternate = input('phone_alternate', '');
    $linkedinUrl = input('linkedin_url', $cv['applicant_linkedin']);
    
    // Location & Work Status
    $currentLocation = input('current_location', 'Belgium');
    $willingToJoin = isset($_POST['willing_to_join']) ? 1 : 0;
    $workAuthorizationId = !empty(input('work_authorization_id')) ? (int)input('work_authorization_id') : null;
    
    // Employment
    $currentEmployer = input('current_employer', '');
    $currentPosition = input('current_position', '');
    $currentWorkingStatus = input('current_working_status', '');
    
    // Compensation
    $expectedSalary = !empty(input('expected_salary')) ? (float)input('expected_salary') : null;
    $expectedDailyRate = !empty(input('expected_daily_rate')) ? (float)input('expected_daily_rate') : null;
    
    // Availability
    $noticePeriodDays = !empty(input('notice_period_days')) ? (int)input('notice_period_days') : null;
    $availableFrom = input('available_from', '') ?: null;
    
    // BUSINESS FIELDS - CRITICAL!
    $leadType = $data['lead_type'];
    $leadTypeRole = input('lead_type_role', '');
    
    // Status
    $initialStatus = input('initial_status', 'qualified');
    
    // Skills (simplified - array of skill IDs)
    $skills = input('skills', []);
    
    // Notes
    $internalNotes = input('internal_notes', '');
    
    // Auto-submit to job?
    $autoSubmit = (int)input('auto_submit', 0);
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // STEP 1: Create or update candidate
        if ($isNewCandidate) {
            $sql = "
                INSERT INTO candidates (
                    candidate_code, candidate_name, email, phone, phone_alternate,
                    linkedin_url, current_location, willing_to_join, work_authorization_id,
                    current_employer, current_position, current_working_status,
                    expected_salary, expected_daily_rate,
                    notice_period_days, available_from,
                    lead_type, lead_type_role,
                    status, screening_result,
                    candidate_cv, assigned_to, created_by,
                    internal_notes,
                    created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?,
                    ?, ?,
                    ?,
                    ?, ?,
                    ?, 'pending',
                    ?, ?, ?,
                    ?,
                    NOW(), NOW()
                )
            ";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param(
                "ssssssssisssddissssssss",
                $candidateCode,
                $data['candidate_name'],
                $data['email'],
                $phone,
                $phoneAlternate,
                $linkedinUrl,
                $currentLocation,
                $willingToJoin,
                $workAuthorizationId,
                $currentEmployer,
                $currentPosition,
                $currentWorkingStatus,
                $expectedSalary,
                $expectedDailyRate,
                $noticePeriodDays,
                $availableFrom,
                $leadType,
                $leadTypeRole,
                $initialStatus,
                $cv['cv_path'],
                $user['user_code'],
                $user['user_code'],
                $internalNotes
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create candidate: ' . $stmt->error);
            }
            
            // STEP 2: Add skills (simplified - no proficiency in form)
            if (!empty($skills) && is_array($skills)) {
                $stmt = $conn->prepare("
                    INSERT INTO candidate_skills (
                        candidate_code, skill_id, proficiency_level, is_primary, added_by, added_at
                    ) VALUES (?, ?, 'Intermediate', ?, ?, NOW())
                ");
                
                foreach ($skills as $index => $skillId) {
                    $skillId = (int)$skillId;
                    $isPrimary = ($index === 0) ? 1 : 0;
                    
                    $stmt->bind_param("siis", $candidateCode, $skillId, $isPrimary, $user['user_code']);
                    $stmt->execute();
                }
            }
            
            Logger::getInstance()->logActivity(
                'create',
                'candidates',
                $candidateCode,
                "Created candidate from CV inbox: {$data['candidate_name']}",
                ['cv_id' => $cvId, 'cv_code' => $cv['cv_code']]
            );
        }
        
        // STEP 3: Create submission if auto-submit enabled
        if ($autoSubmit && !empty($cv['job_code'])) {
            $submissionCode = 'SUB' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $stmt = $conn->prepare("
                INSERT INTO submissions (
                    submission_code, candidate_code, job_code,
                    submitted_by, internal_status, client_status,
                    submission_notes, created_at
                ) VALUES (?, ?, ?, ?, 'pending', 'not_sent', 'Converted from CV inbox', NOW())
            ");
            
            $stmt->bind_param("ssss", $submissionCode, $candidateCode, $cv['job_code'], $user['user_code']);
            $stmt->execute();
            
            Logger::getInstance()->logActivity(
                'create',
                'submissions',
                $submissionCode,
                "Auto-created submission from CV conversion"
            );
        }
        
        // STEP 4: Update CV inbox status
        $stmt = $conn->prepare("
            UPDATE cv_inbox 
            SET status = 'converted',
                converted_to_candidate_code = ?,
                converted_by = ?,
                converted_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssi", $candidateCode, $user['user_code'], $cvId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update CV status: ' . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Success redirect
        redirectWithMessage(
            "/panel/modules/candidates/view.php?code={$candidateCode}",
            'CV converted to candidate successfully!',
            'success'
        );
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('CV conversion failed', [
        'error' => $e->getMessage(),
        'cv_id' => $cvId ?? null,
        'trace' => $e->getTraceAsString()
    ]);
    
    redirectBack('Failed to convert CV: ' . $e->getMessage());
}