<?php
namespace ProConsultancy\Core;

use ProConsultancy\Core\Database;
class Branding {
    
    private static $settings = null;
    
    /**
     * Load all branding settings
     */
    private static function loadSettings() {
        if (self::$settings !== null) {
            return;
        }
        
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $query = "SELECT setting_key, setting_value 
                  FROM system_settings 
                  WHERE setting_category = 'branding'";
        $result = $conn->query($query);
        
        self::$settings = [];
        while ($row = $result->fetch_assoc()) {
            self::$settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    /**
     * Get branding setting
     */
    public static function get($key, $default = '') {
        self::loadSettings();
        return self::$settings[$key] ?? $default;
    }
    
    /**
     * Get company name
     */
    public static function companyName() {
        return self::get('company_name', 'ProConsultancy');
    }
    
    /**
     * Get company logo URL
     */
    public static function logoUrl() {
        return self::get('company_logo_url', '/panel/assets/img/logo.png');
    }
    
    /**
     * Get primary color
     */
    public static function primaryColor() {
        return self::get('theme_primary_color', '#696cff');
    }
    
    /**
     * Get all colors as CSS variables
     */
    public static function getCSSVariables() {
        self::loadSettings();
        
        return "
            :root {
                --bs-primary: " . self::get('theme_primary_color', '#696cff') . ";
                --bs-secondary: " . self::get('theme_secondary_color', '#8592a3') . ";
                --bs-success: " . self::get('theme_success_color', '#71dd37') . ";
                --bs-danger: " . self::get('theme_danger_color', '#ff3e1d') . ";
                --bs-warning: " . self::get('theme_warning_color', '#ffab00') . ";
                --bs-info: " . self::get('theme_info_color', '#03c3ec') . ";
            }
        ";
    }
    
    /**
     * Get login page styles
     */
    public static function getLoginStyles() {
        $bgImage = self::get('login_background_image', '');
        $bgColor = self::get('login_background_color', '#f5f5f9');
        
        if ($bgImage) {
            return "background-image: url('{$bgImage}'); background-size: cover; background-position: center;";
        } else {
            return "background-color: {$bgColor};";
        }
    }
}
?>