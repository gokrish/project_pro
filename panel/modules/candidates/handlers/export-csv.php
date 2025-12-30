<?php
/**
 * Export Candidates to CSV
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;

// Check permission
Permission::require('candidates', 'view');

// Get filters from query string
$filters = $_GET;

// Build WHERE clause (same as list.php)
$db = Database::getInstance();
$conn = $db->getConnection();

$whereConditions = ['1=1'];
$params = [];
$types = '';

// Row-level security
$accessFilter = Permission::getAccessibleCandidates();
if ($accessFilter) {
    $whereConditions[] = "({$accessFilter})";
}

// Apply all filters (same logic as list.php)
// Search, status, lead_type, assigned_to, etc.
if (!empty($filters['search'])) {
    $whereConditions[] = "(candidate_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $searchTerm = '%' . $filters['search'] . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

if (!empty($filters['status'])) {
    $whereConditions[] = "status = ?";
    $params[] = $filters['status'];
    $types .= 's';
}

if (!empty($filters['lead_type'])) {
    $whereConditions[] = "lead_type = ?";
    $params[] = $filters['lead_type'];
    $types .= 's';
}

// ... other filters ...

$whereClause = implode(' AND ', $whereConditions);

// Get candidates
$sql = "
    SELECT 
        c.candidate_code,
        c.candidate_name,
        c.email,
        c.phone,
        c.current_position,
        c.current_company,
        c.total_experience,
        c.skills,
        c.current_location,
        c.work_authorization_status,
        c.status,
        c.lead_type,
        c.rating,
        u.name as assigned_to_name,
        c.created_at,
        c.updated_at
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    WHERE {$whereClause}
    ORDER BY c.updated_at DESC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$candidates = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="candidates_export_' . date('Y-m-d_His') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write CSV headers
fputcsv($output, [
    'Candidate Code',
    'Name',
    'Email',
    'Phone',
    'Position',
    'Company',
    'Experience (Years)',
    'Skills',
    'Location',
    'Work Authorization',
    'Status',
    'Lead Type',
    'Rating',
    'Assigned To',
    'Created Date',
    'Last Updated'
]);

// Write data rows
foreach ($candidates as $candidate) {
    fputcsv($output, [
        $candidate['candidate_code'],
        $candidate['candidate_name'],
        $candidate['email'],
        $candidate['phone'],
        $candidate['current_position'] ?: '',
        $candidate['current_company'] ?: '',
        $candidate['total_experience'],
        $candidate['skills'] ?: '',
        $candidate['current_location'] ?: '',
        $candidate['work_authorization_status'],
        $candidate['status'],
        $candidate['lead_type'],
        $candidate['rating'],
        $candidate['assigned_to_name'] ?: 'Unassigned',
        date('Y-m-d H:i:s', strtotime($candidate['created_at'])),
        date('Y-m-d H:i:s', strtotime($candidate['updated_at']))
    ]);
}

fclose($output);

// Log export
Logger::getInstance()->logActivity('export', 'candidates', null, 'Exported candidates to CSV', [
    'count' => count($candidates),
    'filters' => $filters
]);

exit;