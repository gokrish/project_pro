-- ============================================================================
-- CANDIDATES MODULE - FINAL PRODUCTION SCHEMA
-- Version: 2.0
-- Focus: Business-first, recruiter-friendly
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables
DROP TABLE IF EXISTS `candidate_hr_comments`;
DROP TABLE IF EXISTS `candidate_communications`;
DROP TABLE IF EXISTS `candidate_status_history`;
DROP TABLE IF EXISTS `candidate_edit_history`;
DROP TABLE IF EXISTS `candidate_resume_parse`;
DROP TABLE IF EXISTS `candidate_skills`;
DROP TABLE IF EXISTS `job_skills`;

-- ============================================================================
-- 1. CANDIDATES TABLE 
-- ============================================================================
CREATE TABLE `candidates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- ========== BASIC INFO (Required) ==========
    `candidate_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `alternate_email` VARCHAR(255),
    `phone` VARCHAR(20) NOT NULL,
    `phone_alternate` VARCHAR(20),
    `linkedin_url` VARCHAR(255),
    
    -- ========== LOCATION ==========
    `current_location` ENUM(
        'Belgium', 'Netherlands', 'Luxembourg', 
        'Germany', 'France', 'India', 'Other'
    ) DEFAULT 'Belgium',
    `willing_to_join` BOOLEAN DEFAULT 0,
    
    -- ========== WORK STATUS with applied for which job ==========
    `current_employer` VARCHAR(255),
    `current_position` VARCHAR(255),
    `current_agency` VARCHAR(255),
    `current_working_status` ENUM(
        'Freelance_Self', 
        'Freelance_Company', 
        'Employee', 
        'Unemployed'
    ),
    `work_authorization_id` INT COMMENT 'FK to work_authorization',
    `professional_summary` TEXT,
    `role_addressed` text DEFAULT NULL,
    -- ========== KNOWN Languages INFO ==========
    `languages` JSON COMMENT '["English","French","Dutch"]',
    -- ========== COMPENSATION (Both models supported) ==========
    `current_salary` DECIMAL(12,2) COMMENT 'Annual for employees',
    `expected_salary` DECIMAL(12,2) COMMENT 'Annual expected',
    `current_daily_rate` DECIMAL(10,2) COMMENT 'For freelancers',
    `expected_daily_rate` DECIMAL(10,2) COMMENT 'For freelancers',
    
    -- ========== AVAILABILITY ==========
    `notice_period_days` INT COMMENT 'Notice period in days',
    `available_from` DATE COMMENT 'When can start',
    
    -- ========== LEAD MANAGEMENT ==========
    `lead_type` ENUM('Hot', 'Warm', 'Cold', 'Blacklist') DEFAULT 'Warm',
    `lead_type_role` ENUM('Payroll', 'Recruitment', 'InProgress', 'WaitingConfirmation'),
    
    -- ========== STATUS WORKFLOW ==========
    `status` ENUM(
        'new',          -- Just added
        'screening',    -- Being reviewed
        'qualified',    -- Ready for jobs
        'active',       -- Has active submissions
        'placed',       -- Successfully placed
        'rejected',     -- Failed screening
        'archived'      -- Inactive
    ) DEFAULT 'new',
    
    `screening_completed_at` TIMESTAMP NULL,
    `screening_notes` TEXT,
    `screening_result` ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
    
    -- ========== FOLLOW-UP ==========
    `follow_up_status` ENUM('Done', 'Not_Done', 'Scheduled') DEFAULT 'Not_Done',
    `follow_up_date` DATE,
    `last_contacted_date` DATE,
    `face_to_face_date` DATE,
    
    -- ========== DOCUMENTS ==========
    `candidate_cv` VARCHAR(500),
    `consultancy_cv` VARCHAR(500),
    `cv_last_updated` TIMESTAMP NULL,
    
    -- ========== CONSENT & GDPR ==========
    `consent_given` BOOLEAN DEFAULT 0,
    `consent_date` DATE,
    `consent_type` ENUM('Email', 'Phone', 'InPerson', 'Online'),
    
    -- ========== OWNERSHIP ==========
    `assigned_to` VARCHAR(50) COMMENT 'user_code',
    `created_by` VARCHAR(50),
    
    -- ========== COUNTERS (Auto-updated) ==========
    `total_submissions` INT DEFAULT 0,
    `total_interviews` INT DEFAULT 0,
    `total_placements` INT DEFAULT 0,
    `last_submission_date` DATE,
    `last_interview_date` DATE,
    
    -- ========== NOTES ==========
    `internal_notes` TEXT COMMENT 'Private recruiter notes',
    `extra_details` TEXT,
    
    -- ========== TIMESTAMPS ==========
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    -- ========== INDEXES ==========
    INDEX `idx_code` (`candidate_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_location` (`current_location`),
    INDEX `idx_lead_type` (`lead_type`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_status_screening` (`status`, `screening_result`),
    INDEX `idx_available` (`available_from`),
    
    FULLTEXT INDEX `ft_name_summary` (`candidate_name`, `professional_summary`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Candidate profiles - simplified for fast entry';

-- ============================================================================
-- 2. CANDIDATE SKILLS (Normalized for filtering)
-- ============================================================================
CREATE TABLE `candidate_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `skill_id` INT NOT NULL,
    `proficiency_level` ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
    `added_by` VARCHAR(50),
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `technical_skills`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_candidate_skill` (`candidate_code`, `skill_id`),
    
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_proficiency` (`proficiency_level`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 3. TECHNICAL SKILLS (Master list)
-- ============================================================================
CREATE TABLE `technical_skills` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `skill_name` VARCHAR(100) UNIQUE NOT NULL,
    `skill_category` ENUM(
        'Programming', 'Framework', 'Database', 
        'Cloud', 'DevOps', 'Tools', 
        'Soft_Skills', 'Industry', 'Other'
    ) DEFAULT 'Other',
    `is_active` BOOLEAN DEFAULT 1,
    `usage_count` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_category` (`skill_category`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_name` (`skill_name`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed common skills
INSERT INTO `technical_skills` (`skill_name`, `skill_category`) VALUES
-- Programming
('Java', 'Programming'),
('Python', 'Programming'),
('JavaScript', 'Programming'),
('C#', 'Programming'),
('PHP', 'Programming'),
('Ruby', 'Programming'),
('Go', 'Programming'),
('TypeScript', 'Programming'),
-- Frameworks
('Spring Boot', 'Framework'),
('React', 'Framework'),
('Angular', 'Framework'),
('Vue.js', 'Framework'),
('Django', 'Framework'),
('.NET', 'Framework'),
('Laravel', 'Framework'),
('Node.js', 'Framework'),
-- Databases
('MySQL', 'Database'),
('PostgreSQL', 'Database'),
('MongoDB', 'Database'),
('Oracle', 'Database'),
('SQL Server', 'Database'),
('Redis', 'Database'),
-- Cloud
('AWS', 'Cloud'),
('Azure', 'Cloud'),
('Google Cloud', 'Cloud'),
-- DevOps
('Docker', 'DevOps'),
('Kubernetes', 'DevOps'),
('Jenkins', 'DevOps'),
('Git', 'DevOps'),
('CI/CD', 'DevOps'),
-- Tools
('JIRA', 'Tools'),
('Confluence', 'Tools'),
('Postman', 'Tools'),
-- Soft Skills
('Team Leadership', 'Soft_Skills'),
('Communication', 'Soft_Skills'),
('Problem Solving', 'Soft_Skills'),
('Agile/Scrum', 'Soft_Skills');

-- ============================================================================
-- 4. WORK AUTHORIZATION
-- ============================================================================
CREATE TABLE `work_authorization` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `status_name` VARCHAR(100) NOT NULL,
    `requires_sponsorship` BOOLEAN DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT 1,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `work_authorization` (`status_name`, `requires_sponsorship`, `display_order`) VALUES
('EU Citizen', 0, 1),
('EU Permanent Resident', 0, 2),
('Work Permit', 0, 3),
('Requires Sponsorship', 1, 4),
('Student Visa', 0, 5),
('Other', 0, 6);

-- ============================================================================
-- 5. COMMUNICATIONS LOG
-- ============================================================================
CREATE TABLE `candidate_communications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `communication_type` ENUM('Call', 'Email', 'Meeting', 'WhatsApp', 'LinkedIn', 'Other') NOT NULL,
    `direction` ENUM('Inbound', 'Outbound') DEFAULT 'Outbound',
    `subject` VARCHAR(255),
    `notes` TEXT NOT NULL,
    `duration_minutes` INT,
    `next_action` VARCHAR(255),
    `next_action_date` DATE,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_type` (`communication_type`),
    INDEX `idx_created` (`created_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- CAN STATUS HISTORY TRACKING
-- ============================================================================


CREATE TABLE `candidate_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50) NOT NULL,
    `changed_by` VARCHAR(50),
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. HR COMMENTS (Confidential)
-- ============================================================================
CREATE TABLE `candidate_hr_comments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `comment_type` ENUM(
        'Screening', 'Interview_Feedback', 'General', 
        'Manager_Review', 'Red_Flag', 'Recommendation'
    ) NOT NULL,
    `comment` TEXT NOT NULL,
    `is_confidential` BOOLEAN DEFAULT 1,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_type` (`comment_type`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 7. STATUS HISTORY
-- ============================================================================
CREATE TABLE `candidate_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50) NOT NULL,
    `changed_by` VARCHAR(50),
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `reason` TEXT,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_changed_at` (`changed_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 8. EDIT HISTORY (Audit trail)
-- ============================================================================
CREATE TABLE `candidate_edit_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `old_value` TEXT,
    `new_value` TEXT,
    `edit_type` ENUM('manual', 'import', 'system') DEFAULT 'manual',
    `edited_by` VARCHAR(50),
    `edited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_field` (`field_name`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 9. JOB SKILLS (For matching)
-- ============================================================================
CREATE TABLE `job_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) NOT NULL,
    `skill_id` INT NOT NULL,
    `is_required` BOOLEAN DEFAULT 1,
    `minimum_years` DECIMAL(3,1) DEFAULT 0,
    `priority` INT DEFAULT 0,
    `added_by` VARCHAR(50),
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`job_code`) REFERENCES `jobs`(`job_code`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `technical_skills`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_job` (`job_code`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_required` (`is_required`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================================
-- 10. RESUME PARSE (Future AI integration)
-- ============================================================================
CREATE TABLE `candidate_resume_parse` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `cv_file_path` VARCHAR(500) NOT NULL,
    `parse_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `parse_status` ENUM('success', 'partial', 'failed') DEFAULT 'success',
    `parsed_skills` JSON,
    `parsed_summary` TEXT,
    `confidence_score` DECIMAL(3,2),
    `reviewed` BOOLEAN DEFAULT 0,
    `reviewed_by` VARCHAR(50),
    `reviewed_at` TIMESTAMP NULL,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Candidate indexes (for faster reporting)
CREATE INDEX idx_candidates_created_at ON candidates(created_at);
CREATE INDEX idx_candidates_status ON candidates(status);
CREATE INDEX idx_candidates_assigned_to ON candidates(assigned_to);
CREATE INDEX idx_candidates_follow_up_date ON candidates(follow_up_date);

-- Submission indexes
CREATE INDEX idx_submissions_submitted_at ON submissions(submitted_at);
CREATE INDEX idx_submissions_submitted_by ON submissions(submitted_by);

-- Status log indexes
CREATE INDEX idx_status_log_changed_at ON candidate_status_log(changed_at);
CREATE INDEX idx_status_log_candidate ON candidate_status_log(candidate_code);

-- Communication indexes
CREATE INDEX idx_communications_contacted_at ON candidate_communications(contacted_at);
CREATE INDEX idx_communications_contacted_by ON candidate_communications(contacted_by);
-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 
    'Candidate schema installed successfully!' as message,
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = DATABASE() 
     AND table_name IN ('candidates', 'candidate_skills', 'technical_skills', 
                        'candidate_communications', 'candidate_hr_comments')) as tables_created;