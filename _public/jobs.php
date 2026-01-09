<?php
/**
 * Public Job Listings
 * Server-side rendering with database integration
 */

require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 6;
$offset = ($page - 1) * $perPage;

// Get total count
$stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM jobs j
    WHERE j.status = 'open'
    AND j.is_published = 1
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
");
$stmt->execute();
$total = $stmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($total / $perPage);

// Get jobs for current page
$stmt = $conn->prepare("
    SELECT 
        j.job_code,
        j.job_refno,
        j.job_title,
        j.description,
        j.location,
        j.salary_min,
        j.salary_max,
        j.show_salary,
        j.employment_type,
        j.published_at,
        c.company_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.status = 'open'
    AND j.is_published = 1
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
    ORDER BY j.published_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $perPage, $offset);
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get sidebar jobs (random 5)
$stmt = $conn->prepare("
    SELECT job_refno, job_title, location
    FROM jobs
    WHERE status = 'open'
    AND is_published = 1
    AND approval_status = 'approved'
    AND deleted_at IS NULL
    ORDER BY RAND()
    LIMIT 5
");
$stmt->execute();
$sidebarJobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper functions
function truncate($text, $length = 150) {
    $text = strip_tags($text);
    return strlen($text) > $length ? substr($text, 0, $length) . '...' : $text;
}

function formatSalary($min, $max, $show) {
    if (!$show || (!$min && !$max)) return 'Competitive';
    if ($min && $max) return '€' . number_format($min) . ' - €' . number_format($max);
    return $min ? 'From €' . number_format($min) : 'Up to €' . number_format($max);
}

function timeAgo($date) {
    $diff = time() - strtotime($date);
    if ($diff < 86400) return 'Today';
    if ($diff < 172800) return 'Yesterday';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    if ($diff < 2592000) return floor($diff / 604800) . ' weeks ago';
    return date('M d, Y', strtotime($date));
}

// Page config
$pageTitle = 'Job Opportunities at Pro Consultancy - Current Openings';
$pageKeywords = 'Job Opportunities, Current Openings, Career at Pro Consultancy, Employment, Job Listings';
$pageDescription = 'Explore job opportunities at Pro Consultancy. View our current openings and join our team for a rewarding career.';
$currentPage = 'jobs';
$pageHeader = true;
$headerTitle = 'Current Job Openings';
$headerSubtitle = 'Find your next career opportunity';
$headerImage = 'job.png';

require_once '_includes/header.php';
?>

<!-- Jobs List Start -->
<div class="container-fluid py-5 wow fadeInUp" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="row g-5">
            
            <!-- Main Job Listings -->
            <div class="col-lg-8">
                <div class="section-title section-title-sm position-relative pb-3 mb-4">
                    <h3 class="mb-0">Current Openings <?= $total > 0 ? "($total Jobs)" : '' ?></h3>
                </div>
                
                <?php if (!empty($jobs)): ?>
                    <div class="row g-4">
                        <?php foreach ($jobs as $job): ?>
                            <div class="col-12 wow slideInUp" data-wow-delay="0.1s">
                                <div class="blog-item bg-light rounded overflow-hidden">
                                    <div class="p-4">
                                        <div class="d-flex mb-3">
                                            <small class="me-3">
                                                <i class="far fa-calendar-alt text-primary me-2"></i>
                                                <?= timeAgo($job['published_at']) ?>
                                            </small>
                                            <small>
                                                <i class="fas fa-map-marker-alt text-primary me-2"></i>
                                                <?= htmlspecialchars($job['location']) ?>
                                            </small>
                                        </div>
                                        
                                        <h5 class="mb-3"><?= htmlspecialchars($job['job_title']) ?></h5>
                                        <p><?= truncate($job['description']) ?></p>
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <div>
                                                <?php if (!empty($job['company_name'])): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-building me-2"></i>
                                                        <?= htmlspecialchars($job['company_name']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <span class="badge bg-primary">
                                                    <?= formatSalary($job['salary_min'], $job['salary_max'], $job['show_salary']) ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <a href="/public/job-detail.php?ref=<?= urlencode($job['job_refno']) ?>" 
                                           class="btn btn-sm btn-primary py-2">
                                            View Details & Apply
                                            <i class="fas fa-arrow-right ms-2"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="col-12 wow slideInUp mt-4" data-wow-delay="0.1s">
                            <div class="text-center">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>" class="btn btn-primary">
                                        <i class="bi bi-arrow-left me-2"></i>Previous
                                    </a>
                                <?php endif; ?>
                                
                                <span class="mx-3 fw-semibold">
                                    Page <?= $page ?> of <?= $totalPages ?>
                                </span>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?page=<?= $page + 1 ?>" class="btn btn-primary">
                                        Next<i class="bi bi-arrow-right ms-2"></i>
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- No Jobs Message -->
                    <div class="col-12">
                        <div class="alert alert-info text-center py-5">
                            <i class="fas fa-briefcase fa-3x mb-3 text-primary"></i>
                            <h5>No jobs currently available</h5>
                            <p>We don't have any open positions at the moment. Please check back later or submit your CV for future opportunities.</p>
                            <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#cvModal">
                                <i class="bi bi-upload me-2"></i>Submit Your CV
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Sidebar: More Jobs -->
            <div class="col-lg-4">
                <div class="mb-5 wow slideInUp" data-wow-delay="0.1s">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">More Jobs</h3>
                    </div>
                    <div class="link-animated d-flex flex-column justify-content-start">
                        <?php foreach ($sidebarJobs as $sJob): ?>
                            <a href="/public/job-detail.php?ref=<?= urlencode($sJob['job_refno']) ?>" 
                               class="h6 fw-semi-bold bg-light rounded py-2 px-3 mb-2">
                                <i class="bi bi-arrow-right text-primary me-2"></i>
                                <?= htmlspecialchars($sJob['job_title']) ?>
                                <small class="text-muted d-block ms-4">
                                    <i class="fas fa-map-marker-alt me-1"></i>
                                    <?= htmlspecialchars($sJob['location']) ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- CTA Box -->
                <div class="mb-5 wow slideInUp" data-wow-delay="0.2s">
                    <div class="bg-primary text-white rounded p-4 text-center">
                        <h5 class="text-white mb-3">Can't find the right job?</h5>
                        <p class="mb-3">Submit your CV and we'll contact you when suitable positions become available.</p>
                        <button type="button" class="btn btn-light" data-bs-toggle="modal" data-bs-target="#cvModal">
                            <i class="bi bi-upload me-2"></i>Submit CV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Jobs List End -->

<?php require_once '_includes/footer.php'; ?>