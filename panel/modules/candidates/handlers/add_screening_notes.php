<?php
require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('candidates', 'edit');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $candidateCode = $_POST['candidate_code'] ?? null;
    $interestLevel = $_POST['interest_level'] ?? 'medium';
    $availability = trim($_POST['availability'] ?? '');
    $salaryExpectation = trim($_POST['salary_expectation'] ?? '');
    $screeningNotes = trim($_POST['screening_notes'] ?? '');
    $nextFollowUp = $_POST['next_follow_up'] ?? null;
    
    if (!$candidateCode) {
        throw new Exception('Candidate code required');
    }
    
    if (empty($screeningNotes)) {
        throw new Exception('Screening notes are required');
    }
    
    // Verify candidate exists
    $stmt = $conn->prepare("
        SELECT candidate_name, status 
        FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $candidate = $stmt->get_result()->fetch_assoc();
    
    if (!$candidate) {
        throw new Exception('Candidate not found');
    }
    
    $conn->begin_transaction();
    
    // Update candidate with screening info
    $stmt = $conn->prepare("
        UPDATE candidates SET
            screening_notes = ?,
            interest_level = ?,
            availability = ?,
            salary_expectation = ?,
            next_follow_up_date = ?,
            last_contacted_date = CURDATE(),
            status = CASE 
                WHEN status = 'new' THEN 'contacted'
                ELSE status 
            END,
            updated_at = NOW()
        WHERE candidate_code = ?
    ");
    
    $stmt->bind_param("ssssss", 
        $screeningNotes, $interestLevel, $availability, 
        $salaryExpectation, $nextFollowUp, $candidateCode
    );
    $stmt->execute();
    
    // Log to activity
    Logger::getInstance()->logActivity(
        'screening_notes',
        'candidates',
        $candidateCode,
        'Added screening notes after contact',
        [
            'interest_level' => $interestLevel,
            'availability' => $availability,
            'salary_expectation' => $salaryExpectation,
            'auto_status_change' => ($candidate['status'] === 'new')
        ]
    );
    
    $conn->commit();
    
    FlashMessage::success('Screening notes added successfully!');
    
    // If status auto-changed to "contacted", show message
    if ($candidate['status'] === 'new') {
        FlashMessage::info('Candidate status automatically updated to "Contacted"');
    }
    
    redirect(BASE_URL . '/panel/modules/candidates/view.php?code=' . $candidateCode);
    
} catch (Exception $e) {
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    FlashMessage::error('Failed to save screening notes: ' . $e->getMessage());
    back();
}