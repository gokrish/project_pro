<?php
require_once __DIR__ . '/../_common.php';

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';


$conn = dbConnect();

// Filters
$status = $_GET['status'] ?? '';
$job_id = $_GET['job_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$whereConditions = ["ja.deleted_at IS NULL"];
$params = [];
$types = '';

if ($status) {
    $whereConditions[] = "ja.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($job_id) {
    $whereConditions[] = "ja.job_id = ?";
    $params[] = $job_id;
    $types .= 'i';
}

if ($search) {
    $whereConditions[] = "(c.candidate_name LIKE ? OR c.email_id LIKE ? OR j.title LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'sss';
}

$whereClause = implode(' AND ', $whereConditions);

$query = "
    SELECT ja.*, 
           c.candidate_name, 
           c.email_id,
           c.phone,
           j.title as job_title,
           j.job_code,
           cl.client_name,
           u.name as created_by_name
    FROM job_applications ja
    JOIN candidates c ON ja.can_code = c.can_code
    JOIN jobs j ON ja.job_id = j.job_id
    LEFT JOIN clients cl ON j.client_id = cl.client_id
    LEFT JOIN user u ON ja.created_by = u.user_code
    WHERE $whereClause
    ORDER BY ja.created_at DESC
";

$stmt = $conn->prepare($query);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

include '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bx bx-list-ul"></i> All Applications</h2>
            <p class="text-muted">Manage all job applications</p>
        </div>
        <div>
            <a href="bulk_actions.php" class="btn btn-secondary">
                <i class="bx bx-layer"></i> Bulk Actions
            </a>
            <a href="pipeline.php" class="btn btn-primary">
                <i class="bx bx-grid-alt"></i> Pipeline View
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="applied" <?php echo $status === 'applied' ? 'selected' : ''; ?>>Applied</option>
                        <option value="screening" <?php echo $status === 'screening' ? 'selected' : ''; ?>>Screening</option>
                        <option value="pending_approval" <?php echo $status === 'pending_approval' ? 'selected' : ''; ?>>Pending Approval</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="submitted" <?php echo $status === 'submitted' ? 'selected' : ''; ?>>Submitted</option>
                        <option value="interviewing" <?php echo $status === 'interviewing' ? 'selected' : ''; ?>>Interviewing</option>
                        <option value="offered" <?php echo $status === 'offered' ? 'selected' : ''; ?>>Offered</option>
                        <option value="placed" <?php echo $status === 'placed' ? 'selected' : ''; ?>>Placed</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Job</label>
                    <select name="job_id" class="form-control">
                        <option value="">All Jobs</option>
                        <?php
                        $jobsResult = mysqli_query($conn, "SELECT job_id, title, job_code FROM jobs WHERE deleted_at IS NULL ORDER BY created_at DESC");
                        while ($job = mysqli_fetch_assoc($jobsResult)):
                        ?>
                        <option value="<?php echo $job['job_id']; ?>" <?php echo $job_id == $job['job_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($job['title']) . ' (' . $job['job_code'] . ')'; ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Candidate name, email, job title..." value="<?php echo htmlspecialchars($search); ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bx bx-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Applications Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="applicationsTable">
                    <thead>
                        <tr>
                            <th>
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th>Candidate</th>
                            <th>Job</th>
                            <th>Client</th>
                            <th>Status</th>
                            <th>Applied Date</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($applications->num_rows > 0): ?>
                            <?php while ($app = mysqli_fetch_assoc($applications)): ?>
                            <tr>
                                <td>
                                    <input type="checkbox" class="application-checkbox" value="<?php echo $app['application_id']; ?>">
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($app['candidate_name']); ?></strong><br>
                                        <small class="text-muted">
                                            <i class="bx bx-envelope"></i> <?php echo htmlspecialchars($app['email_id']); ?>
                                        </small>
                                        <?php if ($app['phone']): ?>
                                        <br><small class="text-muted">
                                            <i class="bx bx-phone"></i> <?php echo htmlspecialchars($app['phone']); ?>
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($app['job_title']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['job_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($app['client_name'] ?? '-'); ?></td>
                                <td><?php include 'components/status_badge.php'; ?></td>
                                <td><?php echo date('M d, Y', strtotime($app['created_at'])); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($app['updated_at'])); ?></td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view.php?id=<?php echo $app['application_id']; ?>" class="btn btn-sm btn-info">
                                            <i class="bx bx-show"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-secondary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="#" onclick="changeStatus(<?php echo $app['application_id']; ?>)">
                                                    <i class="bx bx-edit"></i> Change Status
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="../candidates/view.php?code=<?php echo $app['can_code']; ?>">
                                                    <i class="bx bx-user"></i> View Candidate
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="../jobs/view.php?id=<?php echo $app['job_id']; ?>">
                                                    <i class="bx bx-briefcase"></i> View Job
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-4">
                                    <i class="bx bx-info-circle bx-lg text-muted"></i>
                                    <p class="text-muted mt-2">No applications found</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Change Status Modal -->
<?php include 'modals/change_status_modal.php'; ?>

<script>
// Select All Checkbox
$('#selectAll').on('change', function() {
    $('.application-checkbox').prop('checked', $(this).prop('checked'));
});

// DataTable
$('#applicationsTable').DataTable({
    order: [[5, 'desc']], // Sort by applied date
    pageLength: 25,
    columnDefs: [
        { orderable: false, targets: [0, 7] } // Disable sorting on checkbox and actions
    ]
});

// Change Status
function changeStatus(applicationId) {
    $('#changeStatusModal').data('application-id', applicationId).modal('show');
}
</script>

<?php include '../../includes/footer.php'; ?>