<?php
/**
 * Get Application Statistics (API)
 */

require_once __DIR__ . '/../_common.php';

header('Content-Type: application/json');

try {
    $conn = dbConnect();
    
    $filter = $_GET['filter'] ?? 'all'; // all, my, today, week, month
    $user = Auth::user();
    
    // Base query
    $whereConditions = ["ja.deleted_at IS NULL"];
    
    if ($filter === 'my' && $user['level'] !== 'admin') {
        $whereConditions[] = "ja.created_by = '{$user['user_code']}'";
    }
    
    if ($filter === 'today') {
        $whereConditions[] = "DATE(ja.created_at) = CURDATE()";
    }
    
    if ($filter === 'week') {
        $whereConditions[] = "YEARWEEK(ja.created_at) = YEARWEEK(NOW())";
    }
    
    if ($filter === 'month') {
        $whereConditions[] = "YEAR(ja.created_at) = YEAR(NOW()) AND MONTH(ja.created_at) = MONTH(NOW())";
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get counts by status
    $statusQuery = "
        SELECT status, COUNT(*) as count
        FROM job_applications ja
        WHERE $whereClause
        GROUP BY status
    ";
    
    $result = mysqli_query($conn, $statusQuery);
    
    $stats = [
        'total' => 0,
        'by_status' => []
    ];
    
    while ($row = mysqli_fetch_assoc($result)) {
        $stats['by_status'][$row['status']] = (int)$row['count'];
        $stats['total'] += (int)$row['count'];
    }
    
    // Recent activity
    $activityQuery = "
        SELECT ja.application_id, ja.status, ja.created_at,
               c.candidate_name, j.title as job_title
        FROM job_applications ja
        JOIN candidates c ON ja.can_code = c.can_code
        JOIN jobs j ON ja.job_id = j.job_id
        WHERE $whereClause
        ORDER BY ja.created_at DESC
        LIMIT 5
    ";
    
    $activityResult = mysqli_query($conn, $activityQuery);
    $stats['recent'] = mysqli_fetch_all($activityResult, MYSQLI_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $stats
    ]);
    
} catch (Exception $e) {
    error_log('Get stats error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>