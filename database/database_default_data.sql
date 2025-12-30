-- ============================================================================
-- PROCONSULTANCY ATS - DEFAULT DATA INITIALIZATION
-- This script adds essential default data to the database
-- Run AFTER the main schema has been created
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- 1. DEFAULT ROLES (6 System Roles)
-- ============================================================================

INSERT INTO `roles` (`role_code`, `role_name`, `description`, `is_system_role`) VALUES
('super_admin', 'Super Administrator', 'Full system access with no restrictions', 1),
('admin', 'Administrator', 'Administrative access to most features', 1),
('manager', 'Manager', 'Team management and oversight capabilities', 1),
('senior_recruiter', 'Senior Recruiter', 'Advanced recruiting functions', 1),
('recruiter', 'Recruiter', 'Standard recruiting operations', 1),
('coordinator', 'Coordinator', 'Basic coordination and support tasks', 1);

-- ============================================================================
-- 2. DEFAULT PERMISSIONS (40+ Granular Permissions)
-- ============================================================================

-- Dashboard & Reports
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('reports.view_dashboard', 'View Dashboard', 'reports', 'Access to main dashboard'),
('reports.view_reports', 'View Reports', 'reports', 'Access to reporting section'),
('reports.view_analytics', 'View Analytics', 'reports', 'Access to analytics'),
('reports.export_reports', 'Export Reports', 'reports', 'Export reports to Excel/PDF');

-- Contacts Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('contacts.view_all', 'View All Contacts', 'contacts', 'View all contacts in system'),
('contacts.view_own', 'View Own Contacts', 'contacts', 'View only assigned contacts'),
('contacts.create', 'Create Contact', 'contacts', 'Create new contacts'),
('contacts.edit', 'Edit Any Contact', 'contacts', 'Edit any contact'),
('contacts.edit_own', 'Edit Own Contacts', 'contacts', 'Edit only assigned contacts'),
('contacts.delete', 'Delete Any Contact', 'contacts', 'Delete any contact'),
('contacts.delete_own', 'Delete Own Contacts', 'contacts', 'Delete only assigned contacts'),
('contacts.convert', 'Convert to Candidate', 'contacts', 'Convert contact to candidate'),
('contacts.export', 'Export Contacts', 'contacts', 'Export contacts to CSV/Excel'),
('contacts.import', 'Import Contacts', 'contacts', 'Import contacts from file'),
('contacts.assign', 'Assign Contacts', 'contacts', 'Assign contacts to recruiters');

-- Candidates Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('candidates.view_all', 'View All Candidates', 'candidates', 'View all candidates'),
('candidates.view_own', 'View Own Candidates', 'candidates', 'View only assigned candidates'),
('candidates.create', 'Create Candidate', 'candidates', 'Create new candidates'),
('candidates.edit', 'Edit Any Candidate', 'candidates', 'Edit any candidate'),
('candidates.edit_own', 'Edit Own Candidates', 'candidates', 'Edit only assigned candidates'),
('candidates.delete', 'Delete Any Candidate', 'candidates', 'Delete any candidate'),
('candidates.delete_own', 'Delete Own Candidates', 'candidates', 'Delete only assigned candidates'),
('candidates.export', 'Export Candidates', 'candidates', 'Export candidates'),
('candidates.import', 'Import Candidates', 'candidates', 'Import candidates'),
('candidates.assign', 'Assign Candidates', 'candidates', 'Assign candidates to recruiters'),
('candidates.view_pipeline', 'View Pipeline', 'candidates', 'View candidate pipeline'),
('candidates.parse_resume', 'Parse Resume', 'candidates', 'Use resume parsing'),
('candidates.bulk_actions', 'Bulk Actions', 'candidates', 'Perform bulk operations');

-- Jobs Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('jobs.view_all', 'View All Jobs', 'jobs', 'View all jobs'),
('jobs.view_own', 'View Own Jobs', 'jobs', 'View only own jobs'),
('jobs.create', 'Create Job', 'jobs', 'Create new jobs'),
('jobs.edit', 'Edit Any Job', 'jobs', 'Edit any job'),
('jobs.edit_own', 'Edit Own Jobs', 'jobs', 'Edit only own jobs'),
('jobs.delete', 'Delete Any Job', 'jobs', 'Delete any job'),
('jobs.delete_own', 'Delete Own Jobs', 'jobs', 'Delete only own jobs'),
('jobs.publish', 'Publish Job', 'jobs', 'Publish job posting'),
('jobs.close', 'Close Job', 'jobs', 'Close job posting'),
('jobs.export', 'Export Jobs', 'jobs', 'Export jobs');

-- Clients Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('clients.view_all', 'View All Clients', 'clients', 'View all clients'),
('clients.view_own', 'View Own Clients', 'clients', 'View only assigned clients'),
('clients.create', 'Create Client', 'clients', 'Create new clients'),
('clients.edit', 'Edit Any Client', 'clients', 'Edit any client'),
('clients.edit_own', 'Edit Own Clients', 'clients', 'Edit only assigned clients'),
('clients.delete', 'Delete Any Client', 'clients', 'Delete any client'),
('clients.delete_own', 'Delete Own Clients', 'clients', 'Delete only assigned clients'),
('clients.export', 'Export Clients', 'clients', 'Export clients');

-- Submissions Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('submissions.view_all', 'View All Submissions', 'submissions', 'View all submissions'),
('submissions.view_own', 'View Own Submissions', 'submissions', 'View only own submissions'),
('submissions.create', 'Create Submission', 'submissions', 'Create new submissions'),
('submissions.edit', 'Edit Any Submission', 'submissions', 'Edit any submission'),
('submissions.edit_own', 'Edit Own Submissions', 'submissions', 'Edit only own submissions'),
('submissions.delete', 'Delete Any Submission', 'submissions', 'Delete any submission'),
('submissions.delete_own', 'Delete Own Submissions', 'submissions', 'Delete only own submissions'),
('submissions.approve', 'Approve Submission', 'submissions', 'Approve submissions'),
('submissions.reject', 'Reject Submission', 'submissions', 'Reject submissions'),
('submissions.client_response', 'Record Client Response', 'submissions', 'Record client feedback'),
('submissions.convert_to_application', 'Convert to Application', 'submissions', 'Convert approved submission');

-- Applications Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('applications.view_all', 'View All Applications', 'applications', 'View all applications'),
('applications.view_own', 'View Own Applications', 'applications', 'View only own applications'),
('applications.create', 'Create Application', 'applications', 'Create new applications'),
('applications.edit', 'Edit Any Application', 'applications', 'Edit any application'),
('applications.edit_own', 'Edit Own Applications', 'applications', 'Edit only own applications'),
('applications.delete', 'Delete Any Application', 'applications', 'Delete any application'),
('applications.delete_own', 'Delete Own Applications', 'applications', 'Delete only own applications'),
('applications.schedule_interview', 'Schedule Interview', 'applications', 'Schedule interviews'),
('applications.make_offer', 'Make Offer', 'applications', 'Create job offers'),
('applications.update_status', 'Update Status', 'applications', 'Update application status'),
('applications.view_pipeline', 'View Pipeline', 'applications', 'View application pipeline'),
('applications.place_candidate', 'Place Candidate', 'applications', 'Complete placement');

-- CV Inbox Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('cv_inbox.view_all', 'View All CVs', 'cv_inbox', 'View all CV inbox items'),
('cv_inbox.view_own', 'View Own CVs', 'cv_inbox', 'View only assigned CVs'),
('cv_inbox.add_manual', 'Add Manual CV', 'cv_inbox', 'Manually add CV'),
('cv_inbox.convert', 'Convert CV', 'cv_inbox', 'Convert CV to candidate'),
('cv_inbox.delete', 'Delete CV', 'cv_inbox', 'Delete CV from inbox'),
('cv_inbox.assign', 'Assign CV', 'cv_inbox', 'Assign CV to recruiter');

-- Users & Roles Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('users.view_all', 'View All Users', 'users', 'View all users'),
('users.create', 'Create User', 'users', 'Create new users'),
('users.edit', 'Edit User', 'users', 'Edit user details'),
('users.delete', 'Delete User', 'users', 'Delete users'),
('users.reset_password', 'Reset Password', 'users', 'Reset user passwords'),
('users.toggle_status', 'Toggle User Status', 'users', 'Activate/deactivate users'),
('users.manage_roles', 'Manage Roles', 'users', 'Create/edit/delete roles'),
('users.manage_permissions', 'Manage Permissions', 'users', 'Assign permissions to roles'),
('users.view_activity', 'View Activity Log', 'users', 'View user activity logs');

-- Settings Module
INSERT INTO `permissions` (`permission_code`, `permission_name`, `module`, `description`) VALUES
('settings.view_general', 'View General Settings', 'settings', 'View general settings'),
('settings.edit_general', 'Edit General Settings', 'settings', 'Edit general settings'),
('settings.view_branding', 'View Branding', 'settings', 'View branding settings'),
('settings.edit_branding', 'Edit Branding', 'settings', 'Edit branding settings'),
('settings.view_email', 'View Email Settings', 'settings', 'View email settings'),
('settings.edit_email', 'Edit Email Settings', 'settings', 'Edit email settings'),
('settings.view_recruitment', 'View Recruitment Settings', 'settings', 'View recruitment settings'),
('settings.edit_recruitment', 'Edit Recruitment Settings', 'settings', 'Edit recruitment settings'),
('settings.view_security', 'View Security Settings', 'settings', 'View security settings'),
('settings.edit_security', 'Edit Security Settings', 'settings', 'Edit security settings'),
('settings.view_integrations', 'View Integrations', 'settings', 'View integration settings'),
('settings.edit_integrations', 'Edit Integrations', 'settings', 'Edit integration settings');

-- ============================================================================
-- 3. ROLE-PERMISSION MAPPINGS
-- ============================================================================

-- Super Admin: ALL PERMISSIONS
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'super_admin';

-- Admin: All except some system settings
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'admin'
AND p.permission_code NOT IN (
    'settings.edit_security',
    'users.manage_roles',
    'users.manage_permissions'
);

-- Manager: Team oversight permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'manager'
AND p.permission_code IN (
    -- Reports
    'reports.view_dashboard',
    'reports.view_reports',
    'reports.view_analytics',
    'reports.export_reports',
    -- Contacts
    'contacts.view_all',
    'contacts.create',
    'contacts.edit',
    'contacts.delete_own',
    'contacts.convert',
    'contacts.export',
    'contacts.assign',
    -- Candidates
    'candidates.view_all',
    'candidates.create',
    'candidates.edit',
    'candidates.delete_own',
    'candidates.export',
    'candidates.assign',
    'candidates.view_pipeline',
    'candidates.bulk_actions',
    -- Jobs
    'jobs.view_all',
    'jobs.create',
    'jobs.edit',
    'jobs.delete_own',
    'jobs.publish',
    'jobs.close',
    -- Clients
    'clients.view_all',
    'clients.create',
    'clients.edit',
    'clients.export',
    -- Submissions
    'submissions.view_all',
    'submissions.create',
    'submissions.edit',
    'submissions.approve',
    'submissions.reject',
    'submissions.client_response',
    'submissions.convert_to_application',
    -- Applications
    'applications.view_all',
    'applications.create',
    'applications.edit',
    'applications.schedule_interview',
    'applications.make_offer',
    'applications.update_status',
    'applications.view_pipeline',
    'applications.place_candidate',
    -- CV Inbox
    'cv_inbox.view_all',
    'cv_inbox.convert',
    'cv_inbox.assign',
    -- Users
    'users.view_all'
);

-- Senior Recruiter: Advanced recruiting permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'senior_recruiter'
AND p.permission_code IN (
    -- Reports
    'reports.view_dashboard',
    'reports.view_reports',
    -- Contacts
    'contacts.view_all',
    'contacts.view_own',
    'contacts.create',
    'contacts.edit_own',
    'contacts.delete_own',
    'contacts.convert',
    'contacts.export',
    -- Candidates
    'candidates.view_all',
    'candidates.create',
    'candidates.edit',
    'candidates.delete_own',
    'candidates.export',
    'candidates.view_pipeline',
    'candidates.parse_resume',
    'candidates.bulk_actions',
    -- Jobs
    'jobs.view_all',
    'jobs.create',
    'jobs.edit_own',
    -- Clients
    'clients.view_all',
    'clients.view_own',
    'clients.create',
    'clients.edit_own',
    -- Submissions
    'submissions.view_all',
    'submissions.view_own',
    'submissions.create',
    'submissions.edit_own',
    'submissions.delete_own',
    -- Applications
    'applications.view_all',
    'applications.view_own',
    'applications.create',
    'applications.edit_own',
    'applications.schedule_interview',
    'applications.make_offer',
    'applications.update_status',
    'applications.view_pipeline',
    -- CV Inbox
    'cv_inbox.view_all',
    'cv_inbox.add_manual',
    'cv_inbox.convert'
);

-- Recruiter: Standard recruiting permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'recruiter'
AND p.permission_code IN (
    -- Reports
    'reports.view_dashboard',
    -- Contacts
    'contacts.view_own',
    'contacts.create',
    'contacts.edit_own',
    'contacts.delete_own',
    'contacts.convert',
    -- Candidates
    'candidates.view_own',
    'candidates.create',
    'candidates.edit_own',
    'candidates.view_pipeline',
    'candidates.parse_resume',
    -- Jobs
    'jobs.view_all',
    -- Clients
    'clients.view_own',
    -- Submissions
    'submissions.view_own',
    'submissions.create',
    'submissions.edit_own',
    -- Applications
    'applications.view_own',
    'applications.create',
    'applications.edit_own',
    'applications.schedule_interview',
    'applications.update_status',
    -- CV Inbox
    'cv_inbox.view_own',
    'cv_inbox.add_manual',
    'cv_inbox.convert'
);

-- Coordinator: Basic support permissions
INSERT INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.role_code = 'coordinator'
AND p.permission_code IN (
    -- Reports
    'reports.view_dashboard',
    -- Contacts
    'contacts.view_own',
    'contacts.create',
    'contacts.edit_own',
    -- Candidates
    'candidates.view_own',
    'candidates.create',
    -- Jobs
    'jobs.view_all',
    -- Applications
    'applications.view_own',
    'applications.schedule_interview',
    -- CV Inbox
    'cv_inbox.view_own',
    'cv_inbox.add_manual'
);

-- ============================================================================
-- 4. DEFAULT SUPER ADMIN USER
-- Password: Admin@123
-- IMPORTANT: Change this password after first login!
-- ============================================================================

INSERT INTO `users` (
    `user_code`, 
    `role_id`, 
    `name`, 
    `full_name`, 
    `email`, 
    `password`, 
    `level`, 
    `is_active`, 
    `email_verified_at`
) VALUES (
    'USR-20241227-0001',
    (SELECT id FROM roles WHERE role_code = 'super_admin'),
    'Administrator',
    'System Administrator',
    'admin@proconsultancy.be',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Admin@123
    'super_admin',
    1,
    NOW()
);

-- ============================================================================
-- 5. DEFAULT SYSTEM SETTINGS
-- ============================================================================

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `setting_category`, `description`) VALUES
-- General
('app_name', 'ProConsultancy', 'text', 'general', 'Application name'),
('company_name', 'ProConsultancy', 'text', 'general', 'Company name'),
('company_address', 'Brussels, Belgium', 'text', 'general', 'Company address'),
('company_phone', '+32 2 XXX XX XX', 'text', 'general', 'Company phone number'),
('company_email', 'info@proconsultancy.be', 'text', 'general', 'Company email'),
('timezone', 'Europe/Brussels', 'text', 'general', 'System timezone'),
('date_format', 'd/m/Y', 'text', 'general', 'Date display format'),
('items_per_page', '25', 'number', 'general', 'Default items per page'),

-- Branding
('primary_color', '#696cff', 'color', 'branding', 'Primary brand color'),
('secondary_color', '#8592a3', 'color', 'branding', 'Secondary brand color'),
('logo_url', '', 'text', 'branding', 'Company logo URL'),
('favicon_url', '', 'text', 'branding', 'Favicon URL'),

-- Email
('smtp_host', 'localhost', 'text', 'email', 'SMTP server host'),
('smtp_port', '587', 'number', 'email', 'SMTP server port'),
('smtp_username', '', 'text', 'email', 'SMTP username'),
('smtp_password', '', 'text', 'email', 'SMTP password'),
('smtp_encryption', 'tls', 'text', 'email', 'SMTP encryption (tls/ssl)'),
('from_email', 'noreply@proconsultancy.be', 'text', 'email', 'From email address'),
('from_name', 'ProConsultancy', 'text', 'email', 'From name'),

-- Recruitment
('candidate_code_prefix', 'CAN', 'text', 'recruitment', 'Candidate code prefix'),
('job_code_prefix', 'JOB', 'text', 'recruitment', 'Job code prefix'),
('client_code_prefix', 'CLI', 'text', 'recruitment', 'Client code prefix'),
('submission_code_prefix', 'SUB', 'text', 'recruitment', 'Submission code prefix'),
('application_code_prefix', 'APP', 'text', 'recruitment', 'Application code prefix'),
('default_currency', 'EUR', 'text', 'recruitment', 'Default currency'),
('enable_resume_parsing', '1', 'boolean', 'recruitment', 'Enable resume parsing'),
('enable_email_notifications', '1', 'boolean', 'recruitment', 'Enable email notifications'),

-- Security
('password_min_length', '8', 'number', 'security', 'Minimum password length'),
('password_require_special', '1', 'boolean', 'security', 'Require special characters'),
('password_require_number', '1', 'boolean', 'security', 'Require numbers'),
('password_require_uppercase', '1', 'boolean', 'security', 'Require uppercase'),
('session_lifetime', '1800', 'number', 'security', 'Session lifetime (seconds)'),
('max_login_attempts', '5', 'number', 'security', 'Maximum login attempts'),
('account_lock_duration', '30', 'number', 'security', 'Account lock duration (minutes)');

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Count roles
SELECT 'Roles Created' as Status, COUNT(*) as Count FROM roles;

-- Count permissions
SELECT 'Permissions Created' as Status, COUNT(*) as Count FROM permissions;

-- Count role-permission mappings
SELECT 'Role-Permission Mappings' as Status, COUNT(*) as Count FROM role_permissions;

-- Count users
SELECT 'Users Created' as Status, COUNT(*) as Count FROM users;

-- Count settings
SELECT 'System Settings' as Status, COUNT(*) as Count FROM system_settings;

-- Show role breakdown
SELECT 
    r.role_name,
    COUNT(rp.id) as permission_count
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
GROUP BY r.id, r.role_name
ORDER BY r.id;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- INSTALLATION COMPLETE
-- ============================================================================

SELECT '✓ Database initialization completed successfully!' as Status;
SELECT 'Default Login: admin@proconsultancy.be' as Info;
SELECT 'Default Password: Admin@123' as Info;
SELECT '⚠ IMPORTANT: Change the default password after first login!' as Warning;
