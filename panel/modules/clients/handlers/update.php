<?php
/**
 * Update Client Handler
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('clients', 'edit');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $client_code = input('client_code');
    $company_name = input('company_name');
    $contact_person = input('contact_person', '');
    $email = input('email', '');
    $phone = input('phone', '');
    $status = input('status', 'active');
    $account_manager = input('account_manager', '');
    $notes = input('notes', '');
    
    $user = Auth::user();
    
    // Validation
    if (empty($client_code) || empty($company_name)) {
        throw new Exception('Client code and company name are required');
    }
    
    if (!in_array($status, ['active', 'inactive'])) {
        throw new Exception('Invalid status');
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check client exists
    $stmt = $conn->prepare("SELECT * FROM clients WHERE client_code = ?");
    $stmt->bind_param("s", $client_code);
    $stmt->execute();
    $oldClient = $stmt->get_result()->fetch_assoc();
    
    if (!$oldClient) {
        throw new Exception('Client not found');
    }
    
    // Update client
    $stmt = $conn->prepare("
        UPDATE clients
        SET company_name = ?,
            contact_person = ?,
            email = ?,
            phone = ?,
            status = ?,
            account_manager = ?,
            notes = ?,
            updated_at = NOW()
        WHERE client_code = ?
    ");
    
    $stmt->bind_param("ssssssss",
        $company_name, $contact_person, $email, $phone,
        $status, $account_manager, $notes, $client_code
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update client: ' . $conn->error);
    }
    
    // Log activity
    $changes = [];
    if ($oldClient['company_name'] !== $company_name) $changes['company_name'] = ['from' => $oldClient['company_name'], 'to' => $company_name];
    if ($oldClient['status'] !== $status) $changes['status'] = ['from' => $oldClient['status'], 'to' => $status];
    if ($oldClient['account_manager'] !== $account_manager) $changes['account_manager'] = ['from' => $oldClient['account_manager'], 'to' => $account_manager];
    
    Logger::getInstance()->info(
        'clients',
        'update',
        $clientCode,
        "Client information updated",
        ['fields_changed' => $changedFields]
    );
    
    redirectWithMessage(
        "/panel/modules/clients/?action=view&code={$client_code}",
        'Client updated successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Client update failed', [
        'error' => $e->getMessage(),
        'client_code' => $client_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to update client: ' . $e->getMessage());
}