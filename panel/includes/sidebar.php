<?php
/**
 * Main Sidebar Navigation 
 * File: panel/includes/sidebar.php
 * 
 * Permissions-aware navigation with all latest modules:
 * - Contacts (Lead Management)
 * - CV Inbox (separated from candidates)
 * - Candidates
 * - Jobs
 * - Clients
 * - Submissions
 * - Website Inquiries (Queries)
 * - Activity Log
 * - Reports
 */
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\Permission;

$user = Auth::user();
$userRole = $user['level'] ?? 'recruiter';
if ($userRole === '') {
    $userRole = $user['level'] ?? 'user';
}

// Helper function to check permission
function canAccess($module, $action = 'view_all') {
    return Permission::can($module, $action);
}

// Get current page for active state
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentModule = '';
if (strpos($_SERVER['REQUEST_URI'], '/contacts/') !== false) $currentModule = 'contacts';
elseif (strpos($_SERVER['REQUEST_URI'], '/cv-inbox/') !== false || strpos($_SERVER['REQUEST_URI'], '/cv_inbox/') !== false) $currentModule = 'cv_inbox';
elseif (strpos($_SERVER['REQUEST_URI'], '/candidates/') !== false) $currentModule = 'candidates';
elseif (strpos($_SERVER['REQUEST_URI'], '/jobs/') !== false) $currentModule = 'jobs';
elseif (strpos($_SERVER['REQUEST_URI'], '/clients/') !== false) $currentModule = 'clients';
elseif (strpos($_SERVER['REQUEST_URI'], '/submissions/') !== false) $currentModule = 'submissions';
elseif (strpos($_SERVER['REQUEST_URI'], '/queries/') !== false) $currentModule = 'queries';
elseif (strpos($_SERVER['REQUEST_URI'], '/reports/') !== false) $currentModule = 'reports';
elseif (strpos($_SERVER['REQUEST_URI'], '/users/') !== false) $currentModule = 'users';

// Get database connection for counters
$db = Database::getInstance();
$conn = $db->getConnection();
?>

<!-- Menu -->
<aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
    <!-- Brand -->
    <div class="app-brand demo">
        <a href="/panel/dashboard.php" class="app-brand-link">
            <span class="app-brand-logo demo">
                <svg width="25" viewBox="0 0 25 42" version="1.1" xmlns="http://www.w3.org/2000/svg">
                    <defs>
                        <path d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" id="path-1"></path>
                        <path d="M5.47320593,6.00457225 C4.05321814,8.216144 4.36334763,10.0722806 6.40359441,11.5729822 C8.61520715,12.571656 10.0999176,13.2171421 10.8577257,13.5094407 L15.5088241,14.433041 L18.6192054,7.984237 C15.5364148,3.11535317 13.9273018,0.573395879 13.7918663,0.358365126 C13.5790555,0.511491653 10.8061687,2.3935607 5.47320593,6.00457225 Z" id="path-3"></path>
                        <path d="M7.50063644,21.2294429 L12.3234468,23.3159332 C14.1688022,24.7579751 14.397098,26.4880487 13.008334,28.506154 C11.6195701,30.5242593 10.3099883,31.790241 9.07958868,32.3040991 C5.78142938,33.4346997 4.13234973,34 4.13234973,34 C4.13234973,34 2.75489982,33.0538207 2.37032616e-14,31.1614621 C-0.55822714,27.8186216 -0.55822714,26.0572515 -4.05231404e-15,25.8773518 C0.83734071,25.6075023 2.77988457,22.8248993 3.3049379,22.52991 C3.65497346,22.3332504 5.05353963,21.8997614 7.50063644,21.2294429 Z" id="path-4"></path>
                        <path d="M20.6,7.13333333 L25.6,13.8 C26.2627417,14.6836556 26.0836556,15.9372583 25.2,16.6 C24.8538077,16.8596443 24.4327404,17 24,17 L14,17 C12.8954305,17 12,16.1045695 12,15 C12,14.5672596 12.1403557,14.1461923 12.4,13.8 L17.4,7.13333333 C18.0627417,6.24967773 19.3163444,6.07059163 20.2,6.73333333 C20.3516113,6.84704183 20.4862915,6.981722 20.6,7.13333333 Z" id="path-5"></path>
                    </defs>
                    <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g transform="translate(-4.000000, -15.000000)" fill="#696cff">
                            <use xlink:href="#path-1"></use>
                            <use xlink:href="#path-3"></use>
                            <use xlink:href="#path-4"></use>
                            <use xlink:href="#path-5"></use>
                        </g>
                    </g>
                </svg>
            </span>
            <span class="app-brand-text demo menu-text fw-bolder ms-2">ProConsultancy</span>
        </a>

        <a href="javascript:void(0);" class="layout-menu-toggle menu-link text-large ms-auto d-block d-xl-none">
            <i class="bx bx-chevron-left bx-sm align-middle"></i>
        </a>
    </div>

    <div class="menu-inner-shadow"></div>

    <ul class="menu-inner py-1">
        <!-- Dashboard -->
        <?php if (canAccess('reports', 'view_dashboard')): ?>
        <li class="menu-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
            <a href="/panel/dashboard.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-home-circle"></i>
                <div data-i18n="Dashboard">Dashboard</div>
            </a>
        </li>
        <?php endif; ?>

        <!-- SECTION: LEAD MANAGEMENT -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Lead Management</span>
        </li>

        <!-- Contacts & Leads -->
        <?php if (canAccess('contacts', 'view_all') || canAccess('contacts', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'contacts' ? 'active' : '' ?>">
            <a href="/panel/modules/contacts/list.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user-plus"></i>
                <div data-i18n="Contacts">Contacts & Leads</div>
                <?php
                // Show count of pending follow-ups
                try {
                    $userCode = Auth::userCode();
                    
                    $followupQuery = "SELECT COUNT(*) as count FROM contacts 
                                    WHERE next_follow_up <= CURDATE() 
                                    AND status NOT IN ('converted', 'not_interested', 'unresponsive') 
                                    AND deleted_at IS NULL";
                    
                    $params = [];
                    $types = '';
                    
                    if (!canAccess('contacts', 'view_all')) {
                        $followupQuery .= " AND assigned_to = ?";
                        $params[] = $userCode;
                        $types .= 's';
                    }
                    
                    $stmt = $conn->prepare($followupQuery);
                    if (!empty($params)) {
                        $stmt->bind_param($types, ...$params);
                    }
                    $stmt->execute();
                    $followupCount = $stmt->get_result()->fetch_assoc()['count'];
                    
                    if ($followupCount > 0):
                    ?>
                    <div class="badge bg-danger rounded-pill ms-auto"><?= $followupCount ?></div>
                    <?php 
                    endif;
                } catch (Exception $e) {
                    // Silently fail - don't break sidebar if query fails
                }
                ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- SECTION: RECRUITMENT -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Recruitment</span>
        </li>

        <!-- CV Inbox -->
        <?php if (canAccess('cv_inbox', 'view_all') || canAccess('cv_inbox', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'cv_inbox' ? 'active' : '' ?>">
            <a href="/panel/modules/cv-inbox/list.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-envelope"></i>
                <div data-i18n="CV Inbox">CV Inbox</div>
                <?php
                // Show count of new CVs
                try {
                    $newCVQuery = "SELECT COUNT(*) as count FROM cv_inbox 
                                  WHERE status = 'new' AND deleted_at IS NULL";
                    
                    $result = $conn->query($newCVQuery);
                    $newCVCount = $result->fetch_assoc()['count'];
                    
                    if ($newCVCount > 0):
                    ?>
                    <div class="badge bg-warning rounded-pill ms-auto"><?= $newCVCount ?></div>
                    <?php 
                    endif;
                } catch (Exception $e) {
                    // Silently fail
                }
                ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- Candidates -->
        <?php if (canAccess('candidates', 'view_all') || canAccess('candidates', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'candidates' ? 'active open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user-check"></i>
                <div data-i18n="Candidates">Candidates</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="/panel/modules/candidates/list.php" class="menu-link">
                        <div data-i18n="All Candidates">All Candidates</div>
                    </a>
                </li>
                <?php if (canAccess('candidates', 'create')): ?>
                <li class="menu-item">
                    <a href="/panel/modules/candidates/create.php" class="menu-link">
                        <div data-i18n="Add Candidate">Add Candidate</div>
                    </a>
                </li>
                <?php endif; ?>
                <li class="menu-item">
                    <a href="/panel/modules/candidates/pipeline.php" class="menu-link">
                        <div data-i18n="Pipeline View">Pipeline View</div>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Jobs -->
        <?php if (canAccess('jobs', 'view_all') || canAccess('jobs', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'jobs' ? 'active open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-briefcase"></i>
                <div data-i18n="Jobs">Jobs</div>
                <?php
                // Show count of pending approvals (for managers)
                if (canAccess('jobs', 'approve')) {
                    try {
                        $pendingJobsQuery = "SELECT COUNT(*) as count FROM jobs 
                                            WHERE status = 'pending_approval' AND deleted_at IS NULL";
                        $result = $conn->query($pendingJobsQuery);
                        $pendingJobsCount = $result->fetch_assoc()['count'];
                        
                        if ($pendingJobsCount > 0):
                        ?>
                        <div class="badge bg-warning rounded-pill ms-auto"><?= $pendingJobsCount ?></div>
                        <?php 
                        endif;
                    } catch (Exception $e) {
                        // Silently fail
                    }
                }
                ?>
            </a>
            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="/panel/modules/jobs/list.php" class="menu-link">
                        <div data-i18n="All Jobs">All Jobs</div>
                    </a>
                </li>
                <?php if (canAccess('jobs', 'create')): ?>
                <li class="menu-item">
                    <a href="/panel/modules/jobs/create.php" class="menu-link">
                        <div data-i18n="Create Job">Create Job</div>
                    </a>
                </li>
                <?php endif; ?>
                <li class="menu-item">
                    <a href="/panel/modules/jobs/list.php?tab=active" class="menu-link">
                        <div data-i18n="Active Jobs">Active Jobs</div>
                    </a>
                </li>
                <?php if (canAccess('jobs', 'approve')): ?>
                <li class="menu-item">
                    <a href="/panel/modules/jobs/list.php?tab=pending_approval" class="menu-link">
                        <div data-i18n="Pending Approval">Pending Approval</div>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Clients -->
        <?php if (canAccess('clients', 'view_all') || canAccess('clients', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'clients' ? 'active' : '' ?>">
            <a href="/panel/modules/clients/list.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-building"></i>
                <div data-i18n="Clients">Clients</div>
            </a>
        </li>
        <?php endif; ?>

        <!-- SECTION: WORKFLOW -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Workflow</span>
        </li>

        <!-- Submissions -->
        <?php if (canAccess('submissions', 'view_all') || canAccess('submissions', 'view_own')): ?>
        <li class="menu-item <?= $currentModule === 'submissions' ? 'active' : '' ?>">
            <a href="/panel/modules/submissions/list.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-send"></i>
                <div data-i18n="Submissions">Submissions</div>
                <?php
                // Show count of pending approvals (for managers)
                if (canAccess('submissions', 'approve')) {
                    try {
                        $pendingQuery = "SELECT COUNT(*) as count FROM candidate_submissions 
                                        WHERE status = 'pending_review' AND deleted_at IS NULL";
                        $result = $conn->query($pendingQuery);
                        $pendingCount = $result->fetch_assoc()['count'];
                        
                        if ($pendingCount > 0):
                        ?>
                        <div class="badge bg-warning rounded-pill ms-auto"><?= $pendingCount ?></div>
                        <?php 
                        endif;
                    } catch (Exception $e) {
                        // Silently fail
                    }
                }
                ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- SECTION: COMMUNICATION -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Communication</span>
        </li>

        <!-- Website Inquiries -->
        <?php if (canAccess('queries', 'view') || $userRole === 'admin' || $userRole === 'super_admin'): ?>
        <li class="menu-item <?= $currentModule === 'queries' ? 'active' : '' ?>">
            <a href="/panel/modules/queries/list.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-message-square-dots"></i>
                <div data-i18n="Website Inquiries">Website Inquiries</div>
                <?php
                // Show count of today's inquiries
                try {
                    $today = date('Y-m-d');
                    $todayInquiriesQuery = "SELECT COUNT(*) as count FROM queries 
                                           WHERE DATE(submission_date) = '$today'";
                    $result = $conn->query($todayInquiriesQuery);
                    $todayCount = $result->fetch_assoc()['count'];
                    
                    if ($todayCount > 0):
                    ?>
                    <div class="badge bg-info rounded-pill ms-auto"><?= $todayCount ?></div>
                    <?php 
                    endif;
                } catch (Exception $e) {
                    // Silently fail
                }
                ?>
            </a>
        </li>
        <?php endif; ?>

        <!-- SECTION: ANALYTICS -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Analytics</span>
        </li>

        <!-- Reports Module -->
        <?php if (canAccess('reports', 'view_dashboard')): ?>
        <li class="menu-item <?= $currentModule === 'reports' ? 'open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                <div data-i18n="Reports">Reports</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'daily.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/reports/daily.php" class="menu-link">
                        <div data-i18n="Daily Report">Daily Report</div>
                    </a>
                </li>
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'pipeline.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/reports/pipeline.php" class="menu-link">
                        <div data-i18n="Pipeline">Pipeline Analytics</div>
                    </a>
                </li>
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'recruiter_performance.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/reports/recruiter_performance.php" class="menu-link">
                        <div data-i18n="Performance">Recruiter Performance</div>
                    </a>
                </li>
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'followup.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/reports/followup.php" class="menu-link">
                        <div data-i18n="Followup">Follow-up Dashboard</div>
                    </a>
                </li>
            </ul>
        </li>
        <?php endif; ?>
        <!-- ============================================================================ -->
        <!-- SECTION: USER MANAGEMENT & SETTINGS -->
        <!-- ============================================================================ -->

        <!-- ADMINISTRATION SECTION (for Admins Only) -->
        <?php if (in_array($userLevel, ['super_admin', 'admin'])): ?>
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administration</span>
        </li>

        <!-- User Management -->
        <?php if (Permission::can('users', 'view_all')): ?>
        <li class="menu-item <?= $currentModule === 'users' ? 'open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-user"></i>
                <div data-i18n="Users">User Management</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'list.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/users/list.php" class="menu-link">
                        <div data-i18n="All Users">All Users</div>
                    </a>
                </li>
                <?php if (Permission::can('users', 'create')): ?>
                <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'create.php' ? 'active' : '' ?>">
                    <a href="/panel/modules/users/create.php" class="menu-link">
                        <div data-i18n="Add User">Add New User</div>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>
        <?php endif; ?>

        <!-- MY ACCOUNT SECTION (for All Users) -->
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">My Account</span>
        </li>

        <!-- My Profile -->
        <li class="menu-item <?= basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : '' ?>">
            <a href="/panel/modules/users/profile.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-user-circle"></i>
                <div data-i18n="My Profile">My Profile</div>
            </a>
        </li>

        <!-- Settings (Password Change) -->
        <li class="menu-item <?= $currentModule === 'settings' ? 'active' : '' ?>">
            <a href="/panel/modules/settings/general.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
        </li>
        <!-- SECTION: ADMINISTRATION -->
        <?php if (canAccess('users', 'view') || $userRole === 'admin' || $userRole === 'super_admin'): ?>
        <li class="menu-header small text-uppercase">
            <span class="menu-header-text">Administration</span>
        </li>

        <!-- User Management -->
        <?php if (canAccess('users', 'view')): ?>
        <li class="menu-item <?= $currentModule === 'users' ? 'active open' : '' ?>">
            <a href="javascript:void(0);" class="menu-link menu-toggle">
                <i class="menu-icon tf-icons bx bx-group"></i>
                <div data-i18n="User Management">User Management</div>
            </a>
            <ul class="menu-sub">
                <li class="menu-item">
                    <a href="/panel/modules/users/list.php" class="menu-link">
                        <div data-i18n="All Users">All Users</div>
                    </a>
                </li>
                <?php if (canAccess('users', 'create')): ?>
                <li class="menu-item">
                    <a href="/panel/modules/users/create.php" class="menu-link">
                        <div data-i18n="Add User">Add User</div>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (canAccess('users', 'manage_roles')): ?>
                <li class="menu-item">
                    <a href="/panel/modules/users/roles.php" class="menu-link">
                        <div data-i18n="Roles & Permissions">Roles & Permissions</div>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </li>
        <?php endif; ?>

        <!-- Activity Log -->
        <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
        <li class="menu-item <?= $currentPage === 'activity_log' ? 'active' : '' ?>">
            <a href="/panel/activity_log.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-history"></i>
                <div data-i18n="Activity Log">Activity Log</div>
            </a>
        </li>
        <?php endif; ?>

        <!-- Settings -->
        <?php if ($userRole === 'admin' || $userRole === 'super_admin'): ?>
        <li class="menu-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
            <a href="/panel/settings.php" class="menu-link">
                <i class="menu-icon tf-icons bx bx-cog"></i>
                <div data-i18n="Settings">Settings</div>
            </a>
        </li>
        <?php endif; ?>
        <?php endif; ?>
    </ul>
</aside>
<!-- / Menu -->