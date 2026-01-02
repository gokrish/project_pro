<?php
/**
 * Add Skills Handler
 * 
 * @version 2.0
 */

require_once __DIR__ . '/../../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;
use ProConsultancy\Core\ApiResponse;
use ProConsultancy\Core\Auth;

// Check permission
// First check if user can edit at all
if (!Permission::can('candidates', 'edit')) {
    ApiResponse::forbidden();
}

// Then verify ownership if needed
$stmt = $conn->prepare("SELECT created_by FROM candidates WHERE candidate_code = ?");
$stmt->bind_param("s", $candidateCode);
$stmt->execute();
$candidate = $stmt->get_result()->fetch_assoc();

if (!Permission::can('candidates', 'edit_all')) {
    // Check ownership
    if ($candidate['created_by'] !== Auth::userCode() && !Permission::can('candidates', 'edit_own')) {
        ApiResponse::forbidden();
    }
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['candidate_code'])) {
    ApiResponse::error('Candidate code is required');
}

if (empty($_POST['skills']) || !is_array($_POST['skills'])) {
    ApiResponse::error('At least one skill is required');
}

$candidateCode = $_POST['candidate_code'];
$skills = $_POST['skills']; // Array of skill IDs
$user = Auth::user();

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Get candidate and check permissions
    $stmt = $conn->prepare("
        SELECT created_by, assigned_to 
        FROM candidates 
        WHERE candidate_code = ? AND deleted_at IS NULL
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $result = $stmt->get_result();
    $candidate = $result->fetch_assoc();
    
    if (!$candidate) {
        ApiResponse::notFound('Candidate not found');
    }
    
    // Check ownership for .own permission
    if (Permission::can('candidates', 'edit_own') && !Permission::can('candidates', 'edit_all')) {
        if ($candidate['created_by'] !== $user['user_code'] && 
            $candidate['assigned_to'] !== $user['user_code']) {
            ApiResponse::forbidden('You can only edit your own candidates');
        }
    }
    
    // Get existing skills
    $stmt = $conn->prepare("
        SELECT skill_id FROM candidate_skills 
        WHERE candidate_code = ?
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $existingSkillsResult = $stmt->get_result();
    $existingSkills = [];
    while ($row = $existingSkillsResult->fetch_assoc()) {
        $existingSkills[] = (int)$row['skill_id'];
    }
    
    // Determine skills to add (not already present)
    $skillsToAdd = array_diff($skills, $existingSkills);
    $addedSkills = [];
    
    if (!empty($skillsToAdd)) {
        // Prepare insert statement
        $stmt = $conn->prepare("
            INSERT INTO candidate_skills (
                candidate_code, skill_id, proficiency_level, is_primary, added_by, added_at
            ) VALUES (?, ?, 'Intermediate', 0, ?, NOW())
        ");
        
        foreach ($skillsToAdd as $skillId) {
            $skillId = (int)$skillId;
            
            // Verify skill exists
            $checkStmt = $conn->prepare("SELECT skill_name FROM technical_skills WHERE id = ? AND is_active = 1");
            $checkStmt->bind_param("i", $skillId);
            $checkStmt->execute();
            $skillResult = $checkStmt->get_result();
            
            if ($skillResult->num_rows === 0) {
                continue; // Skip invalid skills
            }
            
            $skillData = $skillResult->fetch_assoc();
            
            // Insert skill (with default 'Intermediate' proficiency)
            $stmt->bind_param("sis", $candidateCode, $skillId, $user['user_code']);
            
            if ($stmt->execute()) {
                $addedSkills[] = [
                    'skill_id' => $skillId,
                    'skill_name' => $skillData['skill_name'],
                    'proficiency' => 'Intermediate' // Default stored in DB
                ];
                
                // Update usage count
                $updateCountStmt = $conn->prepare("
                    UPDATE technical_skills 
                    SET usage_count = usage_count + 1 
                    WHERE id = ?
                ");
                $updateCountStmt->bind_param("i", $skillId);
                $updateCountStmt->execute();
            }
        }
    }
    
    // Get updated full skills list
    $stmt = $conn->prepare("
        SELECT cs.*, ts.skill_name, ts.skill_category
        FROM candidate_skills cs
        JOIN technical_skills ts ON cs.skill_id = ts.id
        WHERE cs.candidate_code = ?
        ORDER BY cs.is_primary DESC, cs.added_at ASC
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $allSkillsResult = $stmt->get_result();
    $allSkills = [];
    while ($row = $allSkillsResult->fetch_assoc()) {
        $allSkills[] = [
            'skill_id' => $row['skill_id'],
            'skill_name' => $row['skill_name'],
            'skill_category' => $row['skill_category'],
            'proficiency_level' => $row['proficiency_level'],
            'is_primary' => $row['is_primary']
        ];
    }
    
    // Log activity
    if (!empty($addedSkills)) {
        $skillNames = array_column($addedSkills, 'skill_name');
        
        Logger::getInstance()->logActivity(
            'update',
            'candidates',
            $candidateCode,
            'Added skills: ' . implode(', ', $skillNames),
            [
                'skills_added' => $addedSkills,
                'added_by' => $user['user_code']
            ]
        );
    }
    
    $db->commit();
    
    ApiResponse::success([
        'candidate_code' => $candidateCode,
        'skills_added' => $addedSkills,
        'all_skills' => $allSkills,
        'total_skills' => count($allSkills)
    ], count($addedSkills) . ' skill(s) added successfully');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Failed to add candidate skills', [
        'candidate_code' => $candidateCode,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to add skills', [
        'error' => $e->getMessage()
    ]);
}
