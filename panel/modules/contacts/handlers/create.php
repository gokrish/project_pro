<?php
require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

Permission::require('contacts', 'create');

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    // Generate contact code
    $contactCode = 'CON-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Extract form data
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $linkedinUrl = trim($_POST['linkedin_url'] ?? '');
    
    $currentCompany = trim($_POST['current_company'] ?? '');
    $currentTitle = trim($_POST['current_title'] ?? '');
    $yearsExperience = !empty($_POST['years_of_experience']) ? (float)$_POST['years_of_experience'] : null;
    $currentLocation = trim($_POST['current_location'] ?? '');
    $summary = trim($_POST['summary'] ?? '');
    
    // Skills (convert comma-separated to JSON)
    $skillsInput = trim($_POST['skills'] ?? '');
    $skillsArray = $skillsInput ? array_map('trim', explode(',', $skillsInput)) : [];
    $skills = json_encode($skillsArray);
    
    $source = $_POST['source'];
    $sourceDetails = trim($_POST['source_details'] ?? '');
    $priority = $_POST['priority'] ?? 'medium';
    $status = $_POST['status'] ?? 'new';
    $nextFollowUp = !empty($_POST['next_follow_up']) ? $_POST['next_follow_up'] : null;
    $assignedTo = $_POST['assigned_to'] ?? Auth::userCode();
    $createdBy = Auth::userCode();
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($source)) {
        throw new Exception('Required fields missing');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check for duplicate email
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE email = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] > 0) {
        throw new Exception('A contact with this email already exists');
    }
    
    // Insert
    $stmt = $conn->prepare("
        INSERT INTO contacts (
            contact_code, first_name, last_name, email, phone, linkedin_url,
            current_company, current_title, years_of_experience, current_location,
            skills, summary, status, source, source_details, priority,
            next_follow_up, assigned_to, created_by, created_at
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW()
        )
    ");
    
    $stmt->bind_param(
        "ssssssssdssssssssss",
        $contactCode, $firstName, $lastName, $email, $phone, $linkedinUrl,
        $currentCompany, $currentTitle, $yearsExperience, $currentLocation,
        $skills, $summary, $status, $source, $sourceDetails, $priority,
        $nextFollowUp, $assignedTo, $createdBy
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to create contact: ' . $stmt->error);
    }
    
    Logger::getInstance()->logActivity('create', 'contacts', $contactCode, "Created contact: {$firstName} {$lastName}");
    
    FlashMessage::success('Contact created successfully!');
    redirect(BASE_URL . '/panel/modules/contacts/view.php?contact_code=' . $contactCode);
    
} catch (Exception $e) {
    FlashMessage::error('Failed to create contact: ' . $e->getMessage());
    $_SESSION['old'] = $_POST;
    back();
}
