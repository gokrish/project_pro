<?php
require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Jobs Dashboard';
$breadcrumbs = [
    'Jobs' => '#'
];

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';
require_once ROOT_PATH . '/panel/components/ui_components.php';

// Display breadcrumb
echo renderBreadcrumb($breadcrumbs);

$conn = dbConnect();
$user = Auth::user();

// Get statistics
$stats = [];

// Total applications
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM job_applications WHERE deleted_at IS NULL");
$stats['total'] = mysqli_fetch_assoc($result)['total'];

// By status
$statusCounts = [
    'screening' => 0,
    'pending_approval' => 0,
    'approved' => 0,
    'submitted' => 0,
    'interviewing' => 0,
    'offered' => 0,
    'placed' => 0,
    'rejected' => 0
];

$result = mysqli_query($conn, "
    SELECT status, COUNT(*) as count 
    FROM job_applications 
    WHERE deleted_at IS NULL 
    GROUP BY status
");

while ($row = mysqli_fetch_assoc($result)) {
    $statusCounts[$row['status']] = $row['count'];
}

// Recent applications
$recentQuery = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.deleted_at IS NULL
    ORDER BY ja.created_at DESC
    LIMIT 10
";

$recentApplications = mysqli_query($conn, $recentQuery);


// Include header
require_once ROOT_PATH . '/panel/includes/header.php';
require_once ROOT_PATH . '/panel/components/ui_components.php';

// Display breadcrumb
echo renderBreadcrumb($breadcrumbs);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-briefcase"></i> Applications Dashboard</h2>
            <p class="text-muted">Overview of all job applications</p>
        </div>
        <div>
            <a href="pipeline.php" class="btn btn-primary">
                <i class="bx bx-grid-alt"></i> Pipeline View
            </a>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Total Applications</h6>
                            <h3 class="mb-0"><?php echo number_format($stats['total']); ?></h3>
                        </div>
                        <div class="avatar avatar-lg bg-label-primary">
                            <i class="bx bx-briefcase bx-lg"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Screening</h6>
                            <h3 class="mb-0"><?php echo number_format($statusCounts['screening']); ?></h3>
                        </div>
                        <div class="avatar avatar-lg bg-label-info">
                            <i class="bx bx-search bx-lg"></i>
                        </div>
                    </div>
                    <a href="screening.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Pending Approval</h6>
                            <h3 class="mb-0"><?php echo number_format($statusCounts['pending_approval']); ?></h3>
                        </div>
                        <div class="avatar avatar-lg bg-label-warning">
                            <i class="bx bx-time bx-lg"></i>
                        </div>
                    </div>
                    <a href="pending_approval.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-1">Submitted</h6>
                            <h3 class="mb-0"><?php echo number_format($statusCounts['submitted']); ?></h3>
                        </div>
                        <div class="avatar avatar-lg bg-label-primary">
                            <i class="bx bx-send bx-lg"></i>
                        </div>
                    </div>
                    <a href="submitted.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Stats -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Interviewing</h6>
                    <h4 class="mb-0"><?php echo number_format($statusCounts['interviewing']); ?></h4>
                    <a href="interviewing.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Offered</h6>
                    <h4 class="mb-0"><?php echo number_format($statusCounts['offered']); ?></h4>
                    <a href="offered.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h6 class="text-white mb-1">Placed</h6>
                    <h4 class="mb-0 text-white"><?php echo number_format($statusCounts['placed']); ?></h4>
                    <a href="placed.php" class="btn btn-sm btn-link text-white p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-1">Rejected</h6>
                    <h4 class="mb-0"><?php echo number_format($statusCounts['rejected']); ?></h4>
                    <a href="rejected.php" class="btn btn-sm btn-link p-0 mt-2">View all →</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Applications -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Recent Applications</h5>
            <a href="list.php" class="btn btn-sm btn-primary">View All</a>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($app = mysqli_fetch_assoc($recentApplications)): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($app['email_id']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($app['job_code']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></td>
                            <td><?php include 'components/status_badge.php'; ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                            <td>
                                <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-info">
                                    <i class="bx bx-show"></i> View
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>