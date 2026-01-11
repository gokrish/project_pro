<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Jobs</h2>
    <a href="/jobs/create" class="btn btn-primary">Create New Job</a>
</div>

<?php
$searchAction = '/jobs';
ob_start();
?>
<div class="col-md-3">
    <label class="form-label">Client</label>
    <select name="client_id" class="form-select">
        <option value="">All Clients</option>
        <?php foreach ($clients as $c): ?>
            <option value="<?= $c['id'] ?>" <?= (($client_id ?? '') == $c['id'] ? 'selected' : '') ?>>
                <?= htmlspecialchars($c['company_name']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
<div class="col-md-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
        <option value="">All Statuses</option>
        <option value="open" <?= (($status ?? '') == 'open' ? 'selected' : '') ?>>Open</option>
        <option value="closed" <?= (($status ?? '') == 'closed' ? 'selected' : '') ?>>Closed</option>
        <option value="draft" <?= (($status ?? '') == 'draft' ? 'selected' : '') ?>>Draft</option>
    </select>
</div>
<?php
$filtersHtml = ob_get_clean();
include ROOT_PATH . '/resources/views/partials/search_bar.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Client</th>
                    <th>Location</th>
                    <th>Salary</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jobs as $job): ?>
                    <tr>
                        <td>
                            <a href="/jobs/<?= $job['id'] ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars($job['title']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($job['company_name'] ?? 'Internal') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($job['location'] ?? 'Remote') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($job['salary_range'] ?? 'N/A') ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= $job['status'] == 'open' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($job['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= htmlspecialchars($job['created_by_name']) ?>
                        </td>
                        <td>
                            <a href="/jobs/<?= $job['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Edit</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($jobs)): ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted">No jobs found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>