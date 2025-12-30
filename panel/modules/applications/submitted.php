<?php
/**
 * Submitted to Client - Applications submitted and waiting for client feedback
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications Submitted';

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';


$conn = dbConnect();

// Get submitted applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           cl.contact_person,
           cl.email as client_email,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.status IN ('submitted', 'shortlisted')
    AND ja.deleted_at IS NULL
    ORDER BY ja.submitted_date DESC
";

$applications = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-send"></i> Submitted to Client</h2>
            <p class="text-muted">Applications submitted and awaiting client feedback</p>
        </div>
    </div>

    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-inbox bx-lg text-muted mb-3" style="font-size: 3rem;"></i>
                <h4>No Submitted Applications</h4>
                <p class="text-muted">No applications currently with client</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="submittedTable">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Job</th>
                                <th>Client</th>
                                <th>Submitted Date</th>
                                <th>Days Waiting</th>
                                <th>Feedback</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                            <?php 
                            $daysWaiting = floor((time() - strtotime($app['submitted_date'])) / 86400);
                            $hasFeedback = !empty($app['client_feedback']);
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['email_id']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['job_code']); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></strong><br>
                                    <?php if ($app['contact_person']): ?>
                                        <small class="text-muted">
                                            Contact: <?php echo htmlspecialchars($app['contact_person']); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($app['submitted_date'])); ?></td>
                                <td>
                                    <?php echo $daysWaiting; ?> day<?php echo $daysWaiting !== 1 ? 's' : ''; ?>
                                    <?php if ($daysWaiting > 7): ?>
                                        <br><span class="badge bg-warning">Follow up needed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasFeedback): ?>
                                        <span class="badge bg-success">
                                            <i class="bx bx-check"></i> Received
                                        </span>
                                        <button class="btn btn-sm btn-link p-0" 
                                                onclick="showFeedback('<?php echo htmlspecialchars(addslashes($app['client_feedback'])); ?>')">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bx bx-show"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown"></button>
                                        <ul class="dropdown-menu">
                                            <?php if (!$hasFeedback): ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="addFeedback(<?php echo $app['application_id']; ?>)">
                                                    <i class="bx bx-message-dots"></i> Add Feedback
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="scheduleInterview(<?php echo $app['application_id']; ?>)">
                                                    <i class="bx bx-calendar"></i> Schedule Interview
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <?php if ($app['client_email']): ?>
                                            <li>
                                                <a class="dropdown-item" href="mailto:<?php echo $app['client_email']; ?>?subject=Follow-up: <?php echo urlencode($app['candidate_name']); ?>">
                                                    <i class="bx bx-envelope"></i> Email Client
                                                </a>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
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

<!-- Feedback Display Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Client Feedback</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="feedbackContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Other Modals -->
<?php include 'modals/feedback_modal.php'; ?>
<?php include 'modals/interview_modal.php'; ?>

<script>
$('#submittedTable').DataTable({
    order: [[3, 'desc']], // Sort by submitted date
    pageLength: 25
});

function showFeedback(feedback) {
    $('#feedbackContent').html(feedback.replace(/\n/g, '<br>'));
    $('#feedbackModal').modal('show');
}

function addFeedback(applicationId) {
    $('#feedbackModal').data('application-id', applicationId).modal('show');
}

function scheduleInterview(applicationId) {
    $('#interviewModal').data('application-id', applicationId).modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>