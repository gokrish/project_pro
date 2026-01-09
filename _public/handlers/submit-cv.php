<?php
/**
 * Direct CV Submission Handler
 * Handles CV uploads from public pages (not job-specific)
 */

require_once __DIR__ . '/../../panel/modules/_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Logger;

header('Content-Type: application/json');

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Validate inputs
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
    $mobile = filter_input(INPUT_POST, 'mobile', FILTER_SANITIZE_STRING);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $interest = filter_input(INPUT_POST, 'interest', FILTER_SANITIZE_STRING);
    
    if (!$name || !$mobile || !$email) {
        throw new \Exception('Please fill all required fields');
    }
    
    // Handle file upload
    if (!isset($_FILES['cv_file']) || $_FILES['cv_file']['error'] !== UPLOAD_ERR_OK) {
        throw new \Exception('Please upload your CV');
    }
    
    $file = $_FILES['cv_file'];
    
    // Validate file type
    $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $allowedExtensions = ['pdf', 'docx'];
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file['type'], $allowedTypes) && !in_array($fileExt, $allowedExtensions)) {
        throw new \Exception('Please upload a PDF or DOCX file');
    }
    
    // Validate file size (5MB max)
    if ($file['size'] > 5 * 1024 * 1024) {
        throw new \Exception('File size must be less than 5MB');
    }
    
    // Create upload directory
    $uploadDir = __DIR__ . '/../../uploads/cv-inbox/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $file['name']);
    $filepath = $uploadDir . $filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        throw new \Exception('Failed to save file');
    }
    
    // Save to cv_inbox table
    $stmt = $conn->prepare("
        INSERT INTO cv_inbox (
            applicant_name,
            applicant_email,
            cover_letter,
            resume_filename,
            resume_path,
            source,
            job_code,
            status,
            received_at
        ) VALUES (?, ?, ?, ?, ?, 'website_direct', NULL, 'pending', NOW())
    ");
    
    $relativePath = '/uploads/cv-inbox/' . $filename;
    
    $stmt->bind_param("ssssss",
        $name,
        $email,
        $mobile,
        $interest,
        $filename,
        $relativePath
    );
    
    if (!$stmt->execute()) {
        throw new \Exception('Failed to save application');
    }
    
    // Send confirmation email to applicant
    sendCVConfirmationEmail($email, $name, $mobile, $interest, $relativePath);
    
    // Notify admin/recruiter
    notifyAdminNewCV($name, $email, $mobile, $interest, $relativePath);
    
    // Log activity
    Logger::getInstance()->logActivity('cv_submission', 'cv_inbox', null, 
        "Direct CV submission from $name ($email)");
    
    echo json_encode([
        'success' => true,
        'message' => 'CV submitted successfully'
    ]);
    
} catch (\Exception $e) {
    // Clean up uploaded file on error
    if (isset($filepath) && file_exists($filepath)) {
        @unlink($filepath);
    }
    
    Logger::getInstance()->logError('cv_submission_error', $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

/**
 * Send confirmation email to applicant
 */
function sendCVConfirmationEmail($email, $name, $mobile, $interest, $cvPath)
{
    // Load email template
    $template = file_get_contents(__DIR__ . '/../../panel/email-templates/cv_direct.php');
    
    // Replace placeholders
    $template = str_replace('{{user_name}}', htmlspecialchars($name), $template);
    $template = str_replace('{{user_mobile}}', htmlspecialchars($mobile), $template);
    $template = str_replace('{{user_email}}', htmlspecialchars($email), $template);
    $template = str_replace('{{user_interest}}', htmlspecialchars($interest ?: 'General Application'), $template);
    $template = str_replace('{{user_file}}', htmlspecialchars($cvPath), $template);
    
    // Send email
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Pro Consultancy <admin@proconsultancy.be>' . "\r\n";
    
    mail($email, 'Thank you for submitting your CV - Pro Consultancy', $template, $headers);
}

/**
 * Notify admin of new CV
 */
function notifyAdminNewCV($name, $email, $mobile, $interest, $cvPath)
{
    $adminEmail = 'admin@proconsultancy.be';
    
    $message = "
    <h3>New CV Submission Received</h3>
    <p><strong>Name:</strong> $name</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>Mobile:</strong> $mobile</p>
    <p><strong>Interest:</strong> " . ($interest ?: 'General Application') . "</p>
    <p><strong>CV:</strong> <a href='https://proconsultancy.be/panel$cvPath'>View CV</a></p>
    <p><a href='https://proconsultancy.be/panel/modules/cv-inbox/'>View in CV Inbox</a></p>
    ";
    
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Website <no-reply@proconsultancy.be>' . "\r\n";
    
    mail($adminEmail, 'New CV Submission - ' . $name, $message, $headers);
}
?>