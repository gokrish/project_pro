<?php
/**
 * Helper Functions
 * Common utility functions used throughout the application
 * 
 * @package ProConsultancy
 * @version 3.0
 */

// ============================================================================
// FLASH MESSAGES
// ============================================================================

if (!function_exists('getFlashMessage')) {
    /**
     * Get and clear flash message
     * @return array|null
     */
    function getFlashMessage() {
        if (!isset($_SESSION['flash_message'])) {
            return null;
        }
        
        $message = [
            'message' => $_SESSION['flash_message'],
            'type' => $_SESSION['flash_type'] ?? 'info'
        ];
        
        unset($_SESSION['flash_message'], $_SESSION['flash_type']);
        
        return $message;
    }
}

if (!function_exists('setFlashMessage')) {
    /**
     * Set flash message for next request
     * @param string $message
     * @param string $type (success, error, warning, info)
     */
    function setFlashMessage($message, $type = 'info') {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
}

// ============================================================================
// URL & REDIRECT HELPERS
// ============================================================================

if (!function_exists('redirectWithMessage')) {
    /**
     * Redirect with flash message
     * @param string $url
     * @param string $message
     * @param string $type
     */
    function redirectWithMessage($url, $message, $type = 'info') {
        setFlashMessage($message, $type);
        header("Location: {$url}");
        exit;
    }
}

if (!function_exists('redirectBack')) {
    /**
     * Redirect to previous page
     * @param string $message
     * @param string $type
     */
    function redirectBack($message = '', $type = 'error') {
        $referer = $_SERVER['HTTP_REFERER'] ?? '/panel/dashboard.php';
        if (!empty($message)) {
            redirectWithMessage($referer, $message, $type);
        } else {
            header("Location: {$referer}");
            exit;
        }
    }
}

if (!function_exists('redirect')) {
    /**
     * Simple redirect
     * @param string $url
     */
    function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}

// ============================================================================
// INPUT HELPERS
// ============================================================================

if (!function_exists('input')) {
    /**
     * Get input from GET, POST, or REQUEST
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function input($key, $default = '') {
        return $_REQUEST[$key] ?? $_GET[$key] ?? $_POST[$key] ?? $default;
    }
}

if (!function_exists('inputInt')) {
    /**
     * Get integer input
     * @param string $key
     * @param int $default
     * @return int
     */
    function inputInt($key, $default = 0) {
        return (int)(input($key, $default));
    }
}

if (!function_exists('inputArray')) {
    /**
     * Get array input
     * @param string $key
     * @param array $default
     * @return array
     */
    function inputArray($key, $default = []) {
        $value = input($key, $default);
        return is_array($value) ? $value : $default;
    }
}

// ============================================================================
// OUTPUT ESCAPING
// ============================================================================

if (!function_exists('escape')) {
    /**
     * Escape HTML output
     * @param string $string
     * @return string
     */
    function escape($string) {
        return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('e')) {
    /**
     * Short alias for escape
     * @param string $string
     * @return string
     */
    function e($string) {
        return escape($string);
    }
}

// ============================================================================
// DATE & TIME FORMATTING
// ============================================================================

if (!function_exists('formatDate')) {
    /**
     * Format date
     * @param string $date
     * @param string $format
     * @return string
     */
    function formatDate($date, $format = 'd/m/Y') {
        if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Format datetime
     * @param string $datetime
     * @param string $format
     * @return string
     */
    function formatDateTime($datetime, $format = 'd/m/Y H:i') {
        if (!$datetime || $datetime === '0000-00-00 00:00:00') {
            return '-';
        }
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('timeAgo')) {
    /**
     * Convert timestamp to "time ago" format
     * @param string $datetime
     * @return string
     */
    function timeAgo($datetime) {
        if (!$datetime || $datetime === '0000-00-00 00:00:00') {
            return 'Never';
        }
        
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return formatDate($datetime);
        }
    }
}

// ============================================================================
// FORMATTING HELPERS
// ============================================================================

if (!function_exists('formatMoney')) {
    /**
     * Format money amount
     * @param float $amount
     * @param string $currency
     * @return string
     */
    function formatMoney($amount, $currency = 'EUR') {
        $symbols = [
            'EUR' => '€',
            'USD' => '$',
            'GBP' => '£'
        ];
        
        $symbol = $symbols[$currency] ?? $currency;
        return $symbol . number_format($amount, 2, '.', ',');
    }
}

if (!function_exists('formatNumber')) {
    /**
     * Format number with thousands separator
     * @param int|float $number
     * @param int $decimals
     * @return string
     */
    function formatNumber($number, $decimals = 0) {
        return number_format($number, $decimals, '.', ',');
    }
}

// ============================================================================
// STATUS & BADGE HELPERS
// ============================================================================

if (!function_exists('getStatusBadge')) {
    /**
     * Get Bootstrap badge class for status
     * @param string $status
     * @return string
     */
    function getStatusBadge($status) {
        $badges = [
            // General
            'active' => 'success',
            'inactive' => 'secondary',
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            
            // Jobs
            'draft' => 'secondary',
            'pending_approval' => 'warning',
            'open' => 'info',
            'filling' => 'primary',
            'filled' => 'success',
            'closed' => 'dark',
            'cancelled' => 'danger',
            
            // Candidates
            'new' => 'info',
            'screening' => 'warning',
            'qualified' => 'success',
            'placed' => 'success',
            
            // Submissions
            'not_sent' => 'secondary',
            'submitted' => 'info',
            'interviewing' => 'primary',
            'offered' => 'warning',
        ];
        
        return $badges[$status] ?? 'secondary';
    }
}

if (!function_exists('getStatusLabel')) {
    /**
     * Get human-readable status label
     * @param string $status
     * @return string
     */
    function getStatusLabel($status) {
        return ucfirst(str_replace('_', ' ', $status));
    }
}

// ============================================================================
// VALIDATION HELPERS
// ============================================================================

if (!function_exists('isEmail')) {
    /**
     * Validate email
     * @param string $email
     * @return bool
     */
    function isEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('isPhone')) {
    /**
     * Validate phone number
     * @param string $phone
     * @return bool
     */
    function isPhone($phone) {
        return preg_match('/^\+?[0-9\s\-\(\)]{8,20}$/', $phone);
    }
}

if (!function_exists('isUrl')) {
    /**
     * Validate URL
     * @param string $url
     * @return bool
     */
    function isUrl($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}

// ============================================================================
// PERMISSION HELPERS
// ============================================================================

if (!function_exists('can')) {
    /**
     * Check if user has permission
     * @param string $module
     * @param string $action
     * @return bool
     */
    function can($module, $action) {
        return \ProConsultancy\Core\Permission::can($module, $action);
    }
}

if (!function_exists('cannot')) {
    /**
     * Check if user does NOT have permission
     * @param string $module
     * @param string $action
     * @return bool
     */
    function cannot($module, $action) {
        return !\ProConsultancy\Core\Permission::can($module, $action);
    }
}

// ============================================================================
// CODE GENERATION HELPERS
// ============================================================================

if (!function_exists('generateCode')) {
    /**
     * Generate unique code
     * @param string $prefix
     * @return string
     */
    function generateCode($prefix = 'GEN') {
        return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -4));
    }
}

// ============================================================================
// STRING HELPERS
// ============================================================================

if (!function_exists('truncate')) {
    /**
     * Truncate string to specified length
     * @param string $string
     * @param int $length
     * @param string $append
     * @return string
     */
    function truncate($string, $length = 100, $append = '...') {
        if (strlen($string) <= $length) {
            return $string;
        }
        return substr($string, 0, $length) . $append;
    }
}

if (!function_exists('slug')) {
    /**
     * Create URL-friendly slug
     * @param string $string
     * @return string
     */
    function slug($string) {
        $string = strtolower($string);
        $string = preg_replace('/[^a-z0-9]+/', '-', $string);
        return trim($string, '-');
    }
}

// ============================================================================
// ARRAY HELPERS
// ============================================================================

if (!function_exists('arrayGet')) {
    /**
     * Get value from array using dot notation
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function arrayGet($array, $key, $default = null) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        
        return $array;
    }
}

// ============================================================================
// FILE HELPERS
// ============================================================================

if (!function_exists('uploadFile')) {
    /**
     * Handle file upload
     * @param array $file $_FILES array element
     * @param string $destination Directory path
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Max size in bytes
     * @return string|false New filename or false on failure
     */
    function uploadFile($file, $destination, $allowedTypes = [], $maxSize = 5242880) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return false;
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            return false;
        }
        
        // Check file type
        if (!empty($allowedTypes) && !in_array($file['type'], $allowedTypes)) {
            return false;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $filepath = $destination . '/' . $filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return $filename;
        }
        
        return false;
    }
}

// ============================================================================
// DEBUG HELPERS
// ============================================================================

if (!function_exists('dd')) {
    /**
     * Dump and die
     * @param mixed $var
     */
    function dd($var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
        die();
    }
}

if (!function_exists('dump')) {
    /**
     * Dump variable
     * @param mixed $var
     */
    function dump($var) {
        echo '<pre>';
        var_dump($var);
        echo '</pre>';
    }
}

// ============================================================================
// PAGINATION HELPER
// ============================================================================

if (!function_exists('paginate')) {
    /**
     * Generate pagination links
     * @param int $currentPage
     * @param int $totalPages
     * @param string $baseUrl
     * @param int $range
     * @return string HTML
     */
    function paginate($currentPage, $totalPages, $baseUrl, $range = 2) {
        if ($totalPages <= 1) return '';
        
        $html = '<nav><ul class="pagination">';
        
        // Previous button
        if ($currentPage > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">Previous</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Previous</span></li>';
        }
        
        // Page numbers
        for ($i = max(1, $currentPage - $range); $i <= min($totalPages, $currentPage + $range); $i++) {
            if ($i === $currentPage) {
                $html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
            } else {
                $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
            }
        }
        
        // Next button
        if ($currentPage < $totalPages) {
            $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">Next</a></li>';
        } else {
            $html .= '<li class="page-item disabled"><span class="page-link">Next</span></li>';
        }
        
        $html .= '</ul></nav>';
        
        return $html;
    }
}