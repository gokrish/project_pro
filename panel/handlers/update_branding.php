<?php
require_once __DIR__ . '/../includes/_common.php';

header('Content-Type: application/json');

// Check admin permission
if (!in_array(Auth::user()['level'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    $userId = Auth::userId();
    
    // Handle file uploads
    $uploadDir = ROOT_PATH . '/panel/assets/img/branding/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Process logo upload
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] === UPLOAD_ERR_OK) {
        $logoPath = $uploadDir . 'logo_' . time() . '.png';
        move_uploaded_file($_FILES['company_logo']['tmp_name'], $logoPath);
        
        $logoUrl = '/panel/assets/img/branding/' . basename($logoPath);
        updateSetting('company_logo_url', $logoUrl);
    }
    
    // Process favicon upload
    if (isset($_FILES['favicon']) && $_FILES['favicon']['error'] === UPLOAD_ERR_OK) {
        $faviconPath = $uploadDir . 'favicon.ico';
        move_uploaded_file($_FILES['favicon']['tmp_name'], $faviconPath);
        
        updateSetting('company_favicon_url', '/panel/assets/img/branding/favicon.ico');
    }
    
    // Process background upload
    if (isset($_FILES['login_background']) && $_FILES['login_background']['error'] === UPLOAD_ERR_OK) {
        $bgPath = $uploadDir . 'login_bg_' . time() . '.jpg';
        move_uploaded_file($_FILES['login_background']['tmp_name'], $bgPath);
        
        $bgUrl = '/panel/assets/img/branding/' . basename($bgPath);
        updateSetting('login_background_image', $bgUrl);
    }
    
    // Update text settings
    $textSettings = [
        'company_name',
        'company_tagline',
        'theme_primary_color',
        'theme_secondary_color',
        'login_background_color',
        'footer_text'
    ];
    
    foreach ($textSettings as $key) {
        if (isset($_POST[$key])) {
            updateSetting($key, $_POST[$key]);
        }
    }
    
    // Clear branding cache
    Branding::clearCache(); // Add this method to Branding class
    
    echo json_encode([
        'success' => true,
        'message' => 'Branding updated successfully. Refresh page to see changes.'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function updateSetting($key, $value) {
    global $conn, $userId;
    
    $stmt = $conn->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_by)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            setting_value = VALUES(setting_value),
            updated_by = VALUES(updated_by),
            updated_at = NOW()
    ");
    $stmt->bind_param('ssi', $key, $value, $userId);
    $stmt->execute();
}
?>