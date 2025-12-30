<?php
/**
 * Add Client Note Handler
 */
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

Permission::require('clients', 'view');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('Invalid request method');
}

if (!CSRFToken::verifyRequest()) {
    redirectBack('Invalid security token');
}

try {
    $client_code = input('client_code');
    $content = input('content');
    $note_type = input('note_type', 'general');
    $is_important = input('is_important', 0);
    
    $user = Auth::user();
    
    // Validation
    if (empty($client_code) || empty($content)) {
        throw new Exception('Client code and note content are required');
    }
    
    $validTypes = ['general', 'call', 'meeting', 'email', 'followup'];
    if (!in_array($note_type, $validTypes)) {
        $note_type = 'email';
    }
    
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Check client exists
    $stmt = $conn->prepare("SELECT company_name FROM clients WHERE client_code = ?");
    $stmt->bind_param("s", $client_code);
    $stmt->execute();
    $client = $stmt->get_result()->fetch_assoc();
    
    if (!$client) {
        throw new Exception('Client not found');
    }
    
    // Insert note
    $stmt = $conn->prepare("
        INSERT INTO notes (
            entity_type, entity_code, note_type, content, 
            is_important, created_by, created_at
        ) VALUES ('client', ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->bind_param("sssss",
        $client_code, $note_type, $content, $is_important, $user['user_code']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add note: ' . $conn->error);
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'add_note',
        'clients',
        $client_code,
        "Note added to client: {$client['company_name']}",
        ['note_type' => $note_type, 'created_by' => $user['user_code']]
    );
    
    redirectWithMessage(
        "/panel/modules/clients/?action=view&code={$client_code}",
        'Note added successfully',
        'success'
    );
    
} catch (Exception $e) {
    Logger::getInstance()->error('Add note failed', [
        'error' => $e->getMessage(),
        'client_code' => $client_code ?? null,
        'user' => $user['user_code'] ?? null
    ]);
    
    redirectBack('Failed to add note: ' . $e->getMessage());
}