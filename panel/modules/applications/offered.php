<?php
/**
 * Offered - Applications with active offers
 */

require_once __DIR__ . '/../_common.php';
// Page configuration

$pageTitle = 'Applications Offered';
$breadcrumbs = [
    'Application' => '#'
];
// Include header
require_once ROOT_PATH . '/panel/includes/header.php';
require_once ROOT_PATH . '/panel/components/ui_components.php';

// Display breadcrumb
echo renderBreadcrumb($breadcrumbs);
$conn = dbConnect();

// Get offered applications
$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           o.offered_salary,
           o.offered_currency,
           o.offered_date,
           o.start_date,
           o.candidate_response,
           o.response_date,
           o.negotiation_notes
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN offers o ON ja.application_id = o.application_id
    WHERE ja.status IN ('offered', 'offer_accepted')
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
            <h2><i class="bx bx-gift"></i> Offer Stage</h2>
            <p class="text-muted">Applications with active offers</p>
        </div>
    </div>

    <?php if (mysqli_num_rows($applications) === 0): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bx bx-gift bx-lg text-muted mb-3" style="font-size: 3rem;"></i>
                <h4>No Active Offers</h4>
                <p class="text-muted">No offers currently pending</p>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card h-100 border-success">
                    <div class="card-header bg-light-success">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-0">
                                    <i class="bx bx-user-check"></i> <?php echo htmlspecialchars($app['candidate_name']); ?>
                                </h6>
                                <small class="text-muted"><?php echo htmlspecialchars($app['job_title']); ?></small>
                            </div>
                            <?php if ($app['candidate_response']): ?>
                                <span class="badge bg-<?php echo $app['candidate_response'] === 'accepted' ? 'success' : ($app['candidate_response'] === 'rejected' ? 'danger' : 'warning'); ?>">
                                    <?php echo ucfirst($app['candidate_response']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning">Pending Response</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Offer Details -->
                        <div class="mb-3">
                            <h5 class="text-success mb-2">
                                <?php echo $app['offered_currency']; ?> <?php echo number_format($app['offered_salary'], 2); ?>
                            </h5>
                            <small class="text-muted">Annual Salary</small>
                        </div>

                        <!-- Offer Info -->
                        <table class="table table-sm table-borderless">
                            <tr>
                                <td class="text-muted">Client:</td>
                                <td><strong><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></strong></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Offered Date:</td>
                                <td><?php echo date('M d, Y', strtotime($app['offered_date'])); ?></td>
                            </tr>
                            <?php if ($app['start_date']): ?>
                            <tr>
                                <td class="text-muted">Start Date:</td>
                                <td><?php echo date('M d, Y', strtotime($app['start_date'])); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($app['response_date']): ?>
                            <tr>
                                <td class="text-muted">Response Date:</td>
                                <td><?php echo date('M d, Y', strtotime($app['response_date'])); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>

                        <!-- Days Waiting -->
                        <?php if (!$app['candidate_response']): ?>
                            <?php 
                            $daysWaiting = floor((time() - strtotime($app['offered_date'])) / 86400);
                            ?>
                            <div class="alert alert-<?php echo $daysWaiting > 7 ? 'warning' : 'info'; ?> py-2 mb-3">
                                <small>
                                    <i class="bx bx-time"></i> 
                                    Waiting for response: <?php echo $daysWaiting; ?> day<?php echo $daysWaiting !== 1 ? 's' : ''; ?>
                                    <?php if ($daysWaiting > 7): ?>
                                        <br><strong>Follow up recommended!</strong>
                                    <?php endif; ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Negotiation Notes -->
                        <?php if ($app['negotiation_notes']): ?>
                            <div class="alert alert-light py-2 mb-3">
                                <small>
                                    <strong>Notes:</strong><br>
                                    <?php echo nl2br(htmlspecialchars($app['negotiation_notes'])); ?>
                                </small>
                            </div>
                        <?php endif; ?>

                        <!-- Contact -->
                        <div>
                            <small class="text-muted">Contact:</small><br>
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
                                <i class="bx bx-show"></i> View Full Details
                            </a>
                            <?php if ($app['candidate_response'] === 'accepted'): ?>
                                <button class="btn btn-success btn-sm" onclick="markPlaced(<?php echo $app['application_id']; ?>)">
                                    <i class="bx bx-check-double"></i> Mark as Placed
                                </button>
                            <?php elseif (!$app['candidate_response']): ?>
                                <div class="btn-group">
                                    <button class="btn btn-success btn-sm" onclick="updateResponse(<?php echo $app['application_id']; ?>, 'accepted')">
                                        <i class="bx bx-check"></i> Accepted
                                    </button>
                                    <button class="btn btn-warning btn-sm" onclick="updateResponse(<?php echo $app['application_id']; ?>, 'negotiating')">
                                        <i class="bx bx-message"></i> Negotiating
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="updateResponse(<?php echo $app['application_id']; ?>, 'rejected')">
                                        <i class="bx bx-x"></i> Declined
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function updateResponse(applicationId, response) {
    const confirmMsg = response === 'accepted' ? 'Candidate accepted the offer?' :
                      response === 'rejected' ? 'Candidate declined the offer?' :
                      'Update to negotiating?';
    
    if (confirm(confirmMsg)) {
        $.post('handlers/offer_response_handler.php', {
            application_id: applicationId,
            response: response
        }, function(result) {
            if (result.success) {
                location.reload();
            } else {
                alert('Error: ' + result.message);
            }
        }, 'json');
    }
}

function markPlaced(applicationId) {
    if (confirm('Mark this candidate as successfully placed?')) {
        $.post('handlers/placement_handler.php', {
            application_id: applicationId
        }, function(result) {
            if (result.success) {
                window.location.href = 'placed.php';
            } else {
                alert('Error: ' + result.message);
            }
        }, 'json');
    }
}
</script>

<?php include '../../includes/footer.php'; ?>