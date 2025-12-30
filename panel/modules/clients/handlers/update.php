<?php
/**
 * Client Update Handler
 * File: panel/modules/clients/handlers/update.php
 */

require_once __DIR__ . '/../../_common.php';

header('Content-Type: application/json');

if (!Auth::check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!Permission::can('clients', 'edit')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
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
    
    $clientCode = trim($_POST['client_code'] ?? '');
    
    if (empty($clientCode)) {
        throw new Exception('Client code is required');
    }
    
    // Verify client exists
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE client_code = ?");
    $stmt->bind_param('s', $clientCode);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Client not found');
    }
    
    // Validate fields
    $companyName = trim($_POST['company_name'] ?? '');
    $clientName = trim($_POST['client_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $accountManager = trim($_POST['account_manager'] ?? '');
    
    if (empty($companyName) || empty($clientName) || empty($email) || empty($accountManager)) {
        throw new Exception('Required fields are missing');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check email uniqueness (excluding current client)
    $stmt = $conn->prepare("SELECT client_id FROM clients WHERE email = ? AND client_code != ?");
    $stmt->bind_param('ss', $email, $clientCode);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception('Another client with this email already exists');
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
    
    // Update client
    $sql = "
        UPDATE clients SET
            company_name = ?,
            industry = ?,
            client_name = ?,
            contact_person = ?,
            email = ?,
            phone = ?,
            address = ?,
            city = ?,
            country = ?,
            notes = ?,
            status = ?,
            account_manager = ?,
            updated_at = NOW()
        WHERE client_code = ?
    ";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        'sssssssssssss',
        $companyName, $industry,
        $clientName, $contactPerson, $email, $phone,
        $address, $city, $country,
        $notes, $status, $accountManager,
        $clientCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update client: ' . $stmt->error);
    }
    
    // Log activity
    if (class_exists('Logger')) {
        Logger::getInstance()->logActivity(
            'update',
            'clients',
            $clientCode,
            "Updated client: {$companyName}"
        );
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Client updated successfully',
        'client_code' => $clientCode
    ]);
    
} catch (Exception $e) {
    error_log('Client update error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}