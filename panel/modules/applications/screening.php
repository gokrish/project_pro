<?php
/**
 * Screening Queue - Applications in screening stage
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications Screening';

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';


$conn = dbConnect();
$user = Auth::user();

// Get screening applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           c.experience_years,
           c.current_location,
           c.expected_salary,
           j.title as job_title,
           j.job_code,
           j.salary_min,
           j.salary_max,
           cl.client_name,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.status IN ('screening', 'applied')
    AND ja.deleted_at IS NULL
    ORDER BY ja.created_at ASC
";

$applications = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-search"></i> Screening Queue</h2>
            <p class="text-muted">Applications awaiting initial screening</p>
        </div>
        <div>
            <a href="list.php" class="btn btn-secondary">
                <i class="bx bx-list-ul"></i> All Applications
            </a>
        </div>
    </div>

    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-check-circle bx-lg text-success mb-3" style="font-size: 3rem;"></i>
                <h4>All Caught Up!</h4>
                <p class="text-muted">No applications in screening queue</p>
                <a href="list.php" class="btn btn-primary">View All Applications</a>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bx bx-user"></i> <?php echo htmlspecialchars($app['candidate_name']); ?>
                        </h6>
                        <?php 
                        $status = $app['status'];
                        include 'components/status_badge.php'; 
                        ?>
                    </div>
                    <div class="card-body">
                        <!-- Job Info -->
                        <div class="mb-3">
                            <small class="text-muted d-block">Applied for:</small>
                            <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                            <br>
                            <small class="text-muted">
                                <i class="bx bx-building"></i> <?php echo htmlspecialchars($app['client_name'] ?? 'No client'); ?>
                            </small>
                        </div>

                        <!-- Candidate Quick Info -->
                        <div class="mb-3">
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <td class="text-muted" style="width: 40%;"><i class="bx bx-envelope"></i> Email:</td>
                                    <td><small><?php echo htmlspecialchars($app['email_id']); ?></small></td>
                                </tr>
                                <?php if ($app['phone']): ?>
                                <tr>
                                    <td class="text-muted"><i class="bx bx-phone"></i> Phone:</td>
                                    <td><small><?php echo htmlspecialchars($app['phone']); ?></small></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($app['experience_years']): ?>
                                <tr>
                                    <td class="text-muted"><i class="bx bx-briefcase"></i> Experience:</td>
                                    <td><small><?php echo $app['experience_years']; ?> years</small></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($app['current_location']): ?>
                                <tr>
                                    <td class="text-muted"><i class="bx bx-map"></i> Location:</td>
                                    <td><small><?php echo htmlspecialchars($app['current_location']); ?></small></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($app['expected_salary']): ?>
                                <tr>
                                    <td class="text-muted"><i class="bx bx-dollar"></i> Expected:</td>
                                    <td><small>€<?php echo number_format($app['expected_salary']); ?></small></td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>

                        <!-- Salary Comparison -->
                        <?php if ($app['expected_salary'] && $app['salary_max']): ?>
                            <?php 
                            $salaryDiff = $app['expected_salary'] - $app['salary_max'];
                            $withinBudget = $salaryDiff <= 0;
                            ?>
                            <div class="alert alert-<?php echo $withinBudget ? 'success' : 'warning'; ?> py-2">
                                <small>
                                    <strong>Budget:</strong> €<?php echo number_format($app['salary_min']); ?> - €<?php echo number_format($app['salary_max']); ?><br>
                                    <?php if ($withinBudget): ?>
                                        <i class="bx bx-check-circle"></i> Within budget
                                    <?php else: ?>
                                        <i class="bx bx-error-circle"></i> €<?php echo number_format(abs($salaryDiff)); ?> over budget
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Applied Date -->
                        <div class="text-muted mb-3">
                            <small>
                                <i class="bx bx-time"></i> Applied <?php echo date('M d, Y', strtotime($app['created_at'])); ?>
                                (<?php 
                                $days = floor((time() - strtotime($app['created_at'])) / 86400);
                                echo $days === 0 ? 'today' : "$days days ago";
                                ?>)
                            </small>
                        </div>
                    </div>

                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-primary btn-sm">
                                <i class="bx bx-show"></i> Review Application
                            </a>
                            
                            <div class="btn-group">
                                <button class="btn btn-success btn-sm" onclick="quickApprove(<?php echo $app['application_id']; ?>)">
                                    <i class="bx bx-check"></i> Pass Screening
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="quickReject(<?php echo $app['application_id']; ?>)">
                                    <i class="bx bx-x"></i> Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Quick Actions Modals -->
<?php include 'modals/change_status_modal.php'; ?>
<?php include 'modals/reject_modal.php'; ?>

<script>
function quickApprove(applicationId) {
    if (confirm('Mark this candidate as passed initial screening?')) {
        $.post('handlers/status_handler.php', {
            application_id: applicationId,
            status: 'screening_passed',
            notes: 'Passed initial screening review'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    }
}

function quickReject(applicationId) {
    $('#rejectModal').data('application-id', applicationId).modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>