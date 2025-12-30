<?php
/**
 * Client Delete Handler (Soft Delete)
 * File: panel/modules/clients/handlers/delete.php
 */

require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('clients', 'delete')) {
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
    
    $clientCode = trim($_POST['client_code'] ?? '');
    
    if (empty($clientCode)) {
        throw new Exception('Client code is required');
    }
    
    // Get client details
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    
    if (!$client) {
        throw new Exception('Client not found');
    }
    
    // Check for active jobs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM jobs 
        WHERE client_code = ? 
        AND status = 'open' 
        AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
    ");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    $activeJobs = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($activeJobs > 0) {
        throw new Exception("Cannot delete client with {$activeJobs} active job(s). Please close jobs first.");
    }
    
    // Soft delete: Set status to inactive
    $stmt = $conn->prepare("UPDATE clients SET status = 'inactive', updated_at = NOW() WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete client');
    }
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'delete',
            'clients',
            $clientCode,
            "Deleted (deactivated) client: {$client['company_name']}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Client deactivated successfully'
    ]);
    
} catch (Exception $e) {
    error_log('Client delete error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}