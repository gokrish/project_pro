<?php
/**
 * Pipeline View - Kanban-style visual workflow
 */

require_once __DIR__ . '/../_common.php';
// Page configuration
$pageTitle = 'Applications Pipeline';
$breadcrumbs = [
    'Application' => '#'
];
// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

$conn = dbConnect();

// Define pipeline stages
$stages = [
    'screening' => 'Screening',
    'pending_approval' => 'Pending Approval',
    'approved' => 'Approved',
    'submitted' => 'Submitted',
    'interviewing' => 'Interviewing',
    'offered' => 'Offered',
    'placed' => 'Placed'
];

// Get applications grouped by stage
$stageData = [];
foreach ($stages as $status => $label) {
    $query = "
        SELECT ja.application_id, ja.status,
               c.candidate_name, 
               j.title as job_title,
               cl.client_name,
               ja.created_at
        FROM job_applications ja
        JOIN candidates c ON ja.can_code = c.can_code
        JOIN jobs j ON ja.job_id = j.job_id
        LEFT JOIN clients cl ON j.client_id = cl.client_id
        WHERE ja.status = ?
        AND ja.deleted_at IS NULL
        ORDER BY ja.updated_at DESC
        LIMIT 20
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $stageData[$status] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

include '../../includes/header.php';
?>

<style>
.pipeline-column {
    background: #f8f9fa;
    border-radius: 8px;
    min-height: 500px;
    padding: 15px;
}

.pipeline-column-header {
    background: #fff;
    padding: 10px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.pipeline-card {
    background: #fff;
    border-left: 4px solid #696cff;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.pipeline-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.pipeline-stage-screening { border-left-color: #03c3ec; }
.pipeline-stage-pending_approval { border-left-color: #ffab00; }
.pipeline-stage-approved { border-left-color: #71dd37; }
.pipeline-stage-submitted { border-left-color: #696cff; }
.pipeline-stage-interviewing { border-left-color: #ff3e1d; }
.pipeline-stage-offered { border-left-color: #71dd37; }
.pipeline-stage-placed { border-left-color: #28a745; }
</style>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-grid-alt"></i> Applications Pipeline</h2>
            <p class="text-muted">Visual workflow overview</p>
        </div>
        <div>
            <a href="list.php" class="btn btn-secondary">
                <i class="bx bx-list-ul"></i> List View
            </a>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="bx bx-refresh"></i> Refresh
            </button>
        </div>
    </div>

    <!-- Pipeline Columns -->
    <div class="row">
        <?php foreach ($stages as $status => $label): ?>
        <div class="col-md-3 mb-4">
            <div class="pipeline-column">
                <div class="pipeline-column-header">
                    <h6 class="mb-0">
                        <?php echo $label; ?>
                        <span class="badge bg-primary float-end">
                            <?php echo count($stageData[$status]); ?>
                        </span>
                    </h6>
                </div>

                <div class="pipeline-cards">
                    <?php if (empty($stageData[$status])): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bx bx-inbox bx-lg"></i>
                            <p class="mb-0">No applications</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($stageData[$status] as $app): ?>
                        <div class="pipeline-card pipeline-stage-<?php echo $status; ?>" 
                             onclick="window.location.href='view.php?id=<?php echo $app['application_id']; ?>'">
                            <strong class="d-block mb-1">
                                <?php echo htmlspecialchars($app['candidate_name']); ?>
                            </strong>
                            <small class="text-muted d-block mb-1">
                                <i class="bx bx-briefcase"></i> <?php echo htmlspecialchars($app['job_title']); ?>
                            </small>
                            <?php if ($app['client_name']): ?>
                                <small class="text-muted d-block mb-1">
                                    <i class="bx bx-building"></i> <?php echo htmlspecialchars($app['client_name']); ?>
                                </small>
                            <?php endif; ?>
                            <small class="text-muted">
                                <i class="bx bx-time"></i> 
                                <?php 
                                $days = floor((time() - strtotime($app['created_at'])) / 86400);
                                echo $days === 0 ? 'Today' : "$days days ago";
                                ?>
                            </small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <?php if (count($stageData[$status]) >= 20): ?>
                        <div class="text-center mt-2">
                            <a href="list.php?status=<?php echo $status; ?>" class="btn btn-sm btn-link">
                                View all →
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>