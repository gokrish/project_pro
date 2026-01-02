<?php
/**
 * Update Candidate Handler
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
if (!Permission::can('candidates', 'edit_all') && !Permission::can('candidates', 'edit_own')) {
    redirectBack('You do not have permission to edit candidates');
}

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
    
    // Get candidate code
    $candidateCode = input('candidate_code');
    if (empty($candidateCode)) {
        redirectBack('Candidate code is required');
    }
    
    // Get existing candidate
    $stmt = $conn->prepare("
        SELECT * FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $existingCandidate = $stmt->get_result()->fetch_assoc();
    
    if (!$existingCandidate) {
        redirectBack('Candidate not found');
    }
    
    // Check permission for own candidates
    if (!Permission::can('candidates', 'edit_all')) {
        if ($existingCandidate['created_by'] !== $user['user_code'] && 
            $existingCandidate['assigned_to'] !== $user['user_code']) {
            redirectBack('You can only edit your own candidates');
        }
    }
    
    // Validate input
    $validator = new Validator($_POST);
    
    if (!$validator->validate([
        'candidate_name' => 'required|min:2|max:255',
        'email' => 'required|email|max:255',
        'phone' => 'required|phone',
        'current_location' => 'required',
        'lead_type' => 'required',
        'professional_summary' => 'required|min:10'
    ])) {
        $errors = $validator->errors();
        $firstError = reset($errors)[0] ?? 'Validation failed';
        redirectBack($firstError);
    }
    
    $data = $validator->validated();
    
    // Check email uniqueness (except current candidate)
    $stmt = $conn->prepare("
        SELECT id FROM candidates 
        WHERE email = ? AND candidate_code != ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("ss", $data['email'], $candidateCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        redirectBack('Email already exists for another candidate');
    }
    
    // Handle optional fields
    $alternateEmail = input('alternate_email', '');
    $phoneAlternate = input('phone_alternate', '');
    $linkedinUrl = input('linkedin_url', '');
    
    // Location & Work Status
    $willingToJoin = isset($_POST['willing_to_join']) ? 1 : 0;
    $workAuthorizationId = !empty(input('work_authorization_id')) ? (int)input('work_authorization_id') : null;
    
    // Current Employment
    $currentEmployer = input('current_employer', '');
    $currentPosition = input('current_position', '');
    $currentAgency = input('current_agency', '');
    $currentWorkingStatus = input('current_working_status', '');
    
    // Compensation
    $currentSalary = !empty(input('current_salary')) ? (float)input('current_salary') : null;
    $expectedSalary = !empty(input('expected_salary')) ? (float)input('expected_salary') : null;
    $currentDailyRate = !empty(input('current_daily_rate')) ? (float)input('current_daily_rate') : null;
    $expectedDailyRate = !empty(input('expected_daily_rate')) ? (float)input('expected_daily_rate') : null;
    
    // Availability
    $noticePeriodDays = !empty(input('notice_period_days')) ? (int)input('notice_period_days') : null;
    $availableFrom = input('available_from', '') ?: null;
    
    // Languages
    $languages = input('languages', []);
    if (is_array($languages)) {
        $languages = json_encode($languages);
    } elseif (empty($languages)) {
        $languages = null;
    }
    
    // Business fields
    $leadType = $data['lead_type'];
    $leadTypeRole = input('lead_type_role', '');
    
    // Status (keep existing if not provided)
    $status = input('status', $existingCandidate['status']);
    
    // Follow-up
    $followUpStatus = input('follow_up_status', $existingCandidate['follow_up_status']);
    $followUpDate = input('follow_up_date', '') ?: null;
    
    // Consent
    $consentGiven = isset($_POST['consent_given']) ? 1 : 0;
    $consentDate = $consentGiven ? ($existingCandidate['consent_date'] ?: date('Y-m-d')) : null;
    
    // Assignment (only if has permission)
    $assignedTo = $existingCandidate['assigned_to'];
    if (Permission::can('candidates', 'edit_all')) {
        $assignedTo = input('assigned_to', $assignedTo);
    }
    
    // Notes
    $internalNotes = input('internal_notes', '');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Update candidate
        $sql = "
            UPDATE candidates SET
                candidate_name = ?, email = ?, alternate_email = ?, phone = ?, phone_alternate = ?,
                linkedin_url = ?, current_location = ?, willing_to_join = ?, work_authorization_id = ?,
                current_employer = ?, current_position = ?, current_agency = ?, current_working_status = ?,
                current_salary = ?, expected_salary = ?, current_daily_rate = ?, expected_daily_rate = ?,
                notice_period_days = ?, available_from = ?, professional_summary = ?, languages = ?,
                lead_type = ?, lead_type_role = ?, status = ?,
                follow_up_status = ?, follow_up_date = ?,
                consent_given = ?, consent_date = ?, assigned_to = ?,
                internal_notes = ?, updated_at = NOW()
            WHERE candidate_code = ?
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "ssssssssissssddddisssssssssssss",
            $data['candidate_name'],
            $data['email'],
            $alternateEmail,
            $data['phone'],
            $phoneAlternate,
            $linkedinUrl,
            $data['current_location'],
            $willingToJoin,
            $workAuthorizationId,
            $currentEmployer,
            $currentPosition,
            $currentAgency,
            $currentWorkingStatus,
            $currentSalary,
            $expectedSalary,
            $currentDailyRate,
            $expectedDailyRate,
            $noticePeriodDays,
            $availableFrom,
            $data['professional_summary'],
            $languages,
            $leadType,
            $leadTypeRole,
            $status,
            $followUpStatus,
            $followUpDate,
            $consentGiven,
            $consentDate,
            $assignedTo,
            $internalNotes,
            $candidateCode
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update candidate: ' . $stmt->error);
        }
        
        // Log changed fields to edit history
        $changedFields = [];
        
        // Compare each field and log changes
        $fieldsToCheck = [
            'candidate_name', 'email', 'phone', 'current_location', 'professional_summary',
            'lead_type', 'lead_type_role', 'current_employer', 'current_position',
            'expected_salary', 'expected_daily_rate', 'available_from', 'status'
        ];
        
        foreach ($fieldsToCheck as $field) {
            $oldValue = $existingCandidate[$field] ?? '';
            $newValue = $$field ?? $data[$field] ?? '';
            
            if ($oldValue != $newValue) {
                $changedFields[$field] = [
                    'old' => $oldValue,
                    'new' => $newValue
                ];
                
                // Log to edit history
                $stmt = $conn->prepare("
                    INSERT INTO candidate_edit_history (
                        candidate_code, field_name, old_value, new_value,
                        edit_type, edited_by, edited_at
                    ) VALUES (?, ?, ?, ?, 'manual', ?, NOW())
                ");
                $stmt->bind_param("sssss", $candidateCode, $field, $oldValue, $newValue, $user['user_code']);
                $stmt->execute();
            }
        }
        
        // Log activity
        Logger::getInstance()->logActivity(
            'update',
            'candidates',
            $candidateCode,
            "Updated candidate: {$data['candidate_name']}",
            [
                'changed_fields' => array_keys($changedFields),
                'updated_by' => $user['user_code']
            ]
        );
        
        // Commit transaction
        $conn->commit();
        
        // Success redirect
        redirectWithMessage(
            "/panel/modules/candidates/view.php?code={$candidateCode}",
            'Candidate updated successfully',
            'success'
        );
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Candidate update failed', [
        'error' => $e->getMessage(),
        'candidate_code' => $candidateCode ?? null,
        'trace' => $e->getTraceAsString()
    ]);
    
    redirectBack('Failed to update candidate: ' . $e->getMessage());
}