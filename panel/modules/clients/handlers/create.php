<?php
/**
 * Client Create Handler
 * File: panel/modules/clients/handlers/create.php
 */

require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
header('Content-Type: application/json');

// Check authentication
if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check permission
if (!Permission::can('clients', 'create')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

// Verify CSRF
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $user = Auth::user();
    
    // Validate required fields
    $clientCode = trim($_POST['client_code'] ?? '');
    $companyName = trim($_POST['company_name'] ?? '');
    $clientName = trim($_POST['client_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $accountManager = trim($_POST['account_manager'] ?? '');
    
    if (empty($companyName)) {
        throw new Exception('Company name is required');
    }
    
    if (empty($clientName)) {
        throw new Exception('Contact name is required');
    }
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Valid email is required');
    }
    
    if (empty($accountManager)) {
        throw new Exception('Account manager is required');
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE email = ?");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('A client with this email already exists');
    }
    
    // Ensure unique client code
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $clientCode = 'CLI-' . date('YmdHis') . '-' . rand(100, 999);
    }
    
    // Optional fields
    $industry = trim($_POST['industry'] ?? '');
    $contactPerson = trim($_POST['contact_person'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $country = trim($_POST['country'] ?? 'Belgium');
    $notes = trim($_POST['notes'] ?? '');
    $status = $_POST['status'] ?? 'active';
    
    // Insert client
    $sql = "
        INSERT INTO clients (
            client_code, company_name, industry,
            client_name, contact_person, email, phone,
            address, city, country,
            notes, status, account_manager,
            created_by, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'ssssssssssssss',
        $clientCode, $companyName, $industry,
        $clientName, $contactPerson, $email, $phone,
        $address, $city, $country,
        $notes, $status, $accountManager,
        $user['user_code']
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create client: ' . $stmt->error);
    }
    
    $clientId = $conn->insert_id;
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'create',
            'clients',
            $clientCode,
            "Created client: {$companyName}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Client created successfully',
        'client_id' => $clientId,
        'client_code' => $clientCode
    ]);
    
} catch (Exception $e) {
    error_log('Client create error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}