<?php
/**
 * Convert CV to Candidate Handler
 * Creates candidate + application + updates CV status
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

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
    
    // Get CV details
    $stmt = $conn->prepare("SELECT * FROM cv_inbox WHERE id = ?");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    
    if (!$cv) {
        redirectBack('CV not found');
    }
    
    // Check if already converted
    if ($cv['status'] === 'converted') {
        redirectWithMessage(
            "/panel/modules/candidates/view.php?code={$cv['converted_to_candidate']}",
            'This CV has already been converted',
            'info'
        );
    }
    
    // Validate input
    $validator = new Validator($_POST);
    if (!$validator->validate([
        'candidate_code' => 'required',
        'candidate_name' => 'required|min:2',
        'email' => 'required|email'
    ])) {
        redirectBack(reset($validator->errors())[0] ?? 'Validation failed');
    }
    
    $data = $validator->validated();
    
    // Check if candidate code already exists
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param("s", $data['candidate_code']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        // Regenerate unique code
        $data['candidate_code'] = 'CAN-' . date('Ymd-His') . '-' . rand(100, 999);
    }
    
    // Check if email already exists in candidates
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE email = ?");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    $existingCandidate = $stmt->get_result()->fetch_assoc();
    
    if ($existingCandidate) {
        // Email exists - link to existing candidate instead of creating new
        $data['candidate_code'] = $existingCandidate['candidate_code'];
        $isNewCandidate = false;
    } else {
        $isNewCandidate = true;
    }
    
    // Get optional fields
    $phone = input('phone', '');
    $currentPosition = input('current_position', '');
    $currentCompany = input('current_company', '');
    $totalExperience = !empty(input('total_experience')) ? (float)input('total_experience') : null;
    $currentLocation = input('current_location', '');
    $skills = input('skills', '');
    $expectedCompensation = !empty(input('expected_compensation')) ? (float)input('expected_compensation') : null;
    $availability = input('availability', '');
    $workAuthorization = input('work_authorization', '');
    $leadType = input('lead_type', 'warm');
    $jobCode = input('job_code', '');
    $applicationStatus = input('application_status', 'screening');
    $notes = input('notes', '');
    $sendWelcomeEmail = isset($_POST['send_welcome_email']) ? 1 : 0;
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // STEP 1: Create or Update Candidate
        if ($isNewCandidate) {
            $sql = "
                INSERT INTO candidates (
                    candidate_code, candidate_name, email, phone,
                    current_position, current_company, total_experience,
                    current_location, skills,
                    expected_compensation, compensation_type,
                    availability, work_authorization, lead_type,
                    status, source,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'daily_rate', ?, ?, ?, 'active', ?, ?, NOW())
            ";
            
            $stmt = $conn->prepare($sql);
            $source = 'cv_inbox'; // Mark source
            
            $stmt->bind_param(
                "ssssssdssdssss",
                $data['candidate_code'],
                $data['candidate_name'],
                $data['email'],
                $phone,
                $currentPosition,
                $currentCompany,
                $totalExperience,
                $currentLocation,
                $skills,
                $expectedCompensation,
                $availability,
                $workAuthorization,
                $leadType,
                $source,
                $user['user_code']
            );
            
            if (!$stmt->execute()) {
                throw new Exception('Failed to create candidate: ' . $stmt->error);
            }
            
            Logger::getInstance()->logActivity(
                'create',
                'candidates',
                $data['candidate_code'],
                "Created candidate from CV inbox: {$data['candidate_name']}"
            );
        }
        
        // STEP 2: Copy Resume to Candidate Documents
        if (!empty($cv['resume_path'])) {
            $stmt = $conn->prepare("
                INSERT INTO candidate_documents (
                    candidate_code, document_type, 
                    file_path, file_name,
                    uploaded_by, uploaded_at
                ) VALUES (?, 'resume', ?, ?, ?, NOW())
            ");
            
            $fileName = basename($cv['resume_path']);
            
            $stmt->bind_param(
                "ssss",
                $data['candidate_code'],
                $cv['resume_path'],
                $fileName,
                $user['user_code']
            );
            
            $stmt->execute(); // Don't fail if table doesn't exist
        }
        
        // STEP 3: Create Application (if job selected)
        if (!empty($jobCode)) {
            // Check if application already exists
            $stmt = $conn->prepare("
                SELECT id FROM applications 
                WHERE candidate_code = ? AND job_code = ?
            ");
            $stmt->bind_param("ss", $data['candidate_code'], $jobCode);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                // Create new application
                $applicationCode = 'APP-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $stmt = $conn->prepare("
                    INSERT INTO applications (
                        application_code, candidate_code, job_code,
                        status, source, cv_inbox_id,
                        applied_at, created_by
                    ) VALUES (?, ?, ?, ?, 'cv_inbox', ?, NOW(), ?)
                ");
                
                $stmt->bind_param(
                    "ssssis",
                    $applicationCode,
                    $data['candidate_code'],
                    $jobCode,
                    $applicationStatus,
                    $cvId,
                    $user['user_code']
                );
                
                if (!$stmt->execute()) {
                    throw new Exception('Failed to create application: ' . $stmt->error);
                }
                
                Logger::getInstance()->logActivity(
                    'create',
                    'applications',
                    $applicationCode,
                    "Created application from CV inbox"
                );
            }
        }
        
        // STEP 4: Add Initial Note (if provided)
        if (!empty($notes)) {
            $stmt = $conn->prepare("
                INSERT INTO candidate_notes (
                    candidate_code, note_type, note,
                    created_by, created_at
                ) VALUES (?, 'screening', ?, ?, NOW())
            ");
            
            $stmt->bind_param(
                "sss",
                $data['candidate_code'],
                $notes,
                $user['user_code']
            );
            
            $stmt->execute();
        }
        
        // STEP 5: Update CV Inbox Status
        $stmt = $conn->prepare("
            UPDATE cv_inbox 
            SET status = 'converted',
                converted_to_candidate = ?,
                converted_by = ?,
                converted_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param("ssi", $data['candidate_code'], $user['user_code'], $cvId);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update CV status: ' . $stmt->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // STEP 6: Send Welcome Email (optional)
        if ($sendWelcomeEmail && defined('ENABLE_EMAIL_NOTIFICATIONS') && ENABLE_EMAIL_NOTIFICATIONS) {
            try {
                // Send welcome email logic here
                // $mailer->sendWelcomeEmail($data['email'], $data['candidate_name']);
            } catch (Exception $e) {
                // Don't fail if email fails
                Logger::getInstance()->warning('Welcome email failed', ['error' => $e->getMessage()]);
            }
        }
        
        // Success - redirect to candidate profile
        redirectWithMessage(
            "/panel/modules/candidates/view.php?code={$data['candidate_code']}",
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
        'cv_id' => $cvId ?? null
    ]);
    
    redirectBack('Failed to convert CV: ' . $e->getMessage());
}