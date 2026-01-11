-- Enterprise Upgrade Schema Changes

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Submissions Table
CREATE TABLE IF NOT EXISTS `submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `candidate_id` INT UNSIGNED NOT NULL,
  `job_id` INT UNSIGNED NOT NULL,
  `client_id` INT UNSIGNED NOT NULL,
  `recruiter_id` INT UNSIGNED NOT NULL,
  `status` ENUM('draft', 'submitted', 'client_review', 'interview', 'rejected', 'offer', 'placed') DEFAULT 'draft',
  `salary_expectation` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `is_active` BOOLEAN DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL,
  
  -- Foreign Keys
  FOREIGN KEY (`candidate_id`) REFERENCES `candidates`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`job_id`) REFERENCES `jobs`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`recruiter_id`) REFERENCES `users`(`id`),
  
  -- Indexes for Reports
  INDEX `idx_status` (`status`),
  INDEX `idx_recruiter` (`recruiter_id`),
  INDEX `idx_client` (`client_id`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Submission History (Audit Trail)
CREATE TABLE IF NOT EXISTS `submission_history` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL, -- Who made the change
  `from_status` VARCHAR(50) NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `comment` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (`submission_id`) REFERENCES `submissions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Update Users Table
-- Check if columns exist before adding (using a procedure or just try/catch in script, but simple ADD COLUMN IF NOT EXISTS is MariaDB specific, MySQL 8.0 support valid)
-- We will use safe alter statements that technically might fail if exists but we'll ignore specific errors in PHP script or just rely on 'IF NOT EXISTS' if using recent MySQL.
-- Standard MySQL doesn't support IF NOT EXISTS in ALTER TABLE easily without procedure.
-- We will assume they don't exist since we built it.

ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `remember_token`;
ALTER TABLE `users` ADD COLUMN `timezone` VARCHAR(50) DEFAULT 'UTC' AFTER `avatar_path`;
ALTER TABLE `users` ADD COLUMN `is_active` BOOLEAN DEFAULT 1 AFTER `timezone`;

-- 4. Migrate old candidate_jobs data to submissions?
-- The user said "Preserve existing data".
-- Ideally we should migrate data from `candidate_jobs` to `submissions` if we are replacing it.
-- Or `candidate_jobs` IS the simple application tracking, and `submissions` is the advanced one? 
-- The user said "New submissions table (or extend existing)".
-- `candidate_jobs` has `candidate_id`, `job_id`, `status`.
-- `submissions` adds `client_id`, `recruiter_id`.
-- We will keep `candidate_jobs` for direct applications (if any) or maybe we should migrate it.
-- For now, we will treat `submissions` as the "Recruiter Submission" entity which is richer.
-- Existing `candidate_jobs` can be considered "Applications".
-- We will leave `candidate_jobs` alone to not break existing flow, but forward-looking we might merge them.

SET FOREIGN_KEY_CHECKS = 1;
