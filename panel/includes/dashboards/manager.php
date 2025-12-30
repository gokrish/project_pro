<?php
/**
 * Manager Dashboard
 * File: panel/dashboards/manager.php
 */

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
$db = Database::getInstance();
$conn = $db->getConnection();
$userId = Auth::userCode();

// Team Performance Metrics
$teamStats = [
    'team_members' => $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1 AND level IN ('recruiter', 'senior_recruiter', 'coordinator')")->fetch_assoc()['count'],
    'pending_approvals' => $conn->query("SELECT COUNT(*) as count FROM candidate_submissions WHERE status = 'pending_review'")->fetch_assoc()['count'],
    'active_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs WHERE status = 'open'")->fetch_assoc()['count'],
    'total_submissions_week' => $conn->query("SELECT COUNT(*) as count FROM candidate_submissions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch_assoc()['count'],
    'placements_month' => $conn->query("SELECT COUNT(*) as count FROM placements WHERE placed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetch_assoc()['count'],
];

// Team Member Performance
$teamPerformance = $conn->query("
    SELECT 
        u.name,
        u.level,
        COUNT(DISTINCT cs.id) as submissions_count,
        COUNT(DISTINCT a.id) as applications_count,
        COUNT(DISTINCT p.id) as placements_count
    FROM users u
    LEFT JOIN candidate_submissions cs ON cs.submitted_by = u.user_code AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN applications a ON a.created_by = u.user_code AND a.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN placements p ON p.created_by = u.user_code AND p.placed_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE u.is_active = 1 AND u.level IN ('recruiter', 'senior_recruiter', 'coordinator')
    GROUP BY u.id
    ORDER BY placements_count DESC, submissions_count DESC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Pending Approvals
$pendingApprovals = $conn->query("
    SELECT 
        cs.id,
        cs.submission_code,
        c.first_name,
        c.last_name,
        j.job_title,
        cl.company_name,
        cs.created_at,
        u.name as submitted_by_name
    FROM candidate_submissions cs
    JOIN candidates c ON cs.candidate_code = c.can_code
    JOIN jobs j ON cs.job_code = j.job_code
    JOIN clients cl ON cs.client_code = cl.client_code
    JOIN users u ON cs.submitted_by = u.user_code
    WHERE cs.status = 'pending_review'
    ORDER BY cs.created_at ASC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Manager Overview -->
<div class="row mb-4">
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="bx bx-group fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Team Members</small>
                        <h4 class="mb-0"><?= $teamStats['team_members'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="bx bx-time-five fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Pending Approvals</small>
                        <h4 class="mb-0"><?= $teamStats['pending_approvals'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-send fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Submissions (7d)</small>
                        <h4 class="mb-0"><?= $teamStats['total_submissions_week'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-trophy fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Placements (30d)</small>
                        <h4 class="mb-0"><?= $teamStats['placements_month'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Pending Approvals -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-time-five me-2"></i>Pending Approvals</h5>
                <a href="/panel/modules/submissions/index.php?status=pending_review" class="btn btn-sm btn-primary">
                    Review All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($pendingApprovals)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bx bx-check-circle fs-1 mb-2"></i>
                        <p>No pending approvals</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Candidate</th>
                                <th>Job</th>
                                <th>Submitted By</th>
                                <th>Time</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pendingApprovals as $submission): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($submission['first_name'] . ' ' . $submission['last_name']) ?></strong>
                                </td>
                                <td>
                                    <?= escape($submission['job_title']) ?>
                                    <br><small class="text-muted"><?= escape($submission['company_name']) ?></small>
                                </td>
                                <td><?= escape($submission['submitted_by_name']) ?></td>
                                <td class="text-muted small"><?= timeAgo($submission['created_at']) ?></td>
                                <td>
                                    <a href="/panel/modules/submissions/view.php?id=<?= $submission['id'] ?>" 
                                       class="btn btn-sm btn-outline-primary">Review</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Team Performance -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-bar-chart me-2"></i>Team Performance (30d)</h5>
            </div>
            <div class="card-body">
                <?php foreach ($teamPerformance as $member): ?>
                <div class="d-flex align-items-center mb-3">
                    <div class="avatar avatar-sm me-3">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <?= strtoupper(substr($member['name'], 0, 1)) ?>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <h6 class="mb-0"><?= escape($member['name']) ?></h6>
                        <small class="text-muted">
                            <?= $member['submissions_count'] ?> submissions, 
                            <?= $member['placements_count'] ?> placements
                        </small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>