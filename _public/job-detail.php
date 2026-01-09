<?php
/**
 * Public Job Detail Page
 * Shows complete job information with apply button
 */

require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

// Get job reference
$jobRef = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_STRING);

if (!$jobRef) {
    header('Location: /public/jobs.php');
    exit;
}

// Get job details
$stmt = $conn->prepare("
    SELECT 
        j.*,
        c.company_name,
        c.contact_person,
        u.name as recruiter_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u ON j.created_by = u.user_code
    WHERE (j.job_refno = ? OR j.job_code = ?)
    AND j.status = 'open'
    AND j.is_published = 1
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
");
$stmt->bind_param("ss", $jobRef, $jobRef);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header('Location: /public/jobs.php');
    exit;
}

// Get required skills
$stmt = $conn->prepare("
    SELECT skill_name, proficiency_level, is_required
    FROM job_skills
    WHERE job_code = ?
    ORDER BY is_required DESC, skill_name
");
$stmt->bind_param("s", $job['job_code']);
$stmt->execute();
$skills = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Helper functions
function formatSalary($min, $max, $show) {
    if (!$show || (!$min && !$max)) return 'Competitive';
    if ($min && $max) return '€' . number_format($min) . ' - €' . number_format($max);
    return $min ? 'From €' . number_format($min) : 'Up to €' . number_format($max);
}

// Page config
$pageTitle = htmlspecialchars($job['job_title']) . ' - Pro Consultancy';
$pageKeywords = htmlspecialchars($job['job_title']) . ', ' . htmlspecialchars($job['location']) . ', Job Opportunity';
$pageDescription = htmlspecialchars(substr(strip_tags($job['description']), 0, 160));
$currentPage = 'jobs';
$pageHeader = true;
$headerTitle = htmlspecialchars($job['job_title']);
$headerSubtitle = 'Ref: ' . htmlspecialchars($job['job_refno']) . ' | ' . htmlspecialchars($job['location']);
$headerImage = 'job.png';

require_once '_includes/header.php';
?>

<!-- Job Detail Start -->
<div class="container-fluid py-5 wow fadeInUp" data-wow-delay="0.1s">
    <div class="container py-5">
        <div class="row g-5">
            
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Job Overview -->
                <div class="mb-5">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Job Overview</h3>
                    </div>
                    
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="d-flex align-items-center bg-light rounded p-3">
                                <i class="fas fa-briefcase fa-2x text-primary me-3"></i>
                                <div>
                                    <small class="text-muted">Employment Type</small>
                                    <p class="mb-0 fw-semibold"><?= ucfirst(str_replace('_', ' ', $job['employment_type'])) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center bg-light rounded p-3">
                                <i class="fas fa-map-marker-alt fa-2x text-primary me-3"></i>
                                <div>
                                    <small class="text-muted">Location</small>
                                    <p class="mb-0 fw-semibold"><?= htmlspecialchars($job['location']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center bg-light rounded p-3">
                                <i class="fas fa-euro-sign fa-2x text-primary me-3"></i>
                                <div>
                                    <small class="text-muted">Salary Range</small>
                                    <p class="mb-0 fw-semibold"><?= formatSalary($job['salary_min'], $job['salary_max'], $job['show_salary']) ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="d-flex align-items-center bg-light rounded p-3">
                                <i class="fas fa-calendar-alt fa-2x text-primary me-3"></i>
                                <div>
                                    <small class="text-muted">Posted Date</small>
                                    <p class="mb-0 fw-semibold"><?= date('M d, Y', strtotime($job['published_at'])) ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Job Description -->
                <div class="mb-5">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Job Description</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <?= nl2br(htmlspecialchars($job['description'])) ?>
                    </div>
                </div>

                <!-- Requirements -->
                <?php if (!empty($job['requirements'])): ?>
                <div class="mb-5">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Requirements</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <?= nl2br(htmlspecialchars($job['requirements'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Required Skills -->
                <?php if (!empty($skills)): ?>
                <div class="mb-5">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Required Skills</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <div class="row g-3">
                            <?php foreach ($skills as $skill): ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-check-circle-fill text-primary me-2"></i>
                                        <span>
                                            <?= htmlspecialchars($skill['skill_name']) ?>
                                            <?php if ($skill['is_required']): ?>
                                                <span class="badge bg-danger ms-2">Required</span>
                                            <?php endif; ?>
                                            <?php if ($skill['proficiency_level']): ?>
                                                <small class="text-muted ms-2">(<?= ucfirst($skill['proficiency_level']) ?>)</small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Benefits -->
                <?php if (!empty($job['benefits'])): ?>
                <div class="mb-5">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Benefits</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <?= nl2br(htmlspecialchars($job['benefits'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Apply Button -->
                <div class="text-center mt-5">
                    <a href="/public/apply.php?job=<?= urlencode($job['job_code']) ?>" 
                       class="btn btn-primary btn-lg py-3 px-5">
                        <i class="fas fa-paper-plane me-2"></i>
                        Apply for this Position
                    </a>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Company Info -->
                <?php if (!empty($job['company_name'])): ?>
                <div class="mb-5 wow slideInUp" data-wow-delay="0.1s">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">About Company</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <h5 class="text-primary mb-3"><?= htmlspecialchars($job['company_name']) ?></h5>
                        <?php if (!empty($job['client_description'])): ?>
                            <p><?= nl2br(htmlspecialchars($job['client_description'])) ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Job Summary -->
                <div class="mb-5 wow slideInUp" data-wow-delay="0.2s">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Job Summary</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <div class="mb-3">
                            <small class="text-muted">Job Reference</small>
                            <p class="mb-0 fw-semibold"><?= htmlspecialchars($job['job_refno']) ?></p>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <small class="text-muted">Vacancies</small>
                            <p class="mb-0 fw-semibold"><?= $job['vacancies'] ?? 1 ?> Position(s)</p>
                        </div>
                        <hr>
                        <div class="mb-3">
                            <small class="text-muted">Experience Required</small>
                            <p class="mb-0 fw-semibold">
                                <?php if ($job['experience_min'] && $job['experience_max']): ?>
                                    <?= $job['experience_min'] ?> - <?= $job['experience_max'] ?> years
                                <?php elseif ($job['experience_min']): ?>
                                    Minimum <?= $job['experience_min'] ?> years
                                <?php else: ?>
                                    Not Specified
                                <?php endif; ?>
                            </p>
                        </div>
                        <hr>
                        <div>
                            <small class="text-muted">Application Deadline</small>
                            <p class="mb-0 fw-semibold">
                                <?php if (!empty($job['deadline'])): ?>
                                    <?= date('M d, Y', strtotime($job['deadline'])) ?>
                                <?php else: ?>
                                    Open until filled
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Share Job -->
                <div class="mb-5 wow slideInUp" data-wow-delay="0.3s">
                    <div class="section-title section-title-sm position-relative pb-3 mb-4">
                        <h3 class="mb-0">Share this Job</h3>
                    </div>
                    <div class="bg-light rounded p-4">
                        <div class="d-flex gap-2">
                            <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= urlencode('https://proconsultancy.be/public/job-detail.php?ref=' . $job['job_refno']) ?>" 
                               target="_blank" class="btn btn-primary btn-sm flex-fill">
                                <i class="fab fa-linkedin-in"></i> LinkedIn
                            </a>
                            <a href="https://twitter.com/intent/tweet?url=<?= urlencode('https://proconsultancy.be/public/job-detail.php?ref=' . $job['job_refno']) ?>&text=<?= urlencode($job['job_title']) ?>" 
                               target="_blank" class="btn btn-info btn-sm flex-fill">
                                <i class="fab fa-twitter"></i> Twitter
                            </a>
                            <a href="mailto:?subject=<?= urlencode($job['job_title']) ?>&body=<?= urlencode('Check out this job: https://proconsultancy.be/public/job-detail.php?ref=' . $job['job_refno']) ?>" 
                               class="btn btn-secondary btn-sm flex-fill">
                                <i class="fas fa-envelope"></i> Email
                            </a>
                        </div>
                    </div>
                </div>

                <!-- CTA -->
                <div class="mb-5 wow slideInUp" data-wow-delay="0.4s">
                    <div class="bg-primary text-white rounded p-4 text-center">
                        <h5 class="text-white mb-3">Not the right fit?</h5>
                        <p class="mb-3">Browse other opportunities or submit your CV for future positions.</p>
                        <a href="/public/jobs.php" class="btn btn-light mb-2 w-100">
                            <i class="bi bi-list-ul me-2"></i>View All Jobs
                        </a>
                        <button type="button" class="btn btn-outline-light w-100" data-bs-toggle="modal" data-bs-target="#cvModal">
                            <i class="bi bi-upload me-2"></i>Submit CV
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Job Detail End -->

<?php require_once '_includes/footer.php'; ?>