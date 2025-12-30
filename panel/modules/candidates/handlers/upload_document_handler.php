<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('candidates', 'upload_document');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $candidateCode = $_POST['candidate_code'] ?? null;
    $documentType = $_POST['document_type'] ?? 'other';
    
    if (!$candidateCode) {
        throw new Exception('Candidate code required');
    }
    
    // Check file uploaded
    if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Please select a file to upload');
    }
    
    $file = $_FILES['document'];
    
    // Validate file size (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception('File size must be less than 10MB');
    }
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'application/msword', 
                     'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                     'image/jpeg', 'image/png'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type. Only PDF, DOC, DOCX, JPG, PNG allowed');
    }
    
    // Create upload directory if doesn't exist
    $uploadDir = ROOT_PATH . '/uploads/candidates/' . $candidateCode . '/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $extension;
    $filePath = $uploadDir . $fileName;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }
    
    // Save to database
    $dbPath = '/uploads/candidates/' . $candidateCode . '/' . $fileName;
    $originalName = $file['name'];
    $uploadedBy = Auth::userCode();
    
    $stmt = $conn->prepare("
        INSERT INTO candidate_documents (can_code, document_type, file_name, file_path, uploaded_by)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssss", $candidateCode, $documentType, $originalName, $dbPath, $uploadedBy);
    $stmt->execute();
    
    // Log activity
    Logger::getInstance()->logActivity(
        'document_upload',
        'candidates',
        $candidateCode,
        "Uploaded document: {$originalName} ({$documentType})"
    );
    
    FlashMessage::success('Document uploaded successfully');
    redirect(BASE_URL . '/panel/modules/candidates/view.php?candidate_code=' . $candidateCode . '&tab=documents');
    
} catch (Exception $e) {
    FlashMessage::error('Upload failed: ' . $e->getMessage());
    back();
}