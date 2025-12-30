<?php
/**
 * Candidate Assignment Handler
 * Handles multi-user assignments to candidates
 */

require_once __DIR__ . '/../_common.php';
header('Content-Type: application/json');



$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        
        case 'assign':
            // Assign single or multiple users to a candidate
            $can_code = $_POST['can_code'] ?? '';
            $user_codes = $_POST['user_codes'] ?? []; // Array of user codes
            
            if (empty($can_code)) {
                throw new Exception('Candidate code is required');
            }
            
            if (empty($user_codes) || !is_array($user_codes)) {
                throw new Exception('At least one user must be selected');
            }
            
            $conn->begin_transaction();
            
            // Get user details for each assignment
            $assigned_count = 0;
            foreach ($user_codes as $user_code) {
                // Get user name
                $stmt = $conn->prepare("SELECT name FROM user WHERE user_code = ?");
                $stmt->bind_param("s", $user_code);
                $stmt->execute();
                $result = $stmt->get_result();
                $assigned_user = $result->fetch_assoc();
                $stmt->close();
                
                if (!$assigned_user) {
                    continue; // Skip invalid users
                }
                
                // Check if already assigned
                $stmt = $conn->prepare("
                    SELECT id FROM candidate_assignments 
                    WHERE can_code = ? AND user_code = ?
                ");
                $stmt->bind_param("ss", $can_code, $user_code);
                $stmt->execute();
                $exists = $stmt->get_result()->num_rows > 0;
                $stmt->close();
                
                if ($exists) {
                    continue; // Skip if already assigned
                }
                
                // Insert assignment
                $stmt = $conn->prepare("
                    INSERT INTO candidate_assignments (can_code, user_code, username)
                    VALUES (?, ?, ?)
                ");
                $stmt->bind_param("sss", $can_code, $user_code, $assigned_user['name']);
                $stmt->execute();
                $stmt->close();
                
                $assigned_count++;
            }
            
            // Log activity
            $stmt = $conn->prepare("
                INSERT INTO candidate_activity_log 
                (can_code, activity_type, activity_description, created_by)
                VALUES (?, 'Assigned', ?, ?)
            ");
            $description = "Assigned to $assigned_count user(s)";
            $stmt->bind_param("sss", $can_code, $description, $user['user_code']);
            $stmt->execute();
            $stmt->close();
            
            // Update main candidate table
            if ($assigned_count > 0) {
                $stmt = $conn->prepare("
                    UPDATE candidates SET assigned_to = ?, updated_at = NOW()
                    WHERE can_code = ?
                ");
                $primary_user = $user_codes[0]; // First user as primary
                $stmt->bind_param("ss", $primary_user, $can_code);
                $stmt->execute();
                $stmt->close();
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Candidate assigned to $assigned_count user(s)",
                'assigned_count' => $assigned_count
            ]);
            break;
            
        case 'unassign':
            // Remove assignment
            $can_code = $_POST['can_code'] ?? '';
            $user_code = $_POST['user_code'] ?? '';
            
            if (empty($can_code) || empty($user_code)) {
                throw new Exception('Candidate and user code required');
            }
            
            $conn->begin_transaction();
            
            // Delete assignment
            $stmt = $conn->prepare("
                DELETE FROM candidate_assignments 
                WHERE can_code = ? AND user_code = ?
            ");
            $stmt->bind_param("ss", $can_code, $user_code);
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected > 0) {
                // Log activity
                $stmt = $conn->prepare("
                    INSERT INTO candidate_activity_log 
                    (can_code, activity_type, activity_description, created_by)
                    VALUES (?, 'Unassigned', 'User removed from assignment', ?)
                ");
                $stmt->bind_param("ss", $can_code, $user['user_code']);
                $stmt->execute();
                $stmt->close();
                
                // Check if any assignments left
                $stmt = $conn->prepare("
                    SELECT user_code FROM candidate_assignments 
                    WHERE can_code = ? LIMIT 1
                ");
                $stmt->bind_param("s", $can_code);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    // Update to another assigned user
                    $row = $result->fetch_assoc();
                    $new_primary = $row['user_code'];
                    $stmt->close();
                    
                    $stmt = $conn->prepare("
                        UPDATE candidates SET assigned_to = ? WHERE can_code = ?
                    ");
                    $stmt->bind_param("ss", $new_primary, $can_code);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // No assignments left
                    $stmt->close();
                    $stmt = $conn->prepare("
                        UPDATE candidates SET assigned_to = NULL WHERE can_code = ?
                    ");
                    $stmt->bind_param("s", $can_code);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Assignment removed'
            ]);
            break;
            
        case 'bulk_assign':
            // Assign multiple candidates to users
            $can_codes = $_POST['can_codes'] ?? [];
            $user_codes = $_POST['user_codes'] ?? [];
            
            if (empty($can_codes) || !is_array($can_codes)) {
                throw new Exception('No candidates selected');
            }
            
            if (empty($user_codes) || !is_array($user_codes)) {
                throw new Exception('No users selected');
            }
            
            $conn->begin_transaction();
            
            $total_assigned = 0;
            foreach ($can_codes as $can_code) {
                foreach ($user_codes as $user_code) {
                    // Get user name
                    $stmt = $conn->prepare("SELECT name FROM user WHERE user_code = ?");
                    $stmt->bind_param("s", $user_code);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $assigned_user = $result->fetch_assoc();
                    $stmt->close();
                    
                    if (!$assigned_user) continue;
                    
                    // Check if exists
                    $stmt = $conn->prepare("
                        SELECT id FROM candidate_assignments 
                        WHERE can_code = ? AND user_code = ?
                    ");
                    $stmt->bind_param("ss", $can_code, $user_code);
                    $stmt->execute();
                    $exists = $stmt->get_result()->num_rows > 0;
                    $stmt->close();
                    
                    if ($exists) continue;
                    
                    // Insert
                    $stmt = $conn->prepare("
                        INSERT INTO candidate_assignments (can_code, user_code, username)
                        VALUES (?, ?, ?)
                    ");
                    $stmt->bind_param("sss", $can_code, $user_code, $assigned_user['name']);
                    $stmt->execute();
                    $stmt->close();
                    
                    $total_assigned++;
                }
                
                // Update primary assignment
                if (!empty($user_codes)) {
                    $stmt = $conn->prepare("
                        UPDATE candidates SET assigned_to = ?, updated_at = NOW()
                        WHERE can_code = ?
                    ");
                    $primary = $user_codes[0];
                    $stmt->bind_param("ss", $primary, $can_code);
                    $stmt->execute();
                    $stmt->close();
                }
            }
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => "Bulk assigned to $total_assigned records",
                'total_assigned' => $total_assigned
            ]);
            break;
            
        case 'get_assignments':
            // Get all users assigned to a candidate
            $can_code = $_GET['can_code'] ?? '';
            
            if (empty($can_code)) {
                throw new Exception('Candidate code required');
            }
            
            $stmt = $conn->prepare("
                SELECT 
                    ca.user_code,
                    ca.username,
                    ca.assigned_at,
                    u.email
                FROM candidate_assignments ca
                JOIN user u ON ca.user_code = u.user_code
                WHERE ca.can_code = ?
                ORDER BY ca.assigned_at ASC
            ");
            $stmt->bind_param("s", $can_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $assignments = [];
            while ($row = $result->fetch_assoc()) {
                $assignments[] = $row;
            }
            $stmt->close();
            
            echo json_encode([
                'success' => true,
                'assignments' => $assignments
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($conn->in_transaction) {
        $conn->rollback();
    }
    
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?>
