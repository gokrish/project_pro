<?php
/**
 * Edit Submission Handler
 * File: panel/modules/submissions/handlers/edit.php
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Check permission
Permission::require('submissions', 'edit');

// Validate CSRF token
if (!CSRFToken::verifyRequest()) {
    FlashMessage::error('Invalid security token');
    header('Location: ../index.php');
    exit();
}

try {
    // Get submission ID
    $submissionId = $_POST['submission_id'] ?? null;
    
    if (!$submissionId) {
        FlashMessage::error('Submission ID is required');
        header('Location: ../index.php');
        exit();
    }
    
    // Validate input
    $validator = new Validator($_POST);

    $validator->validate([
        'candidate_code' => 'required',
        'job_code' => 'required',
        'client_code' => 'required',
        'status' => 'required|in:pending_review,approved,rejected,submitted_to_client,client_reviewing,client_accepted,client_rejected',
        'submission_date' => 'date',
        'candidate_availability' => 'date',
        'notice_period' => 'integer'
    ]);

    if ($validator->fails()) {
        $_SESSION['errors'] = $validator->errors();
        $_SESSION['old'] = $_POST;
        header('Location: ../edit.php?id=' . $submissionId);
        exit();
}

    $data = $validator->validated();
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if submission exists
    $checkStmt = $conn->prepare("
        SELECT id, submitted_by, status 
        FROM candidate_submissions 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $checkStmt->bind_param("i", $submissionId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if (!$existing) {
        FlashMessage::error('Submission not found');
        header('Location: ../index.php');
        exit();
    }
    
    // Check ownership if user has only edit_own permission
    if (!Permission::can('submissions', 'edit')) {
        if (Permission::can('submissions', 'edit_own')) {
            if ($existing['submitted_by'] !== Auth::id()) {
                header('Location: /panel/errors/403.php');
                exit();
            }
        } else {
            header('Location: /panel/errors/403.php');
            exit();
        }
    }
    
    // If status is being changed to approved/rejected, check approve permission
    if (in_array($data['status'], ['approved', 'rejected']) && $existing['status'] === 'pending_review') {
        Permission::require('submissions', 'approve');
    }
    
    // Verify candidate exists
    $candidateStmt = $conn->prepare("SELECT can_code FROM candidates WHERE can_code = ? AND deleted_at IS NULL");
    $candidateStmt->bind_param("s", $data['candidate_code']);
    $candidateStmt->execute();
    if ($candidateStmt->get_result()->num_rows === 0) {
        FlashMessage::error('Invalid candidate selected');
        header('Location: ../edit.php?id=' . $submissionId);
        exit();
    }
    
    // Verify job exists
    $jobStmt = $conn->prepare("SELECT job_code FROM jobs WHERE job_code = ? AND deleted_at IS NULL");
    $jobStmt->bind_param("s", $data['job_code']);
    $jobStmt->execute();
    if ($jobStmt->get_result()->num_rows === 0) {
        FlashMessage::error('Invalid job selected');
        header('Location: ../edit.php?id=' . $submissionId);
        exit();
    }
    
    // Verify client exists
    $clientStmt = $conn->prepare("SELECT client_code FROM clients WHERE client_code = ? AND deleted_at IS NULL");
    $clientStmt->bind_param("s", $data['client_code']);
    $clientStmt->execute();
    if ($clientStmt->get_result()->num_rows === 0) {
        FlashMessage::error('Invalid client selected');
        header('Location: ../edit.php?id=' . $submissionId);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Prepare update query
        $updateQuery = "
            UPDATE candidate_submissions SET
                candidate_code = ?,
                job_code = ?,
                client_code = ?,
                status = ?,
                submission_date = ?,
                proposed_salary = ?,
                candidate_availability = ?,
                notice_period = ?,
                cover_letter = ?,
                why_good_fit = ?,
                internal_notes = ?,
                updated_at = NOW(),
                updated_by = ?
        ";
        
        // Add approval fields if status changed to approved
        if ($data['status'] === 'approved' && $existing['status'] === 'pending_review') {
            $updateQuery .= ", approved_by = ?, approved_at = NOW()";
        }
        
        // Add rejection fields if status changed to rejected
        if ($data['status'] === 'rejected' && $existing['status'] === 'pending_review') {
            $updateQuery .= ", rejected_by = ?, rejected_at = NOW(), rejection_reason = ?";
        }
        
        $updateQuery .= " WHERE id = ?";
        
        $stmt = $conn->prepare($updateQuery);
        
        // Bind basic parameters
        $updatedBy = Auth::Id();
        $params = [
            $data['candidate_code'],
            $data['job_code'],
            $data['client_code'],
            $data['status'],
            $data['submission_date'] ?? null,
            $data['proposed_salary'] ?? null,
            $data['candidate_availability'] ?? null,
            $data['notice_period'] ?? null,
            $data['cover_letter'] ?? null,
            $data['why_good_fit'] ?? null,
            $data['internal_notes'] ?? null,
            $updatedBy
        ];
        
        $types = "ssssddissss";
        
        // Add approval/rejection parameters
        if ($data['status'] === 'approved' && $existing['status'] === 'pending_review') {
            $params[] = $updatedBy;
            $types .= "s";
        } elseif ($data['status'] === 'rejected' && $existing['status'] === 'pending_review') {
            $params[] = $updatedBy;
            $params[] = $data['rejection_reason'] ?? 'Not specified';
            $types .= "ss";
        }
        
        $params[] = $submissionId;
        $types .= "i";
        
        $stmt->bind_param($types, ...$params);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update submission: ' . $stmt->error);
        }
        
        // Log activity
        // Logger::getInstance()->info('Submission updated', [
        //     'submission_id' => $submissionId,
        //     'candidate_code' => $data['candidate_code'],
        //     'job_code' => $data['job_code'],
        //     'status' => $data['status'],
        //     'updated_by' => Auth::user()['email']
        // ]);
        
        // Insert activity log entry
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (
                user_id, user_code, module, action, entity_type, entity_id,
                description, ip_address, user_agent
            ) VALUES (?, ?, 'submissions', 'update', 'submission', ?,
                      ?, ?, ?)
        ");
        
        $description = "Updated submission for candidate {$data['candidate_code']} - Status: {$data['status']}";
        $userId = Auth::user()['id'];
        $userCode = Auth::Id();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $activityStmt->bind_param(
            "isisss",
            $userId,
            $userCode,
            $submissionId,
            $description,
            $ipAddress,
            $userAgent
        );
        $activityStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        FlashMessage::success('Submission updated successfully');
        header('Location: ../view.php?id=' . $submissionId);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Submission update error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    FlashMessage::error('An error occurred while updating the submission. Please try again.');
    
    if (isset($submissionId)) {
        header('Location: ../edit.php?id=' . $submissionId);
    } else {
        header('Location: ../index.php');
    }
    exit();
}