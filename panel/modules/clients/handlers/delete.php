<?php
/**
 * Delete Client Handler (Soft Delete)
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('clients', 'delete');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $client_code = input('client_code');
    $user = Auth::user();
    
    if (empty($client_code)) {
        throw new Exception('Client code is required');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check client exists
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_code = ?");
    $stmt->bind_param("s", $client_code);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    
    if (!$client) {
        throw new Exception('Client not found');
    }
    
    // Check for active jobs
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM jobs 
        WHERE client_code = ? AND status IN ('open', 'filling')
    ");
    $stmt->bind_param("s", $client_code);
    $stmt->execute();
    $activeJobs = $stmt->get_result()->fetch_assoc()['count'];
    
    if ($activeJobs > 0) {
        throw new Exception("Cannot delete client with {$activeJobs} active job(s). Please close or delete jobs first.");
    }
    
    // Soft delete
    $stmt = $conn->prepare("
        UPDATE clients
        SET deleted_at = NOW()
        WHERE client_code = ?
    ");
    $stmt->bind_param("s", $client_code);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to delete client: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'delete',
        'clients',
        $client_code,
        "Client deleted: {$client['company_name']}",
        ['deleted_by' => $user['user_code']]
    );
    
    redirectWithMessage(
        "/panel/modules/clients/?action=list",
        'Client deleted successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Client deletion failed', [
        'error' => $e->getMessage(),
        'client_code' => $client_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to delete client: ' . $e->getMessage());
}