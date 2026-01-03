<?php
/**
 * Update Contact Handler
 * Same as create.php but UPDATE instead of INSERT
 */

require_once __DIR__ . '/../../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\FlashMessage;

// Check permission
if (!Permission::can('contacts', 'edit.all') && !Permission::can('contacts', 'edit.own')) {
    throw new PermissionException('You cannot edit contacts.');
}

if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    FlashMessage::error('Invalid security token');
    back();
}

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $contactCode = $_POST['contact_code'] ?? null;
    
    if (!$contactCode) {
        throw new Exception('Contact code is required');
    }
    
    // Fetch existing contact
    $stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_code = ? AND deleted_at IS NULL");
    $stmt->bind_param("s", $contactCode);
    $stmt->execute();
    $existingContact = $stmt->get_result()->fetch_assoc();
    
    if (!$existingContact) {
        throw new Exception('Contact not found');
    }
    
    // Check ownership for edit.own
    if (!Permission::can('contacts', 'edit.all')) {
        if ($existingContact['assigned_to'] !== Auth::userCode()) {
            throw new PermissionException('You can only edit contacts assigned to you');
        }
    }
    
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
    $assignedTo = $_POST['assigned_to'] ?? $existingContact['assigned_to'];
    
    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($source)) {
        throw new Exception('Required fields missing');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Check for duplicate email (excluding current contact)
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM contacts WHERE email = ? AND contact_code != ? AND deleted_at IS NULL");
    $stmt->bind_param("ss", $email, $contactCode);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result['count'] > 0) {
        throw new Exception('A contact with this email already exists');
    }
    
    // Update contact
    $stmt = $conn->prepare("
        UPDATE contacts SET
            candidate_name = ?,
            email = ?,
            phone = ?,
            linkedin_url = ?,
            current_company = ?,
            current_title = ?,
            current_location = ?,
            skills = ?,
            summary = ?,
            status = ?,
            source = ?,
            source_details = ?,
            priority = ?,
            next_follow_up = ?,
            assigned_to = ?,
            updated_at = NOW()
        WHERE contact_code = ?
    ");
    
    $stmt->bind_param(
        "sssssssdssssssssss",
        $firstName, $lastName, $email, $phone, $linkedinUrl,
        $currentCompany, $currentTitle, $yearsExperience, $currentLocation,
        $skills, $summary, $status, $source, $sourceDetails, $priority,
        $nextFollowUp, $assignedTo, $contactCode
    );
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update contact: ' . $stmt->error);
    }
    
    Logger::getInstance()->logActivity('update', 'contacts', $contactCode, "Updated contact: {$firstName} {$lastName}");
    
    FlashMessage::success('Contact updated successfully!');
    redirect(BASE_URL . '/panel/modules/contacts/view.php?contact_code=' . $contactCode);
    
} catch (Exception $e) {
    FlashMessage::error('Failed to update contact: ' . $e->getMessage());
    $_SESSION['old'] = $_POST;
    back();
}
