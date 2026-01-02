<?php
/**
 * Search Skills API
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../../_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\ApiResponse;

// Only allow AJAX requests
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    ApiResponse::error('Invalid request');
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Get search query
    $query = trim($_GET['q'] ?? '');
    $category = trim($_GET['category'] ?? '');
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $limit = min($limit, 50); // Max 50 results
    
    // Build WHERE clause
    $whereConditions = ['is_active = 1'];
    $params = [];
    $types = '';
    
    // Search by skill name
    if (!empty($query)) {
        $whereConditions[] = "skill_name LIKE ?";
        $searchTerm = '%' . $query . '%';
        $params[] = $searchTerm;
        $types .= 's';
    }
    
    // Filter by category
    if (!empty($category)) {
        $whereConditions[] = "skill_category = ?";
        $params[] = $category;
        $types .= 's';
    }
    
    $whereClause = implode(' AND ', $whereConditions);
    
    // Get skills
    $sql = "
        SELECT 
            id,
            skill_name,
            skill_category,
            usage_count
        FROM technical_skills
        WHERE {$whereClause}
        ORDER BY 
            CASE 
                WHEN skill_name LIKE ? THEN 1
                ELSE 2
            END,
            usage_count DESC,
            skill_name ASC
        LIMIT {$limit}
    ";
    
    $stmt = $conn->prepare($sql);
    
    // Add exact match parameter for sorting
    $exactMatch = !empty($query) ? $query . '%' : '%';
    
    if (!empty($params)) {
        $params[] = $exactMatch;
        $types .= 's';
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('s', $exactMatch);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $skills = [];
    while ($row = $result->fetch_assoc()) {
        $skills[] = [
            'id' => (int)$row['id'],
            'text' => $row['skill_name'], // For Select2 compatibility
            'name' => $row['skill_name'],
            'category' => $row['skill_category'],
            'usage_count' => (int)$row['usage_count']
        ];
    }
    
    // Return results in Select2 format
    ApiResponse::success([
        'results' => $skills,
        'pagination' => [
            'more' => false // Could implement pagination if needed
        ]
    ]);
    
} catch (Exception $e) {
    Logger::getInstance()->error('Skill search failed', [
        'error' => $e->getMessage(),
        'query' => $query ?? null
    ]);
    
    ApiResponse::serverError('Search failed', [
        'error' => $e->getMessage()
    ]);
}
