<?php
/**
 * Add Manual CV Entry Handler
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
Permission::require('cv_inbox', 'create');

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
    
    // Validate input
    $validator = new Validator($_POST);
    
    if (!$validator->validate([
        'applicant_name' => 'required|min:2|max:255',
        'applicant_email' => 'required|email|max:255',
    ])) {
        $errors = $validator->errors();
        $firstError = reset($errors)[0] ?? 'Validation failed';
        redirectBack($firstError);
    }
    
    $data = $validator->validated();
    
    // Generate CV code
    $cvCode = 'CV' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Check if code exists (regenerate if needed)
    $stmt = $conn->prepare("SELECT cv_code FROM cv_inbox WHERE cv_code = ?");
    $stmt->bind_param("s", $cvCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $cvCode = 'CV' . date('YmdHis') . rand(100, 999);
    }
    
    // Get optional fields
    $applicantLinkedin = input('applicant_linkedin', '');
    $coverLetterPath = input('cover_letter_path', '');
    
    // Job linking (IMPORTANT!)
    $jobCode = input('job_code', '');
    $jobId = null;
    $jobRefno = null;
    
    if (!empty($jobCode)) {
        // Get job_id and job_refno from jobs table
        $stmt = $conn->prepare("SELECT id, job_refno FROM jobs WHERE job_code = ? AND deleted_at IS NULL");
        $stmt->bind_param("s", $jobCode);
        $stmt->execute();
        $jobResult = $stmt->get_result();
        if ($jobData = $jobResult->fetch_assoc()) {
            $jobId = $jobData['id'];
            $jobRefno = $jobData['job_refno'];
        }
    }
    
    // Source
    $source = input('source', 'Website_Career_Page');
    $validSources = ['Website_Career_Page', 'Email', 'LinkedIn', 'Referral', 'Direct', 'Other'];
    if (!in_array($source, $validSources)) {
        $source = 'Website_Career_Page';
    }
    
    // Status (default: new)
    $status = 'new';
    
    // Assignment
    $assignedTo = input('assigned_to', '');
    $assignedAt = !empty($assignedTo) ? date('Y-m-d H:i:s') : null;
    
    // CV file path (required)
    $cvPath = input('cv_path', '');
    if (empty($cvPath)) {
        // Handle file upload if provided
        if (!empty($_FILES['cv_file']['name'])) {
            // File upload logic here
            // For now, we'll require cv_path to be provided
            redirectBack('CV file is required');
        } else {
            redirectBack('CV file path is required');
        }
    }
    
    // Initial notes
    $initialNotes = input('notes', '');
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Insert CV entry
        $sql = "
            INSERT INTO cv_inbox (
                cv_code,
                job_id,
                job_code,
                job_refno,
                applicant_name,
                applicant_email,
                cv_path,
                cover_letter_path,
                source,
                status,
                assigned_to,
                assigned_at,
                submitted_at,
                updated_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
            )
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
            "sissssssssssss",
            $cvCode,
            $jobId,
            $jobCode,
            $jobRefno,
            $data['applicant_name'],
            $data['applicant_email'],
            $data['applicant_phone'],
            $applicantLinkedin,
            $cvPath,
            $coverLetterPath,
            $source,
            $status,
            $assignedTo,
            $assignedAt
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create CV entry: ' . $stmt->error);
        }
        
        $cvId = $conn->insert_id;
        
        // Add initial note if provided
        if (!empty($initialNotes)) {
            $stmt = $conn->prepare("
                INSERT INTO cv_inbox_notes (cv_id, note_type, note, created_by, created_at)
                VALUES (?, 'general', ?, ?, NOW())
            ");
            $stmt->bind_param("iss", $cvId, $initialNotes, $user['user_code']);
            $stmt->execute();
        }
        
        // Log activity
        Logger::getInstance()->logActivity(
            'create',
            'cv_inbox',
            $cvCode,
            "Manually added CV: {$data['applicant_name']}",
            [
                'cv_code' => $cvCode,
                'applicant_email' => $data['applicant_email'],
                'job_code' => $jobCode,
                'source' => $source,
                'created_by' => $user['user_code']
            ]
        );
        
        // Commit transaction
        $conn->commit();
        
        // Success redirect
        redirectWithMessage(
            "/panel/modules/cv-inbox/view.php?id={$cvId}",
            'CV application added successfully',
            'success'
        );
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('CV manual entry failed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
        'post_data' => $_POST
    ]);
    
    redirectBack('Failed to add CV application: ' . $e->getMessage());
}