<?php
/**
 * Close Job Handler
 * Closes job with reason tracking
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('jobs', 'edit')) {
    echo ApiResponse::forbidden();
    exit;
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    echo ApiResponse::error('Invalid CSRF token', 403);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $jobCode = $input['job_code'] ?? '';
    $closeReason = $input['close_reason'] ?? '';
    $closeNotes = $input['close_notes'] ?? '';
    
    if (empty($jobCode)) {
        echo ApiResponse::validationError(['job_code' => 'Job code is required']);
        exit;
    }
    
    if (empty($closeReason)) {
        echo ApiResponse::validationError(['close_reason' => 'Please select a reason']);
        exit;
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Get job
    $stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ?");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    $job = $stmt->get_result()->fetch_assoc();
    
    if (!$job) {
        echo ApiResponse::error('Job not found', 404);
        exit;
    }
    
    // Update job
    $stmt = $conn->prepare("
        UPDATE jobs 
        SET status = 'closed',
            accept_applications = 0,
            updated_at = NOW()
        WHERE job_code = ?
    ");
    $stmt->bind_param("s", $jobCode);
    $stmt->execute();
    
    // Add close reason to internal notes
    $reasonText = ucwords(str_replace('_', ' ', $closeReason));
    $closeNote = "\n\n--- Job Closed ---\nDate: " . date('Y-m-d H:i:s') . 
                 "\nReason: {$reasonText}" .
                 ($closeNotes ? "\nNotes: {$closeNotes}" : "") .
                 "\nClosed by: {$user['name']}";
    
    $stmt = $conn->prepare("
        UPDATE jobs 
        SET internal_notes = CONCAT(COALESCE(internal_notes, ''), ?)
        WHERE job_code = ?
    ");
    $stmt->bind_param("ss", $closeNote, $jobCode);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'close',
        'jobs',
        $jobCode,
        "Closed job: {$job['job_title']}",
        [
            'reason' => $closeReason,
            'notes' => $closeNotes
        ]
    );
    
    // Notify assigned recruiter
    if ($job['assigned_to']) {
        Notification::send(
            $job['assigned_to'],
            'job_closed',
            'Job Closed',
            "Job '{$job['job_title']}' has been closed",
            'jobs',
            $jobCode
        );
    }
    
    echo ApiResponse::success(null, 'Job closed successfully!');
    
} catch (Exception $e) {
    Logger::getInstance()->error('Close job failed', [
        'error' => $e->getMessage()
    ]);
    
    echo ApiResponse::error('Failed to close job', 500);
}