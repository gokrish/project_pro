<?php
/**
 * Public Job Detail Page
 * Shows complete job information with apply button
 * Location: /public/job-detail.php
 */
require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;

// Get job reference number
$jobRef = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_STRING);

// Backward compatibility with old parameter
if (!$jobRef) {
    $jobRef = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);
}

if (!$jobRef) {
    header('Location: /public/jobs.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

// Get job details
$stmt = $conn->prepare("
    SELECT 
        j.*,
        c.company_name,
        c.contact_person
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($job['job_title']) ?> - ProConsultancy</title>
    <meta name="description" content="<?= htmlspecialchars(substr(strip_tags($job['description']), 0, 160)) ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }
        .navbar {
            background: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .job-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
        }
        .job-content {
            background: white;
            border-radius: 8px;
            padding: 30px;
            margin-top: -30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .sidebar-card {
            position: sticky;
            top: 20px;
            background: white;
            border-radius: 8px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .apply-btn {
            width: 100%;
            padding: 12px;
            font-size: 1.1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand fw-bold" href="/">
                <i class="bx bx-briefcase fs-4"></i> ProConsultancy
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/public/jobs.php">Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/about.php">About Us</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/public/contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Job Header -->
    <div class="job-header">
        <div class="container">
            <a href="/public/jobs.php" class="btn btn-light btn-sm mb-3">
                <i class="bx bx-arrow-back"></i> Back to Jobs
            </a>
            <h1 class="mb-2"><?= htmlspecialchars($job['job_title']) ?></h1>
            <p class="mb-0">
                <i class="bx bx-building"></i> <?= htmlspecialchars($job['company_name'] ?? 'ProConsultancy') ?>
                <span class="mx-2">|</span>
                <i class="bx bx-map"></i> <?= htmlspecialchars($job['location']) ?>
                <span class="mx-2">|</span>
                <i class="bx bx-calendar"></i> Posted <?= date('M d, Y', strtotime($job['published_at'])) ?>
            </p>
        </div>
    </div>
    
    <!-- Job Content -->
    <div class="container my-4">
        <div class="row">
            <!-- Main Content -->
            <div class="col-lg-8">
                <div class="job-content">
                    <h3 class="mb-4">Job Description</h3>
                    <div class="job-description mb-4">
                        <?= nl2br(htmlspecialchars($job['description'])) ?>
                    </div>
                    
                    <!-- Mobile Apply Button -->
                    <div class="d-lg-none mb-4">
                        <a href="/public/apply.php?job=<?= urlencode($job['job_refno']) ?>" 
                           class="btn btn-primary apply-btn">
                            <i class="bx bx-send"></i> Apply Now
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="sidebar-card">
                    <h5 class="mb-4">Job Details</h5>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Company</small>
                        <strong><?= htmlspecialchars($job['company_name'] ?? 'ProConsultancy') ?></strong>
                    </div>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Location</small>
                        <strong><?= htmlspecialchars($job['location']) ?></strong>
                    </div>
                    
                    <?php if ($job['show_salary'] && ($job['salary_min'] || $job['salary_max'])): ?>
                        <div class="mb-3">
                            <small class="text-muted d-block">Salary Range</small>
                            <strong>
                                €<?= number_format($job['salary_min'], 0) ?> - €<?= number_format($job['salary_max'], 0) ?>
                            </strong>
                        </div>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <small class="text-muted d-block">Positions</small>
                        <strong><?= $job['positions_total'] ?> opening<?= $job['positions_total'] > 1 ? 's' : '' ?></strong>
                    </div>
                    
                    <div class="mb-4">
                        <small class="text-muted d-block">Posted</small>
                        <strong><?= date('M d, Y', strtotime($job['published_at'])) ?></strong>
                    </div>
                    
                    <hr class="my-4">
                    
                    <!-- Apply Button -->
                    <a href="/public/apply.php?job=<?= urlencode($job['job_refno']) ?>" 
                       class="btn btn-primary apply-btn mb-3">
                        <i class="bx bx-send"></i> Apply Now
                    </a>
                    
                    <!-- Share Buttons -->
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary btn-sm" onclick="shareJob()">
                            <i class="bx bx-share-alt"></i> Share Job
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">ProConsultancy</h5>
                    <p class="text-muted">Your trusted recruitment partner in Belgium and beyond.</p>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="/" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="/public/jobs.php" class="text-muted text-decoration-none">Jobs</a></li>
                        <li><a href="/public/about.php" class="text-muted text-decoration-none">About Us</a></li>
                        <li><a href="/public/contact.php" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-4 mb-4">
                    <h5 class="mb-3">Contact</h5>
                    <p class="text-muted">
                        <i class="bx bx-envelope"></i> info@proconsultancy.com<br>
                        <i class="bx bx-phone"></i> +32 2 123 4567<br>
                        <i class="bx bx-map"></i> Brussels, Belgium
                    </p>
                </div>
            </div>
            <hr class="border-secondary">
            <div class="text-center text-muted">
                <p class="mb-0">&copy; <?= date('Y') ?> ProConsultancy. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function shareJob() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= addslashes($job['job_title']) ?>',
                    text: 'Check out this job opportunity at <?= addslashes($job['company_name'] ?? 'ProConsultancy') ?>',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href);
                alert('Link copied to clipboard!');
            }
        }
    </script>
</body>
</html>