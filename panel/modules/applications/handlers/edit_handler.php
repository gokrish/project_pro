<?php
/**
 * Edit Application Handler
 * File: panel/modules/applications/handlers/edit_handler.php
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
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
Permission::require('applications', 'edit');

// Validate CSRF token
if (!CSRFToken::verifyRequest()) {
    FlashMessage::error('Invalid security token');
    header('Location: ../index.php?page=list');
    exit();
}

try {
    // Get application ID
    $appId = $_POST['application_id'] ?? null;
    
    if (!$appId) {
        FlashMessage::error('Application ID is required');
        header('Location: ../index.php?page=list');
        exit();
    }
    
    // Validate input
    $validator = new Validator($_POST, [
        'candidate_code' => 'required',
        'job_code' => 'required',
        'status' => 'required|in:applied,screening,interviewing,offered,placed,rejected,withdrawn',
        'application_date' => 'date',
        'expected_salary' => 'numeric',
        'offered_salary' => 'numeric',
        'notice_period' => 'integer',
        'availability_date' => 'date'
    ]);
    
    if (!$validator->validate()) {
        $_SESSION['errors'] = $validator->errors();
        $_SESSION['old'] = $_POST;
        header('Location: ../edit.php?id=' . $appId);
        exit();
    }
    
    $data = $validator->validated();
    
    // Get database connection
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check if application exists
    $checkStmt = $conn->prepare("
        SELECT id, created_by, client_code 
        FROM applications 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $checkStmt->bind_param("i", $appId);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    
    if (!$existing) {
        FlashMessage::error('Application not found');
        header('Location: ../index.php?page=list');
        exit();
    }
    
    // Check ownership if user has only edit_own permission
    if (!Permission::can('applications', 'edit')) {
        if (Permission::can('applications', 'edit_own')) {
            if ($existing['created_by'] !== Auth::userId()) {
                header('Location: /panel/errors/403.php');
                exit();
            }
        } else {
            header('Location: /panel/errors/403.php');
            exit();
        }
    }
    
    // Get client_code from job
    $jobStmt = $conn->prepare("SELECT client_code FROM jobs WHERE job_code = ?");
    $jobStmt->bind_param("s", $data['job_code']);
    $jobStmt->execute();
    $jobResult = $jobStmt->get_result()->fetch_assoc();
    
    if (!$jobResult) {
        FlashMessage::error('Invalid job selected');
        header('Location: ../edit.php?id=' . $appId);
        exit();
    }
    
    $clientCode = $jobResult['client_code'];
    
    // Verify candidate exists
    $candidateStmt = $conn->prepare("SELECT can_code FROM candidates WHERE can_code = ? AND deleted_at IS NULL");
    $candidateStmt->bind_param("s", $data['candidate_code']);
    $candidateStmt->execute();
    if ($candidateStmt->get_result()->num_rows === 0) {
        FlashMessage::error('Invalid candidate selected');
        header('Location: ../edit.php?id=' . $appId);
        exit();
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Prepare update query
        $updateQuery = "
            UPDATE applications SET
                candidate_code = ?,
                job_code = ?,
                client_code = ?,
                status = ?,
                application_date = ?,
                expected_salary = ?,
                offered_salary = ?,
                notice_period = ?,
                availability_date = ?,
                notes = ?,
                updated_at = NOW(),
                updated_by = ?
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($updateQuery);
        
        // Bind parameters
        $updatedBy = Auth::userId();
        $stmt->bind_param(
            "sssssddiissi",
            $data['candidate_code'],
            $data['job_code'],
            $clientCode,
            $data['status'],
            $data['application_date'] ?? null,
            $data['expected_salary'] ?? null,
            $data['offered_salary'] ?? null,
            $data['notice_period'] ?? null,
            $data['availability_date'] ?? null,
            $data['notes'] ?? null,
            $updatedBy,
            $appId
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update application: ' . $stmt->error);
        }
        
        // Log activity
        Logger::getInstance()->info('Application updated', [
            'application_id' => $appId,
            'candidate_code' => $data['candidate_code'],
            'job_code' => $data['job_code'],
            'status' => $data['status'],
            'updated_by' => Auth::user()['email']
        ]);
        
        // Insert activity log entry
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (
                user_id, user_code, module, action, entity_type, entity_id,
                description, ip_address, user_agent
            ) VALUES (?, ?, 'applications', 'update', 'application', ?,
                      ?, ?, ?)
        ");
        
        $description = "Updated application for candidate {$data['candidate_code']} - Status: {$data['status']}";
        $userId = Auth::user()['id'];
        $userCode = Auth::userId();
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        $activityStmt->bind_param(
            "isisss",
            $userId,
            $userCode,
            $appId,
            $description,
            $ipAddress,
            $userAgent
        );
        $activityStmt->execute();
        
        // Commit transaction
        $conn->commit();
        
        FlashMessage::success('Application updated successfully');
        header('Location: ../view.php?id=' . $appId);
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Application update error', [
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    FlashMessage::error('An error occurred while updating the application. Please try again.');
    
    if (isset($appId)) {
        header('Location: ../edit.php?id=' . $appId);
    } else {
        header('Location: ../index.php?page=list');
    }
    exit();
}