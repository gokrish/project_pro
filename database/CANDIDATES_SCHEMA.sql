-- ============================================================================
-- CANDIDATES MODULE - FINAL PRODUCTION SCHEMA (FIXED VERSION)
-- Version: 2.1 - All Errors Corrected
-- Focus: Business-first, recruiter-friendly
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Drop existing tables in correct order
DROP TABLE IF EXISTS `candidate_resume_parse`;
DROP TABLE IF EXISTS `candidate_edit_history`;
DROP TABLE IF EXISTS `candidate_hr_comments`;
DROP TABLE IF EXISTS `candidate_communications`;
DROP TABLE IF EXISTS `candidate_status_history`;
DROP TABLE IF EXISTS `job_skills`;
DROP TABLE IF EXISTS `candidate_skills`;
DROP TABLE IF EXISTS `technical_skills`;
DROP TABLE IF EXISTS `work_authorization`;
DROP TABLE IF EXISTS `candidates`;

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
    `years_experience` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `role_addressed` TEXT DEFAULT NULL,
    
    -- ========== KNOWN Languages INFO ==========
    `languages` JSON COMMENT '["English","French","Dutch","German"]',
    
    -- ========== COMPENSATION (Both models supported) ==========
    `current_salary` DECIMAL(12,2) COMMENT 'Annual for employees',
    `expected_salary` DECIMAL(12,2) COMMENT 'Annual expected',
    `current_daily_rate` DECIMAL(10,2) COMMENT 'For freelancers',
    `expected_daily_rate` DECIMAL(10,2) COMMENT 'For freelancers',
    
    -- ========== AVAILABILITY ==========
    `notice_period_days` INT UNSIGNED DEFAULT 0 COMMENT 'Notice period in days',
    `available_from` DATE COMMENT 'When can start',
    
    -- ========== LEAD MANAGEMENT ==========
    `lead_type` ENUM('Hot', 'Warm', 'Cold', 'Blacklist') DEFAULT 'Warm',
    `lead_type_role` ENUM('Payroll', 'Recruitment', 'InProgress', 'WaitingConfirmation'),
    
    -- ========== STATUS WORKFLOW ==========
    `status` ENUM(
        'open',         -- Active, ready (initial)
        'screening',    -- Being reviewed
        'qualified',    -- Passed screening
        'active',       -- Available for jobs 
        'interviewing', -- In interviews
        'offered',      -- Has offer
        'placed',       -- Working
        'on_hold',      -- Temporarily unavailable
        'rejected',     -- Failed
        'archived'      -- Inactive
    ) DEFAULT 'open',

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
    `consent_type` ENUM('Email', 'LinkedIn', 'InPerson', 'Phone'),
    
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
-- 2. WORK AUTHORIZATION (Must be created BEFORE candidate_skills)
-- ============================================================================
CREATE TABLE `work_authorization` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `status_name` VARCHAR(100) NOT NULL,
    `requires_sponsorship` BOOLEAN DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT 1,
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `work_authorization` (`status_name`, `requires_sponsorship`, `display_order`) VALUES
('EU Citizen', 0, 1),
('Work Permit Holder', 0, 2),
('Require Sponsorship', 1, 4),
('Other', 0, 6);

-- ============================================================================
-- 3. TECHNICAL SKILLS (Master list - Must be created BEFORE candidate_skills)
-- ============================================================================
CREATE TABLE `technical_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `skill_name` VARCHAR(100) UNIQUE NOT NULL,
    `keywords` ,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_category` (`category`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_skill_name` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed common skills
INSERT INTO technical_skills (skill_name, skill_category, keywords) VALUES
-- Programming Languages
('Java',  '["Java", "J2EE", "JDK", "JSE", "JEE"]'),
('JavaScript',  '["JavaScript", "JS", "ECMAScript", "ES6"]'),
('Python',  '["Python", "Python3", "Python2"]'),
('PHP',  '["PHP", "PHP7", "PHP8"]'),
('C#',  '["C#", "CSharp", "C-Sharp", ".NET"]'),
('React','["React", "ReactJS", "React.js"]'),
('Angular', '["Angular", "AngularJS"]'),
('Spring',  '["Spring", "Spring Boot", "Spring Framework"]'),
('Node.js', '["Node", "NodeJS", "Node.js"]'),
-- Databases
('MySQL', '["MySQL", "My SQL"]'),
('PostgreSQL', '["PostgreSQL", "Postgres"]'),
('MongoDB', '["MongoDB", "Mongo"]'),
('Oracle', '["Oracle DB", "Oracle Database"]'),
-- DevOps
('DevOps', '["Docker", "Kubernetes","Git"]'),
('Cloud','["AWS", "Azure","GCP"]'),

-- ============================================================================
-- 4. CANDIDATE SKILLS (Normalized for filtering)
-- ============================================================================
CREATE TABLE `candidate_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `skill_name` VARCHAR(100) NOT NULL,
    `proficiency_level` ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    `years_experience` DECIMAL(3,1) NULL,
    `last_used_year` INT NULL,
    `is_primary` BOOLEAN DEFAULT 0,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `added_by` VARCHAR(50) NULL,
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_skill` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. CANDIDATE COMMUNICATIONS
-- ============================================================================
CREATE TABLE `candidate_communications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `communication_type` ENUM('call', 'email', 'sms', 'meeting', 'other') NOT NULL,
    `direction` ENUM('inbound', 'outbound') NOT NULL,
    `subject` VARCHAR(255) NULL,
    `notes` TEXT NULL,
    `outcome` VARCHAR(255) NULL,
    `next_action` TEXT NULL,
    `next_action_date` DATE NULL,
    `contacted_by` VARCHAR(50) NOT NULL,
    `contacted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_type` (`communication_type`),
    INDEX `idx_contacted_at` (`contacted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 6. HR COMMENTS (Confidential)
-- ============================================================================
CREATE TABLE `candidate_hr_comments` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `comment_type` ENUM('screening', 'interview', 'reference', 'general') DEFAULT 'general',
    `comment` TEXT NOT NULL,
    `is_private` BOOLEAN DEFAULT 0,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_type` (`comment_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 8. EDIT HISTORY (Audit trail)
-- ============================================================================
CREATE TABLE `candidate_edit_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `field_name` VARCHAR(100) NOT NULL,
    `old_value` TEXT NULL,
    `new_value` TEXT NULL,
    `edited_by` VARCHAR(50) NOT NULL,
    `edited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_field` (`field_name`),
    INDEX `idx_edited_at` (`edited_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 9. JOB SKILLS (For matching - references jobs table from main schema)
-- ============================================================================
CREATE TABLE `job_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) NOT NULL,
    `skill_name` VARCHAR(100) NOT NULL,
    `required_level` ENUM('required', 'preferred', 'nice_to_have') DEFAULT 'required',
    `years_required` DECIMAL(3,1) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`job_code`) REFERENCES `jobs`(`job_code`) ON DELETE CASCADE,
    INDEX `idx_job` (`job_code`),
    INDEX `idx_skill` (`skill_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 10. RESUME PARSE (AI integration)
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
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_status` (`parse_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TRIGGERS FOR AUTO-UPDATE COUNTERS
-- ============================================================================

-- Trigger to update last_contacted_date when communication is added
DELIMITER //
CREATE TRIGGER `trg_update_last_contacted` 
AFTER INSERT ON `candidate_communications`
FOR EACH ROW
BEGIN
    UPDATE candidates 
    SET last_contacted_date = DATE(NEW.contacted_at)
    WHERE candidate_code = NEW.candidate_code;
END//
DELIMITER ;

-- Trigger to update submission counters (will be called from submissions table)
DELIMITER //
CREATE TRIGGER `trg_candidate_submission_insert`
AFTER INSERT ON `submissions`
FOR EACH ROW
BEGIN
    UPDATE candidates
    SET 
        total_submissions = total_submissions + 1,
        last_submission_date = CURDATE(),
        status = CASE 
            WHEN status = 'qualified' THEN 'active'
            ELSE status
        END
    WHERE candidate_code = NEW.candidate_code;
END//
DELIMITER ;

-- Trigger when submission status changes to placed
DELIMITER //
CREATE TRIGGER `trg_candidate_placement`
AFTER UPDATE ON `submissions`
FOR EACH ROW
BEGIN
    IF NEW.client_status = 'placed' AND OLD.client_status != 'placed' THEN
        UPDATE candidates
        SET 
            total_placements = total_placements + 1,
            status = 'placed'
        WHERE candidate_code = NEW.candidate_code;
    END IF;
END//
DELIMITER ;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT 
    'Candidate schema installed successfully!' as message,
    (SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = DATABASE() 
     AND table_name IN ('candidates', 'candidate_skills', 'technical_skills', 
                        'work_authorization', 'candidate_communications',
                        'candidate_hr_comments', 'candidate_status_history',
                        'candidate_edit_history', 'job_skills', 'candidate_resume_parse')
    ) as tables_created;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- END OF CANDIDATES SCHEMA
-- ============================================================================