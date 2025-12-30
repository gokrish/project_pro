<?php
/**
 * Successfully Placed - Completed placements
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications Placed';


$conn = dbConnect();

// Get placed applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           o.offered_salary,
           o.offered_currency,
           o.start_date,
           ja.placement_date,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN offers o ON ja.application_id = o.application_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.status = 'placed'
    AND ja.deleted_at IS NULL
    ORDER BY ja.placement_date DESC
";

$applications = mysqli_query($conn, $query);

// Get stats
$statsQuery = "
    SELECT 
        COUNT(*) as total_placed,
        SUM(o.offered_salary) as total_value,
        AVG(DATEDIFF(ja.placement_date, ja.created_at)) as avg_days_to_placement
    FROM job_applications ja
    LEFT JOIN offers o ON ja.application_id = o.application_id
    WHERE ja.status = 'placed'
    AND ja.deleted_at IS NULL
";
$stats = mysqli_fetch_assoc(mysqli_query($conn, $statsQuery));

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-trophy text-success"></i> Successfully Placed</h2>
            <p class="text-muted">Completed placements</p>
        </div>
    </div>

    <!-- Success Stats -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h3 class="mb-0"><?php echo number_format($stats['total_placed']); ?></h3>
                    <p class="mb-0">Total Placements</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h3 class="mb-0">€<?php echo number_format($stats['total_value'] ?? 0); ?></h3>
                    <p class="mb-0">Total Salary Value</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h3 class="mb-0"><?php echo round($stats['avg_days_to_placement'] ?? 0); ?> days</h3>
                    <p class="mb-0">Avg. Time to Placement</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Placements List -->
    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-trophy bx-lg text-muted mb-3" style="font-size: 3rem;"></i>
                <h4>No Placements Yet</h4>
                <p class="text-muted">Successfully placed candidates will appear here</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Placement History</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="placedTable">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Job</th>
                                <th>Client</th>
                                <th>Salary</th>
                                <th>Start Date</th>
                                <th>Placed Date</th>
                                <th>Time to Place</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                            <?php 
                            $daysToPlace = floor((strtotime($app['placement_date']) - strtotime($app['created_at'])) / 86400);
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm bg-success me-2">
                                            <i class="bx bx-check text-white"></i>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($app['email_id']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['job_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></td>
                                <td>
                                    <?php if ($app['offered_salary']): ?>
                                        <strong><?php echo $app['offered_currency']; ?> <?php echo number_format($app['offered_salary']); ?></strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($app['start_date']): ?>
                                        <?php echo date('M d, Y', strtotime($app['start_date'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['placement_date'])); ?></td>
                                <td>
                                    <span class="badge bg-info"><?php echo $daysToPlace; ?> days</span>
                                </td>
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
    <?php endif; ?>
</div>

<script>
$('#placedTable').DataTable({
    order: [[5, 'desc']], // Sort by placement date
    pageLength: 25,
    dom: 'Bfrtip',
    buttons: [
        'copy', 'csv', 'excel', 'pdf', 'print'
    ]
});
</script>

<?php include '../../includes/footer.php'; ?>