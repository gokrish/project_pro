<?php
require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications View';


$application_id = (int)($_GET['id'] ?? 0);
$conn = dbConnect();
$user = Auth::user();
$isAdmin = ($user['level'] === 'admin');

// Fetch application details
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           c.current_location,
           c.expected_salary,
           j.title as job_title,
           j.job_code,
           j.description as job_description,
           j.location as job_location,
           j.salary_min,
           j.salary_max,
           cl.client_name,
           cl.contact_person as client_contact,
           cl.email as client_email,
           u.name as created_by_name,
           approver.name as approver_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    LEFT JOIN user approver ON ja.approved_by = approver.user_code
    WHERE ja.application_id = ? AND ja.deleted_at IS NULL
";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $application_id);
$stmt->execute();
$app = $stmt->get_result()->fetch_assoc();

if (!$app) {
    header('Location: list.php');
    exit;
}

// Get activity timeline
$timelineQuery = "
    SELECT al.*, u.name as user_name
    FROM activity_log al
    LEFT JOIN user u ON al.user_code = u.user_code
    WHERE al.entity_type = 'application' AND al.entity_id = ?
    ORDER BY al.created_at DESC
";

$timelineStmt = $conn->prepare($timelineQuery);
$timelineStmt->bind_param('i', $application_id);
$timelineStmt->execute();
$timeline = $timelineStmt->get_result();

// Get interviews
$interviewsQuery = "
    SELECT i.*, u.name as interviewer_name
    FROM interviews i
    LEFT JOIN user u ON i.interviewer_user_code = u.user_code
    WHERE i.application_id = ?
    ORDER BY i.interview_date DESC
";

$interviewsStmt = $conn->prepare($interviewsQuery);
$interviewsStmt->bind_param('i', $application_id);
$interviewsStmt->execute();
$interviews = $interviewsStmt->get_result();

// Get notes
$notesQuery = "
    SELECT cn.*, u.name as created_by_name
    FROM candidate_notes cn
    LEFT JOIN user u ON cn.created_by = u.user_code
    WHERE cn.can_code = ? AND cn.job_id = ?
    ORDER BY cn.created_at DESC
";

$notesStmt = $conn->prepare($notesQuery);
$notesStmt->bind_param('si', $app['can_code'], $app['job_id']);
$notesStmt->execute();
$notes = $notesStmt->get_result();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Back Button -->
    <div class="mb-3">
        <a href="list.php" class="btn btn-secondary btn-sm">
            <i class="bx bx-arrow-back"></i> Back to Applications
        </a>
    </div>

    <!-- Application Header -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h3 class="mb-1"><?php echo htmlspecialchars($app['candidate_name']); ?></h3>
                    <p class="text-muted mb-0">
                        Applied for: <strong><?php echo htmlspecialchars($app['job_title']); ?></strong>
                    </p>
                    <small class="text-muted">
                        Applied on: <?php echo date('F d, Y', strtotime($app['created_at'])); ?>
                    </small>
                </div>
                <div class="col-md-6 text-end">
                    <?php 
                    $status = $app['status'];
                    include 'components/status_badge.php'; 
                    ?>
                    <div class="mt-2">
                        <small class="text-muted">Application ID: #<?php echo $app['application_id']; ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Left Column -->
        <div class="col-md-8">
            <!-- Candidate & Job Summary -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Application Details</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Candidate Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><i class="bx bx-user"></i> Name:</td>
                                    <td><strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-envelope"></i> Email:</td>
                                    <td><a href="mailto:<?php echo $app['email_id']; ?>"><?php echo htmlspecialchars($app['email_id']); ?></a></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-phone"></i> Phone:</td>
                                    <td><?php echo htmlspecialchars($app['phone'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-map"></i> Location:</td>
                                    <td><?php echo htmlspecialchars($app['current_location'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-dollar"></i> Expected Salary:</td>
                                    <td><?php echo $app['expected_salary'] ? '€' . number_format($app['expected_salary']) : '-'; ?></td>
                                </tr>
                            </table>
                            <a href="../candidates/view.php?code=<?php echo $app['can_code']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-show"></i> View Full Profile
                            </a>
                        </div>

                        <div class="col-md-6">
                            <h6 class="text-muted mb-3">Job Information</h6>
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><i class="bx bx-briefcase"></i> Job:</td>
                                    <td><strong><?php echo htmlspecialchars($app['job_title']); ?></strong></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-hash"></i> Job Code:</td>
                                    <td><?php echo htmlspecialchars($app['job_code']); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-building"></i> Client:</td>
                                    <td><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-map"></i> Location:</td>
                                    <td><?php echo htmlspecialchars($app['job_location'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <td><i class="bx bx-dollar"></i> Salary Range:</td>
                                    <td>
                                        <?php if ($app['salary_min'] && $app['salary_max']): ?>
                                            €<?php echo number_format($app['salary_min']); ?> - €<?php echo number_format($app['salary_max']); ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                            <a href="../jobs/view.php?id=<?php echo $app['job_id']; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bx bx-show"></i> View Job Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status-Specific Information -->
            <?php if ($app['approved_by']): ?>
            <div class="card mb-4 border-success">
                <div class="card-body">
                    <h6><i class="bx bx-check-circle text-success"></i> Approval Information</h6>
                    <p class="mb-0">
                        Approved by <strong><?php echo htmlspecialchars($app['approver_name']); ?></strong> 
                        on <?php echo date('M d, Y H:i', strtotime($app['approved_at'])); ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($app['submitted_to_client']): ?>
            <div class="card mb-4 border-primary">
                <div class="card-body">
                    <h6><i class="bx bx-send text-primary"></i> Client Submission</h6>
                    <p>
                        <strong>Submitted on:</strong> <?php echo date('M d, Y', strtotime($app['submitted_date'])); ?><br>
                        <strong>Client:</strong> <?php echo htmlspecialchars($app['client_name']); ?><br>
                        <?php if ($app['client_contact']): ?>
                            <strong>Contact:</strong> <?php echo htmlspecialchars($app['client_contact']); ?>
                            <?php if ($app['client_email']): ?>
                                (<a href="mailto:<?php echo $app['client_email']; ?>"><?php echo htmlspecialchars($app['client_email']); ?></a>)
                            <?php endif; ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($app['client_feedback']): ?>
                        <div class="alert alert-info mb-0">
                            <strong>Client Feedback:</strong><br>
                            <?php echo nl2br(htmlspecialchars($app['client_feedback'])); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Interviews -->
            <?php if ($interviews->num_rows > 0): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bx bx-calendar"></i> Interviews</h5>
                </div>
                <div class="card-body">
                    <?php while ($interview = mysqli_fetch_assoc($interviews)): ?>
                    <div class="border-bottom pb-3 mb-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1">
                                    <?php echo htmlspecialchars($interview['interview_type'] ?? 'Interview'); ?>
                                    <span class="badge bg-<?php echo $interview['status'] === 'completed' ? 'success' : ($interview['status'] === 'scheduled' ? 'primary' : 'secondary'); ?>">
                                        <?php echo ucfirst($interview['status']); ?>
                                    </span>
                                </h6>
                                <p class="mb-1">
                                    <i class="bx bx-calendar"></i> <?php echo date('M d, Y', strtotime($interview['interview_date'])); ?> 
                                    <i class="bx bx-time ms-2"></i> <?php echo date('H:i', strtotime($interview['interview_time'])); ?>
                                </p>
                                <?php if ($interview['interviewer_name']): ?>
                                    <p class="mb-1">
                                        <i class="bx bx-user"></i> Interviewer: <?php echo htmlspecialchars($interview['interviewer_name']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($interview['location']): ?>
                                    <p class="mb-1">
                                        <i class="bx bx-map"></i> <?php echo htmlspecialchars($interview['location']); ?>
                                    </p>
                                <?php endif; ?>
                                <?php if ($interview['notes']): ?>
                                    <div class="alert alert-light mt-2 mb-0">
                                        <strong>Notes:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($interview['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Notes -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bx bx-note"></i> Notes & Comments</h5>
                    <button class="btn btn-sm btn-primary" onclick="addNote()">
                        <i class="bx bx-plus"></i> Add Note
                    </button>
                </div>
                <div class="card-body">
                    <?php if ($notes->num_rows > 0): ?>
                        <?php while ($note = mysqli_fetch_assoc($notes)): ?>
                        <div class="border-bottom pb-3 mb-3">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <strong><?php echo htmlspecialchars($note['created_by_name']); ?></strong>
                                    <small class="text-muted ms-2">
                                        <?php echo date('M d, Y H:i', strtotime($note['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            <p class="mb-0 mt-2"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <p class="text-muted text-center py-3">No notes yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="col-md-4">
            <!-- Actions -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Actions</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <!-- Status-based actions -->
                        <?php if ($app['status'] === 'screening' && $isAdmin): ?>
                            <button class="btn btn-success" onclick="approveApplication()">
                                <i class="bx bx-check-circle"></i> Approve for Submission
                            </button>
                        <?php endif; ?>

                        <?php if ($app['status'] === 'approved' && !$app['submitted_to_client']): ?>
                            <button class="btn btn-primary" onclick="submitToClient()">
                                <i class="bx bx-send"></i> Submit to Client
                            </button>
                        <?php endif; ?>

                        <?php if ($app['status'] === 'submitted'): ?>
                            <button class="btn btn-info" onclick="addClientFeedback()">
                                <i class="bx bx-message-dots"></i> Add Client Feedback
                            </button>
                            <button class="btn btn-primary" onclick="scheduleInterview()">
                                <i class="bx bx-calendar"></i> Schedule Interview
                            </button>
                        <?php endif; ?>

                        <?php if (in_array($app['status'], ['interviewing', 'interview_passed'])): ?>
                            <button class="btn btn-success" onclick="createOffer()">
                                <i class="bx bx-gift"></i> Create Offer
                            </button>
                        <?php endif; ?>

                        <?php if ($app['status'] === 'offered'): ?>
                            <button class="btn btn-success" onclick="markAsPlaced()">
                                <i class="bx bx-check-double"></i> Mark as Placed
                            </button>
                        <?php endif; ?>

                        <!-- Always available actions -->
                        <button class="btn btn-secondary" onclick="changeStatus()">
                            <i class="bx bx-edit"></i> Change Status
                        </button>

                        <button class="btn btn-danger" onclick="rejectApplication()">
                            <i class="bx bx-x-circle"></i> Reject Application
                        </button>

                        <hr>

                        <a href="../candidates/view.php?code=<?php echo $app['can_code']; ?>" class="btn btn-outline-primary">
                            <i class="bx bx-user"></i> View Candidate Profile
                        </a>

                        <a href="../jobs/view.php?id=<?php echo $app['job_id']; ?>" class="btn btn-outline-primary">
                            <i class="bx bx-briefcase"></i> View Job Details
                        </a>
                    </div>
                </div>
            </div>

            <!-- Activity Timeline -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bx bx-time"></i> Activity Timeline</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php if ($timeline->num_rows > 0): ?>
                            <?php while ($activity = mysqli_fetch_assoc($timeline)): ?>
                            <div class="timeline-item mb-3">
                                <div class="timeline-marker bg-primary"></div>
                                <div class="timeline-content">
                                    <p class="mb-0"><strong><?php echo htmlspecialchars($activity['action']); ?></strong></p>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?> - 
                                        <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                    </small>
                                    <?php if ($activity['description']): ?>
                                        <p class="mb-0 mt-1 small"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-muted text-center">No activity yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php include 'modals/change_status_modal.php'; ?>
<?php include 'modals/approve_modal.php'; ?>
<?php include 'modals/submit_modal.php'; ?>
<?php include 'modals/feedback_modal.php'; ?>
<?php include 'modals/interview_modal.php'; ?>
<?php include 'modals/offer_modal.php'; ?>
<?php include 'modals/reject_modal.php'; ?>
<?php include 'modals/notes_modal.php'; ?>

<script>
const applicationId = <?php echo $application_id; ?>;
const canCode = '<?php echo $app['can_code']; ?>';
const jobId = <?php echo $app['job_id']; ?>;

function changeStatus() {
    $('#changeStatusModal').data('application-id', applicationId).modal('show');
}

function approveApplication() {
    $('#approveModal').data('application-id', applicationId).modal('show');
}

function submitToClient() {
    $('#submitModal').data('application-id', applicationId).modal('show');
}

function addClientFeedback() {
    $('#feedbackModal').data('application-id', applicationId).modal('show');
}

function scheduleInterview() {
    $('#interviewModal').data('application-id', applicationId).modal('show');
}

function createOffer() {
    $('#offerModal').data('application-id', applicationId).modal('show');
}

function markAsPlaced() {
    if (confirm('Mark this application as successfully placed?')) {
        $.post('handlers/placement_handler.php', {
            application_id: applicationId
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert('Error: ' + response.message);
            }
        }, 'json');
    }
}

function rejectApplication() {
    $('#rejectModal').data('application-id', applicationId).modal('show');
}

function addNote() {
    $('#notesModal').data('can-code', canCode).data('job-id', jobId).modal('show');
}
</script>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline-item {
    position: relative;
}

.timeline-marker {
    position: absolute;
    left: -30px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #696cff;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 12px;
    height: calc(100% + 12px);
    width: 2px;
    background: #e7e7e7;
}
</style>

<?php include '../../includes/footer.php'; ?>