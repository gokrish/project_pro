<?php
/**
 * Recruiter Dashboard - Focused on daily recruitment tasks
 * 
 * @version 2.0
 */

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;

$db = Database::getInstance();
$conn = $db->getConnection();
$userCode = Auth::userCode();

// Personal Stats
$myStats = [
    'my_candidates' => $conn->query("SELECT COUNT(*) as count FROM candidates 
        WHERE (created_by = '$userCode' OR assigned_to = '$userCode') 
        AND deleted_at IS NULL")->fetch_assoc()['count'],
    'today_followups' => $conn->query("SELECT COUNT(*) as count FROM candidates 
        WHERE assigned_to = '$userCode' 
        AND next_follow_up = CURDATE() 
        AND deleted_at IS NULL")->fetch_assoc()['count'],
    'pending_submissions' => $conn->query("SELECT COUNT(*) as count FROM candidate_submissions 
        WHERE submitted_by = '$userCode' 
        AND status = 'pending_review' 
        AND deleted_at IS NULL")->fetch_assoc()['count'],
    'active_jobs' => $conn->query("SELECT COUNT(*) as count FROM jobs 
        WHERE status = 'open' 
        AND deleted_at IS NULL")->fetch_assoc()['count'],
];

// Recent Candidates
$recentCandidates = $conn->query("
    SELECT candidate_code, first_name, last_name, email, status, created_at, lead_type
    FROM candidates 
    WHERE assigned_to = '$userCode' 
    AND deleted_at IS NULL
    ORDER BY created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Recent Submissions
$mySubmissions = $conn->query("
    SELECT cs.id, cs.submission_code, c.first_name, c.last_name, j.job_title, cl.company_name,
           cs.status, cs.created_at
    FROM candidate_submissions cs
    JOIN candidates c ON cs.candidate_code = c.candidate_code
    JOIN jobs j ON cs.job_code = j.job_code
    JOIN clients cl ON cs.client_code = cl.client_code
    WHERE cs.submitted_by = '$userCode'
    AND cs.deleted_at IS NULL
    ORDER BY cs.created_at DESC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Today's Follow-ups
$todayFollowUps = $conn->query("
    SELECT candidate_code, first_name, last_name, email, phone, next_follow_up, notes
    FROM candidates 
    WHERE assigned_to = '$userCode' 
    AND next_follow_up = CURDATE()
    AND deleted_at IS NULL
    ORDER BY next_follow_up ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Quick Stats for Today
$todayPlacements = $conn->query("SELECT COUNT(*) as count FROM placements 
    WHERE created_by = '$userCode' 
    AND placed_date = CURDATE() 
    AND deleted_at IS NULL")->fetch_assoc()['count'];
?>

<!-- Recruiter Overview - Action Focused -->
<div class="row g-3 mb-4">
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
                        <small class="text-muted d-block">My Candidates</small>
                        <h4 class="mb-0"><?= $myStats['my_candidates'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
                        <small class="text-muted d-block">Today's Follow-ups</small>
                        <h4 class="mb-0"><?= $myStats['today_followups'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-info">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-info">
                            <i class="bx bx-send fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Pending Submissions</small>
                        <h4 class="mb-0"><?= $myStats['pending_submissions'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-success">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="avatar flex-shrink-0 me-3">
                        <span class="avatar-initial rounded bg-label-success">
                            <i class="bx bx-briefcase fs-4"></i>
                        </span>
                    </div>
                    <div>
                        <small class="text-muted d-block">Active Jobs</small>
                        <h4 class="mb-0"><?= $myStats['active_jobs'] ?></h4>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Today's Follow-ups -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-calendar-check me-2"></i>Today's Follow-ups</h5>
                <span class="badge bg-label-warning"><?= count($todayFollowUps) ?> items</span>
            </div>
            <div class="card-body">
                <?php if (empty($todayFollowUps)): ?>
                    <div class="text-center py-4">
                        <i class="bx bx-check-circle fs-1 mb-2 text-success"></i>
                        <p class="lead mb-1">All caught up!</p>
                        <p class="text-muted mb-0">No follow-ups required today</p>
                    </div>
                <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($todayFollowUps as $followUp): ?>
                    <div class="border rounded p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <h6 class="mb-1"><?= escape($followUp['first_name'] . ' ' . $followUp['last_name']) ?></h6>
                                <p class="text-muted mb-1 small"><?= escape($followUp['email']) ?></p>
                                <p class="mb-0 small"><?= nl2br(escape(substr($followUp['notes'], 0, 60))) ?>...</p>
                            </div>
                            <div class="dropdown ms-2">
                                <button type="button" class="btn p-0 dropdown-toggle hide-arrow" data-bs-toggle="dropdown">
                                    <i class="bx bx-dots-vertical-rounded"></i>
                                </button>
                                <div class="dropdown-menu">
                                    <a class="dropdown-item" href="/panel/modules/candidates/view.php?id=<?= $followUp['candidate_code'] ?>">
                                        <i class="bx bx-show me-1"></i> View Profile
                                    </a>
                                    <a class="dropdown-item" href="/panel/modules/candidates/edit.php?id=<?= $followUp['candidate_code'] ?>">
                                        <i class="bx bx-edit me-1"></i> Update Notes
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Candidates -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-user-plus me-2"></i>My Recent Candidates</h5>
                <a href="/panel/modules/candidates/list.php" class="btn btn-sm btn-primary">
                    <i class="bx bx-list-ul me-1"></i> View All
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($recentCandidates)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No recent candidates</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <tbody>
                            <?php foreach ($recentCandidates as $candidate): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar avatar-xs me-2">
                                            <span class="avatar-initial rounded-circle bg-label-primary"><?= strtoupper(substr($candidate['first_name'], 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <strong><?= escape($candidate['first_name'] . ' ' . $candidate['last_name']) ?></strong>
                                            <br><small class="text-muted"><?= escape($candidate['email']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-label-<?= getBadgeColorForStatus($candidate['status']) ?>">
                                        <?= escape($candidate['status']) ?>
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
<div class="card">
    <div class="card-header">Pending Approvals (<?= $pendingCount ?>)</div>
    <div class="card-body">
        <?php foreach ($pendingSubmissions as $sub): ?>
            <div class="d-flex justify-content-between">
                <span><?= $sub['candidate_name'] ?> → <?= $sub['job_title'] ?></span>
                <small><?= timeAgo($sub['created_at']) ?></small>
            </div>
        <?php endforeach; ?>
    </div>
</div>
    <!-- Quick Actions & Submissions -->
    <div class="col-lg-4 mb-4">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="bx bx-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="/panel/modules/candidates/create.php" class="btn btn-primary">
                        <i class="bx bx-plus me-2"></i>Add Candidate
                    </a>
                    <a href="/panel/modules/submissions/create.php" class="btn btn-info">
                        <i class="bx bx-send me-2"></i>New Submission
                    </a>
                    <a href="/panel/modules/candidates/pipeline.php" class="btn btn-outline-primary">
                        <i class="bx bx-grid-alt me-2"></i>View Pipeline
                    </a>
                    <a href="/panel/modules/jobs/list.php" class="btn btn-outline-success">
                        <i class="bx bx-briefcase me-2"></i>Active Jobs
                    </a>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="bx bx-send me-2"></i>My Submissions</h5>
                <span class="badge bg-label-info"><?= count($mySubmissions) ?> items</span>
            </div>
            <div class="card-body">
                <?php if (empty($mySubmissions)): ?>
                    <div class="text-center py-4">
                        <p class="text-muted">No recent submissions</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-borderless mb-0">
                        <tbody>
                            <?php foreach ($mySubmissions as $sub): ?>
                            <tr>
                                <td>
                                    <strong><?= escape($sub['first_name'] . ' ' . $sub['last_name']) ?></strong>
                                    <br><small class="text-muted"><?= escape($sub['job_title']) ?></small>
                                </td>
                                <td class="text-end">
                                    <span class="badge bg-label-<?= getStatusBadge($sub['status']) ?>">
                                        <?= escape($sub['status']) ?>
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
</div>