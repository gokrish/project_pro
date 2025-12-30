<?php
require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;

$jobCode = filter_input(INPUT_GET, 'code', FILTER_SANITIZE_STRING);

// Try old parameter for backward compatibility
if (!$jobCode) {
    $jobCode = filter_input(INPUT_GET, 'ref_no', FILTER_SANITIZE_STRING);
}

if (!$jobCode) {
    header('Location: jobs.php');
    exit;
}

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("
    SELECT 
        j.*,
        c.company_name,
        c.company_description,
        u.name as contact_name,
        u.email as contact_email
    FROM jobs j
    LEFT JOIN clients c ON j.client_code = c.client_code
    LEFT JOIN users u ON j.created_by = u.user_code
    WHERE (j.job_code = ? OR j.job_refno = ?)
    AND j.status = 'open'
    AND j.is_published = 1
    AND j.deleted_at IS NULL
");
$stmt->bind_param("ss", $jobCode, $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header('Location: jobs.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?php echo htmlspecialchars($job['job_title']); ?> - Pro Consultancy</title>
    <meta name="description" content="<?php echo htmlspecialchars(substr(strip_tags($job['job_description']), 0, 160)); ?>">
    <!-- Same CSS as jobs.php -->
</head>
<body>
    <!-- Same header as jobs.php -->
    
    <div class="container my-5">
        <div class="row">
            <div class="col-lg-8">
                <h1><?php echo htmlspecialchars($job['job_title']); ?></h1>
                
                <div class="job-meta mb-4">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($job['employment_type']); ?></span>
                    <span class="badge bg-secondary"><?php echo htmlspecialchars($job['location']); ?></span>
                </div>
                
                <div class="job-description">
                    <h3>Job Description</h3>
                    <?php echo $job['job_description']; // Already HTML from TinyMCE ?>
                </div>
                
                <div class="job-requirements mt-4">
                    <h3>Requirements</h3>
                    <?php echo $job['requirements']; ?>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card sticky-top">
                    <div class="card-body">
                        <h5>Job Details</h5>
                        <table class="table table-sm">
                            <tr>
                                <th>Company:</th>
                                <td><?php echo htmlspecialchars($job['company_name'] ?? 'Pro Consultancy'); ?></td>
                            </tr>
                            <tr>
                                <th>Location:</th>
                                <td><?php echo htmlspecialchars($job['location']); ?></td>
                            </tr>
                            <tr>
                                <th>Type:</th>
                                <td><?php echo htmlspecialchars($job['employment_type']); ?></td>
                            </tr>
                            <?php if ($job['salary_min'] && $job['salary_max']): ?>
                            <tr>
                                <th>Salary:</th>
                                <td><?php echo formatMoney($job['salary_min']); ?> - <?php echo formatMoney($job['salary_max']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Posted:</th>
                                <td><?php echo formatDate($job['created_at']); ?></td>
                            </tr>
                        </table>
                        
                        <a href="apply.php?job=<?php echo urlencode($job['job_code']); ?>" 
                           class="btn btn-primary btn-lg w-100 mt-3">
                            <i class="bi bi-file-earmark-text"></i> Apply Now
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Same footer as jobs.php -->
</body>
</html>