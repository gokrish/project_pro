<?php
/**
 * Public Job Board - List All Published Jobs
 * Location: /public/jobs.php
 */
require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;

$db = Database::getInstance();
$conn = $db->getConnection();

// Get all published jobs
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
        j.created_at,
        j.published_at,
        c.company_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.status = 'open'
    AND j.is_published = 1
    AND j.approval_status = 'approved'
    AND j.deleted_at IS NULL
    ORDER BY j.published_at DESC
");
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Job Opportunities - ProConsultancy</title>
    <meta name="description" content="Explore job opportunities at ProConsultancy. View our current openings.">
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
        .job-card {
            transition: transform 0.2s, box-shadow 0.2s;
            border-left: 4px solid #0d6efd;
        }
        .job-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
        }
        .hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 60px 0;
        }
        .badge-salary {
            background: #e7f3ff;
            color: #0d6efd;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 500;
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
    
    <!-- Hero Section -->
    <div class="hero">
        <div class="container text-center">
            <h1 class="display-4 fw-bold mb-3">Find Your Dream Job</h1>
            <p class="lead mb-4">Explore our current job openings and take the next step in your career</p>
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" placeholder="Search jobs..." id="searchInput">
                        <button class="btn btn-light" type="button">
                            <i class="bx bx-search"></i> Search
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Jobs Listing -->
    <div class="container my-5">
        <div class="row mb-4">
            <div class="col-12">
                <h2 class="mb-3">Current Openings</h2>
                <p class="text-muted">We have <?= count($jobs) ?> position<?= count($jobs) !== 1 ? 's' : '' ?> available</p>
            </div>
        </div>
        
        <?php if (empty($jobs)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm">
                        <div class="card-body text-center py-5">
                            <i class="bx bx-briefcase display-1 text-muted"></i>
                            <h4 class="mt-3">No openings at the moment</h4>
                            <p class="text-muted">Check back soon for new opportunities!</p>
                            <a href="/public/contact.php" class="btn btn-primary mt-3">
                                <i class="bx bx-envelope"></i> Contact Us
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($jobs as $job): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card job-card h-100 shadow-sm">
                            <div class="card-body">
                                <!-- Company -->
                                <div class="d-flex align-items-center mb-3">
                                    <div class="bg-primary bg-opacity-10 rounded p-2 me-2">
                                        <i class="bx bx-building fs-4 text-primary"></i>
                                    </div>
                                    <small class="text-muted"><?= htmlspecialchars($job['company_name'] ?? 'ProConsultancy') ?></small>
                                </div>
                                
                                <!-- Job Title -->
                                <h5 class="card-title mb-3">
                                    <a href="/public/job-detail.php?ref=<?= urlencode($job['job_refno']) ?>" 
                                       class="text-decoration-none text-dark">
                                        <?= htmlspecialchars($job['job_title']) ?>
                                    </a>
                                </h5>
                                
                                <!-- Meta Info -->
                                <div class="mb-3">
                                    <span class="text-muted me-3">
                                        <i class="bx bx-map"></i> <?= htmlspecialchars($job['location']) ?>
                                    </span>
                                    <span class="text-muted">
                                        <i class="bx bx-calendar"></i> <?= date('M d, Y', strtotime($job['published_at'])) ?>
                                    </span>
                                </div>
                                
                                <!-- Salary -->
                                <?php if ($job['show_salary'] && ($job['salary_min'] || $job['salary_max'])): ?>
                                    <div class="mb-3">
                                        <span class="badge-salary">
                                            <i class="bx bx-euro"></i>
                                            <?= number_format($job['salary_min'], 0) ?> - <?= number_format($job['salary_max'], 0) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Description Preview -->
                                <p class="text-muted mb-3">
                                    <?= htmlspecialchars(substr(strip_tags($job['description']), 0, 150)) ?>...
                                </p>
                                
                                <!-- Action Button -->
                                <a href="/public/job-detail.php?ref=<?= urlencode($job['job_refno']) ?>" 
                                   class="btn btn-primary">
                                    View Details & Apply <i class="bx bx-right-arrow-alt"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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
    
    <!-- Simple Search -->
    <script>
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const jobCards = document.querySelectorAll('.job-card');
            
            jobCards.forEach(card => {
                const text = card.textContent.toLowerCase();
                const parent = card.closest('.col-md-6');
                parent.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    </script>
</body>
</html>