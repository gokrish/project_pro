-- ============================================================================
-- PROCONSULTANCY ATS - COMPLETE DATABASE SCHEMA
-- Version: FINAL 2.0
-- Date: December 26, 2024
-- Status: Production Ready
-- ============================================================================
-- 
-- INSTALLATION INSTRUCTIONS:
-- 1. Backup existing database: mysqldump -u root -p proconsultancy > backup.sql
-- 2. Drop and recreate: DROP DATABASE proconsultancy; CREATE DATABASE proconsultancy;
-- 3. Import: mysql -u root -p proconsultancy < ProConsultancy_COMPLETE_FINAL_SCHEMA.sql
-- 4. Verify: SHOW TABLES; SELECT COUNT(*) FROM users;
--
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- SECTION 1: CORE SYSTEM TABLES
-- ============================================================================

-- Roles table (6 default roles)
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_code` VARCHAR(50) UNIQUE NOT NULL,
    `role_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_system_role` BOOLEAN DEFAULT 0 COMMENT 'System roles cannot be deleted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Permissions table (40+ granular permissions)
CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `permission_code` VARCHAR(100) UNIQUE NOT NULL COMMENT 'Format: module.action',
    `permission_name` VARCHAR(150) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_permission_code` (`permission_code`),
    INDEX `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Role permissions mapping
CREATE TABLE `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
    INDEX `idx_role_id` (`role_id`),
    INDEX `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User-specific permissions (overrides)
CREATE TABLE `user_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted` BOOLEAN DEFAULT 1 COMMENT '1=granted, 0=revoked',
    `expires_at` TIMESTAMP NULL COMMENT 'NULL=permanent, otherwise temporary',
    `granted_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table (enhanced with role_id)
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) UNIQUE NOT NULL,
    `role_id` INT UNSIGNED NULL COMMENT 'Foreign key to roles table',
    `name` VARCHAR(255) NOT NULL,
    `full_name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `level` ENUM('super_admin', 'admin', 'manager', 'senior_recruiter', 'recruiter', 'coordinator') DEFAULT 'recruiter',
    `phone` VARCHAR(20),
    `department` VARCHAR(100),
    `position` VARCHAR(100),
    `is_active` BOOLEAN DEFAULT 1,
    `last_login` TIMESTAMP NULL,
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `password_changed_at` TIMESTAMP NULL,
    `email_verified_at` TIMESTAMP NULL,
    `created_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_code` (`user_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_level` (`level`),
    INDEX `idx_is_active` (`is_active`),
    INDEX `idx_role_id` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User sessions (remember me)
CREATE TABLE `user_sessions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) NOT NULL,
    `session_token` VARCHAR(255) UNIQUE NOT NULL,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_code` (`user_code`),
    INDEX `idx_session_token` (`session_token`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- System settings
CREATE TABLE `system_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('text', 'number', 'boolean', 'json', 'color') DEFAULT 'text',
    `setting_category` VARCHAR(50) DEFAULT 'general' COMMENT 'general, branding, email, recruitment, security',
    `description` TEXT,
    `updated_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_setting_key` (`setting_key`),
    INDEX `idx_category` (`setting_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity log (global audit trail)
CREATE TABLE `activity_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED,
    `user_code` VARCHAR(50),
    `module` VARCHAR(50) NOT NULL COMMENT 'contacts, candidates, jobs, etc',
    `action` VARCHAR(50) NOT NULL COMMENT 'create, update, delete, view, export, etc',
    `entity_type` VARCHAR(50) COMMENT 'contact, candidate, job, etc',
    `entity_id` VARCHAR(50) COMMENT 'Record ID or code',
    `description` TEXT,
    `ip_address` VARCHAR(45),
    `user_agent` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_user_code` (`user_code`),
    INDEX `idx_module` (`module`),
    INDEX `idx_action` (`action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 2: CONTACTS MODULE (Lead Management)
-- ============================================================================

-- Contacts (leads before they become candidates)
CREATE TABLE `contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- Basic Information
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `alternate_phone` VARCHAR(20),
    `linkedin_url` VARCHAR(255),
    
    -- Professional Information
    `current_company` VARCHAR(255),
    `current_title` VARCHAR(255),
    `years_of_experience` DECIMAL(4,1),
    `current_location` VARCHAR(255),
    `preferred_location` VARCHAR(255),
    `notice_period_days` INT,
    
    -- Compensation
    `current_salary` DECIMAL(12,2),
    `expected_salary` DECIMAL(12,2),
    `salary_currency` VARCHAR(3) DEFAULT 'EUR',
    
    -- Skills & Experience
    `skills` JSON COMMENT 'Array of skills',
    `summary` TEXT COMMENT 'Brief professional summary',
    
    -- Lead Management
    `status` ENUM('new', 'contacted', 'qualified', 'nurturing', 'converted', 'not_interested', 'unresponsive') DEFAULT 'new',
    `source` ENUM('linkedin', 'referral', 'website', 'job_board', 'networking', 'social_media', 'cold_outreach', 'other') DEFAULT 'other',
    `source_details` VARCHAR(255),
    `priority` ENUM('high', 'medium', 'low') DEFAULT 'medium',
    `next_follow_up` DATE,
    `last_contacted` DATE,
    
    -- Assignment & Ownership
    `assigned_to` VARCHAR(50) COMMENT 'user_code of assigned recruiter',
    
    -- Conversion Tracking
    `converted_to_candidate` BOOLEAN DEFAULT 0,
    `converted_date` TIMESTAMP NULL,
    `candidate_code` VARCHAR(50) NULL COMMENT 'Links to candidates table after conversion',
    `conversion_reason` TEXT,
    
    -- Metadata
    `is_archived` BOOLEAN DEFAULT 0,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_contact_code` (`contact_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_status_assigned` (`status`, `assigned_to`),
    INDEX `idx_source` (`source`),
    INDEX `idx_priority` (`priority`),
    INDEX `idx_next_follow_up` (`next_follow_up`),
    INDEX `idx_followup_status` (`next_follow_up`, `status`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_converted` (`converted_to_candidate`),
    INDEX `idx_candidate_code` (`candidate_code`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact tags (for categorization)
CREATE TABLE `contact_tags` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `tag_name` VARCHAR(50) UNIQUE NOT NULL,
    `tag_color` VARCHAR(7) DEFAULT '#696cff',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact tag mapping (many-to-many)
CREATE TABLE `contact_tag_map` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_code` VARCHAR(50) NOT NULL,
    `tag_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`tag_id`) REFERENCES `contact_tags`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_contact_tag` (`contact_code`, `tag_id`),
    INDEX `idx_contact_code` (`contact_code`),
    INDEX `idx_tag_id` (`tag_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact notes/activities
CREATE TABLE `contact_notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_code` VARCHAR(50) NOT NULL,
    `note` TEXT NOT NULL,
    `note_type` ENUM('general', 'call', 'meeting', 'email', 'follow_up') DEFAULT 'general',
    `is_internal` BOOLEAN DEFAULT 0 COMMENT 'Internal notes not shared',
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_code` (`contact_code`),
    INDEX `idx_note_type` (`note_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contact documents
CREATE TABLE `contact_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_code` VARCHAR(50) NOT NULL,
    `document_type` ENUM('resume', 'cover_letter', 'certificate', 'other') DEFAULT 'other',
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT COMMENT 'Size in bytes',
    `uploaded_by` VARCHAR(50),
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_contact_code` (`contact_code`),
    INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 3: CANDIDATES MODULE
-- ============================================================================

-- Candidates (enhanced)
CREATE TABLE `candidates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `can_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- Basic Information
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(20),
    `alternate_phone` VARCHAR(20),
    `linkedin_url` VARCHAR(255),
    
    -- Professional Information
    `current_company` VARCHAR(255),
    `current_title` VARCHAR(255),
    `years_of_experience` DECIMAL(4,1),
    `current_location` VARCHAR(255),
    `preferred_location` VARCHAR(255),
    `notice_period_days` INT,
    `availability_date` DATE,
    
    -- Compensation
    `current_salary` DECIMAL(12,2),
    `expected_salary` DECIMAL(12,2),
    `salary_currency` VARCHAR(3) DEFAULT 'EUR',
    
    -- Work Authorization
    `work_auth_status` VARCHAR(100),
    `visa_sponsorship_required` BOOLEAN DEFAULT 0,
    `willing_to_relocate` BOOLEAN DEFAULT 0,
    
    -- Skills & Experience
    `skills` JSON COMMENT 'Array of skills',
    `summary` TEXT COMMENT 'Professional summary',
    
    -- Lead Classification
    `lead_temperature` ENUM('hot', 'warm', 'cold') DEFAULT 'warm',
    
    -- Conversion Tracking
    `source_contact_id` VARCHAR(50) NULL COMMENT 'If converted from contact',
    `source` ENUM('contact_conversion', 'website', 'referral', 'linkedin', 'direct', 'other') DEFAULT 'other',
    
    -- Assignment & Ownership
    `assigned_to` VARCHAR(50) COMMENT 'user_code of assigned recruiter',
    
    -- CV/Resume
    `cv_path` VARCHAR(500),
    `cv_parsed` BOOLEAN DEFAULT 0,
    `cv_parsed_data` JSON,
    
    -- Status
    `status` ENUM('active', 'placed', 'archived', 'blacklisted') DEFAULT 'active',
    
    -- Metadata
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_can_code` (`can_code`),
    INDEX `idx_email` (`email`),
    INDEX `idx_status` (`status`),
    INDEX `idx_status_created` (`status`, `created_at`),
    INDEX `idx_assigned_to` (`assigned_to`),
    INDEX `idx_source_contact` (`source_contact_id`),
    INDEX `idx_lead_temp` (`lead_temperature`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidate documents
CREATE TABLE `candidate_documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `document_type` ENUM('resume', 'cover_letter', 'certificate', 'portfolio', 'other') DEFAULT 'other',
    `file_name` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT COMMENT 'Size in bytes',
    `uploaded_by` VARCHAR(50),
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_candidate_code` (`candidate_code`),
    INDEX `idx_document_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Candidate activity/notes
CREATE TABLE `candidate_activity` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `candidate_code` VARCHAR(50) NOT NULL,
    `activity_type` ENUM('note', 'call', 'email', 'meeting', 'status_change', 'document_upload') DEFAULT 'note',
    `description` TEXT NOT NULL,
    `is_internal` BOOLEAN DEFAULT 0,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_candidate_code` (`candidate_code`),
    INDEX `idx_activity_type` (`activity_type`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 4: CLIENTS & JOBS
-- ============================================================================

-- Clients (enhanced with auto-updated stats)
CREATE TABLE `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_code` VARCHAR(50) UNIQUE NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `industry` VARCHAR(100),
    `client_name` VARCHAR(255) COMMENT 'Primary contact person name',
    `contact_person` VARCHAR(255),
    `email` VARCHAR(255),
    `phone` VARCHAR(20),
    `address` TEXT,
    `city` VARCHAR(100),
    `country` VARCHAR(100),
    `account_manager` VARCHAR(50) COMMENT 'user_code of account manager',
    
    -- Auto-updated statistics (via triggers)
    `total_jobs` INT DEFAULT 0,
    `active_jobs` INT DEFAULT 0,
    `total_placements` INT DEFAULT 0,
    
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `notes` TEXT,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_client_code` (`client_code`),
    INDEX `idx_company_name` (`company_name`),
    INDEX `idx_status` (`status`),
    INDEX `idx_account_manager` (`account_manager`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jobs (enhanced)
CREATE TABLE `jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) UNIQUE NOT NULL,
    `client_code` VARCHAR(50) NOT NULL,
    `job_title` VARCHAR(255) NOT NULL,
    `job_reference` VARCHAR(100) COMMENT 'Client internal reference',
    `description` TEXT,
    `requirements` TEXT,
    `nice_to_have` TEXT,
    
    -- Compensation
    `salary_min` DECIMAL(12,2),
    `salary_max` DECIMAL(12,2),
    `salary_currency` VARCHAR(3) DEFAULT 'EUR',
    `salary_period` ENUM('hourly', 'daily', 'monthly', 'yearly') DEFAULT 'yearly',
    
    -- Location & Type
    `location` VARCHAR(255),
    `work_type` ENUM('remote', 'hybrid', 'onsite') DEFAULT 'onsite',
    `employment_type` ENUM('permanent', 'contract', 'temporary') DEFAULT 'permanent',
    `contract_duration_months` INT NULL,
    
    -- Dates
    `start_date` DATE,
    `application_deadline` DATE,
    
    -- Status
    `status` ENUM('draft', 'open', 'on_hold', 'filled', 'closed', 'cancelled') DEFAULT 'draft',
    `positions_available` INT DEFAULT 1,
    `positions_filled` INT DEFAULT 0,
    
    -- Publishing
    `is_published` BOOLEAN DEFAULT 0,
    `published_at` TIMESTAMP NULL,
    
    -- Metadata
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_job_code` (`job_code`),
    INDEX `idx_client_code` (`client_code`),
    INDEX `idx_job_title` (`job_title`),
    INDEX `idx_status` (`status`),
    INDEX `idx_client_status` (`client_code`, `status`),
    INDEX `idx_is_published` (`is_published`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 5: SUBMISSIONS MODULE (Internal Approval Workflow)
-- ============================================================================

-- Candidate Submissions (internal workflow before client sees)
CREATE TABLE `candidate_submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_code` VARCHAR(50) UNIQUE NOT NULL,
    
    -- Core Relations
    `candidate_code` VARCHAR(50) NOT NULL,
    `job_code` VARCHAR(50) NOT NULL,
    `client_code` VARCHAR(50) NOT NULL,
    
    -- Submission Details
    `submitted_by` VARCHAR(50) NOT NULL COMMENT 'Recruiter who submitted',
    `submission_type` ENUM('client_submission', 'internal_review') DEFAULT 'client_submission',
    
    -- Proposed Terms
    `proposed_rate` DECIMAL(12,2),
    `rate_type` ENUM('hourly', 'daily', 'monthly') DEFAULT 'daily',
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `availability_date` DATE,
    `contract_duration_months` INT,
    
    -- Submission Content
    `fit_reason` TEXT COMMENT 'Why this candidate is a good fit',
    `key_strengths` TEXT,
    `concerns` TEXT COMMENT 'Internal only - any concerns',
    
    -- Workflow Status
    `status` ENUM('draft', 'pending_review', 'approved', 'rejected', 'submitted', 'accepted', 'rejected_by_client', 'withdrawn') DEFAULT 'pending_review',
    
    -- Manager Review
    `reviewed_by` VARCHAR(50) COMMENT 'Manager who reviewed',
    `reviewed_at` TIMESTAMP NULL,
    `review_notes` TEXT,
    
    -- Client Submission
    `submitted_to_client_at` TIMESTAMP NULL,
    `client_notified` BOOLEAN DEFAULT 0,
    
    -- Client Response
    `client_response` ENUM('interested', 'not_interested', 'on_hold') NULL,
    `client_feedback` TEXT,
    `client_response_date` TIMESTAMP NULL,
    
    -- Conversion to Application
    `application_id` INT UNSIGNED NULL COMMENT 'If converted to application',
    `converted_to_application` BOOLEAN DEFAULT 0,
    `conversion_date` TIMESTAMP NULL,
    
    -- Documents
    `documents_attached` JSON COMMENT 'Array of document paths',
    
    -- Follow-up
    `followup_count` INT DEFAULT 0,
    
    -- Metadata
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_submission_code` (`submission_code`),
    INDEX `idx_candidate_code` (`candidate_code`),
    INDEX `idx_job_code` (`job_code`),
    INDEX `idx_client_code` (`client_code`),
    INDEX `idx_submitted_by` (`submitted_by`),
    INDEX `idx_status` (`status`),
    INDEX `idx_status_submitted` (`status`, `submitted_to_client_at`),
    INDEX `idx_reviewed_by` (`reviewed_by`),
    INDEX `idx_application_id` (`application_id`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Submission notes
CREATE TABLE `submission_notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_code` VARCHAR(50) NOT NULL,
    `note` TEXT NOT NULL,
    `note_type` ENUM('general', 'internal', 'client_feedback', 'followup') DEFAULT 'general',
    `is_internal` BOOLEAN DEFAULT 0,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_submission_code` (`submission_code`),
    INDEX `idx_note_type` (`note_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 6: APPLICATIONS MODULE (Keep Your Existing Structure)
-- ============================================================================
-- Note: Your applications module structure is already good.
-- Just ensure these columns exist for submissions integration:
-- - submission_id (link back to submissions table)
-- - source (enum including 'internal_submission')

CREATE TABLE IF NOT EXISTS `job_applications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` VARCHAR(50) UNIQUE NOT NULL,
    `can_code` VARCHAR(50) NOT NULL,
    `job_code` VARCHAR(50) NOT NULL,
    `submission_id` VARCHAR(50) NULL COMMENT 'Link to candidate_submissions if came from there',
    
    `status` ENUM('screening', 'pending_approval', 'approved', 'submitted', 'client_reviewing', 
                  'shortlisted', 'interviewing_round_1', 'interviewing_round_2', 'interviewing_round_3',
                  'interviewing_final', 'offer_pending', 'offered', 'offer_accepted', 
                  'placed', 'rejected') DEFAULT 'screening',
    
    `application_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `source` ENUM('internal_submission', 'website_apply', 'manual_add', 'referral', 'other') DEFAULT 'other',
    `referred_by` VARCHAR(50) NULL,
    
    -- Salary & Terms
    `expected_salary` DECIMAL(12,2),
    `expected_salary_currency` VARCHAR(3) DEFAULT 'EUR',
    `notice_period_days` INT,
    `availability_date` DATE,
    
    -- Documents
    `cover_letter` TEXT,
    
    -- Status Tracking
    `status_history` JSON COMMENT 'Track all status changes',
    `current_stage_since` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `interview_count` INT DEFAULT 0,
    
    -- Rejection
    `rejection_reason` VARCHAR(255),
    `rejection_feedback` TEXT,
    `rejected_at` TIMESTAMP NULL,
    
    -- Placement
    `placement_fee` DECIMAL(12,2),
    `start_date` DATE,
    `placed_at` TIMESTAMP NULL,
    
    -- Metadata
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_application_id` (`application_id`),
    INDEX `idx_can_code` (`can_code`),
    INDEX `idx_job_code` (`job_code`),
    INDEX `idx_submission_id` (`submission_id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_application_date` (`application_date`),
    INDEX `idx_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application interviews
CREATE TABLE IF NOT EXISTS `application_interviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` VARCHAR(50) NOT NULL,
    `interview_round` INT NOT NULL COMMENT '1, 2, 3, etc.',
    `interview_date` DATE NOT NULL,
    `interview_time` TIME NOT NULL,
    `interviewer_name` VARCHAR(255),
    `interviewer_email` VARCHAR(255),
    `interview_type` ENUM('phone', 'video', 'onsite', 'technical_test') DEFAULT 'video',
    `location` VARCHAR(255) COMMENT 'Office address or N/A for remote',
    `meeting_link` VARCHAR(500) COMMENT 'Zoom/Teams link',
    `duration_minutes` INT DEFAULT 60,
    `feedback` TEXT,
    `rating` TINYINT COMMENT '1-5 scale',
    `recommendation` ENUM('hire', 'reject', 'maybe', 'pending') DEFAULT 'pending',
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_application_id` (`application_id`),
    INDEX `idx_interview_date` (`interview_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application offers
CREATE TABLE IF NOT EXISTS `application_offers` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` VARCHAR(50) NOT NULL,
    `offer_date` DATE NOT NULL,
    `salary_offered` DECIMAL(12,2) NOT NULL,
    `currency` VARCHAR(3) DEFAULT 'EUR',
    `bonus` DECIMAL(12,2),
    `benefits` TEXT,
    `start_date` DATE,
    `probation_period_months` INT DEFAULT 3,
    `contract_type` ENUM('permanent', 'contract') DEFAULT 'permanent',
    `offer_letter_path` VARCHAR(500),
    `expiry_date` DATE COMMENT 'When offer expires',
    `candidate_response` ENUM('pending', 'accepted', 'rejected', 'negotiating') DEFAULT 'pending',
    `response_date` DATE,
    `notes` TEXT,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_application_id` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Application placements
CREATE TABLE IF NOT EXISTS `application_placements` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `application_id` VARCHAR(50) NOT NULL,
    `placed_date` DATE NOT NULL,
    `start_date` DATE NOT NULL,
    `probation_end_date` DATE,
    `replacement_guarantee_months` INT DEFAULT 3,
    `placement_fee_percentage` DECIMAL(5,2) DEFAULT 20.00,
    `placement_fee_amount` DECIMAL(12,2),
    `invoice_number` VARCHAR(50),
    `invoice_sent_date` DATE,
    `payment_received_date` DATE,
    `status` ENUM('active', 'completed', 'replaced') DEFAULT 'active',
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_application_id` (`application_id`),
    UNIQUE KEY `unique_application_placement` (`application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 7: STORED PROCEDURES & FUNCTIONS
-- ============================================================================

DELIMITER $$

-- Function: Generate contact code (CON-YYYYMMDD-XXXX)
DROP FUNCTION IF EXISTS fn_generate_contact_code$$
CREATE FUNCTION fn_generate_contact_code() 
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    DECLARE new_code VARCHAR(50);
    DECLARE code_exists INT;
    DECLARE counter INT DEFAULT 1;
    
    REPEAT
        SET new_code = CONCAT('CON-', DATE_FORMAT(NOW(), '%Y%m%d'), '-', LPAD(counter, 4, '0'));
        SELECT COUNT(*) INTO code_exists FROM contacts WHERE contact_code = new_code;
        SET counter = counter + 1;
    UNTIL code_exists = 0 END REPEAT;
    
    RETURN new_code;
END$$

-- Function: Check if user has permission
DROP FUNCTION IF EXISTS fn_user_has_permission$$
CREATE FUNCTION fn_user_has_permission(
    p_user_id INT UNSIGNED,
    p_permission_code VARCHAR(100)
) 
RETURNS BOOLEAN
READS SQL DATA
BEGIN
    DECLARE has_perm BOOLEAN DEFAULT FALSE;
    DECLARE user_role_id INT UNSIGNED;
    
    -- Get user's role
    SELECT role_id INTO user_role_id FROM users WHERE id = p_user_id;
    
    -- Check if user is super_admin or admin (bypass all checks)
    IF EXISTS (SELECT 1 FROM users WHERE id = p_user_id AND level IN ('super_admin', 'admin')) THEN
        RETURN TRUE;
    END IF;
    
    -- Check user-specific permissions (grants)
    IF EXISTS (
        SELECT 1 FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = p_user_id 
        AND p.permission_code = p_permission_code
        AND up.granted = 1
        AND (up.expires_at IS NULL OR up.expires_at > NOW())
    ) THEN
        RETURN TRUE;
    END IF;
    
    -- Check user-specific revocations
    IF EXISTS (
        SELECT 1 FROM user_permissions up
        JOIN permissions p ON p.id = up.permission_id
        WHERE up.user_id = p_user_id 
        AND p.permission_code = p_permission_code
        AND up.granted = 0
    ) THEN
        RETURN FALSE;
    END IF;
    
    -- Check role-based permissions
    IF user_role_id IS NOT NULL AND EXISTS (
        SELECT 1 FROM role_permissions rp
        JOIN permissions p ON p.id = rp.permission_id
        WHERE rp.role_id = user_role_id
        AND p.permission_code = p_permission_code
    ) THEN
        RETURN TRUE;
    END IF;
    
    RETURN FALSE;
END$$

-- Procedure: Auto-assign all permissions to super_admin role
DROP PROCEDURE IF EXISTS sp_assign_superadmin_permissions$$
CREATE PROCEDURE sp_assign_superadmin_permissions()
BEGIN
    DECLARE super_admin_role_id INT;
    
    SELECT id INTO super_admin_role_id FROM roles WHERE role_code = 'super_admin' LIMIT 1;
    
    IF super_admin_role_id IS NOT NULL THEN
        INSERT IGNORE INTO role_permissions (role_id, permission_id)
        SELECT super_admin_role_id, id FROM permissions;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- SECTION 8: TRIGGERS
-- ============================================================================

DELIMITER $$

-- Trigger: Auto-generate contact code before insert
DROP TRIGGER IF EXISTS trg_contacts_before_insert$$
CREATE TRIGGER trg_contacts_before_insert
BEFORE INSERT ON contacts
FOR EACH ROW
BEGIN
    IF NEW.contact_code IS NULL OR NEW.contact_code = '' THEN
        SET NEW.contact_code = fn_generate_contact_code();
    END IF;
END$$

-- Trigger: Log contact creation
DROP TRIGGER IF EXISTS trg_contacts_after_insert$$
CREATE TRIGGER trg_contacts_after_insert
AFTER INSERT ON contacts
FOR EACH ROW
BEGIN
    INSERT INTO activity_log (user_code, module, action, entity_type, entity_id, description)
    VALUES (NEW.created_by, 'contacts', 'create', 'contact', NEW.contact_code, 
            CONCAT('Created contact: ', NEW.first_name, ' ', NEW.last_name));
END$$

-- Trigger: Log contact updates
DROP TRIGGER IF EXISTS trg_contacts_after_update$$
CREATE TRIGGER trg_contacts_after_update
AFTER UPDATE ON contacts
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO activity_log (user_code, module, action, entity_type, entity_id, description)
        VALUES (NEW.created_by, 'contacts', 'status_change', 'contact', NEW.contact_code,
                CONCAT('Status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END$$

-- Trigger: Update client job counts when job is created
DROP TRIGGER IF EXISTS trg_jobs_after_insert$$
CREATE TRIGGER trg_jobs_after_insert
AFTER INSERT ON jobs
FOR EACH ROW
BEGIN
    UPDATE clients 
    SET total_jobs = total_jobs + 1,
        active_jobs = active_jobs + IF(NEW.status IN ('open', 'draft'), 1, 0)
    WHERE client_code = NEW.client_code;
END$$

-- Trigger: Update client job counts when job status changes
DROP TRIGGER IF EXISTS trg_jobs_after_update$$
CREATE TRIGGER trg_jobs_after_update
AFTER UPDATE ON jobs
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        UPDATE clients 
        SET active_jobs = active_jobs 
            - IF(OLD.status IN ('open', 'draft'), 1, 0)
            + IF(NEW.status IN ('open', 'draft'), 1, 0)
        WHERE client_code = NEW.client_code;
    END IF;
END$$

-- Trigger: Update client placement count when application is placed
DROP TRIGGER IF EXISTS trg_applications_after_update$$
CREATE TRIGGER trg_applications_after_update
AFTER UPDATE ON job_applications
FOR EACH ROW
BEGIN
    IF OLD.status != 'placed' AND NEW.status = 'placed' THEN
        UPDATE clients c
        JOIN jobs j ON j.client_code = c.client_code
        SET c.total_placements = c.total_placements + 1
        WHERE j.job_code = NEW.job_code;
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- SECTION 9: VIEWS
-- ============================================================================

-- View: User activities with role info
CREATE OR REPLACE VIEW vw_user_activities AS
SELECT 
    al.*,
    u.name as user_name,
    u.email as user_email,
    u.level as user_level
FROM activity_log al
LEFT JOIN users u ON u.user_code = al.user_code
ORDER BY al.created_at DESC;

-- View: Contact conversion rate by source
CREATE OR REPLACE VIEW vw_contact_conversion_by_source AS
SELECT 
    source,
    COUNT(*) as total_contacts,
    SUM(converted_to_candidate) as total_converted,
    ROUND(SUM(converted_to_candidate) * 100.0 / COUNT(*), 2) as conversion_rate_percent
FROM contacts
WHERE deleted_at IS NULL
GROUP BY source;

-- View: Recruiter contact performance
CREATE OR REPLACE VIEW vw_recruiter_contact_performance AS
SELECT 
    c.assigned_to as recruiter_code,
    u.name as recruiter_name,
    COUNT(*) as total_contacts,
    SUM(CASE WHEN c.status = 'qualified' THEN 1 ELSE 0 END) as qualified_contacts,
    SUM(c.converted_to_candidate) as converted_contacts,
    ROUND(SUM(c.converted_to_candidate) * 100.0 / COUNT(*), 2) as conversion_rate_percent,
    SUM(CASE WHEN c.next_follow_up < CURDATE() AND c.status NOT IN ('converted', 'not_interested') THEN 1 ELSE 0 END) as overdue_followups
FROM contacts c
LEFT JOIN users u ON u.user_code = c.assigned_to
WHERE c.deleted_at IS NULL AND c.assigned_to IS NOT NULL
GROUP BY c.assigned_to, u.name;

-- ============================================================================
-- SECTION 10: INDEXES (Performance Optimization)
-- ============================================================================

-- Composite indexes for common queries
CREATE INDEX idx_contacts_status_assigned ON contacts(status, assigned_to);
CREATE INDEX idx_contacts_followup_status ON contacts(next_follow_up, status);
CREATE INDEX idx_candidates_status_created ON candidates(status, created_at);
CREATE INDEX idx_jobs_client_status ON jobs(client_code, status);
CREATE INDEX idx_submissions_status_submitted ON candidate_submissions(status, submitted_to_client_at);
CREATE INDEX idx_applications_status_date ON job_applications(status, application_date);
CREATE INDEX idx_activity_log_user_module ON activity_log(user_id, module, created_at);

-- ============================================================================
-- SECTION 11: DEFAULT DATA
-- ============================================================================

-- Insert default roles
INSERT INTO `roles` (`role_code`, `role_name`, `description`, `is_system_role`) VALUES
('super_admin', 'Super Administrator', 'Full system access, cannot be deleted', 1),
('admin', 'Administrator', 'Full operational access, manages users', 1),
('manager', 'Manager', 'Approves submissions, views all reports', 1),
('senior_recruiter', 'Senior Recruiter', 'Full CRUD on candidates/jobs, can submit to clients', 1),
('recruiter', 'Recruiter', 'Basic recruitment operations', 1),
('coordinator', 'Coordinator', 'Schedule interviews, update statuses', 1);

-- Insert permissions (40+ granular permissions)
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
-- Contacts permissions
('contacts.view.all', 'View All Contacts', 'contacts', 'View all contacts regardless of assignment'),
('contacts.view.own', 'View Own Contacts', 'contacts', 'View only assigned contacts'),
('contacts.create', 'Create Contact', 'contacts', 'Add new contacts'),
('contacts.edit.all', 'Edit All Contacts', 'contacts', 'Edit any contact'),
('contacts.edit.own', 'Edit Own Contacts', 'contacts', 'Edit only assigned contacts'),
('contacts.delete', 'Delete Contact', 'contacts', 'Delete contacts'),
('contacts.export', 'Export Contacts', 'contacts', 'Export contact list'),
('contacts.convert', 'Convert to Candidate', 'contacts', 'Convert contact to candidate'),

-- Candidates permissions
('candidates.view.all', 'View All Candidates', 'candidates', 'View all candidates'),
('candidates.view.own', 'View Own Candidates', 'candidates', 'View only assigned candidates'),
('candidates.create', 'Create Candidate', 'candidates', 'Add new candidates'),
('candidates.edit.all', 'Edit All Candidates', 'candidates', 'Edit any candidate'),
('candidates.edit.own', 'Edit Own Candidates', 'candidates', 'Edit only assigned candidates'),
('candidates.delete', 'Delete Candidate', 'candidates', 'Delete candidates'),
('candidates.export', 'Export Candidates', 'candidates', 'Export candidate list'),
('candidates.bulk_action', 'Bulk Actions', 'candidates', 'Perform bulk operations'),
('candidates.view_salary', 'View Salary Info', 'candidates', 'View salary information'),

-- Jobs permissions
('jobs.view.all', 'View All Jobs', 'jobs', 'View all jobs'),
('jobs.create', 'Create Job', 'jobs', 'Create new jobs'),
('jobs.edit', 'Edit Job', 'jobs', 'Edit job details'),
('jobs.delete', 'Delete Job', 'jobs', 'Delete jobs'),
('jobs.close', 'Close Job', 'jobs', 'Close/fill jobs'),

-- Clients permissions
('clients.view.all', 'View All Clients', 'clients', 'View all clients'),
('clients.create', 'Create Client', 'clients', 'Add new clients'),
('clients.edit', 'Edit Client', 'clients', 'Edit client details'),
('clients.delete', 'Delete Client', 'clients', 'Delete clients'),
('clients.export', 'Export Clients', 'clients', 'Export client list'),

-- Submissions permissions
('submissions.view.all', 'View All Submissions', 'submissions', 'View all submissions'),
('submissions.view.own', 'View Own Submissions', 'submissions', 'View only own submissions'),
('submissions.create', 'Create Submission', 'submissions', 'Submit candidates to clients'),
('submissions.edit', 'Edit Submission', 'submissions', 'Edit submissions'),
('submissions.approve', 'Approve Submission', 'submissions', 'Approve submissions (manager only)'),
('submissions.delete', 'Delete Submission', 'submissions', 'Delete submissions'),

-- Applications permissions
('applications.view.all', 'View All Applications', 'applications', 'View all applications'),
('applications.view.own', 'View Own Applications', 'applications', 'View only assigned applications'),
('applications.create', 'Create Application', 'applications', 'Create applications'),
('applications.edit', 'Edit Application', 'applications', 'Edit applications'),
('applications.delete', 'Delete Application', 'applications', 'Delete applications'),

-- Reports permissions
('reports.view.dashboard', 'View Dashboard', 'reports', 'View main dashboard'),
('reports.view.daily', 'View Daily Report', 'reports', 'View daily activity report'),
('reports.view.recruiter_performance', 'View Recruiter Performance', 'reports', 'View individual performance'),
('reports.view.team_performance', 'View Team Performance', 'reports', 'View team metrics'),
('reports.view.revenue', 'View Revenue Report', 'reports', 'View financial reports'),
('reports.export', 'Export Reports', 'reports', 'Export report data'),

-- Users permissions
('users.view', 'View Users', 'users', 'View user list'),
('users.create', 'Create User', 'users', 'Add new users'),
('users.edit', 'Edit User', 'users', 'Edit user details'),
('users.delete', 'Delete User', 'users', 'Delete users'),
('users.manage_roles', 'Manage Roles', 'users', 'Create/edit roles and permissions');

-- Assign all permissions to super_admin role
CALL sp_assign_superadmin_permissions();

-- Assign permissions to other roles (selective)
-- Admin role (almost all except user management)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'admin'
AND p.permission_code NOT LIKE 'users.%';

-- Manager role (approval + viewing all)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'manager'
AND (
    p.permission_code LIKE '%.view.all' OR
    p.permission_code = 'submissions.approve' OR
    p.permission_code LIKE 'reports.%'
);

-- Senior Recruiter role
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'senior_recruiter'
AND (
    p.permission_code IN (
        'contacts.view.all', 'contacts.create', 'contacts.edit.all', 'contacts.convert',
        'candidates.view.all', 'candidates.create', 'candidates.edit.all', 'candidates.view_salary',
        'jobs.view.all', 'clients.view.all',
        'submissions.view.all', 'submissions.create', 'submissions.edit',
        'applications.view.all', 'applications.create', 'applications.edit',
        'reports.view.dashboard', 'reports.view.daily', 'reports.view.recruiter_performance'
    )
);

-- Recruiter role (own records only)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'recruiter'
AND (
    p.permission_code IN (
        'contacts.view.own', 'contacts.create', 'contacts.edit.own', 'contacts.convert',
        'candidates.view.own', 'candidates.create', 'candidates.edit.own',
        'jobs.view.all', 'clients.view.all',
        'submissions.view.own', 'submissions.create',
        'applications.view.own', 'applications.create',
        'reports.view.dashboard'
    )
);

-- Coordinator role (limited to scheduling/updates)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'coordinator'
AND (
    p.permission_code IN (
        'candidates.view.all', 'jobs.view.all', 'clients.view.all',
        'applications.view.all', 'applications.edit'
    )
);

-- Insert default admin user
INSERT INTO `users` (
    `user_code`, `role_id`, `name`, `full_name`, `email`, `password`, 
    `level`, `is_active`, `created_at`
)
SELECT 
    'USR-ADMIN-0001',
    (SELECT id FROM roles WHERE role_code = 'super_admin' LIMIT 1),
    'Admin',
    'System Administrator',
    'admin@proconsultancy.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- password: admin123
    'super_admin',
    1,
    NOW();

-- Insert default system settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_category`, `description`) VALUES
-- General settings
('company_name', 'ProConsultancy', 'text', 'general', 'Company name'),
('company_email', 'info@proconsultancy.com', 'text', 'general', 'Company email'),
('company_phone', '+32 2 123 4567', 'text', 'general', 'Company phone'),
('company_address', 'Brussels, Belgium', 'text', 'general', 'Company address'),
('currency', 'EUR', 'text', 'general', 'Default currency'),
('timezone', 'Europe/Brussels', 'text', 'general', 'System timezone'),
('date_format', 'd/m/Y', 'text', 'general', 'Date format'),

-- Branding settings
('company_logo_url', '/panel/assets/img/logo.png', 'text', 'branding', 'Company logo path'),
('company_favicon_url', '/panel/assets/img/favicon.ico', 'text', 'branding', 'Favicon path'),
('company_tagline', 'Your Recruitment Partner', 'text', 'branding', 'Company tagline'),
('theme_primary_color', '#696cff', 'color', 'branding', 'Primary brand color'),
('theme_secondary_color', '#8592a3', 'color', 'branding', 'Secondary color'),
('login_background_color', '#f5f5f9', 'color', 'branding', 'Login page background color'),
('footer_text', '© 2024 ProConsultancy. All rights reserved.', 'text', 'branding', 'Footer copyright text'),

-- Recruitment settings
('default_placement_fee_percent', '20', 'number', 'recruitment', 'Default placement fee percentage'),
('require_submission_approval', '1', 'boolean', 'recruitment', 'Require manager approval for submissions'),
('auto_generate_codes', '1', 'boolean', 'recruitment', 'Auto-generate entity codes');

-- Insert sample contact tags
INSERT INTO `contact_tags` (`tag_name`, `tag_color`) VALUES
('Hot Lead', '#ff3e1d'),
('Passive Candidate', '#ffab00'),
('Needs Follow-up', '#03c3ec'),
('Experienced', '#71dd37'),
('Senior Level', '#696cff');

-- ============================================================================
-- SECTION 12: VERIFICATION & CLEANUP
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- Verify installation
SELECT 'Database schema installation complete!' as status;
SELECT COUNT(*) as total_tables FROM information_schema.tables WHERE table_schema = DATABASE();
SELECT COUNT(*) as total_roles FROM roles;
SELECT COUNT(*) as total_permissions FROM permissions;
SELECT COUNT(*) as total_users FROM users;
SELECT COUNT(*) as super_admin_permissions FROM role_permissions 
WHERE role_id = (SELECT id FROM roles WHERE role_code = 'super_admin');

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
-- 
-- IMPORTANT NOTES:
-- 1. Default admin credentials: admin@proconsultancy.com / admin123
-- 2. CHANGE ADMIN PASSWORD IMMEDIATELY after first login!
-- 3. All system roles have is_system_role=1 and cannot be deleted via UI
-- 4. Super admin has all permissions by default
-- 5. Contact codes auto-generate as CON-YYYYMMDD-XXXX
-- 6. Client stats (total_jobs, active_jobs, total_placements) auto-update via triggers
-- 7. Activity log tracks all major actions
-- 8. Soft deletes enabled (deleted_at) for candidates, contacts, jobs, clients
--
-- NEXT STEPS:
-- 1. Login with admin credentials
-- 2. Go to Settings → Change admin password
-- 3. Create users with different roles
-- 4. Test permissions
-- 5. Start using the system!
--
-- ============================================================================