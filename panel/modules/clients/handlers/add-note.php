<?php
/**
 * Add Client Note Handler
 * File: panel/modules/clients/handlers/add-note.php
 */

require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    $note = trim($_POST['note'] ?? '');
    $noteType = trim($_POST['note_type'] ?? 'general');
    
    if (empty($clientCode) || empty($note)) {
        throw new Exception('Client code and note are required');
    }
    
    // Verify client exists
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Client not found');
    }
    
    // Insert note
    $stmt = $conn->prepare("
        INSERT INTO client_notes (client_code, note, note_type, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('ssss', $clientCode, $note, $noteType, $user['user_code']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add note');
    }
    
    $noteId = $conn->insert_id;
    
    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully',
        'note_id' => $noteId
    ]);
    
} catch (Exception $e) {
    error_log('Add note error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}