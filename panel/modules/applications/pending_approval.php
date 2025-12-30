<?php
/**
 * Pending Approval - Applications awaiting manager/admin approval
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
// Page configuration
$pageTitle = 'Applications Pending Approval';

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

$conn = dbConnect();
$user = Auth::user();
$isAdmin = ($user['level'] === 'admin' || $user['level'] === 'manager');

// Get pending approval applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.status = 'pending_approval'
    AND ja.deleted_at IS NULL
    ORDER BY ja.updated_at ASC
";

$applications = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-time-five"></i> Pending Approval</h2>
            <p class="text-muted">Applications waiting for manager/admin approval</p>
        </div>
        <?php if (!$isAdmin): ?>
            <div class="alert alert-warning mb-0">
                <i class="bx bx-info-circle"></i> Only admins can approve applications
            </div>
        <?php endif; ?>
    </div>

    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-check-circle bx-lg text-success mb-3" style="font-size: 3rem;"></i>
                <h4>No Pending Approvals</h4>
                <p class="text-muted">All applications have been reviewed</p>
                <a href="list.php" class="btn btn-primary">View All Applications</a>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="pendingTable">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Job</th>
                                <th>Client</th>
                                <th>Submitted By</th>
                                <th>Waiting Since</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                            <?php 
                            $waitingDays = floor((time() - strtotime($app['updated_at'])) / 86400);
                            $urgentClass = $waitingDays > 2 ? 'table-warning' : '';
                            ?>
                            <tr class="<?php echo $urgentClass; ?>">
                                <td>
                                    <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['email_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['job_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($app['created_by_name']); ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($app['updated_at'])); ?><br>
                                    <small class="text-muted">
                                        (<?php echo $waitingDays === 0 ? 'today' : "$waitingDays days ago"; ?>)
                                    </small>
                                    <?php if ($waitingDays > 2): ?>
                                        <br><span class="badge bg-warning">Urgent</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-info">
                                        <i class="bx bx-show"></i> Review
                                    </a>
                                    <?php if ($isAdmin): ?>
                                        <button class="btn btn-sm btn-success" onclick="approveApp(<?php echo $app['application_id']; ?>)">
                                            <i class="bx bx-check"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="rejectApp(<?php echo $app['application_id']; ?>)">
                                            <i class="bx bx-x"></i> Reject
                                        </button>
                                    <?php endif; ?>
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

<!-- Modals -->
<?php include 'modals/approve_modal.php'; ?>
<?php include 'modals/reject_modal.php'; ?>

<script>
$('#pendingTable').DataTable({
    order: [[4, 'asc']], // Sort by waiting time (oldest first)
    pageLength: 25
});

function approveApp(applicationId) {
    $('#approveModal').data('application-id', applicationId).modal('show');
}

function rejectApp(applicationId) {
    $('#rejectModal').data('application-id', applicationId).modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>