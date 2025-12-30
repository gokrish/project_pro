<?php
/**
 * Flash Messages Component
 * Display session-based flash messages
 * 
 * HYBRID VERSION: Supports BOTH old Session-based and new FlashMessage class
 * 
 * @version 5.1 (Backward Compatible)
 */

use ProConsultancy\Core\Session;
use ProConsultancy\Core\FlashMessage;

// ============================================================================
// NEW SYSTEM: FlashMessage class (if available)
// ============================================================================
if (class_exists('ProConsultancy\Core\FlashMessage')) {
    if (FlashMessage::has()) {
        echo FlashMessage::render();
    }
}

// ============================================================================
// OLD SYSTEM: Session-based flash messages (backward compatibility)
// ============================================================================
$messageTypes = ['success', 'error', 'warning', 'info'];

foreach ($messageTypes as $type) {
    if (Session::has("flash_{$type}")) {
        $message = Session::get("flash_{$type}");
        
        // Icon mapping for Bootstrap Icons
        $iconMap = [
            'success' => 'bx-check-circle',
            'error' => 'bx-error-circle',
            'warning' => 'bx-error',
            'info' => 'bx-info-circle'
        ];
        $icon = $iconMap[$type] ?? 'bx-info-circle';
        
        // Alert class mapping
        $alertClass = ($type === 'error') ? 'alert-danger' : "alert-{$type}";
        
        echo <<<HTML
        <div class="alert {$alertClass} alert-dismissible fade show" role="alert">
            <i class="bx {$icon} me-2"></i>
            {$message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        HTML;
        
        // Clear the flash message after displaying
        Session::remove("flash_{$type}");
    }
}
?>