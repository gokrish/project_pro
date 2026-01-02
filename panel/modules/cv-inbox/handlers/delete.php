<?php
/**
 * Delete CV Handler
 * Supports both soft delete (default) and hard delete (permanent)
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

// Check permission
if (!Permission::can('cv_inbox', 'delete')) {
    ApiResponse::forbidden();
}

// Verify CSRF
if (!CSRFToken::verifyRequest()) {
    ApiResponse::error('Invalid CSRF token', 403);
}

try {
    $cvId = (int)input('cv_id');
    $permanent = (bool)input('permanent', false); // Add permanent flag
    
    if (!$cvId) {
        ApiResponse::error('CV ID is required', 400);
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get CV details
    $stmt = $conn->prepare("SELECT * FROM cv_inbox WHERE id = ?");
    $stmt->bind_param("i", $cvId);
    $stmt->execute();
    $cv = $stmt->get_result()->fetch_assoc();
    
    if (!$cv) {
        ApiResponse::error('CV not found', 404);
    }
    
    // Begin transaction
    $conn->begin_transaction();
    $user = Auth::user();
    try {
        if ($permanent) {
            // Hard delete (permanent removal)
            // Delete related notes
            $conn->query("DELETE FROM cv_notes WHERE cv_id = {$cvId}");
            
            // Delete CV
            $stmt = $conn->prepare("DELETE FROM cv_inbox WHERE id = ?");
            $stmt->bind_param("i", $cvId);
            $stmt->execute();
            
            // Delete resume file
            if (!empty($cv['resume_path']) && file_exists(ROOT_PATH . $cv['resume_path'])) {
                unlink(ROOT_PATH . $cv['resume_path']);
            }
            
            $message = 'CV permanently deleted';
        } else {
            // Soft delete (mark as deleted)
            $stmt = $conn->prepare("
                UPDATE cv_inbox 
                SET status = 'deleted',
                    deleted_by = ?,
                    deleted_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("si", $user, $cvId);
            $stmt->execute();
            
            $message = 'CV moved to trash';
        }
        
        // Commit
        $conn->commit();
        
        // Log activity
        Logger::getInstance()->logActivity(
            $permanent ? 'hard_delete' : 'soft_delete',
            'cv_inbox',
            $cvId,
            $permanent ? "Permanently deleted CV: {$cv['candidate_name']}" : "Soft deleted CV: {$cv['candidate_name']}"
        );
        
        ApiResponse::success(null, $message);
        
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('Delete CV failed', [
        'error' => $e->getMessage()
    ]);
    
    ApiResponse::error('Failed to delete CV', 500);
}