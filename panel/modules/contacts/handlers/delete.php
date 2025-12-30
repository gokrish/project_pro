<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Logger;

Permission::require('contacts', 'delete');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token', 403);
}

$db = Database::getInstance();
$conn = $db->getConnection();

$contactCode = $_POST['contact_code'] ?? null;

if (!$contactCode) {
    ApiResponse::error('Contact code is required', 400);
}

try {
    // Soft delete
    $stmt = $conn->prepare("UPDATE contacts SET deleted_at = NOW() WHERE contact_code = ?");
    $stmt->bind_param("s", $contactCode);
    
    if ($stmt->execute()) {
        Logger::getInstance()->logActivity('delete', 'contacts', $contactCode, 'Contact deleted');
        ApiResponse::success(['contact_code' => $contactCode], 'Contact deleted successfully');
    } else {
        ApiResponse::error('Failed to delete contact', 500);
    }
    
} catch (Exception $e) {
    ApiResponse::error($e->getMessage(), 500);
}
