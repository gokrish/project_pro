<?php
/**
 * Application Constants
 * Defines all constants used throughout the application
 * 
 * @version 2.0
 */

// Prevent direct access
if (!defined('PANEL_ACCESS')) {
    die('Direct access not permitted');
}

// ============================================================================
// USER ROLES & LEVELS
// ============================================================================
define('ROLE_SUPER_ADMIN', 'super_admin');
define('ROLE_ADMIN', 'admin');
define('ROLE_MANAGER', 'manager');
define('ROLE_SENIOR_RECRUITER', 'senior_recruiter');
define('ROLE_RECRUITER', 'recruiter');
define('ROLE_COORDINATOR', 'coordinator');
define('ROLE_USER', 'user');
// define('APP_VERSION', '2.0.0');
define('DEBUG_MODE', $_ENV['APP_DEBUG'] ?? false);
// define('LOG_LEVEL', $_ENV['LOG_LEVEL'] ?? 'error');
// define('MAX_LOGIN_ATTEMPTS', 5);
define('SESSION_TIMEOUT', 3600);
define('CSRF_TOKEN_EXPIRE', 7200);
// All available roles
define('ALL_ROLES', [
    ROLE_SUPER_ADMIN,
    ROLE_ADMIN,
    ROLE_MANAGER,
    ROLE_SENIOR_RECRUITER,
    ROLE_RECRUITER,
    ROLE_COORDINATOR,
    ROLE_USER
]);

// Role display names
define('ROLE_NAMES', [
    ROLE_SUPER_ADMIN => 'Super Administrator',
    ROLE_ADMIN => 'Administrator',
    ROLE_MANAGER => 'Manager',
    ROLE_SENIOR_RECRUITER => 'Senior Recruiter',
    ROLE_RECRUITER => 'Recruiter',
    ROLE_COORDINATOR => 'Coordinator'
]);

// ============================================================================
// MODULE PERMISSIONS
// ============================================================================

/**
 * Complete permission structure for all modules
 * Format: 'module' => ['action1', 'action2', ...]
 */
define('MODULE_PERMISSIONS', [
    // Dashboard & Reports
    'reports' => [
        'view_dashboard',
        'view_own',
        'view_all',
        'export'
    ],
    // Contacts Module
    'contacts' => [
        'view_all',
        'view_own',
        'create',
        'edit',
        'edit_own',
        'delete',
        'delete_own',
        'convert',
        'export',
        'import',
        'assign'
    ],
    
    // Candidates Module
    'candidates' => [
        'view_all',
        'view_own',
        'create',
        'edit',
        'edit_own',
        'delete',
        'delete_own',
        'export',
        'import',
        'assign',
        'view_pipeline',
        'parse_resume',
        'bulk_actions'
    ],
    
    // Jobs Module
    'jobs' => [
        'view_all',
        'view_own',
        'create',
        'edit',
        'edit_own',
        'delete',
        'delete_own',
        'publish',
        'close',
        'export'
    ],
    
    // Clients Module
    'clients' => [
        'view_all',
        'view_own',
        'create',
        'edit',
        'edit_own',
        'delete',
        'delete_own',
        'export'
    ],
    
    // Submissions Module
    'submissions' => [
        'view_all',
        'view_own',
        'create',
        'edit',
        'edit_own',
        'delete',
        'delete_own',
        'approve',
        'reject',
        'client_response',
        'convert_to_application'
    ],
    
    
    // CV Inbox Module
    'cv_inbox' => [
        'view_all',
        'view_own',
        'add_manual',
        'convert',
        'delete',
        'assign'
    ],
    
    // Users & Roles Module
    'users' => [
        'view_all',
        'create',
        'edit',
        'delete',
        'reset_password',
        'toggle_status',
        'manage_roles',
        'manage_permissions',
        'view_activity'
    ],
    
    // Settings Module
    'settings' => [
        'view_general',
        'edit_general',
        'view_branding',
        'edit_branding',
        'view_email',
        'edit_email',
        'view_recruitment',
        'edit_recruitment',
        'view_security',
        'edit_security',
        'view_integrations',
        'edit_integrations'
    ]
]);

// ============================================================================
// CONTACT STATUSES
// ============================================================================
define('CONTACT_STATUS_NEW', 'new');
define('CONTACT_STATUS_CONTACTED', 'contacted');
define('CONTACT_STATUS_QUALIFIED', 'qualified');
define('CONTACT_STATUS_NURTURING', 'nurturing');
define('CONTACT_STATUS_CONVERTED', 'converted');
define('CONTACT_STATUS_NOT_INTERESTED', 'not_interested');
define('CONTACT_STATUS_UNRESPONSIVE', 'unresponsive');

define('CONTACT_STATUSES', [
    CONTACT_STATUS_NEW => 'New',
    CONTACT_STATUS_CONTACTED => 'Contacted',
    CONTACT_STATUS_QUALIFIED => 'Qualified',
    CONTACT_STATUS_NURTURING => 'Nurturing',
    CONTACT_STATUS_CONVERTED => 'Converted',
    CONTACT_STATUS_NOT_INTERESTED => 'Not Interested',
    CONTACT_STATUS_UNRESPONSIVE => 'Unresponsive'
]);

// ============================================================================
// CONTACT SOURCES
// ============================================================================
define('CONTACT_SOURCE_LINKEDIN', 'linkedin');
define('CONTACT_SOURCE_REFERRAL', 'referral');
define('CONTACT_SOURCE_WEBSITE', 'website');
define('CONTACT_SOURCE_JOB_BOARD', 'job_board');
define('CONTACT_SOURCE_NETWORKING', 'networking');
define('CONTACT_SOURCE_SOCIAL_MEDIA', 'social_media');
define('CONTACT_SOURCE_COLD_OUTREACH', 'cold_outreach');
define('CONTACT_SOURCE_OTHER', 'other');

define('CONTACT_SOURCES', [
    CONTACT_SOURCE_LINKEDIN => 'LinkedIn',
    CONTACT_SOURCE_REFERRAL => 'Referral',
    CONTACT_SOURCE_WEBSITE => 'Website',
    CONTACT_SOURCE_JOB_BOARD => 'Job Board',
    CONTACT_SOURCE_NETWORKING => 'Networking Event',
    CONTACT_SOURCE_SOCIAL_MEDIA => 'Social Media',
    CONTACT_SOURCE_COLD_OUTREACH => 'Cold Outreach',
    CONTACT_SOURCE_OTHER => 'Other'
]);

// ============================================================================
// PRIORITY LEVELS
// ============================================================================
define('PRIORITY_HIGH', 'high');
define('PRIORITY_MEDIUM', 'medium');
define('PRIORITY_LOW', 'low');

define('PRIORITIES', [
    PRIORITY_HIGH => 'High',
    PRIORITY_MEDIUM => 'Medium',
    PRIORITY_LOW => 'Low'
]);

// ============================================================================
// CANDIDATE STATUSES
// ============================================================================
define('CANDIDATE_STATUS_NEW', 'new');
define('CANDIDATE_STATUS_SCREENING', 'screening');
define('CANDIDATE_STATUS_QUALIFIED', 'qualified');
define('CANDIDATE_STATUS_ACTIVE', 'active');
define('CANDIDATE_STATUS_PLACED', 'placed');
define('CANDIDATE_STATUS_REJECTED', 'rejected');
define('CANDIDATE_STATUS_ARCHIVED', 'archived');

define('CANDIDATE_STATUSES', [
    CANDIDATE_STATUS_NEW => 'New',
    CANDIDATE_STATUS_SCREENING => 'Screening',
    CANDIDATE_STATUS_QUALIFIED => 'Qualified',
    CANDIDATE_STATUS_ACTIVE => 'Active',
    CANDIDATE_STATUS_PLACED => 'Placed',
    CANDIDATE_STATUS_REJECTED => 'Rejected',
    CANDIDATE_STATUS_ARCHIVED => 'Archived'
]);

// Status workflow transitions
define('CANDIDATE_STATUS_TRANSITIONS', [
    'new' => ['screening', 'rejected', 'archived'],
    'screening' => ['qualified', 'rejected', 'archived'],
    'qualified' => ['active', 'archived'],
    'active' => ['placed', 'qualified', 'archived'],
    'placed' => ['archived'],
    'rejected' => ['archived'],
    'archived' => ['new']  // Can reactivate
]);

// Lead types (separate from status)
define('LEAD_TYPE_HOT', 'Hot');
define('LEAD_TYPE_WARM', 'Warm');
define('LEAD_TYPE_COLD', 'Cold');
define('LEAD_TYPE_BLACKLIST', 'Blacklist');

define('LEAD_TYPES', [
    LEAD_TYPE_HOT => 'Hot',
    LEAD_TYPE_WARM => 'Warm',
    LEAD_TYPE_COLD => 'Cold',
    LEAD_TYPE_BLACKLIST => 'Blacklist'
]);

// Lead type roles
define('LEAD_TYPE_ROLES', [
    'Payroll' => 'Payroll',
    'Recruitment' => 'Recruitment',
    'InProgress' => 'In Progress',
    'WaitingConfirmation' => 'Waiting Confirmation'
]);


// ============================================================================
// JOB STATUSES
// ============================================================================
define('JOB_STATUS_DRAFT', 'draft');
define('JOB_STATUS_OPEN', 'open');
define('JOB_STATUS_ON_HOLD', 'on_hold');
define('JOB_STATUS_FILLED', 'filled');
define('JOB_STATUS_CLOSED', 'closed');
define('JOB_STATUS_CANCELLED', 'cancelled');

define('JOB_STATUSES', [
    JOB_STATUS_DRAFT => 'Draft',
    JOB_STATUS_OPEN => 'Open',
    JOB_STATUS_ON_HOLD => 'On Hold',
    JOB_STATUS_FILLED => 'Filled',
    JOB_STATUS_CLOSED => 'Closed',
    JOB_STATUS_CANCELLED => 'Cancelled'
]);

// ============================================================================
// JOB TYPES
// ============================================================================
define('JOB_TYPE_PERMANENT', 'permanent');
define('JOB_TYPE_CONTRACT', 'contract');
define('JOB_TYPE_TEMPORARY', 'temporary');
define('JOB_TYPE_FREELANCE', 'freelance');
define('JOB_TYPE_INTERNSHIP', 'internship');

define('JOB_TYPES', [
    JOB_TYPE_PERMANENT => 'Permanent',
    JOB_TYPE_CONTRACT => 'Contract',
    JOB_TYPE_TEMPORARY => 'Temporary',
    JOB_TYPE_FREELANCE => 'Freelance',
    JOB_TYPE_INTERNSHIP => 'Internship'
]);

// ============================================================================
// SUBMISSION STATUSES
// ============================================================================
define('SUBMISSION_STATUS_PENDING_REVIEW', 'pending_review');
define('SUBMISSION_STATUS_APPROVED', 'approved');
define('SUBMISSION_STATUS_REJECTED', 'rejected');
define('SUBMISSION_STATUS_SUBMITTED', 'submitted');
define('SUBMISSION_STATUS_ACCEPTED', 'accepted');
define('SUBMISSION_STATUS_REJECTED_BY_CLIENT', 'rejected_by_client');
define('SUBMISSION_STATUS_WITHDRAWN', 'withdrawn');

define('SUBMISSION_STATUSES', [
    SUBMISSION_STATUS_PENDING_REVIEW => 'Pending Review',
    SUBMISSION_STATUS_APPROVED => 'Approved (Ready to Submit)',
    SUBMISSION_STATUS_REJECTED => 'Rejected',
    SUBMISSION_STATUS_SUBMITTED => 'Submitted to Client',
    SUBMISSION_STATUS_ACCEPTED => 'Accepted by Client',
    SUBMISSION_STATUS_REJECTED_BY_CLIENT => 'Rejected by Client',
    SUBMISSION_STATUS_WITHDRAWN => 'Withdrawn'
]);


// ============================================================================
// INTERVIEW STATUSES
// ============================================================================
define('INTERVIEW_STATUS_SCHEDULED', 'scheduled');
define('INTERVIEW_STATUS_COMPLETED', 'completed');
define('INTERVIEW_STATUS_CANCELLED', 'cancelled');
define('INTERVIEW_STATUS_NO_SHOW', 'no_show');

define('INTERVIEW_STATUSES', [
    INTERVIEW_STATUS_SCHEDULED => 'Scheduled',
    INTERVIEW_STATUS_COMPLETED => 'Completed',
    INTERVIEW_STATUS_CANCELLED => 'Cancelled',
    INTERVIEW_STATUS_NO_SHOW => 'No Show'
]);

// ============================================================================
// INTERVIEW TYPES
// ============================================================================
define('INTERVIEW_TYPE_PHONE', 'phone');
define('INTERVIEW_TYPE_VIDEO', 'video');
define('INTERVIEW_TYPE_IN_PERSON', 'in_person');
define('INTERVIEW_TYPE_ASSESSMENT', 'assessment');

define('INTERVIEW_TYPES', [
    INTERVIEW_TYPE_PHONE => 'Phone Screen',
    INTERVIEW_TYPE_VIDEO => 'Video Interview',
    INTERVIEW_TYPE_IN_PERSON => 'In-Person Interview',
    INTERVIEW_TYPE_ASSESSMENT => 'Assessment/Test'
]);

// ============================================================================
// OFFER STATUSES
// ============================================================================
define('OFFER_STATUS_DRAFT', 'draft');
define('OFFER_STATUS_SENT', 'sent');
define('OFFER_STATUS_ACCEPTED', 'accepted');
define('OFFER_STATUS_REJECTED', 'rejected');
define('OFFER_STATUS_NEGOTIATING', 'negotiating');
define('OFFER_STATUS_EXPIRED', 'expired');

define('OFFER_STATUSES', [
    OFFER_STATUS_DRAFT => 'Draft',
    OFFER_STATUS_SENT => 'Sent',
    OFFER_STATUS_ACCEPTED => 'Accepted',
    OFFER_STATUS_REJECTED => 'Rejected',
    OFFER_STATUS_NEGOTIATING => 'Negotiating',
    OFFER_STATUS_EXPIRED => 'Expired'
]);

// ============================================================================
// CV INBOX STATUSES
// ============================================================================
define('CV_INBOX_STATUS_NEW', 'new');
define('CV_INBOX_STATUS_REVIEWED', 'reviewed');
define('CV_INBOX_STATUS_CONVERTED', 'converted');
define('CV_INBOX_STATUS_REJECTED', 'rejected');
define('CV_INBOX_STATUS_ARCHIVED', 'archived');

define('CV_INBOX_STATUSES', [
    CV_INBOX_STATUS_NEW => 'New',
    CV_INBOX_STATUS_REVIEWED => 'Reviewed',
    CV_INBOX_STATUS_CONVERTED => 'Converted',
    CV_INBOX_STATUS_REJECTED => 'Rejected',
    CV_INBOX_STATUS_ARCHIVED => 'Archived'
]);

// ============================================================================
// NOTE TYPES
// ============================================================================
define('NOTE_TYPE_GENERAL', 'general');
define('NOTE_TYPE_CALL', 'call');
define('NOTE_TYPE_MEETING', 'meeting');
define('NOTE_TYPE_EMAIL', 'email');
define('NOTE_TYPE_FOLLOW_UP', 'follow_up');
define('NOTE_TYPE_INTERVIEW', 'interview');

define('NOTE_TYPES', [
    NOTE_TYPE_GENERAL => 'General Note',
    NOTE_TYPE_CALL => 'Phone Call',
    NOTE_TYPE_MEETING => 'Meeting',
    NOTE_TYPE_EMAIL => 'Email',
    NOTE_TYPE_FOLLOW_UP => 'Follow-up',
    NOTE_TYPE_INTERVIEW => 'Interview'
]);

// ============================================================================
// DOCUMENT TYPES
// ============================================================================
define('DOCUMENT_TYPE_RESUME', 'resume');
define('DOCUMENT_TYPE_COVER_LETTER', 'cover_letter');
define('DOCUMENT_TYPE_CERTIFICATE', 'certificate');
define('DOCUMENT_TYPE_PORTFOLIO', 'portfolio');
define('DOCUMENT_TYPE_CONTRACT', 'contract');
define('DOCUMENT_TYPE_OTHER', 'other');

define('DOCUMENT_TYPES', [
    DOCUMENT_TYPE_RESUME => 'Resume/CV',
    DOCUMENT_TYPE_COVER_LETTER => 'Cover Letter',
    DOCUMENT_TYPE_CERTIFICATE => 'Certificate',
    DOCUMENT_TYPE_PORTFOLIO => 'Portfolio',
    DOCUMENT_TYPE_CONTRACT => 'Contract',
    DOCUMENT_TYPE_OTHER => 'Other'
]);

// ============================================================================
// ACTIVITY LOG ACTIONS
// ============================================================================
define('ACTIVITY_CREATE', 'create');
define('ACTIVITY_UPDATE', 'update');
define('ACTIVITY_DELETE', 'delete');
define('ACTIVITY_VIEW', 'view');
define('ACTIVITY_EXPORT', 'export');
define('ACTIVITY_IMPORT', 'import');
define('ACTIVITY_LOGIN', 'login');
define('ACTIVITY_LOGOUT', 'logout');
define('ACTIVITY_CONVERT', 'convert');
define('ACTIVITY_ASSIGN', 'assign');
define('ACTIVITY_STATUS_CHANGE', 'status_change');

// ============================================================================
// GENDER OPTIONS
// ============================================================================
define('GENDER_MALE', 'male');
define('GENDER_FEMALE', 'female');
define('GENDER_OTHER', 'other');
define('GENDER_PREFER_NOT_TO_SAY', 'prefer_not_to_say');

define('GENDERS', [
    GENDER_MALE => 'Male',
    GENDER_FEMALE => 'Female',
    GENDER_OTHER => 'Other',
    GENDER_PREFER_NOT_TO_SAY => 'Prefer not to say'
]);

// ============================================================================
// CURRENCIES
// ============================================================================
define('CURRENCIES', [
    'EUR' => '€ Euro',
    'USD' => '$ US Dollar',
    'GBP' => '£ British Pound',
    'CHF' => 'CHF Swiss Franc',
    'CAD' => 'C$ Canadian Dollar',
    'AUD' => 'A$ Australian Dollar',
    'JPY' => '¥ Japanese Yen',
    'CNY' => '¥ Chinese Yuan'
]);

// ============================================================================
// EXPERIENCE LEVELS
// ============================================================================
define('EXPERIENCE_ENTRY', 'entry');
define('EXPERIENCE_JUNIOR', 'junior');
define('EXPERIENCE_MID', 'mid');
define('EXPERIENCE_SENIOR', 'senior');
define('EXPERIENCE_LEAD', 'lead');
define('EXPERIENCE_EXECUTIVE', 'executive');

define('EXPERIENCE_LEVELS', [
    EXPERIENCE_ENTRY => 'Entry Level (0-2 years)',
    EXPERIENCE_JUNIOR => 'Junior (2-5 years)',
    EXPERIENCE_MID => 'Mid-Level (5-8 years)',
    EXPERIENCE_SENIOR => 'Senior (8-12 years)',
    EXPERIENCE_LEAD => 'Lead/Principal (12+ years)',
    EXPERIENCE_EXECUTIVE => 'Executive/C-Level'
]);

// ============================================================================
// EDUCATION LEVELS
// ============================================================================
define('EDUCATION_HIGH_SCHOOL', 'high_school');
define('EDUCATION_ASSOCIATE', 'associate');
define('EDUCATION_BACHELOR', 'bachelor');
define('EDUCATION_MASTER', 'master');
define('EDUCATION_DOCTORATE', 'doctorate');
define('EDUCATION_OTHER', 'other');

define('EDUCATION_LEVELS', [
    EDUCATION_HIGH_SCHOOL => 'High School',
    EDUCATION_ASSOCIATE => 'Associate Degree',
    EDUCATION_BACHELOR => 'Bachelor\'s Degree',
    EDUCATION_MASTER => 'Master\'s Degree',
    EDUCATION_DOCTORATE => 'Doctorate/PhD',
    EDUCATION_OTHER => 'Other'
]);

// ============================================================================
// EMPLOYMENT TYPES
// ============================================================================
define('EMPLOYMENT_FULL_TIME', 'full_time');
define('EMPLOYMENT_PART_TIME', 'part_time');
define('EMPLOYMENT_CONTRACT', 'contract');
define('EMPLOYMENT_FREELANCE', 'freelance');
define('EMPLOYMENT_INTERNSHIP', 'internship');

define('EMPLOYMENT_TYPES', [
    EMPLOYMENT_FULL_TIME => 'Full-time',
    EMPLOYMENT_PART_TIME => 'Part-time',
    EMPLOYMENT_CONTRACT => 'Contract',
    EMPLOYMENT_FREELANCE => 'Freelance',
    EMPLOYMENT_INTERNSHIP => 'Internship'
]);

// ============================================================================
// WORK AUTHORIZATION
// ============================================================================
define('WORK_AUTH_CITIZEN', 'citizen');
define('WORK_AUTH_PERMANENT_RESIDENT', 'permanent_resident');
define('WORK_AUTH_WORK_PERMIT', 'work_permit');
define('WORK_AUTH_STUDENT_VISA', 'student_visa');
define('WORK_AUTH_REQUIRES_SPONSORSHIP', 'requires_sponsorship');

define('WORK_AUTHORIZATIONS', [
    WORK_AUTH_CITIZEN => 'Citizen',
    WORK_AUTH_PERMANENT_RESIDENT => 'Permanent Resident',
    WORK_AUTH_WORK_PERMIT => 'Work Permit',
    WORK_AUTH_STUDENT_VISA => 'Student Visa',
    WORK_AUTH_REQUIRES_SPONSORSHIP => 'Requires Sponsorship'
]);

// ============================================================================
// NOTICE PERIODS
// ============================================================================
define('NOTICE_PERIODS', [
    0 => 'Immediate',
    7 => '1 Week',
    14 => '2 Weeks',
    30 => '1 Month',
    60 => '2 Months',
    90 => '3 Months'
]);

// ============================================================================
// PAGINATION DISPLAY OPTIONS
// ============================================================================
define('PAGINATION_OPTIONS', [10, 25, 50, 100, 250]);

// ============================================================================
// BOOLEAN TEXT REPRESENTATIONS
// ============================================================================
define('BOOLEAN_YES', 'Yes');
define('BOOLEAN_NO', 'No');

// ============================================================================
// FILE SIZE LIMITS (for display)
// ============================================================================
define('FILE_SIZE_UNITS', ['B', 'KB', 'MB', 'GB']);

// ============================================================================
// DEFAULT VALUES
// ============================================================================
define('DEFAULT_PAGE_SIZE', 25);
define('DEFAULT_SORT_ORDER', 'DESC');
define('DEFAULT_SORT_COLUMN', 'created_at');