-- Recruiter Assignment Schema

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add recruiter_id to jobs
-- This allows assigning a job to a specific recruiter user, distinct from who created it.
ALTER TABLE `jobs` ADD COLUMN `recruiter_id` INT UNSIGNED NULL;
ALTER TABLE `jobs` ADD CONSTRAINT `fk_jobs_recruiter` FOREIGN KEY (`recruiter_id`) REFERENCES `users`(`id`) ON DELETE SET NULL;
ALTER TABLE `jobs` ADD INDEX `idx_job_recruiter` (`recruiter_id`);

SET FOREIGN_KEY_CHECKS = 1;
