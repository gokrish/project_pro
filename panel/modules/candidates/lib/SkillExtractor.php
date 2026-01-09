<?php
/**
 * Skill Extractor
 * Matches skills from resume text against technical_skills database
 * 
 * @version 1.0
 */

namespace ProConsultancy\Candidates;

use ProConsultancy\Core\Database;

class SkillExtractor
{
    private $db;
    private $conn;
    private $text;
    private $foundSkills = [];
    
    public function __construct($text)
    {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->text = strtolower($text);
    }
    
    /**
     * Extract skills by matching against database
     */
    public function extract()
    {
        // Get all technical skills from database
        $stmt = $this->conn->prepare("
            SELECT skill_name, skill_category, keywords 
            FROM technical_skills 
            WHERE is_active = 1
        ");
        $stmt->execute();
        $skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        foreach ($skills as $skill) {
            // Check if skill name appears in text
            if ($this->skillFoundInText($skill['skill_name'], $skill['keywords'])) {
                $this->foundSkills[] = [
                    'skill_name' => $skill['skill_name'],
                    'category' => $skill['skill_category'],
                    'proficiency' => 'intermediate', // Default
                    'is_primary' => 0
                ];
            }
        }
        
        // Remove duplicates
        $this->foundSkills = $this->removeDuplicates($this->foundSkills);
        
        // Sort by category
        usort($this->foundSkills, function($a, $b) {
            return strcmp($a['category'], $b['category']);
        });
        
        return $this->foundSkills;
    }
    
    /**
     * Check if skill or its keywords appear in text
     */
    private function skillFoundInText($skillName, $keywordsJson)
    {
        $keywords = json_decode($keywordsJson, true) ?: [];
        $keywords[] = $skillName; // Add skill name itself
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower(trim($keyword));
            
            // Word boundary matching (avoid partial matches)
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
            
            if (preg_match($pattern, $this->text)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Remove duplicate skills
     */
    private function removeDuplicates($skills)
    {
        $unique = [];
        $seen = [];
        
        foreach ($skills as $skill) {
            if (!in_array($skill['skill_name'], $seen)) {
                $unique[] = $skill;
                $seen[] = $skill['skill_name'];
            }
        }
        
        return $unique;
    }
    
    /**
     * Get found skills count
     */
    public function getCount()
    {
        return count($this->foundSkills);
    }
}