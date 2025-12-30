<?php
/**
 * Interviewing - Applications in interview stage
 */

// Load common bootstrap
require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Application Interview';


// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

$conn = dbConnect();

// Get interviewing applications with upcoming/past interviews
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           u.name as created_by_name,
           (SELECT COUNT(*) FROM interviews WHERE application_id = ja.application_id) as interview_count,
           (SELECT interview_date FROM interviews WHERE application_id = ja.application_id ORDER BY interview_date DESC LIMIT 1) as last_interview_date,
           (SELECT status FROM interviews WHERE application_id = ja.application_id ORDER BY interview_date DESC LIMIT 1) as last_interview_status
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE ja.status IN ('interviewing', 'interview_passed')
    AND ja.deleted_at IS NULL
    ORDER BY ja.updated_at DESC
";

$applications = mysqli_query($conn, $query);

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-calendar"></i> Interview Stage</h2>
            <p class="text-muted">Applications currently in interview process</p>
        </div>
        <a href="list.php?status=interviewing" class="btn btn-primary">
            <i class="bx bx-filter"></i> Filter View
        </a>
    </div>

    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-calendar-x bx-lg text-muted mb-3" style="font-size: 3rem;"></i>
                <h4>No Active Interviews</h4>
                <p class="text-muted">No applications currently in interview stage</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-0"><?php echo htmlspecialchars($app['candidate_name']); ?></h6>
                                <small class="text-muted"><?php echo htmlspecialchars($app['job_title']); ?></small>
                            </div>
                            <?php 
                            $status = $app['status'];
                            include 'components/status_badge.php'; 
                            ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Client Info -->
                        <div class="mb-3">
                            <small class="text-muted">Client:</small>
                            <strong class="d-block"><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></strong>
                        </div>

                        <!-- Interview Stats -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span><i class="bx bx-calendar-event"></i> Interviews:</span>
                                <span class="badge bg-primary"><?php echo $app['interview_count']; ?> Round<?php echo $app['interview_count'] !== 1 ? 's' : ''; ?></span>
                            </div>
                            
                            <?php if ($app['last_interview_date']): ?>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span><i class="bx bx-time"></i> Last Interview:</span>
                                    <span><?php echo date('M d, Y', strtotime($app['last_interview_date'])); ?></span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span>Status:</span>
                                    <span class="badge bg-<?php echo $app['last_interview_status'] === 'completed' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($app['last_interview_status']); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Contact Info -->
                        <div class="mb-3">
                            <small class="text-muted d-block">Contact:</small>
                            <small>
                                <i class="bx bx-envelope"></i> <?php echo htmlspecialchars($app['email_id']); ?><br>
                                <?php if ($app['phone']): ?>
                                    <i class="bx bx-phone"></i> <?php echo htmlspecialchars($app['phone']); ?>
                                <?php endif; ?>
                            </small>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="d-grid gap-2">
                            <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-info btn-sm">
                                <i class="bx bx-show"></i> View Details
                            </a>
                            <div class="btn-group">
                                <button class="btn btn-primary btn-sm" onclick="scheduleInterview(<?php echo $app['application_id']; ?>)">
                                    <i class="bx bx-calendar-plus"></i> Schedule
                                </button>
                                <button class="btn btn-success btn-sm" onclick="createOffer(<?php echo $app['application_id']; ?>)">
                                    <i class="bx bx-gift"></i> Offer
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

<!-- Modals -->
<?php include 'modals/interview_modal.php'; ?>
<?php include 'modals/offer_modal.php'; ?>

<script>
function scheduleInterview(applicationId) {
    $('#interviewModal').data('application-id', applicationId).modal('show');
}

function createOffer(applicationId) {
    $('#offerModal').data('application-id', applicationId).modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>