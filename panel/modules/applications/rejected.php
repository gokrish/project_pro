<?php
/**
 * Rejected Applications - For analysis and future reference
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications Rejected';
$breadcrumbs = [
    'Application' => '#'
];

$conn = dbConnect();

// Get rejected applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           rejector.name as rejected_by_name,
           ja.rejected_at
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user rejector ON ja.rejected_by = rejector.user_code
    WHERE ja.status = 'rejected'
    AND ja.deleted_at IS NULL
    ORDER BY ja.rejected_at DESC
";

$applications = mysqli_query($conn, $query);

// Get rejection statistics
$reasonsQuery = "
    SELECT rejection_reason, COUNT(*) as count
    FROM job_applications
    WHERE status = 'rejected'
    AND deleted_at IS NULL
    GROUP BY rejection_reason
    ORDER BY count DESC
    LIMIT 10
";
$topReasons = mysqli_query($conn, $reasonsQuery);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-x-circle text-danger"></i> Rejected Applications</h2>
            <p class="text-muted">Historical data for analysis and improvement</p>
        </div>
    </div>

    <!-- Top Rejection Reasons -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Top Rejection Reasons</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <?php while ($reason = mysqli_fetch_assoc($topReasons)): ?>
                <div class="col-md-6 mb-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span><?php echo htmlspecialchars($reason['rejection_reason']); ?></span>
                        <span class="badge bg-secondary"><?php echo $reason['count']; ?></span>
                    </div>
                    <div class="progress mt-1" style="height: 5px;">
                        <div class="progress-bar bg-danger" role="progressbar" 
                             style="width: <?php echo ($reason['count'] / mysqli_num_rows($applications)) * 100; ?>%">
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>

    <!-- Rejected Applications List -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="rejectedTable">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Client</th>
                            <th>Rejection Stage</th>
                            <th>Reason</th>
                            <th>Rejected By</th>
                            <th>Rejected Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php mysqli_data_seek($applications, 0); // Reset pointer ?>
                        <?php while ($app = mysqli_fetch_assoc($applications)): ?>
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
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo ucfirst(str_replace('_', ' ', $app['rejection_stage'])); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-link p-0" 
                                        onclick="showReason('<?php echo htmlspecialchars(addslashes($app['rejection_reason'])); ?>')">
                                    View Reason
                                </button>
                            </td>
                            <td><?php echo htmlspecialchars($app['rejected_by_name'] ?? 'System'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($app['rejected_at'])); ?></td>
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

<!-- Rejection Reason Modal -->
<div class="modal fade" id="reasonModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Rejection Reason</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reasonContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
$('#rejectedTable').DataTable({
    order: [[6, 'desc']], // Sort by rejected date
    pageLength: 25
});

function showReason(reason) {
    $('#reasonContent').html(reason.replace(/\n/g, '<br>'));
    $('#reasonModal').modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>