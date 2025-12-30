<?php
namespace ProConsultancy\Core;

/**
 * API Response Handler
 * Standardizes all API/AJAX responses
 * 
 * @version 5.0
 * @package ProConsultancy\Core
 */
class ApiResponse {
    
    /**
     * Send success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $code HTTP status code
     * @return void (exits after sending)
     */
    public static function success($data = [], string $message = 'Success', int $code = 200): void {
        self::send([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }
    
    /**
     * Send error response
     * 
     * @param string $message Error message
     * @param int $code HTTP status code
     * @param mixed $details Additional error details
     * @return void (exits after sending)
     */
    public static function error(string $message, int $code = 400, $details = []): void {
        self::send([
            'success' => false,
            'message' => $message,
            'details' => $details,
            'timestamp' => date('Y-m-d H:i:s')
        ], $code);
    }
 
    public static function validationError(array $errors): void {
        self::validation($errors);
    }   
    /**
     * Send validation error response
     * 
     * @param array $errors Validation errors
     * @return void (exits after sending)
     */
    public static function validation(array $errors): void {
        self::error('Validation failed', 422, ['errors' => $errors]);
    }
    
    /**
     * Send forbidden response (403)
     * 
     * @param string $message Custom message
     * @return void (exits after sending)
     */
    public static function forbidden(string $message = 'Access denied'): void {
        self::error($message, 403);
    }
    
    /**
     * Send not found response (404)
     * 
     * @param string $message Custom message
     * @return void (exits after sending)
     */
    public static function notFound(string $message = 'Resource not found'): void {
        self::error($message, 404);
    }
    
    /**
     * Send unauthorized response (401)
     * 
     * @param string $message Custom message
     * @return void (exits after sending)
     */
    public static function unauthorized(string $message = 'Unauthorized'): void {
        self::error($message, 401);
    }
    
    /**
     * Send server error response (500)
     * 
     * @param string $message Error message
     * @param mixed $details Error details (only in debug mode)
     * @return void (exits after sending)
     */
    public static function serverError(string $message = 'Internal server error', $details = []): void {
        // Only include details in debug mode
        $config = require __DIR__ . '/../config/app.php';
        if (!$config['app_debug']) {
            $details = [];
        }
        
        self::error($message, 500, $details);
    }
    
    /**
     * Send created response (201)
     * 
     * @param mixed $data Created resource data
     * @param string $message Success message
     * @return void (exits after sending)
     */
    public static function created($data = [], string $message = 'Resource created successfully'): void {
        self::success($data, $message, 201);
    }
    
    /**
     * Send no content response (204)
     * 
     * @return void (exits after sending)
     */
    public static function noContent(): void {
        http_response_code(204);
        exit;
    }
    
    /**
     * Send paginated response
     * 
     * @param array $items Items array
     * @param int $total Total count
     * @param int $perPage Items per page
     * @param int $currentPage Current page
     * @param string $message Optional message
     * @return void (exits after sending)
     */
    public static function paginated(
        array $items, 
        int $total, 
        int $perPage, 
        int $currentPage, 
        string $message = 'Success'
    ): void {
        $totalPages = (int) ceil($total / $perPage);
        
        self::success([
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $currentPage,
                'total_pages' => $totalPages,
                'from' => (($currentPage - 1) * $perPage) + 1,
                'to' => min($currentPage * $perPage, $total)
            ]
        ], $message);
    }
    
    /**
     * Core send method
     * 
     * @param array $data Response data
     * @param int $code HTTP status code
     * @return void (exits after sending)
     */
    private static function send(array $data, int $code = 200): void {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
        
        // Add CORS headers if needed
        $config = require __DIR__ . '/../config/app.php';
        if ($config['api_cors_enabled'] ?? false) {
            header('Access-Control-Allow-Origin: ' . ($config['api_cors_origin'] ?? '*'));
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization');
        }
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
        // Log API response in debug mode
        if ($config['app_debug'] ?? false) {
                Logger::getInstance()->logActivity(
                    'APICALL',
                    'APIResponse',
                    $code,
                    'API',
                    [
                        'success' => $data['success'] ?? false,
                        'message' => $data['message'] ?? '',
                        'endpoint' => $_SERVER['REQUEST_URI'] ?? '',
                        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
                    ],
                    'warning'
                );
        }
        
        exit;
    }
}