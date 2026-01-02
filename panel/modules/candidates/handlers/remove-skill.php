<?php
/**
 * Remove Skill Handler
 * Remove a skill from candidate's profile
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
if (!Permission::can('candidates', 'edit_all') && !Permission::can('candidates', 'edit_own')) {
    ApiResponse::forbidden('You do not have permission to update candidate skills');
}

// Verify CSRF token
if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
    ApiResponse::error('Invalid security token');
}

// Validate input
if (empty($_POST['candidate_code'])) {
    ApiResponse::error('Candidate code is required');
}

if (empty($_POST['skill_id'])) {
    ApiResponse::error('Skill ID is required');
}

$candidateCode = $_POST['candidate_code'];
$skillId = (int)$_POST['skill_id'];
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
    
    // Get skill name before deletion
    $stmt = $conn->prepare("
        SELECT ts.skill_name, cs.proficiency_level
        FROM candidate_skills cs
        JOIN technical_skills ts ON cs.skill_id = ts.id
        WHERE cs.candidate_code = ? AND cs.skill_id = ?
    ");
    $stmt->bind_param("si", $candidateCode, $skillId);
    $stmt->execute();
    $skillResult = $stmt->get_result();
    $skillData = $skillResult->fetch_assoc();
    
    if (!$skillData) {
        ApiResponse::error('Skill not found for this candidate');
    }
    
    // Delete skill
    $stmt = $conn->prepare("
        DELETE FROM candidate_skills 
        WHERE candidate_code = ? AND skill_id = ?
    ");
    $stmt->bind_param("si", $candidateCode, $skillId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to remove skill');
    }
    
    // Update usage count
    $stmt = $conn->prepare("
        UPDATE technical_skills 
        SET usage_count = GREATEST(0, usage_count - 1)
        WHERE id = ?
    ");
    $stmt->bind_param("i", $skillId);
    $stmt->execute();
    
    // Get remaining skills
    $stmt = $conn->prepare("
        SELECT cs.*, ts.skill_name, ts.skill_category
        FROM candidate_skills cs
        JOIN technical_skills ts ON cs.skill_id = ts.id
        WHERE cs.candidate_code = ?
        ORDER BY cs.is_primary DESC, cs.added_at ASC
    ");
    $stmt->bind_param("s", $candidateCode);
    $stmt->execute();
    $remainingSkillsResult = $stmt->get_result();
    $remainingSkills = [];
    while ($row = $remainingSkillsResult->fetch_assoc()) {
        $remainingSkills[] = [
            'skill_id' => $row['skill_id'],
            'skill_name' => $row['skill_name'],
            'skill_category' => $row['skill_category'],
            'proficiency_level' => $row['proficiency_level'],
            'is_primary' => $row['is_primary']
        ];
    }
    
    // Log activity
    Logger::getInstance()->logActivity(
        'update',
        'candidates',
        $candidateCode,
        "Removed skill: {$skillData['skill_name']}",
        [
            'skill_id' => $skillId,
            'skill_name' => $skillData['skill_name'],
            'removed_by' => $user['user_code']
        ]
    );
    
    $db->commit();
    
    ApiResponse::success([
        'candidate_code' => $candidateCode,
        'removed_skill' => [
            'skill_id' => $skillId,
            'skill_name' => $skillData['skill_name']
        ],
        'remaining_skills' => $remainingSkills,
        'total_skills' => count($remainingSkills)
    ], 'Skill removed successfully');
    
} catch (Exception $e) {
    $db->rollback();
    
    Logger::getInstance()->error('Failed to remove candidate skill', [
        'candidate_code' => $candidateCode,
        'skill_id' => $skillId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    ApiResponse::serverError('Failed to remove skill', [
        'error' => $e->getMessage()
    ]);
}
