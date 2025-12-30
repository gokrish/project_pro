<?php
require_once __DIR__ . '/../_common.php';
header('Content-Type: application/json');



$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

try {
    if ($action === 'schedule') {
        $can_code = $_POST['can_code'];
        $date = $_POST['date'];
        $notes = $_POST['notes'];
        $logged_by = Auth::user()['user_code'];
        
        $stmt = $conn->prepare("INSERT INTO candidate_interviews (can_code, interview_date, notes, logged_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $can_code, $date, $notes, $logged_by);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
    } // Add update, cancel, etc.
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>