<?php
/**
 * User Save Handler
 * Handles both creating and editing users
 * 
 * @package ProConsultancy
 * @version 5.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\ApiResponse;

// Check permission
Permission::require('users', 'create');

// Get request data
$action = $_POST['action'] ?? 'create';
$userId = $_POST['user_id'] ?? null;

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // Validate CSRF token
    if (!isset($_POST['token']) || $_POST['token'] !== Auth::token()) {
        throw new Exception('Invalid security token');
    }
    
    // Validation rules
    $rules = [
        'name' => 'required|max:100',
        'email' => 'required|email|max:255',
        'level' => 'required|in:super_admin,admin,manager,senior_recruiter,recruiter,coordinator',
        'phone' => 'max:20',
        'department' => 'max:100',
        'position' => 'max:100'
    ];
    
    // Add password validation for new users
    if ($action === 'create') {
        $rules['password'] = 'required|min:8';
        $rules['password_confirmation'] = 'required|same:password';
    } elseif (!empty($_POST['password'])) {
        // Only validate password if provided for edit
        $rules['password'] = 'min:8';
        $rules['password_confirmation'] = 'required|same:password';
    }
    
    // Validate input
    $validator = new Validator($_POST, $rules);
    
    if (!$validator->validate()) {
        echo ApiResponse::error('Validation failed', $validator->errors());
        exit;
    }
    
    // Get validated data
    $data = $validator->validated();
    
    // Check if email already exists (exclude current user for edit)
    $emailCheckQuery = "SELECT id FROM users WHERE email = ?";
    $emailCheckParams = [$data['email']];
    
    if ($action === 'edit' && $userId) {
        $emailCheckQuery .= " AND id != ?";
        $emailCheckParams[] = $userId;
    }
    
    $stmt = $conn->prepare($emailCheckQuery);
    $stmt->bind_param(str_repeat('s', count($emailCheckParams)), ...$emailCheckParams);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo ApiResponse::error('Email address already exists');
        exit;
    }
    
    // Get role_id from role code (level)
    $roleStmt = $conn->prepare("SELECT id FROM roles WHERE role_code = ?");
    $roleStmt->bind_param("s", $data['level']);
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    $role = $roleResult->fetch_assoc();
    
    if (!$role) {
        echo ApiResponse::error('Invalid role selected');
        exit;
    }
    
    $roleId = $role['id'];
    
    if ($action === 'create') {
        // Generate unique user code
        $userCode = 'USR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check if code exists
        $codeCheckStmt = $conn->prepare("SELECT id FROM users WHERE user_code = ?");
        $codeCheckStmt->bind_param("s", $userCode);
        $codeCheckStmt->execute();
        
        while ($codeCheckStmt->get_result()->num_rows > 0) {
            $userCode = 'USR-' . date('Ymd') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $codeCheckStmt->bind_param("s", $userCode);
            $codeCheckStmt->execute();
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        
        // Insert new user
        $insertQuery = "
            INSERT INTO users (
                user_code, role_id, name, full_name, email, password, level, 
                phone, department, position, is_active, created_by, 
                password_changed_at, email_verified_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW(), NOW())
        ";
        
        $stmt = $conn->prepare($insertQuery);
        $currentUserCode = Auth::userId();
        
        $stmt->bind_param(
            "sisssssssss",
            $userCode,
            $roleId,
            $data['name'],
            $data['name'], // full_name same as name
            $data['email'],
            $hashedPassword,
            $data['level'],
            $data['phone'] ?? null,
            $data['department'] ?? null,
            $data['position'] ?? null,
            $currentUserCode
        );
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create user: ' . $stmt->error);
        }
        
        $newUserId = $conn->insert_id;
        
        // Log activity
        Logger::getInstance()->info('User created', [
            'user_code' => $userCode,
            'email' => $data['email'],
            'level' => $data['level'],
            'created_by' => Auth::user()['email']
        ]);
        
        // Log to activity_log table
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (
                user_id, user_code, module, action, entity_type, entity_id, 
                description, ip_address, user_agent
            ) VALUES (?, ?, 'users', 'create', 'user', ?, ?, ?, ?)
        ");
        
        $description = "Created new user: {$data['name']} ({$data['email']})";
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $currentUserId = Auth::user()['id'];
        
        $activityStmt->bind_param(
            "isssss",
            $currentUserId,
            $currentUserCode,
            $userCode,
            $description,
            $ipAddress,
            $userAgent
        );
        $activityStmt->execute();
        
        echo ApiResponse::success('User created successfully', [
            'user_id' => $newUserId,
            'user_code' => $userCode,
            'redirect' => 'index.php?action=list'
        ]);
        
    } else {
        // Edit existing user
        Permission::require('users', 'edit');
        
        if (!$userId) {
            echo ApiResponse::error('User ID is required');
            exit;
        }
        
        // Build update query
        $updateFields = [
            'name = ?',
            'full_name = ?',
            'email = ?',
            'level = ?',
            'role_id = ?',
            'phone = ?',
            'department = ?',
            'position = ?'
        ];
        
        $updateParams = [
            $data['name'],
            $data['name'], // full_name
            $data['email'],
            $data['level'],
            $roleId,
            $data['phone'] ?? null,
            $data['department'] ?? null,
            $data['position'] ?? null
        ];
        
        $paramTypes = 'ssssisss';
        
        // Add password if provided
        if (!empty($data['password'])) {
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
            $updateFields[] = 'password = ?';
            $updateFields[] = 'password_changed_at = NOW()';
            $updateParams[] = $hashedPassword;
            $paramTypes .= 's';
        }
        
        $updateParams[] = $userId;
        $paramTypes .= 'i';
        
        $updateQuery = "
            UPDATE users 
            SET " . implode(', ', $updateFields) . "
            WHERE id = ?
        ";
        
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param($paramTypes, ...$updateParams);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to update user: ' . $stmt->error);
        }
        
        // Log activity
        Logger::getInstance()->info('User updated', [
            'user_id' => $userId,
            'email' => $data['email'],
            'level' => $data['level'],
            'password_changed' => !empty($data['password']),
            'updated_by' => Auth::user()['email']
        ]);
        
        // Log to activity_log table
        $activityStmt = $conn->prepare("
            INSERT INTO activity_log (
                user_id, user_code, module, action, entity_type, entity_id, 
                description, ip_address, user_agent
            ) VALUES (?, ?, 'users', 'update', 'user', ?, ?, ?, ?)
        ");
        
        $description = "Updated user: {$data['name']} ({$data['email']})";
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $currentUserId = Auth::user()['id'];
        $currentUserCode = Auth::userId();
        
        $activityStmt->bind_param(
            "issss",
            $currentUserId,
            $currentUserCode,
            $userId,
            $description,
            $ipAddress,
            $userAgent
        );
        $activityStmt->execute();
        
        echo ApiResponse::success('User updated successfully', [
            'user_id' => $userId,
            'redirect' => 'index.php?action=list'
        ]);
    }
    
} catch (Exception $e) {
    Logger::getInstance()->error('User save error', [
        'action' => $action,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    echo ApiResponse::error('An error occurred: ' . $e->getMessage());
}