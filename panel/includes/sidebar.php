<?php
/**
 * ============================================================================
 * PRODUCTION-GRADE PROFESSIONAL SIDEBAR v5.0
 * ============================================================================
 * 
 * FEATURES:
 * - Light blue gradient background
 * - Collapsible submenus (closed by default)
 * - Proper business-focused organization
 * - Better font sizes and spacing
 * - Professional visual hierarchy
 * - Complete menu structure for both Admin and User roles
 * 
 * MENU ORGANIZATION (Business Priority):
 * 1. Dashboard
 * 2. RECRUITMENT SECTION (Primary Focus)
 *    - CV Inbox & Leads (Combined)
 *    - Candidates
 *    - Jobs
 *    - Clients
 * 3. WORKFLOW SECTION
 *    - Submissions
 * 4. COMMUNICATION SECTION
 *    - Contact Requests (Web Inquiries)
 * 5. ANALYTICS SECTION
 *    - Reports
 * 6. ADMINISTRATION SECTION (Admin Only)
 *    - Users
 * 
 * @version 5.0 PRODUCTION
 * @date 2026-01-03
 */

use ProConsultancy\Core\Auth;

$current_page = basename($_SERVER['PHP_SELF'], '.php');
$current_uri = $_SERVER['REQUEST_URI'];

$user = Auth::user();
$user_level = $user['level'] ?? 'user';
$user_name = $user['name'] ?? 'User';
$user_initials = strtoupper(substr($user_name, 0, 2));

// Detect active modules
$in_cv_inbox = strpos($current_uri, 'cv-inbox') !== false || strpos($current_uri, 'cv_inbox') !== false;
$in_contacts = strpos($current_uri, 'contacts') !== false;
$in_candidates = strpos($current_uri, 'candidates') !== false;
$in_jobs = strpos($current_uri, 'jobs') !== false;
$in_clients = strpos($current_uri, 'clients') !== false;
$in_submissions = strpos($current_uri, 'submissions') !== false;
$in_queries = strpos($current_uri, 'queries') !== false;
$in_users = strpos($current_uri, 'users') !== false;
$in_reports = strpos($current_uri, 'reports') !== false;
?>

<aside class="sidebar">
    <!-- Brand Header -->
    <div class="sidebar-brand">
        <div class="brand-icon">
            <i class='bx bx-briefcase'></i>
        </div>
        <span class="brand-text">ProConsultancy</span>
    </div>
    
    <!-- Navigation -->
    <nav class="sidebar-nav">
        <!-- ================================================================
             DASHBOARD
             ================================================================ -->
        <a href="/panel/dashboard.php" class="nav-item <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
            <i class='bx bx-home-circle'></i>
            <span>Dashboard</span>
        </a>
        
        <!-- ================================================================
             RECRUITMENT SECTION - PRIMARY BUSINESS FOCUS
             ================================================================ -->
        <div class="nav-section">RECRUITMENT</div>
        
        <!-- CV Inbox & Leads (Combined Section) -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= ($in_cv_inbox || $in_contacts) ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-inbox'></i>
                <span>CV Inbox & Leads</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/cv-inbox/list.php" class="submenu-item <?= $in_cv_inbox ? 'active' : '' ?>">
                    <i class='bx bx-envelope'></i>
                    <span>CV Inbox</span>
                </a>
                <a href="/panel/modules/contacts/list.php" class="submenu-item <?= $in_contacts ? 'active' : '' ?>">
                    <i class='bx bx-user-plus'></i>
                    <span>Contact Leads</span>
                </a>
                <a href="/panel/modules/contacts/create.php" class="submenu-item">
                    <i class='bx bx-plus-circle'></i>
                    <span>Add New Lead</span>
                </a>
            </div>
        </div>
        
        <!-- Candidates -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_candidates ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-user-check'></i>
                <span>Candidates</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/candidates/list.php" class="submenu-item">
                    <i class='bx bx-list-ul'></i>
                    <span>All Candidates</span>
                </a>
                <a href="/panel/modules/candidates/create.php" class="submenu-item">
                    <i class='bx bx-plus-circle'></i>
                    <span>Add Candidate</span>
                </a>
            </div>
        </div>
        
        <!-- Jobs -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_jobs ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-briefcase-alt-2'></i>
                <span>Jobs</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/jobs/list.php" class="submenu-item">
                    <i class='bx bx-list-ul'></i>
                    <span>All Jobs</span>
                </a>
                <a href="/panel/modules/jobs/create.php" class="submenu-item">
                    <i class='bx bx-plus-circle'></i>
                    <span>Post New Job</span>
                </a>
                <?php if ($user_level === 'admin' || $user_level === 'manager'): ?>
                <a href="/panel/modules/jobs/list.php?status=pending_approval" class="submenu-item">
                    <i class='bx bx-time-five'></i>
                    <span>Pending Approval</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Clients -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_clients ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-buildings'></i>
                <span>Clients</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/clients/list.php" class="submenu-item">
                    <i class='bx bx-list-ul'></i>
                    <span>All Clients</span>
                </a>
                <a href="/panel/modules/clients/create.php" class="submenu-item">
                    <i class='bx bx-plus-circle'></i>
                    <span>Add Client</span>
                </a>
            </div>
        </div>
        
        <!-- ================================================================
             WORKFLOW SECTION
             ================================================================ -->
        <div class="nav-section">WORKFLOW</div>
        
        <!-- Submissions -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_submissions ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-send'></i>
                <span>Submissions</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/submissions/list.php" class="submenu-item">
                    <i class='bx bx-list-ul'></i>
                    <span>All Submissions</span>
                </a>
            </div>
        </div>
        
        <!-- ================================================================
             COMMUNICATION SECTION
             ================================================================ -->
        <div class="nav-section">COMMUNICATION</div>
        
        <!-- Website Inquiries / Contact Requests -->
        <a href="/panel/modules/queries/list.php" class="nav-item <?= $in_queries ? 'active' : '' ?>">
            <i class='bx bx-message-square-dots'></i>
            <span>Website Inquiries</span>
        </a>
        
        <!-- ================================================================
             ANALYTICS SECTION
             ================================================================ -->
        <div class="nav-section">ANALYTICS</div>
        
        <!-- Reports -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_reports ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-bar-chart-alt-2'></i>
                <span>Reports</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/reports/daily.php" class="submenu-item">
                    <i class='bx bx-calendar'></i>
                    <span>Daily Report</span>
                </a>
                <a href="/panel/modules/reports/pipeline.php" class="submenu-item">
                    <i class='bx bx-trending-up'></i>
                    <span>Pipeline Analytics</span>
                </a>
                <a href="/panel/modules/reports/recruiter_performance.php" class="submenu-item">
                    <i class='bx bx-trophy'></i>
                    <span>Recruiter Performance</span>
                </a>
                <a href="/panel/modules/reports/followup.php" class="submenu-item">
                    <i class='bx bx-bell'></i>
                    <span>Follow-up Dashboard</span>
                </a>
            </div>
        </div>
        
        <!-- ================================================================
             ADMINISTRATION SECTION (Admin Only)
             ================================================================ -->
        <?php if ($user_level === 'admin' || $user_level === 'super_admin'): ?>
        <div class="nav-section">ADMINISTRATION</div>
        
        <!-- User Management -->
        <div class="nav-item-group">
            <div class="nav-item has-submenu <?= $in_users ? 'active' : '' ?>" onclick="toggleSubmenu(this)">
                <i class='bx bx-group'></i>
                <span>Users</span>
                <i class='bx bx-chevron-right submenu-arrow'></i>
            </div>
            <div class="submenu">
                <a href="/panel/modules/users/list.php" class="submenu-item">
                    <i class='bx bx-list-ul'></i>
                    <span>All Users</span>
                </a>
                <a href="/panel/modules/users/create.php" class="submenu-item">
                    <i class='bx bx-user-plus'></i>
                    <span>Add User</span>
                </a>
            </div>
        </div>
        
        <!-- Settings -->
        <a href="/panel/modules/settings/general.php" class="nav-item">
            <i class='bx bx-cog'></i>
            <span>Settings</span>
        </a>
        <?php endif; ?>
    </nav>
    
    <!-- Logout -->
    <div class="sidebar-footer">
        <a href="/panel/logout.php" class="logout-btn">
            <i class='bx bx-log-out'></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<script>
/**
 * Sidebar Submenu Toggle
 * Handles collapsible menu functionality
 */
function toggleSubmenu(element) {
    // Close all other submenus
    document.querySelectorAll('.nav-item.open').forEach(item => {
        if (item !== element) {
            item.classList.remove('open');
        }
    });
    
    // Toggle clicked submenu
    element.classList.toggle('open');
}

/**
 * Auto-open active submenu on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    const activeItem = document.querySelector('.nav-item.active.has-submenu');
    if (activeItem) {
        activeItem.classList.add('open');
    }
});

/**
 * Mobile Menu Toggle (for responsive view)
 */
function toggleMobileSidebar() {
    document.querySelector('.sidebar').classList.toggle('mobile-open');
}
</script>