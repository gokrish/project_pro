<?php
/**
 * Parse Resume Handler
 * Handles AJAX resume upload and parsing
 */

require_once __DIR__ . '/../../_common.php';
require_once __DIR__ . '/../lib/ResumeParser.php';
require_once __DIR__ . '/../lib/SkillExtractor.php';

use ProConsultancy\Core\{Auth, Permission};
use ProConsultancy\Candidates\{ResumeParser, SkillExtractor};

header('Content-Type: application/json');

// Permission check
if (!Permission::can('candidates', 'create')) {
    echo json_encode(['success' => false, 'message' => 'No permission']);
    exit;
}

try {
    // Validate file upload
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('No file uploaded or upload error');
    }
    
    $file = $_FILES['resume'];
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowedExtensions = ['pdf', 'docx'];
    
    $fileType = $file['type'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($fileType, $allowedTypes) && !in_array($fileExt, $allowedExtensions)) {
        throw new \Exception('Invalid file type. Only PDF and DOCX are supported.');
    }
    
    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new \Exception('File too large. Maximum size is 5MB.');
    }
    
    // Create temp directory if not exists
    $tempDir = __DIR__ . '/../uploads/temp/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    // Generate unique filename
    $tempFile = $tempDir . uniqid('resume_') . '.' . $fileExt;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $tempFile)) {
        throw new \Exception('Failed to save uploaded file');
    }
    
    // Parse resume
    $parser = new ResumeParser($tempFile);
    $parseResult = $parser->parse();
    
    if (!$parseResult['success']) {
        throw new \Exception('Failed to parse resume: ' . $parseResult['error']);
    }
    
    // Extract skills
    $skillExtractor = new SkillExtractor($parser->getRawText());
    $skills = $skillExtractor->extract();
    
    // Clean up temp file
    @unlink($tempFile);
    
    // Return parsed data
    echo json_encode([
        'success' => true,
        'data' => [
            'name' => $parseResult['data']['name'] ?? '',
            'email' => $parseResult['data']['email'] ?? '',
            'phone' => $parseResult['data']['phone'] ?? '',
            'linkedin_url' => $parseResult['data']['linkedin_url'] ?? '',
            'location' => $parseResult['data']['location'] ?? 'Belgium',
            'skills' => $skills,
            'raw_text' => $parser->getRawText(),
            'parse_status' => $parseResult['parse_status']
        ],
        'message' => 'Resume parsed successfully. Please review and correct the information.'
    ]);
    
} catch (\Exception $e) {
    // Clean up temp file on error
    if (isset($tempFile) && file_exists($tempFile)) {
        @unlink($tempFile);
    }
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>