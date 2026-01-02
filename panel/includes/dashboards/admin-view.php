<?php
/**
 * Admin Dashboard - Unified view for team admin
 * Combines manager, coordinator, and admin functions
 * 
 * @version 2.0
 */

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$db = Database::getInstance();
$conn = $db->getConnection();
$userId = Auth::userCode();

// Unified Team Stats
$teamStats = [
    'total_candidates' => $conn->query("SELECT COUNT(*) as count FROM candidates WHERE deleted_at IS NULL")->fetch_assoc()['count'],
    'follow_ups_today' => $conn->query("SELECT COUNT(*) as count FROM candidates 
        WHERE next_follow_up = CURDATE() AND deleted_at IS NULL")->fetch_assoc()['count'],
    'pending_approvals' => $conn->query("SELECT COUNT(*) as count FROM candidate_submissions 
        WHERE status = 'pending_review' AND deleted_at IS NULL")->fetch_assoc()['count'],
    'urgent_tasks' => $conn->query("SELECT COUNT(*) as count FROM activity_log 
        WHERE action = 'follow_up' AND created_at >= CURDATE() AND completed_at IS NULL")->fetch_assoc()['count'],
    'team_members' => $conn->query("SELECT COUNT(*) as count FROM users 
        WHERE is_active = 1 AND deleted_at IS NULL")->fetch_assoc()['count'],
];

// Follow-up Candidates (Today & Overdue)
$followUps = $conn->query("
    SELECT c.candidate_code, c.first_name, c.last_name, c.email, c.phone,
           c.next_follow_up, c.lead_type, u.name as assigned_to
    FROM candidates c
    LEFT JOIN users u ON c.assigned_to = u.user_code
    WHERE (c.next_follow_up = CURDATE() OR c.next_follow_up < CURDATE())
    AND c.status IN ('active', 'contacted', 'qualified')
    AND c.deleted_at IS NULL
    ORDER BY c.next_follow_up ASC
    LIMIT 10
")->fetch_all(MYSQLI_ASSOC);

// Administrative Tasks
$adminTasks = [
    ['title' => 'Review pending candidate submissions', 'count' => $teamStats['pending_approvals'], 'url' => '/panel/modules/submissions/index.php?status=pending_review'],
    ['title' => 'Candidates needing follow-up today', 'count' => $teamStats['follow_ups_today'], 'url' => '/panel/modules/candidates/list.php?follow_up=today'],
    ['title' => 'System users management', 'count' => 0, 'url' => '/panel/modules/users/index.php?action=list'],
    ['title' => 'Client accounts review', 'count' => 0, 'url' => '/panel/modules/clients/index.php'],
    ['title' => 'Job postings status check', 'count' => 0, 'url' => '/panel/modules/jobs/list.php']
];

// Team Performance (Last 30 Days)
$teamPerformance = $conn->query("
    SELECT 
        u.name,
        u.level,
        COUNT(DISTINCT ca.candidate_code) as candidates_added,
        COUNT(DISTINCT cs.id) as submissions_count,
        AVG(CASE WHEN cs.status = 'approved' THEN 1 ELSE 0 END) * 100 as approval_rate
    FROM users u
    LEFT JOIN candidates ca ON ca.created_by = u.user_code AND ca.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND ca.deleted_at IS NULL
    LEFT JOIN candidate_submissions cs ON cs.submitted_by = u.user_code AND cs.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND cs.deleted_at IS NULL
    WHERE u.is_active = 1 AND u.deleted_at IS NULL
    GROUP BY u.id
    ORDER BY candidates_added DESC, submissions_count DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);
?>

<!-- Admin Overview - Consolidated Metrics -->
<div class="row g-3 mb-4">
    <!-- Total Candidates -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-primary">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-primary">
                            <i class="bx bx-user fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Total Candidates</small>
                        <h4 class="mb-0"><?= number_format($teamStats['total_candidates']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Follow-ups -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-warning">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-warning">
                            <i class="bx bx-calendar fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Follow-ups Today</small>
                        <h4 class="mb-0"><?= number_format($teamStats['follow_ups_today']) ?></h4>
                        <?php if ($teamStats['follow_ups_today'] > 0): ?>
                        <small class="text-warning mt-1 d-block"><i class="bx bx-info-circle me-1"></i><?= $teamStats['follow_ups_today'] ?> require attention</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Approvals -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-danger">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-danger">
                            <i class="bx bx-time-five fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Pending Approvals</small>
                        <h4 class="mb-0"><?= number_format($teamStats['pending_approvals']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Team Members -->
    <div class="col-md-3 col-sm-6">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-group fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Team Members</small>
                        <h4 class="mb-0"><?= number_format($teamStats['team_members']) ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Administrative Tasks -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-task me-2"></i>Administrative Tasks</h5>
                <span class="badge bg-label-primary"><?= count($adminTasks) ?> tasks</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($adminTasks as $task): ?>
                    <div class="d-flex align-items-start">
                        <div class="me-3 mt-1">
                            <i class="bx bx-chevron-right text-muted"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between">
                                <a href="<?= $task['url'] ?>" class="text-decoration-none fw-medium"><?= $task['title'] ?></a>
                                <?php if ($task['count'] > 0): ?>
                                <span class="badge bg-label-danger"><?= $task['count'] ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Today's Follow-ups -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-calendar-check me-2"></i>Today's Follow-ups</h5>
                <span class="badge bg-label-warning"><?= count($followUps) ?> candidates</span>
            </div>
            <div class="card-body">
                <?php if (empty($followUps)): ?>
                    <div class="text-center py-4">
                        <i class="bx bx-check-circle fs-1 mb-2 text-success"></i>
                        <p class="lead mb-1">All caught up!</p>
                        <p class="text-muted mb-0">No follow-ups required today</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless mb-0">
                        <tbody>
                            <?php foreach ($followUps as $candidate): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-sm me-2">
                                            <span class="avatar-initial rounded-circle bg-label-primary"><?= strtoupper(substr($candidate['first_name'], 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <h6 class="mb-0"><?= escape($candidate['first_name'] . ' ' . $candidate['last_name']) ?></h6>
                                            <small class="text-muted"><?= escape($candidate['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-label-<?= $candidate['next_follow_up'] < date('Y-m-d') ? 'danger' : 'warning' ?>">
                                        <?= $candidate['next_follow_up'] < date('Y-m-d') ? 'Overdue' : 'Today' ?>
                                    </span>
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
                <div class="d-flex align-items-center mb-3 border-bottom pb-3">
                    <div class="avatar avatar-md me-3">
                        <span class="avatar-initial rounded-circle bg-label-primary">
                            <?= strtoupper(substr($member['name'], 0, 2)) ?>
                        </span>
                    </div>
                    <div class="flex-grow-1">
                        <div class="d-flex justify-content-between">
                            <h6 class="mb-0"><?= escape($member['name']) ?></h6>
                            <small class="text-muted"><?= escape(ucfirst($member['level'])) ?></small>
                        </div>
                        <div class="d-flex justify-content-between mt-2">
                            <small class="text-muted">
                                <i class="bx bx-user-plus me-1"></i> <?= $member['candidates_added'] ?> candidates
                            </small>
                            <small class="<?= $member['approval_rate'] > 70 ? 'text-success' : 'text-warning' ?>">
                                <i class="bx bx-check-circle me-1"></i> <?= round($member['approval_rate']) ?>% approval
                            </small>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>