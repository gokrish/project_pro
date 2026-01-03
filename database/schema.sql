-- ============================================================================
-- PROCONSULTANCY ATS - FIAL PRODUCTION SCHEMA

-- ============================================================================
--
-- KEY FEATURES:
-- - Simplified submissions 
-- - Dual-status workflow (internal approval + client phases)
-- - Auto-update triggers for candidate/job status
-- - Complete permission system with defaults
-- - Backward compatible with existing team structure
-- - Status history tracking
--
-- INSTALLATION:
-- mysql -u root -p proconsultancy < schema.sql
--
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- Drop existing tables in correct order (respecting foreign keys)
DROP TABLE IF EXISTS `submission_status_history`;
DROP TABLE IF EXISTS `job_status_history`;
DROP TABLE IF EXISTS `candidate_status_history`;
DROP TABLE IF EXISTS `submissions`;
DROP TABLE IF EXISTS `applications`;
DROP TABLE IF EXISTS `candidate_submissions`;
DROP TABLE IF EXISTS `activity_log`;
DROP TABLE IF EXISTS `notes`;
DROP TABLE IF EXISTS `documents`;
DROP TABLE IF EXISTS `cv_notes`;
DROP TABLE IF EXISTS `cv_inbox`;
DROP TABLE IF EXISTS `cv_inbox_notes`;
DROP TABLE IF EXISTS `contact_documents`;
DROP TABLE IF EXISTS `contact_notes`;
DROP TABLE IF EXISTS `contact_tag_map`;
DROP TABLE IF EXISTS `contact_tags`;
DROP TABLE IF EXISTS `contacts`;
DROP TABLE IF EXISTS `candidate_documents`;
DROP TABLE IF EXISTS `candidate_notes`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `client_contacts`;
DROP TABLE IF EXISTS `clients`;
DROP TABLE IF EXISTS `candidates`;
DROP TABLE IF EXISTS `user_permissions`;
DROP TABLE IF EXISTS `role_permissions`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `user_sessions`;
DROP TABLE IF EXISTS `password_resets`;
DROP TABLE IF EXISTS `tokens`;
DROP TABLE IF EXISTS `roles`;
DROP TABLE IF EXISTS `settings`;
DROP TABLE IF EXISTS `email_templates`;
DROP TABLE IF EXISTS `work_authorization`;
-- ============================================================================
-- SECTION 1: AUTHENTICATION & PERMISSIONS
-- ============================================================================

-- Roles table
CREATE TABLE `roles` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_code` VARCHAR(50) UNIQUE NOT NULL,
    `role_name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_system_role` BOOLEAN DEFAULT 0 COMMENT 'Cannot be deleted',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default roles
INSERT INTO `roles` (`role_code`, `role_name`, `description`, `is_system_role`) VALUES
('super_admin', 'Super Administrator', 'Full system access', 1),
('admin', 'Administrator', 'Administrative access', 1),
('manager', 'Manager', 'Team management and approvals', 1),
('senior_recruiter', 'Senior Recruiter', 'Experienced recruiter with extended access', 1),
('recruiter', 'Recruiter', 'Standard recruiter access', 1),
('coordinator', 'Coordinator', 'Support and coordination tasks', 1);

-- Permissions table
CREATE TABLE `permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `permission_code` VARCHAR(100) UNIQUE NOT NULL,
    `permission_name` VARCHAR(150) NOT NULL,
    `module` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_permission_code` (`permission_code`),
    INDEX `idx_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default permissions
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
-- Candidate permissions
('candidates.view_all', 'View All Candidates', 'candidates', 'View all candidates in system'),
('candidates.view_own', 'View Own Candidates', 'candidates', 'View only assigned candidates'),
('candidates.create', 'Create Candidate', 'candidates', 'Add new candidates'),
('candidates.edit', 'Edit Candidate', 'candidates', 'Modify candidate information'),
('candidates.delete', 'Delete Candidate', 'candidates', 'Delete candidates'),
('candidates.assign', 'Assign Candidates', 'candidates', 'Assign candidates to recruiters'),
('candidates.qualify', 'Qualify Candidates', 'candidates', 'Mark candidates as qualified/rejected'),

-- Job permissions
('jobs.view_all', 'View All Jobs', 'jobs', 'View all jobs'),
('jobs.view_own', 'View Own Jobs', 'jobs', 'View only assigned jobs'),
('jobs.create', 'Create Job', 'jobs', 'Create new jobs'),
('jobs.edit', 'Edit Job', 'jobs', 'Modify job details'),
('jobs.delete', 'Delete Job', 'jobs', 'Delete jobs'),
('jobs.publish', 'Publish Jobs', 'jobs', 'Publish jobs to public board'),
('jobs.close', 'Close Jobs', 'jobs', 'Mark jobs as filled/closed'),

-- Client permissions
('clients.view_all', 'View All Clients', 'clients', 'View all clients'),
('clients.view_own', 'View Own Clients', 'clients', 'View only assigned clients'),
('clients.create', 'Create Client', 'clients', 'Add new clients'),
('clients.edit', 'Edit Client', 'clients', 'Modify client information'),
('clients.delete', 'Delete Client', 'clients', 'Delete clients'),

-- Submission permissions
('submissions.view_all', 'View All Submissions', 'submissions', 'View all submissions'),
('submissions.view_own', 'View Own Submissions', 'submissions', 'View only own submissions'),
('submissions.create', 'Create Submission', 'submissions', 'Submit candidates to jobs'),
('submissions.edit', 'Edit Submission', 'submissions', 'Modify submission details'),
('submissions.delete', 'Delete Submission', 'submissions', 'Delete submissions'),
('submissions.approve', 'Approve Submissions', 'submissions', 'Approve/reject submissions (Manager)'),
('submissions.send_client', 'Send to Client', 'submissions', 'Send approved submissions to client'),
('submissions.update_status', 'Update Status', 'submissions', 'Update submission status'),
('submissions.withdraw', 'Withdraw Submission', 'submissions', 'Withdraw submissions'),

-- Contact permissions
('contacts.view_all', 'View All Contacts', 'contacts', 'View all contacts'),
('contacts.view_own', 'View Own Contacts', 'contacts', 'View only assigned contacts'),
('contacts.create', 'Create Contact', 'contacts', 'Add new contacts'),
('contacts.edit', 'Edit Contact', 'contacts', 'Modify contacts'),
('contacts.delete', 'Delete Contact', 'contacts', 'Delete contacts'),
('contacts.convert', 'Convert Contact', 'contacts', 'Convert contact to candidate'),

-- User management permissions
('users.view_all', 'View All Users', 'users', 'View all users'),
('users.create', 'Create User', 'users', 'Add new users'),
('users.edit', 'Edit User', 'users', 'Modify user details'),
('users.delete', 'Delete User', 'users', 'Delete users'),
('users.manage_permissions', 'Manage Permissions', 'users', 'Assign permissions to users'),

-- Settings permissions
('settings.view', 'View Settings', 'settings', 'View system settings'),
('settings.edit', 'Edit Settings', 'settings', 'Modify system settings'),

-- Reports permissions
('reports.view', 'View Reports', 'reports', 'Access reporting module'),
('reports.export', 'Export Reports', 'reports', 'Export report data');

-- Role permissions mapping (Default permissions for each role)
CREATE TABLE `role_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `role_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Assign permissions to roles (Backward compatible defaults)
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p WHERE r.role_code = 'super_admin';

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_code = 'admin'
AND p.permission_code NOT IN ('settings.edit', 'users.delete');

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_code = 'manager'
AND p.permission_code IN (
    'candidates.view_all', 'candidates.create', 'candidates.edit', 'candidates.assign', 'candidates.qualify',
    'jobs.view_all', 'jobs.create', 'jobs.edit', 'jobs.close',
    'clients.view_all', 'clients.create', 'clients.edit',
    'submissions.view_all', 'submissions.create', 'submissions.edit', 
    'submissions.approve', 'submissions.send_client', 'submissions.update_status',
    'contacts.view_all', 'contacts.create', 'contacts.edit', 'contacts.convert',
    'reports.view', 'reports.export'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_code = 'senior_recruiter'
AND p.permission_code IN (
    'candidates.view_all', 'candidates.create', 'candidates.edit', 'candidates.qualify',
    'jobs.view_all', 'jobs.create', 'jobs.edit',
    'clients.view_all', 'clients.create', 'clients.edit',
    'submissions.view_all', 'submissions.create', 'submissions.edit', 
    'submissions.send_client', 'submissions.update_status',
    'contacts.view_all', 'contacts.create', 'contacts.edit', 'contacts.convert',
    'reports.view'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_code = 'recruiter'
AND p.permission_code IN (
    'candidates.view_own', 'candidates.create', 'candidates.edit',
    'jobs.view_all',
    'clients.view_all',
    'submissions.view_own', 'submissions.create', 'submissions.edit',
    'submissions.send_client', 'submissions.update_status',
    'contacts.view_own', 'contacts.create', 'contacts.edit', 'contacts.convert',
    'reports.view'
);

INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM roles r, permissions p 
WHERE r.role_code = 'coordinator'
AND p.permission_code IN (
    'candidates.view_own', 'candidates.create',
    'jobs.view_all',
    'clients.view_all',
    'submissions.view_own',
    'contacts.view_own', 'contacts.create'
);

-- User-specific permission overrides
CREATE TABLE `user_permissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `permission_id` INT UNSIGNED NOT NULL,
    `granted` BOOLEAN DEFAULT 1,
    `expires_at` TIMESTAMP NULL,
    `granted_by` INT UNSIGNED,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Users table
CREATE TABLE `users` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) UNIQUE NOT NULL,
    `role_id` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) UNIQUE NOT NULL,
    `phone` VARCHAR(20),
    `password` VARCHAR(255) NOT NULL,
    `password_changed_at` TIMESTAMP NULL,
    `level` ENUM('super_admin', 'admin', 'manager', 'senior_recruiter', 'recruiter', 'user') DEFAULT 'recruiter',
    `is_active` BOOLEAN DEFAULT 1,
    `last_login` TIMESTAMP NULL,
    `failed_login_attempts` INT DEFAULT 0,
    `locked_until` TIMESTAMP NULL,
    `email_verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `created_by` INT UNSIGNED,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    FOREIGN KEY (`role_id`) REFERENCES `roles`(`id`) ON DELETE SET NULL,
    INDEX `idx_users_level` (`level`),
    INDEX `idx_users_is_active` (`is_active`),
    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_user_code` (`user_code`)
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

-- ============================================================================
-- TOKENS TABLE (for authentication)
-- ============================================================================
CREATE TABLE `tokens` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_token` (`token`),
    INDEX `idx_user_code` (`user_code`),
    INDEX `idx_expires_at` (`expires_at`)
    
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PASSWORD RESETS TABLE (for forgot password)
-- ============================================================================
CREATE TABLE `password_resets` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) NOT NULL,
    `token` VARCHAR(255) NOT NULL UNIQUE,
    `expires_at` DATETIME NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX `idx_token` (`token`),
    INDEX `idx_user_code` (`user_code`),
    INDEX `idx_expires_at` (`expires_at`)
    
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 2: CORE ENTITIES 
-- ============================================================================

-- Clients 
CREATE TABLE `clients` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `client_code` VARCHAR(50) UNIQUE NOT NULL,
    `company_name` VARCHAR(255) NOT NULL,
    `contact_person` VARCHAR(255),
    `email` VARCHAR(255),
    `phone` VARCHAR(20),
    `status` ENUM('active', 'inactive') DEFAULT 'active',
    `account_manager` VARCHAR(255),
    `notes` TEXT,
    
    -- Activity counters (auto-updated)
    `total_jobs` INT DEFAULT 0,
    `total_placements` INT DEFAULT 0,
    
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    INDEX `idx_code` (`client_code`),
    INDEX `idx_company` (`company_name`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Client companies info';

-- Jobs
CREATE TABLE `jobs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) UNIQUE NOT NULL COMMENT 'Internal code (e.g., JOB20241230001)',
    `job_refno` VARCHAR(50) UNIQUE NULL COMMENT 'Public reference number for publishing',
    `client_code` VARCHAR(50) NOT NULL,
    
    -- Basic Information
    `job_title` VARCHAR(255) NOT NULL,
    `description` TEXT COMMENT 'Rich text job description',
    `notes` TEXT COMMENT 'Internal notes (not public)',
    
    -- Job Details
    `location` VARCHAR(255) DEFAULT 'Belgium',
    
    -- Salary Information (Simplified)
    `salary_min` DECIMAL(12,2) NULL COMMENT 'Minimum salary',
    `salary_max` DECIMAL(12,2) NULL COMMENT 'Maximum salary',
    `show_salary` BOOLEAN DEFAULT 0 COMMENT 'Display salary on public posting',
    
    -- Status & Workflow
    `status` ENUM(
        'draft',              -- Being created
        'pending_approval',   -- Waiting for approval
        'open',              -- Accepting submissions
        'filling',           -- Has active submissions
        'filled',            -- All positions filled
        'closed',            -- No longer active
        'cancelled'          -- Cancelled
    ) DEFAULT 'draft',
    
    -- Approval Workflow
    `approval_status` ENUM('draft', 'pending_approval', 'approved', 'rejected') DEFAULT 'draft',
    `submitted_for_approval_at` TIMESTAMP NULL,
    `approved_by` VARCHAR(50) NULL,
    `approved_at` TIMESTAMP NULL,
    `rejected_by` VARCHAR(50) NULL,
    `rejected_at` TIMESTAMP NULL,
    `rejection_reason` TEXT NULL,
    
    -- Capacity Management
    `positions_total` INT DEFAULT 1 COMMENT 'Total positions to fill',
    `positions_filled` INT DEFAULT 0 COMMENT 'Positions filled (auto-updated)',
    
    -- Publishing
    `is_published` BOOLEAN DEFAULT 0 COMMENT 'Visible on public job board',
    `published_at` TIMESTAMP NULL,
    
    -- Activity Counters (Auto-updated by triggers)
    `total_submissions` INT DEFAULT 0,
    `total_interviews` INT DEFAULT 0,
    `total_placements` INT DEFAULT 0,
    
    -- Assignment
    `assigned_recruiter` VARCHAR(50) NULL COMMENT 'Primary recruiter handling this job',
    
    -- Metadata
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    `closed_at` TIMESTAMP NULL,
    `closed_by` VARCHAR(50) NULL,
    
    -- Foreign Keys
    FOREIGN KEY (`client_code`) REFERENCES `clients`(`client_code`) ON DELETE RESTRICT,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_code`) ON DELETE RESTRICT,
    FOREIGN KEY (`assigned_recruiter`) REFERENCES `users`(`user_code`) ON DELETE SET NULL,
    FOREIGN KEY (`approved_by`) REFERENCES `users`(`user_code`) ON DELETE SET NULL,
    FOREIGN KEY (`rejected_by`) REFERENCES `users`(`user_code`) ON DELETE SET NULL,
    
    -- Indexes
    INDEX `idx_code` (`job_code`),
    INDEX `idx_refno` (`job_refno`),
    INDEX `idx_client` (`client_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_approval_status` (`approval_status`),
    INDEX `idx_published` (`is_published`),
    INDEX `idx_assigned` (`assigned_recruiter`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Job openings';


-- ============================================================================
-- SECTION 3: SUBMISSIONS
-- ============================================================================

CREATE TABLE `submissions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_code` VARCHAR(50) UNIQUE NOT NULL,
    `candidate_code` VARCHAR(50) NOT NULL,
    `job_code` VARCHAR(50) NOT NULL,
    `submitted_by` VARCHAR(50) NOT NULL COMMENT 'user_code',
    
    -- ========== DUAL STATUS SYSTEM ==========
    -- Phase 1: Internal approval workflow
    `internal_status` ENUM(
        'pending',      -- Waiting for manager approval
        'approved',     -- Manager approved
        'rejected',     -- Manager rejected
        'withdrawn'     -- Recruiter withdrew before approval
    ) DEFAULT 'pending',
    
    -- Phase 2: Client interaction workflow
    `client_status` ENUM(
        'not_sent',     -- Approved but not sent yet
        'submitted',    -- Sent to client
        'interviewing', -- Interview phase
        'offered',      -- Client made offer
        'placed',       -- Candidate started work
        'rejected',     -- Client or candidate rejected
        'withdrawn'     -- Withdrawn after sending
    ) DEFAULT 'not_sent',
    
    -- ========== NOTES ONLY (No payment fields) ==========
    `submission_notes` TEXT COMMENT 'Initial submission notes',
    
    -- ========== INTERNAL APPROVAL ==========
    `approved_by` VARCHAR(50) NULL COMMENT 'user_code',
    `approved_at` TIMESTAMP NULL,
    `approval_notes` TEXT,
    `rejection_reason` TEXT NULL,
    
    -- ========== CLIENT INTERACTION ==========
    `sent_to_client_at` TIMESTAMP NULL,
    `sent_to_client_by` VARCHAR(50) NULL COMMENT 'user_code',
    
    -- ========== INTERVIEW TRACKING ==========
    `interview_date` TIMESTAMP NULL,
    `interview_notes` TEXT COMMENT 'Recruiter will manually record details',
    `interview_result` ENUM('positive', 'neutral', 'negative') NULL,
    
    -- ========== OFFER TRACKING ==========
    `offer_date` DATE NULL,
    `offer_notes` TEXT COMMENT 'Recruiter will manually record offer details',
    
    -- ========== PLACEMENT ==========
    `placement_date` DATE NULL,
    `placement_notes` TEXT COMMENT 'Final placement details recorded by recruiter',
    
    -- ========== REJECTION/WITHDRAWAL ==========
    `rejected_date` DATE NULL,
    `rejected_by` ENUM('client', 'candidate', 'internal') NULL,
    `rejected_reason` TEXT,
    `withdrawn_date` DATE NULL,
    `withdrawal_reason` TEXT,
    
    -- Timestamps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    FOREIGN KEY (`candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE CASCADE,
    FOREIGN KEY (`job_code`) REFERENCES `jobs`(`job_code`) ON DELETE CASCADE,
    UNIQUE KEY `unique_candidate_job` (`candidate_code`, `job_code`),
    INDEX `idx_code` (`submission_code`),
    INDEX `idx_candidate` (`candidate_code`),
    INDEX `idx_job` (`job_code`),
    INDEX `idx_internal_status` (`internal_status`),
    INDEX `idx_client_status` (`client_status`),
    INDEX `idx_submitted_by` (`submitted_by`),
    INDEX `idx_approved_by` (`approved_by`),
    INDEX `idx_pending_approval` (`internal_status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Simplified submissions - NO payment fields, notes only for tracking';

-- ============================================================================
-- SECTION 4: SUPPORTING TABLES
-- ============================================================================

-- Contacts (Lead management)
CREATE TABLE IF NOT EXISTS `contacts` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `contact_code` VARCHAR(50) UNIQUE NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `company` VARCHAR(255) NULL,
    `position` VARCHAR(255) NULL,
    `source` VARCHAR(100) NULL,
    `status` ENUM('new', 'contacted', 'qualified', 'nurturing', 'converted', 'not_interested', 'unresponsive') DEFAULT 'new',
    `next_follow_up` DATE NULL,
    `notes` TEXT NULL,
    `assigned_to` VARCHAR(50) NULL,
    `created_by` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`user_code`) ON DELETE RESTRICT,
    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`user_code`) ON DELETE SET NULL,
    INDEX `idx_code` (`contact_code`),
    INDEX `idx_status` (`status`),
    INDEX `idx_assigned` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 5. CV INBOX - Career Page Applications (Linked to Jobs)
-- ============================================================================
CREATE TABLE `cv_inbox` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cv_code` VARCHAR(50) UNIQUE NOT NULL COMMENT 'CV001, CV002, etc.',
    
    -- ========== JOB REFERENCE ==========
    `job_id` INT UNSIGNED NULL COMMENT 'FK to jobs.id',
    `job_code` VARCHAR(50) NULL COMMENT 'Internal job code',
    `job_refno` VARCHAR(50) NULL COMMENT 'Public job reference (JOB-2024-001)',
    
    -- ========== APPLICANT INFO (RENAMED!) ==========
    `applicant_name` VARCHAR(255) NOT NULL,
    `applicant_email` VARCHAR(255) NOT NULL,
    -- `applicant_phone` VARCHAR(50),
    
    -- ========== CV & DOCUMENTS ==========
    `cv_path` VARCHAR(500) NOT NULL COMMENT 'Path to CV file',
    `cover_letter_path` VARCHAR(500) COMMENT 'Optional cover letter',
    
    -- ========== SOURCE ==========
    `source` ENUM(
        'Website_Career_Page',
        'Email',
        'LinkedIn',
        'Referral',
        'Direct',
        'Other'
    ) DEFAULT 'Website_Career_Page',
    
    -- ========== STATUS ==========
    `status` ENUM(
        'new',          -- Just submitted/added
        'screening',    -- Being reviewed
        'shortlisted',  -- Looks promising
        'converted',    -- Converted to candidate
        'rejected',     -- Not suitable
        'spam'          -- Marked as spam
    ) DEFAULT 'new',
    
    -- ========== REVIEW TRACKING ==========
    `reviewed_by` VARCHAR(50) COMMENT 'user_code',
    `reviewed_at` TIMESTAMP NULL,
    `review_notes` TEXT COMMENT 'Screening notes',
    `rejection_reason` TEXT COMMENT 'Why rejected',
    `quality_score` TINYINT COMMENT '1-5 rating',
    
    -- ========== ASSIGNMENT ==========
    `assigned_to` VARCHAR(50) COMMENT 'user_code',
    `assigned_at` TIMESTAMP NULL,
    
    -- ========== CONVERSION ==========
    `converted_to_candidate_code` VARCHAR(50) NULL,
    `converted_by` VARCHAR(50),
    `converted_at` TIMESTAMP NULL,
    
    -- ========== TIMESTAMPS  ==========
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL,
    
    -- ========== FOREIGN KEYS ==========
    FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`converted_to_candidate_code`) REFERENCES `candidates`(`candidate_code`) ON DELETE SET NULL,
    
    -- ========== INDEXES ==========
    INDEX `idx_cv_code` (`cv_code`),
    INDEX `idx_job_id` (`job_id`),
    INDEX `idx_job_code` (`job_code`),
    INDEX `idx_job_refno` (`job_refno`),
    INDEX `idx_status` (`status`),
    INDEX `idx_email` (`applicant_email`),
    INDEX `idx_source` (`source`),
    INDEX `idx_submitted` (`submitted_at`),
    INDEX `idx_assigned` (`assigned_to`),
    INDEX `idx_converted` (`converted_to_candidate_code`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='CV Inbox - Website Career Page and email applications';

-- ============================================================================
-- STEP 4: CREATE CV NOTES TABLE
-- ============================================================================
CREATE TABLE `cv_inbox_notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `cv_id` INT UNSIGNED NOT NULL,
    `note_type` ENUM('general', 'screening', 'call', 'email', 'meeting') DEFAULT 'general',
    `note` TEXT NOT NULL,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (`cv_id`) REFERENCES `cv_inbox`(`id`) ON DELETE CASCADE,
    INDEX `idx_cv_id` (`cv_id`),
    INDEX `idx_created` (`created_at`)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Website Queries

CREATE TABLE IF NOT EXISTS `queries` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `query_code` VARCHAR(50) UNIQUE NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `phone` VARCHAR(50) NULL,
    `message` TEXT NOT NULL,
    `type` VARCHAR(50) NULL,
    `status` ENUM('new', 'in_progress', 'responded', 'closed') DEFAULT 'new',
    `submission_date` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `handled_by` VARCHAR(50) NULL,
    `handled_at` TIMESTAMP NULL,
    INDEX `idx_code` (`query_code`),
    INDEX `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Activity Log
CREATE TABLE `activity_log` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NOT NULL,
    `entity_id` VARCHAR(50) NOT NULL,
    `description` TEXT,
    `details` JSON,
    `level` VARCHAR(20) DEFAULT 'info',
    `ip_address` VARCHAR(45),
    `user_agent` VARCHAR(255),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Notes (Universal)
CREATE TABLE `notes` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `entity_type` ENUM('candidate', 'client', 'job', 'submission', 'contact','cv_inbox') NOT NULL,
    `entity_code` VARCHAR(50) NOT NULL,
    `note_type` ENUM('general', 'call', 'meeting', 'email', 'followup') DEFAULT 'general',
    `content` TEXT NOT NULL,
    `is_important` BOOLEAN DEFAULT 0,
    `created_by` VARCHAR(50),
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_entity` (`entity_type`, `entity_code`),
    INDEX `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Documents
CREATE TABLE `documents` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `document_code` VARCHAR(50) UNIQUE NOT NULL,
    `entity_type` ENUM('candidate', 'client', 'job', 'submission') NOT NULL,
    `entity_code` VARCHAR(50) NOT NULL,
    `document_type` ENUM('resume', 'cover_letter', 'certificate', 'contract', 'other') NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `file_path` VARCHAR(500) NOT NULL,
    `file_size` INT,
    `uploaded_by` VARCHAR(50),
    `uploaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_entity` (`entity_type`, `entity_code`),
    INDEX `idx_type` (`document_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 5: STATUS HISTORY TRACKING
-- ============================================================================

CREATE TABLE `job_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_code` VARCHAR(50) NOT NULL,
    `old_status` VARCHAR(50),
    `new_status` VARCHAR(50) NOT NULL,
    `changed_by` VARCHAR(50),
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    INDEX `idx_job` (`job_code`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `submission_status_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `submission_code` VARCHAR(50) NOT NULL,
    `old_internal_status` VARCHAR(50),
    `new_internal_status` VARCHAR(50),
    `old_client_status` VARCHAR(50),
    `new_client_status` VARCHAR(50),
    `changed_by` VARCHAR(50),
    `changed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `notes` TEXT,
    INDEX `idx_submission` (`submission_code`),
    INDEX `idx_changed_at` (`changed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SECTION 6: SYSTEM TABLES
-- ============================================================================

CREATE TABLE `settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) UNIQUE NOT NULL,
    `setting_value` TEXT,
    `setting_type` ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    `setting_group` VARCHAR(50),
    `description` TEXT,
    `updated_by` INT UNSIGNED,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_key` (`setting_key`),
    INDEX `idx_group` (`setting_group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `email_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `template_code` VARCHAR(50) UNIQUE NOT NULL,
    `template_name` VARCHAR(150) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `is_active` BOOLEAN DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_code` (`template_code`),
    INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin user (password: password)
INSERT INTO `users` (`user_code`, `name`, `email`, `password`, `level`, `is_active`) VALUES
('ADMIN001', 'Admin User', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 1);
INSERT INTO `users` (`user_code`, `name`, `email`, `password`, `level`, `is_active`) VALUES
('ADM01', 'Admin User', 'admin1@test.com', 'admin123', 'admin', 1);

INSERT INTO `users` (`user_code`, `name`, `email`, `password`, `level`, `is_active`) VALUES
('USR01', 'Team User', 'user@test.com', 'user123', 'user', 1);


-- Notifications table (for Notification.php class)
CREATE TABLE IF NOT EXISTS `notifications` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_code` VARCHAR(50) NOT NULL,
    `type` ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    `title` VARCHAR(255) NOT NULL,
    `message` TEXT NOT NULL,
    `link` VARCHAR(255) NULL,
    `is_read` BOOLEAN DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `read_at` TIMESTAMP NULL,
    FOREIGN KEY (`user_code`) REFERENCES `users`(`user_code`) ON DELETE CASCADE,
    INDEX `idx_user_unread` (`user_code`, `is_read`),
    INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User notifications for system events';

-- Email Queue table (optional - for Mailer queue feature)
CREATE TABLE IF NOT EXISTS `email_queue` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `to_email` VARCHAR(255) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `body` TEXT NOT NULL,
    `template` VARCHAR(100) NULL,
    `variables` JSON NULL,
    `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    `attempts` INT DEFAULT 0,
    `last_attempt_at` TIMESTAMP NULL,
    `sent_at` TIMESTAMP NULL,
    `error_message` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Email queue for batch sending and retry logic';
-- ============================================================================
-- SECTION 7: AUTO-UPDATE TRIGGERS
-- ============================================================================

DELIMITER $$

-- Trigger: Auto-update candidate when submission created
CREATE TRIGGER `after_submission_insert`
AFTER INSERT ON `submissions`
FOR EACH ROW
BEGIN
    -- Update candidate to active
    UPDATE candidates 
    SET status = 'active',
        total_submissions = total_submissions + 1
    WHERE candidate_code = NEW.candidate_code
    AND status IN ('qualified', 'new');
    
    -- Update job
    UPDATE jobs
    SET total_submissions = total_submissions + 1,
        status = CASE 
            WHEN status = 'open' THEN 'filling'
            ELSE status
        END
    WHERE job_code = NEW.job_code;
    
    -- Log status change
    INSERT INTO candidate_status_history 
        (candidate_code, old_status, new_status, changed_by, notes)
    SELECT 
        NEW.candidate_code,
        c.status,
        'active',
        NEW.submitted_by,
        CONCAT('Submission created: ', NEW.submission_code)
    FROM candidates c
    WHERE c.candidate_code = NEW.candidate_code
    AND c.status != 'active';
END$$

-- Trigger: Auto-update when placement happens
CREATE TRIGGER `after_submission_placed`
AFTER UPDATE ON `submissions`
FOR EACH ROW
BEGIN
    IF NEW.client_status = 'placed' AND OLD.client_status != 'placed' THEN
        -- Update candidate
        UPDATE candidates
        SET status = 'placed',
            total_placements = total_placements + 1
        WHERE candidate_code = NEW.candidate_code;
        
        -- Update job
        UPDATE jobs
        SET positions_filled = positions_filled + 1,
            total_placements = total_placements + 1,
            status = CASE 
                WHEN positions_filled + 1 >= positions_total THEN 'filled'
                ELSE 'filling'
            END
        WHERE job_code = NEW.job_code;
        
        -- Update client
        UPDATE clients c
        JOIN jobs j ON c.client_code = j.client_code
        SET c.total_placements = c.total_placements + 1
        WHERE j.job_code = NEW.job_code;
        
        -- Withdraw other active submissions for this candidate
        UPDATE submissions
        SET internal_status = 'withdrawn',
            client_status = 'withdrawn',
            withdrawal_reason = 'Candidate placed elsewhere'
        WHERE candidate_code = NEW.candidate_code
        AND id != NEW.id
        AND client_status NOT IN ('placed', 'rejected', 'withdrawn');
        
        -- Log placement
        INSERT INTO candidate_status_history
            (candidate_code, old_status, new_status, changed_by, notes)
        VALUES (
            NEW.candidate_code,
            'active',
            'placed',
            NEW.approved_by,
            CONCAT('Placed via submission: ', NEW.submission_code)
        );
    END IF;
    
    -- Track interview
    IF NEW.client_status = 'interviewing' AND OLD.client_status != 'interviewing' THEN
        UPDATE candidates
        SET total_interviews = total_interviews + 1
        WHERE candidate_code = NEW.candidate_code;
        
        UPDATE jobs
        SET total_interviews = total_interviews + 1
        WHERE job_code = NEW.job_code;
    END IF;
    
    -- Track status changes in history
    IF NEW.internal_status != OLD.internal_status OR NEW.client_status != OLD.client_status THEN
        INSERT INTO submission_status_history
            (submission_code, old_internal_status, new_internal_status,
             old_client_status, new_client_status, changed_by, notes)
        VALUES (
            NEW.submission_code,
            OLD.internal_status,
            NEW.internal_status,
            OLD.client_status,
            NEW.client_status,
            NEW.approved_by,
            CONCAT('Status updated: ', OLD.client_status, ' → ', NEW.client_status)
        );
    END IF;
END$$

DELIMITER ;

-- ============================================================================
-- SECTION 8: DEFAULT DATA & COMPLETION
-- ============================================================================

SET FOREIGN_KEY_CHECKS = 1;

-- Verification query
SELECT 
    'Schema installation complete!' as message,
    (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()) as total_tables,
    (SELECT COUNT(*) FROM roles) as roles_count,
    (SELECT COUNT(*) FROM permissions) as permissions_count,
    (SELECT COUNT(*) FROM role_permissions) as role_permissions_count;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================