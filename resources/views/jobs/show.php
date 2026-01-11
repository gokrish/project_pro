<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>
            <?= htmlspecialchars($job['title']) ?>
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($job['company_name'] ?? 'Internal') ?> |
            <i class="bi bi-geo-alt"></i>
            <?= htmlspecialchars($job['location'] ?? 'Remote') ?>
        </p>
    </div>
    <div>
        <a href="/jobs" class="btn btn-outline-secondary">Back</a>
        <a href="/submissions/create?job_id=<?= $job['id'] ?>" class="btn btn-success">Submit Candidate</a>
        <a href="/jobs/<?= $job['id'] ?>/edit" class="btn btn-primary">Edit Job</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <!-- Job Details -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Description</h5>
                <p>
                    <?= nl2br(htmlspecialchars($job['description'] ?? '')) ?>
                </p>

                <div class="mt-4">
                    <strong>Salary:</strong>
                    <?= htmlspecialchars($job['salary_range']) ?> <br>
                    <strong>Status:</strong> <span
                        class="badge bg-<?= $job['status'] == 'open' ? 'success' : 'secondary' ?>">
                        <?= ucfirst($job['status']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Applications (Candidates) -->
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Candidates (
                    <?= count($applications) ?>)
                </h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Candidate</th>
                            <th>Applied</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                            <tr>
                                <td>
                                    <a href="/candidates/<?= $app['candidate_id'] ?>" class="fw-bold text-decoration-none">
                                        <?= htmlspecialchars($app['first_name'] . ' ' . $app['last_name']) ?>
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($app['email']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= date('M d', strtotime($app['created_at'])) ?>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        <?= ucfirst($app['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#updateStatusModal<?= $app['id'] ?>">
                                        Update
                                    </button>

                                    <!-- Status Modal -->
                                    <div class="modal fade" id="updateStatusModal<?= $app['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <form action="/applications/status" method="POST">
                                                    <div class="modal-header">
                                                        <h5 class="modal-title">Update Status</h5>
                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal"></button>
                                                    </div>
                                                    <div class="modal-body">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                        <input type="hidden" name="application_id"
                                                            value="<?= $app['id'] ?>">
                                                        <input type="hidden" name="job_id" value="<?= $job['id'] ?>">

                                                        <label class="form-label">New Status</label>
                                                        <select name="status" class="form-select">
                                                            <?php foreach (['screening', 'interview', 'offer', 'hired', 'rejected'] as $s): ?>
                                                                <option value="<?= $s ?>" <?= $app['status'] == $s ? 'selected' : '' ?>>
                                                                    <?= ucfirst($s) ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="submit" class="btn btn-primary">Save Changes</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($applications)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">No applications yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Sidebar Info -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h6>Job Info</h6>
                <ul class="list-unstyled">
                    <li><strong>Posted:</strong>
                        <?= date('M d, Y', strtotime($job['created_at'])) ?>
                    </li>
                    <li><strong>By:</strong>
                        <?= htmlspecialchars($job['created_by_name']) ?>
                    </li>
                    <?php if(!empty($job['recruiter_name'])): ?>
                    <li><strong>Recruiter:</strong>
                        <?= htmlspecialchars($job['recruiter_name']) ?>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>