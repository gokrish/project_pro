<?php
require_once __DIR__ . '/../modules/_common.php';

use ProConsultancy\Core\{Database, Auth, Sanitizer};

header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    http_response_code(401);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

$query = $_GET['q'] ?? '';
$query = trim($query);

if (strlen($query) < 2) {
    die(json_encode(['success' => true, 'results' => []]));
}

$db = Database::getInstance();
$conn = $db->getConnection();

$results = [];
$searchTerm = '%' . $query . '%';

// Search Candidates
$candidateStmt = $conn->prepare("
    SELECT 
        candidate_code as code,
        name,
        email,
        phone,
        'candidate' as type
    FROM candidates
    WHERE deleted_at IS NULL
    AND (
        name LIKE ? OR
        email LIKE ? OR
        phone LIKE ? OR
        candidate_code LIKE ?
    )
    LIMIT 10
");
$candidateStmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$candidateStmt->execute();
$candidates = $candidateStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$results = array_merge($results, $candidates);

// Search Jobs
$jobStmt = $conn->prepare("
    SELECT 
        j.job_code as code,
        j.job_title as title,
        j.location,
        c.company_name as client,
        'job' as type
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.deleted_at IS NULL
    AND (
        j.job_title LIKE ? OR
        j.job_code LIKE ? OR
        j.job_refno LIKE ? OR
        c.company_name LIKE ?
    )
    LIMIT 10
");
$jobStmt->bind_param("ssss", $searchTerm, $searchTerm, $searchTerm, $searchTerm);
$jobStmt->execute();
$jobs = $jobStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$results = array_merge($results, $jobs);

// Search Clients
$clientStmt = $conn->prepare("
    SELECT 
        client_code as code,
        company_name as name,
        industry,
        'client' as type
    FROM clients
    WHERE deleted_at IS NULL
    AND (
        company_name LIKE ? OR
        client_code LIKE ? OR
        industry LIKE ?
    )
    LIMIT 10
");
$clientStmt->bind_param("sss", $searchTerm, $searchTerm, $searchTerm);
$clientStmt->execute();
$clients = $clientStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$results = array_merge($results, $clients);

echo json_encode([
    'success' => true,
    'results' => $results,
    'query' => $query
]);