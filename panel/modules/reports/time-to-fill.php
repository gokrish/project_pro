
<?php
require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\{Permission, Database, Auth};

// Check permission
Permission::require('reports', 'view_dashboard');

$pageTitle = 'Time-to-Fill Report';

// Calculate average time to fill
$query = "
    SELECT 
        j.job_title,
        j.created_at as job_posted,
        p.start_date as placement_date,
        DATEDIFF(p.start_date, j.created_at) as days_to_fill,
        c.company_name as client,
        CONCAT(cand.first_name, ' ', cand.last_name) as placed_candidate
    FROM jobs j
    JOIN placement_records p ON j.job_id = p.job_id
    JOIN clients c ON j.client_id = c.client_id
    JOIN candidates cand ON p.candidate_id = cand.candidate_id
    WHERE p.status = 'active'
    ORDER BY p.start_date DESC
";

$stmt = $db->query($query);
$placements = $stmt->fetchAll();

// Calculate averages
$total_days = 0;
foreach ($placements as $p) {
    $total_days += $p['days_to_fill'];
}
$avg_days = count($placements) > 0 ? round($total_days / count($placements), 1) : 0;

include '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3>Time-to-Fill Report</h3>
                    <p class="text-muted">Average time from job posting to placement</p>
                </div>
                <div class="card-body">
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h2><?= $avg_days ?></h2>
                                <p>Average Days to Fill</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h2><?= count($placements) ?></h2>
                                <p>Total Placements</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h2><?= min(array_column($placements, 'days_to_fill') ?? [0]) ?></h2>
                                <p>Fastest Fill (days)</p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card">
                                <h2><?= max(array_column($placements, 'days_to_fill') ?? [0]) ?></h2>
                                <p>Slowest Fill (days)</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover" id="timeToFillTable">
                            <thead>
                                <tr>
                                    <th>Job Title</th>
                                    <th>Client</th>
                                    <th>Candidate</th>
                                    <th>Posted Date</th>
                                    <th>Placement Date</th>
                                    <th>Days to Fill</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($placements as $placement): ?>
                                <tr>
                                    <td><?= htmlspecialchars($placement['job_title']) ?></td>
                                    <td><?= htmlspecialchars($placement['client']) ?></td>
                                    <td><?= htmlspecialchars($placement['placed_candidate']) ?></td>
                                    <td><?= date('M d, Y', strtotime($placement['job_posted'])) ?></td>
                                    <td><?= date('M d, Y', strtotime($placement['placement_date'])) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $placement['days_to_fill'] < 30 ? 'success' : ($placement['days_to_fill'] < 60 ? 'warning' : 'danger') ?>">
                                            <?= $placement['days_to_fill'] ?> days
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="mt-3">
                        <button class="btn btn-success" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Export to Excel
                        </button>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Initialize DataTable
$('#timeToFillTable').DataTable({
    order: [[5, 'asc']],
    pageLength: 25
});

function exportToExcel() {
    window.location.href = 'handlers/export-time-to-fill.php';
}
</script>

<?php include '../includes/footer.php'; ?>