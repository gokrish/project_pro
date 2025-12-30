<?php
/**
 * Duplicate Check API
 * Check if email already exists
 */
require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');


$email = $_GET['email'] ?? '';
$exclude_code = $_GET['exclude'] ?? ''; // For edit mode

if (empty($email)) {
    echo json_encode(['exists' => false]);
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();

if ($exclude_code) {
    $stmt = $conn->prepare("
        SELECT can_code, candidate_name 
        FROM candidates 
        WHERE email_id = ? AND can_code != ? AND is_archived = 0
        LIMIT 1
    ");
    $stmt->bind_param("ss", $email, $exclude_code);
} else {
    $stmt = $conn->prepare("
        SELECT can_code, candidate_name 
        FROM candidates 
        WHERE email_id = ? AND is_archived = 0
        LIMIT 1
    ");
    $stmt->bind_param("s", $email);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $candidate = $result->fetch_assoc();
    echo json_encode([
        'exists' => true,
        'candidate' => $candidate
    ]);
} else {
    echo json_encode(['exists' => false]);
}

$stmt->close();
$conn->close();
?>
