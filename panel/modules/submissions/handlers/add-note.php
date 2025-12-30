<?php
/**
 * Add Note to Submission
 * File: panel/modules/submissions/handlers/add-note.php
 */

require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
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
    
    $submissionCode = trim($_POST['submission_code'] ?? '');
    $note = trim($_POST['note'] ?? '');
    $noteType = $_POST['note_type'] ?? 'general';
    
    if (empty($submissionCode) || empty($note)) {
        throw new Exception('Submission code and note are required');
    }
    
    // Verify submission exists
    $stmt = $conn->prepare("SELECT submission_id FROM candidate_submissions WHERE submission_code = ?");
    $stmt->bind_param('s', $submissionCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        throw new Exception('Submission not found');
    }
    
    // Insert note
    $stmt = $conn->prepare("
        INSERT INTO submission_notes (submission_code, note, note_type, created_by, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->bind_param('ssss', $submissionCode, $note, $noteType, $user['user_code']);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to add note');
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Note added successfully',
        'note_id' => $conn->insert_id
    ]);
    
} catch (Exception $e) {
    error_log('Add note error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}