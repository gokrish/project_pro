-- Search & Filter Indexes

SET FOREIGN_KEY_CHECKS = 0;

-- 1. Candidates Indexes
-- Fulltext for search
ALTER TABLE `candidates` ADD FULLTEXT INDEX `ft_candidate_search` (`first_name`, `last_name`, `email`, `skills_text`, `summary`);

-- Standard indexes for filtering
ALTER TABLE `candidates` ADD INDEX `idx_status` (`status`);
ALTER TABLE `candidates` ADD INDEX `idx_source` (`source`);

-- 2. Jobs Indexes
-- Fulltext for search
ALTER TABLE `jobs` ADD FULLTEXT INDEX `ft_job_search` (`title`, `description`, `location`);

-- Standard indexes for filtering
ALTER TABLE `jobs` ADD INDEX `idx_job_status` (`status`);
ALTER TABLE `jobs` ADD INDEX `idx_client_id` (`client_id`);
ALTER TABLE `jobs` ADD INDEX `idx_location` (`location`);

-- 3. Submissions Indexes (already added partly, reinforcing)
-- We already added idx_status, idx_recruiter, idx_client in previous step.
-- Adding composite if needed for dashboard filtering, but single column usually enough for small datasets.

SET FOREIGN_KEY_CHECKS = 1;
