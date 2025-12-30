<?php
/**
 * Convert Submission to Client
 * File: panel/modules/submissions/handlers/convert-to-application.php
 * 
 * When client is interested, create application in pipeline
 */
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;
require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('submissions', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $submissionCode = $input['submission_code'] ?? '';
    
    if (empty($submissionCode)) {
        throw new Exception('Submission code is required');
    }
    
    // Get submission
    $stmt = $conn->prepare("
        SELECT s.*, j.job_id, c.can_code
        FROM candidate_submissions s
        LEFT JOIN jobs j ON s.job_code = j.job_code
        LEFT JOIN candidates c ON s.candidate_code = c.candidate_code
        WHERE s.submission_code = ? AND s.deleted_at IS NULL
    ");
    $stmt->bind_param('s', $submissionCode);
    $stmt->execute();
    $submission = $stmt->get_result()->fetch_assoc();
    
    if (!$submission) {
        throw new Exception('Submission not found');
    }
    
    if ($submission['client_response'] !== 'interested') {
        throw new Exception('Can only convert submissions where client is interested');
    }
    
    if ($submission['converted_to_application']) {
        throw new Exception('Submission has already been converted');
    }
    
    // Check if application already exists
    $stmt = $conn->prepare("
        SELECT application_id FROM job_applications 
        WHERE can_code = ? AND job_id = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param('si', $submission['can_code'], $submission['job_id']);
    $stmt->execute();
    $existingApp = $stmt->get_result()->fetch_assoc();
    
    if ($existingApp) {
        // Update submission to link to existing application
        $stmt = $conn->prepare("
            UPDATE candidate_submissions 
            SET 
                application_id = ?,
                converted_to_application = 1,
                updated_at = NOW()
            WHERE submission_code = ?
        ");
        $stmt->bind_param('is', $existingApp['application_id'], $submissionCode);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Linked to existing application',
            'application_id' => $existingApp['application_id']
        ]);
        exit;
    }
    
    // Create new application
    $stmt = $conn->prepare("
        INSERT INTO job_applications (
            can_code, job_id,
            status, application_date,
            source, referred_by,
            expected_salary, expected_salary_currency,
            notice_period, availability_date,
            cover_letter,
            created_by, created_at
        ) VALUES (
            ?, ?,
            'submitted', NOW(),
            'internal_submission', ?,
            ?, ?,
            NULL, ?,
            ?,
            ?, NOW()
        )
    ");
    
    $source = 'internal_submission';
    $expectedSalary = $submission['proposed_rate'];
    $currency = $submission['currency'];
    $coverLetter = "Submitted by: {$submission['submitted_by']}\n\nFit Reason:\n{$submission['fit_reason']}\n\nKey Strengths:\n{$submission['key_strengths']}";
    
    $stmt->bind_param(
        'sisdssss',
        $submission['can_code'],
        $submission['job_id'],
        $user['user_code'],
        $expectedSalary,
        $currency,
        $submission['availability_date'],
        $coverLetter,
        $user['user_code']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create application: ' . $stmt->error);
    }
    
    $applicationId = $conn->insert_id;
    
    // Update submission
    $stmt = $conn->prepare("
        UPDATE candidate_submissions 
        SET 
            application_id = ?,
            converted_to_application = 1,
            updated_at = NOW()
        WHERE submission_code = ?
    ");
    $stmt->bind_param('is', $applicationId, $submissionCode);
    $stmt->execute();
    
    // Add note to submission
    $noteText = "Converted to application #{$applicationId} - Now in recruitment pipeline";
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, 'internal', ?, NOW())
    ");
    $stmt->bind_param('sss', $submissionCode, $noteText, $user['user_code']);
    $stmt->execute();
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'convert',
            'submissions',
            $submissionCode,
            "Converted to application #{$applicationId}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Submission converted to application successfully',
        'application_id' => $applicationId
    ]);
    
} catch (Exception $e) {
    error_log('Conversion error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}