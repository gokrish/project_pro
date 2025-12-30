<?php
/**
 * Public Job Board - List All Jobs
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
        j.job_description,
        j.location,
        j.employment_type,
        j.salary_min,
        j.salary_max,
        j.created_at,
        c.company_name
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    WHERE j.status = 'open'
    AND j.is_published = 1
    AND (j.expires_at IS NULL OR j.expires_at > NOW())
    AND j.deleted_at IS NULL
    ORDER BY j.created_at DESC
");
$stmt->execute();
$jobs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Job Opportunities - Pro Consultancy</title>
    <meta name="description" content="Explore job opportunities at Pro Consultancy. View our current openings.">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Copy all CSS/fonts from old jobpost.php -->
    <link rel="stylesheet" href="css/bootstrap.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <!-- Copy header/nav from old jobpost.php -->
    
    <div class="container my-5">
        <h1 class="text-center mb-4">Current Job Openings</h1>
        
        <div class="row">
            <?php foreach ($jobs as $job): ?>
                <div class="col-md-6 mb-4">
                    <div class="card job-card h-100">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo htmlspecialchars($job['job_title']); ?></h5>
                            <p class="text-muted">
                                <i class="bi bi-building"></i> <?php echo htmlspecialchars($job['company_name'] ?? 'Pro Consultancy'); ?><br>
                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars($job['location']); ?><br>
                                <i class="bi bi-briefcase"></i> <?php echo htmlspecialchars($job['employment_type']); ?>
                            </p>
                            <p><?php echo substr(strip_tags($job['job_description']), 0, 150); ?>...</p>
                            <a href="job-detail.php?code=<?php echo urlencode($job['job_code']); ?>" 
                               class="btn btn-primary">
                                View Details & Apply
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($jobs)): ?>
                <div class="col-12 text-center">
                    <p class="lead">No openings at the moment. Check back soon!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Copy footer from old jobpost.php -->
</body>
</html>