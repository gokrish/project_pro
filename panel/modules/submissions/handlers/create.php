<?php
/**
 * Create Submission Handler
 * File: panel/modules/submissions/handlers/create.php
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('submissions', 'create')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Validate required fields
    $requiredFields = ['submission_code', 'candidate_code', 'job_code', 'client_code', 'proposed_rate', 'fit_reason'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception(ucwords(str_replace('_', ' ', $field)) . ' is required');
        }
    }
    
    $submissionCode = trim($_POST['submission_code']);
    $candidateCode = trim($_POST['candidate_code']);
    $jobCode = trim($_POST['job_code']);
    $clientCode = trim($_POST['client_code']);
    
    // Verify entities exist
    $stmt = $conn->prepare("SELECT candidate_code FROM candidates WHERE candidate_code = ?");
    $stmt->bind_param('s', $candidateCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Candidate not found');
    }
    
    $stmt = $conn->prepare("SELECT job_code FROM jobs WHERE job_code = ?");
    $stmt->bind_param('s', $jobCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Job not found');
    }
    
    $stmt = $conn->prepare("SELECT client_code FROM clients WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Client not found');
    }
    
    // Check for duplicate submission
    $stmt = $conn->prepare("
        SELECT submission_id FROM candidate_submissions 
        WHERE candidate_code = ? AND job_code = ? 
        AND status NOT IN ('rejected', 'withdrawn')
        AND deleted_at IS NULL
    ");
    $stmt->bind_param('ss', $candidateCode, $jobCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('This candidate has already been submitted for this job');
    }
    
    // Get form data
    $proposedRate = (float)$_POST['proposed_rate'];
    $rateType = $_POST['rate_type'] ?? 'daily';
    $currency = $_POST['currency'] ?? 'EUR';
    $availabilityDate = !empty($_POST['availability_date']) ? $_POST['availability_date'] : null;
    $contractDuration = !empty($_POST['contract_duration']) ? (int)$_POST['contract_duration'] : null;
    $fitReason = trim($_POST['fit_reason']);
    $keyStrengths = trim($_POST['key_strengths'] ?? '');
    $concerns = trim($_POST['concerns'] ?? '');
    $submissionType = $_POST['submission_type'] ?? 'client_submission';
    $saveAsDraft = isset($_POST['save_as_draft']) && $_POST['save_as_draft'] === '1';
    
    // Determine status
    $status = $saveAsDraft ? 'draft' : 'pending_review';
    
    // Handle file uploads
    $documentsAttached = [];
    if (isset($_FILES['cv_file']) && $_FILES['cv_file']['error'] === UPLOAD_ERR_OK) {
        // Upload CV
        $uploadDir = ROOT_PATH . '/uploads/submissions/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = $submissionCode . '_cv_' . basename($_FILES['cv_file']['name']);
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['cv_file']['tmp_name'], $filePath)) {
            $documentsAttached[] = [
                'type' => 'cv',
                'path' => 'uploads/submissions/' . $fileName,
                'name' => $_FILES['cv_file']['name']
            ];
        }
    }
    
    $documentsJson = json_encode($documentsAttached);
    
    // Insert submission
    $sql = "
        INSERT INTO candidate_submissions (
            submission_code, candidate_code, job_code, client_code,
            submitted_by, submission_type,
            proposed_rate, rate_type, currency,
            availability_date, contract_duration,
            fit_reason, key_strengths, concerns,
            status, documents_attached,
            created_at, updated_at
        ) VALUES (
            ?, ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            ?, ?, ?,
            ?, ?,
            NOW(), NOW()
        )
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssdssisssss',
        $submissionCode, $candidateCode, $jobCode, $clientCode,
        $user['user_code'], $submissionType,
        $proposedRate, $rateType, $currency,
        $availabilityDate, $contractDuration,
        $fitReason, $keyStrengths, $concerns,
        $status, $documentsJson
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create submission: ' . $stmt->error);
    }
    
    $submissionId = $conn->insert_id;
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'create',
            'submissions',
            $submissionCode,
            "Created submission for {$candidateCode} to {$clientCode}"
        );
    }
    
    // Create initial note
    $initialNote = $saveAsDraft ? 'Submission saved as draft' : 'Submission created and sent for review';
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, 'general', ?, NOW())
    ");
    $stmt->bind_param('sss', $submissionCode, $initialNote, $user['user_code']);
    $stmt->execute();
    
    echo json_encode([
        'success' => true,
        'message' => $saveAsDraft ? 'Submission saved as draft' : 'Submission created successfully',
        'submission_id' => $submissionId,
        'submission_code' => $submissionCode
    ]);
    
} catch (Exception $e) {
    error_log('Submission create error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}