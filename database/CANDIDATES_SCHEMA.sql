-- ============================================================================
-- OPTIMIZED CANDIDATES SCHEMA
-- Focus: Easy filtering, flexible skills, resume parsing ready
-- ============================================================================

-- ============================================================================
-- 1. CANDIDATES TABLE - Main Profile (Simplified Form)
-- ============================================================================
CREATE TABLE `candidates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- ========== BASIC INFO (Required - Simple Form) ==========
    `candidate_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `alternate_email` VARCHAR(255),
    `phone` VARCHAR(20) NOT NULL,
    `phone_alternate` VARCHAR(20),
    `linkedin_url` VARCHAR(255),
    
    -- ========== LOCATION & AVAILABILITY (Dropdown-based) ==========
    `current_location` ENUM(
        'Belgium', 'Netherlands', 'Luxembourg', 
        'Germany', 'France', 'India', 'Other'
    ) DEFAULT 'Belgium',
    `willing_to_relocate` BOOLEAN DEFAULT 0,
    
    -- ========== WORK AUTHORIZATION (Foreign Key) ==========
    `work_authorization_id` INT NULL COMMENT 'FK to work_authorization table',
    
    -- ========== CURRENT EMPLOYMENT ==========
    `current_employer` VARCHAR(255),
    `current_position` VARCHAR(255),
    `current_agency` VARCHAR(255) COMMENT 'If working via agency',
    `current_working_status` ENUM(
        'Freelance_Self', 
        'Freelance_Company', 
        'Employee', 
        'Unemployed'
    ),
    
    -- ========== SALARY & RATES (Flexible for both models) ==========
    `current_salary` DECIMAL(12,2) COMMENT 'Annual salary for employees',
    `expected_salary` DECIMAL(12,2) COMMENT 'Expected annual salary',
    `current_daily_rate` DECIMAL(10,2) COMMENT 'For freelancers',
    `expected_daily_rate` DECIMAL(10,2) COMMENT 'Expected daily rate',
    
    -- ========== AVAILABILITY ==========
    `notice_period_days` INT COMMENT 'Notice period in days',
    `available_from` DATE COMMENT 'When can start',
    
    -- ========== PROFESSIONAL SUMMARY ==========
    `professional_summary` TEXT COMMENT 'Brief bio/summary',
    
    -- ========== LANGUAGES (Multi-select, stored as JSON) ==========
    `languages` JSON COMMENT '["English", "French", "Dutch", "German"]',
    
    -- ========== LEAD CLASSIFICATION ==========
    `lead_type` ENUM('Hot', 'Warm', 'Cold', 'Blacklist') DEFAULT 'Warm',
    `lead_type_role` ENUM('Payroll', 'Recruitment', 'Both', 'Invoice'),
    
    -- ========== STATUS WORKFLOW ==========
    `status` ENUM(
        'new',          -- Just added
        'screening',    -- Being reviewed
        'qualified',    -- Ready for submissions
        'active',       -- Has active submissions
        'placed',       -- Successfully placed
        'rejected',     -- Failed screening
        'archived'      -- Inactive/not interested
    ) DEFAULT 'new',
    
    -- ========== SCREENING ==========
    `screening_completed_at` TIMESTAMP NULL,
    `screening_notes` TEXT,
    `screening_result` ENUM('pass', 'fail', 'pending') DEFAULT 'pending',
    
    -- ========== FOLLOW-UP ==========
    `follow_up_status` ENUM('Done', 'Not_Done', 'Scheduled') DEFAULT 'Not_Done',
    `follow_up_date` DATE,
    `last_contacted_date` DATE,
    `face_to_face_date` DATE COMMENT 'Interview date',
    
    -- ========== DOCUMENTS ==========
    `candidate_cv` VARCHAR(500) COMMENT 'Original CV path',
    `consultancy_cv` VARCHAR(500) COMMENT 'Formatted CV path',
    `cv_last_updated` TIMESTAMP NULL,
    
    -- ========== CONSENT & GDPR ==========
    `consent_given` BOOLEAN DEFAULT 0,
    `consent_date` DATE,
    `consent_type` ENUM('Email', 'Phone', 'InPerson', 'Online'),
    
    -- ========== ASSIGNMENT & OWNERSHIP ==========
    `assigned_to` VARCHAR(50) COMMENT 'user_code of recruiter',
    `created_by` VARCHAR(50),
    
    -- ========== ACTIVITY COUNTERS (Auto-updated by triggers) ==========
    `total_submissions` INT DEFAULT 0,
    `total_interviews` INT DEFAULT 0,
    `total_placements` INT DEFAULT 0,
    `last_submission_date` DATE,
    `last_interview_date` DATE,
    
    -- ========== NOTES & EXTRA ==========
    `internal_notes` TEXT COMMENT 'Private notes for recruiters',
    `extra_details` TEXT COMMENT 'Any additional information',
    
    -- ========== TIMESTAMPS ==========
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    -- ========== INDEXES for FAST FILTERING ==========
    INDEX `idx_code` (`candidate_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_location` (`current_location`),
    INDEX `idx_lead_type` (`lead_type`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_created_by` (`created_by`),
    INDEX `idx_status_screening` (`status`, `screening_result`),
    INDEX `idx_available` (`available_from`),
    
    -- Full-text search on name and summary
    FULLTEXT INDEX `ft_name_summary` (`candidate_name`, `professional_summary`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Main candidate profiles - optimized for filtering and matching';

-- ============================================================================
-- 2. CANDIDATE SKILLS - Normalized for Easy Filtering
-- ============================================================================
CREATE TABLE `candidate_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `skill_id` INT NOT NULL COMMENT 'FK to technical_skills',
    `proficiency_level` ENUM('Beginner', 'Intermediate', 'Advanced', 'Expert') DEFAULT 'Intermediate',
    `years_of_experience` DECIMAL(3,1) COMMENT 'Years with this skill',
    `is_primary` BOOLEAN DEFAULT 0 COMMENT 'Primary/core skill',
    `added_by` VARCHAR(50) COMMENT 'Manual or parsed',
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `technical_skills`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_candidate_skill` (`candidate_code`, `skill_id`),
    
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_proficiency` (`proficiency_level`),
    INDEX `idx_primary` (`is_primary`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Candidate skills - normalized for easy filtering and job matching';

-- ============================================================================
-- 3. TECHNICAL SKILLS - Master List
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
    `usage_count` INT DEFAULT 0 COMMENT 'How many candidates have this skill',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_category` (`skill_category`),
    INDEX `idx_active` (`is_active`),
    INDEX `idx_usage` (`usage_count`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master skills list - categorized for better organization';

-- ============================================================================
-- 4. WORK AUTHORIZATION - Reference Table
-- ============================================================================
CREATE TABLE `work_authorization` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `status_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `requires_sponsorship` BOOLEAN DEFAULT 0,
    `display_order` INT DEFAULT 0,
    `is_active` BOOLEAN DEFAULT 1,
    
    INDEX `idx_active` (`is_active`),
    INDEX `idx_order` (`display_order`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Work authorization statuses';

-- Sample data for work authorization
INSERT INTO `work_authorization` (`status_name`, `requires_sponsorship`, `display_order`) VALUES
('EU Citizen', 0, 1),
('EU Permanent Resident', 0, 2),
('Work Permit', 0, 3),
('Requires Sponsorship', 1, 4),
('Student Visa', 0, 5),
('Other', 0, 6);

-- ============================================================================
-- 5. CV INBOX - Career Page Applications (Linked to Jobs)
-- ============================================================================
CREATE TABLE `cv_inbox` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cv_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- ========== JOB REFERENCE (Critical) ==========
    `job_id` INT UNSIGNED NULL COMMENT 'FK to jobs.id if applied for specific job',
    `job_code` VARCHAR(50) NULL COMMENT 'job_code for reference',
    `job_refno` VARCHAR(50) NULL COMMENT 'Public job reference number',
    
    -- ========== APPLICANT INFO (From Career Page Form) ==========
    `applicant_name` VARCHAR(255) NOT NULL,
    `applicant_email` VARCHAR(255) NOT NULL,
    `applicant_phone` VARCHAR(50),
    
    -- ========== CV & DOCUMENTS ==========
    `cv_path` VARCHAR(500) NOT NULL,
    `cover_letter_path` VARCHAR(500),
    
    -- ========== SOURCE & CHANNEL ==========
    `source` ENUM('Website_Career_Page', 'LinkedIn', 'Job_Board', 'Referral', 'Direct') DEFAULT 'Website_Career_Page',
    `referrer_url` VARCHAR(500) COMMENT 'Where they came from',
    
    -- ========== PROCESSING STATUS ==========
    `status` ENUM(
        'new',          -- Just submitted
        'screening',    -- Being reviewed
        'shortlisted',  -- Looks promising
        'converted',    -- Converted to candidate
        'rejected',     -- Not suitable
        'spam'          -- Marked as spam
    ) DEFAULT 'new',
    
    -- ========== REVIEW TRACKING ==========
    `reviewed_by` VARCHAR(50) COMMENT 'user_code',
    `reviewed_at` TIMESTAMP NULL,
    `review_notes` TEXT,
    `rejection_reason` TEXT,
    
    -- ========== CONVERSION ==========
    `converted_to_candidate_code` VARCHAR(50) NULL COMMENT 'If converted to candidate',
    `converted_by` VARCHAR(50),
    `converted_at` TIMESTAMP NULL,
    
    -- ========== TIMESTAMPS ==========
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    -- ========== FOREIGN KEYS ==========
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`converted_to_candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE SET NULL,
    
    -- ========== INDEXES for FILTERING ==========
    INDEX `idx_code` (`cv_code`),
    INDEX `idx_job` (`job_id`),
    INDEX `idx_job_code` (`job_code`),
    INDEX `idx_job_refno` (`job_refno`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`applicant_email`),
    INDEX `idx_submitted` (`submitted_at`),
    INDEX `idx_reviewed` (`reviewed_by`, `reviewed_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CV Inbox - Career page applications linked to specific jobs';

-- ============================================================================
-- 6. CANDIDATE EDIT HISTORY - Track All Changes
-- ============================================================================
CREATE TABLE `candidate_edit_history` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `field_name` VARCHAR(100) NOT NULL COMMENT 'Which field was edited',
    `old_value` TEXT,
    `new_value` TEXT,
    `edit_type` ENUM('manual', 'import', 'parsed', 'system') DEFAULT 'manual',
    `edited_by` VARCHAR(50),
    `edited_by_name` VARCHAR(100),
    `edited_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `edit_reason` VARCHAR(255),
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_field` (`field_name`),
    INDEX `idx_edited_by` (`edited_by`),
    INDEX `idx_edited_at` (`edited_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail of candidate profile changes';

-- ============================================================================
-- 7. RESUME PARSING DATA - Structured Parsed Information
-- ============================================================================
CREATE TABLE `candidate_resume_parse` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    
    -- ========== PARSING METADATA ==========
    `cv_file_path` VARCHAR(500) NOT NULL,
    `parse_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `parser_version` VARCHAR(50),
    `parse_status` ENUM('success', 'partial', 'failed') DEFAULT 'success',
    
    -- ========== PARSED DATA (JSON for flexibility) ==========
    `parsed_contact_info` JSON COMMENT '{"name": "", "email": "", "phone": "", "linkedin": ""}',
    `parsed_summary` TEXT,
    `parsed_skills` JSON COMMENT '["skill1", "skill2"]',
    `parsed_experience` JSON COMMENT '[{"company": "", "role": "", "duration": ""}]',
    `parsed_education` JSON COMMENT '[{"degree": "", "institution": "", "year": ""}]',
    `parsed_certifications` JSON COMMENT '["cert1", "cert2"]',
    `parsed_languages` JSON COMMENT '["English", "French"]',
    
    -- ========== CONFIDENCE SCORES ==========
    `confidence_score` DECIMAL(3,2) COMMENT '0.00 to 1.00',
    `skills_confidence` DECIMAL(3,2),
    `experience_confidence` DECIMAL(3,2),
    
    -- ========== MANUAL REVIEW ==========
    `reviewed` BOOLEAN DEFAULT 0,
    `reviewed_by` VARCHAR(50),
    `reviewed_at` TIMESTAMP NULL,
    `review_notes` TEXT,
    
    -- ========== RAW DATA ==========
    `raw_text` LONGTEXT COMMENT 'Extracted raw text from CV',
    `raw_json` JSON COMMENT 'Complete parser response',
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_parse_date` (`parse_date`),
    INDEX `idx_status` (`parse_status`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Resume parsing results - structured for AI processing';

-- ============================================================================
-- 8. JOB SKILLS REQUIREMENTS - For Matching
-- ============================================================================
CREATE TABLE `job_skills` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) NOT NULL,
    `skill_id` INT NOT NULL,
    `is_required` BOOLEAN DEFAULT 1 COMMENT '1=must have, 0=nice to have',
    `minimum_experience_years` DECIMAL(3,1) DEFAULT 0,
    `priority` INT DEFAULT 0 COMMENT 'Higher = more important',
    `added_by` VARCHAR(50),
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`job_code`) REFERENCES `jobs`(`job_code`) ON DELETE CASCADE,
    FOREIGN KEY (`skill_id`) REFERENCES `technical_skills`(`id`) ON DELETE CASCADE,
    
    INDEX `idx_job` (`job_code`),
    INDEX `idx_skill` (`skill_id`),
    INDEX `idx_required` (`is_required`),
    INDEX `idx_priority` (`priority`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Job skill requirements - for candidate matching algorithms';

-- ============================================================================
-- END OF OPTIMIZED SCHEMA
-- ============================================================================
