<?php
/**
 * Candidate Search API for Select2
 * Returns JSON results
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\{Database, Auth, Permission};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('candidates', 'view_all') && !Permission::can('candidates', 'view_own')) {
    echo json_encode(['success' => false, 'message' => 'No permission', 'results' => []]);
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $searchTerm = $_GET['q'] ?? '';
    $statusFilter = $_GET['status'] ?? 'active,qualified,screening';
    
    if (strlen($searchTerm) < 2) {
        echo json_encode(['success' => true, 'results' => []]);
        exit;
    }
    
    // Build status filter
    $statuses = explode(',', $statusFilter);
    $statusPlaceholders = implode(',', array_fill(0, count($statuses), '?'));
    
    // Search query
    $sql = "
        SELECT 
            candidate_code,
            candidate_name,
            email,
            phone,
            current_position,
            status
        FROM candidates
        WHERE (
            candidate_name LIKE ?
            OR email LIKE ?
            OR phone LIKE ?
            OR candidate_code LIKE ?
        )
        AND status IN ($statusPlaceholders)
        AND deleted_at IS NULL
        ORDER BY candidate_name
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $searchPattern = "%$searchTerm%";
    $types = 'ssss' . str_repeat('s', count($statuses));
    $params = [$searchPattern, $searchPattern, $searchPattern, $searchPattern, ...$statuses];
    
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $results = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
    
} catch (\Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'results' => []
    ]);
}
?>