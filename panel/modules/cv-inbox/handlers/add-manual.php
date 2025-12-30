<?php
/**
 * Add Manual CV Entry Handler
 * For applications received via email/LinkedIn
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\FileUpload;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('cv_inbox', 'create')) {
    echo ApiResponse::forbidden();
    exit;
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Validate input
    $validator = new Validator($_POST);
    if (!$validator->validate([
        'candidate_name' => 'required|min:2',
        'email' => 'required|email',
        'source' => 'required'
    ])) {
        echo ApiResponse::validationError($validator->errors());
        exit;
    }
    
    $data = $validator->validated();
    
    // Check if email already exists in inbox (prevent duplicates)
    $stmt = $conn->prepare("SELECT id FROM cv_inbox WHERE email = ? AND status != 'rejected'");
    $stmt->bind_param("s", $data['email']);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo ApiResponse::error('This email already exists in the inbox', 400);
        exit;
    }
    
    // Handle resume upload
    $resumePath = null;
    $resumeFilename = null;

    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        echo ApiResponse::error('Resume file is required', 400);
        exit;
    }

    // Upload directory
    $uploadDir = ROOT_PATH . '/uploads/cv-inbox/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileUpload = new FileUpload();
    $result = $fileUpload->upload('resume', $uploadDir, [
        'allowed_types' => ['pdf', 'doc', 'docx'],
        'max_size' => 5 * 1024 * 1024,
        'generate_unique_name' => true
    ]);

    if (!$result['success']) {
        echo ApiResponse::error($result['error'], 400);
        exit;
    }

    // Store relative path (without ROOT_PATH)
    $resumePath = '/uploads/cv-inbox/' . $result['filename'];
    $resumeFilename = $result['filename'];
    
    // Optional fields
    $phone = input('phone', '');
    $jobCode = input('job_code', '');
    $notes = input('notes', '');
    
    // Insert into cv_inbox
    $sql = "
        INSERT INTO cv_inbox (
            candidate_name, email, phone,
            job_code, source, resume_path,
            status, assigned_to,
            received_at
        ) VALUES (?, ?, ?, ?, ?, ?, 'new', ?, NOW())
    ";
    
    $stmt = $conn->prepare($sql);
    $assignedTo = $user['user_code']; // Assign to current user by default
    
    $stmt->bind_param(
        "sssssss",
        $data['candidate_name'],
        $data['email'],
        $phone,
        $jobCode,
        $data['source'],
        $resumePath,
        $assignedTo
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add CV to inbox: ' . $stmt->error);
    }
    
    $cvId = $conn->insert_id;
    
    // Add initial note if provided
    if (!empty($notes)) {
        $stmt = $conn->prepare("
            INSERT INTO cv_notes (cv_id, note, created_by, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->bind_param("iss", $cvId, $notes, $user['user_code']);
        $stmt->execute();
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'create',
        'cv_inbox',
        $cvId,
        "Added CV to inbox: {$data['candidate_name']}",
        [
            'email' => $data['email'],
            'source' => $data['source'],
            'job_code' => $jobCode
        ]
    );
    
    // Send notification to assigned recruiter (if different from current user)
    if ($assignedTo !== $user['user_code']) {
        Notification::send(
            $assignedTo,
            'cv_inbox_new',
            'New CV Application',
            "New application received from {$data['candidate_name']}",
            'cv_inbox',
            $cvId
        );
    }
    
    echo ApiResponse::success(['cv_id' => $cvId], 'Application added to inbox successfully');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Add CV failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to add application', 500);
}