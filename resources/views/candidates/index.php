<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Candidates</h2>
    <a href="/candidates/create" class="btn btn-primary">Add Candidate</a>
</div>

<?php
$searchAction = '/candidates';
$filtersHtml = '
    <div class="col-md-3">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="active" ' . (($status ?? '') == 'active' ? 'selected' : '') . '>Active</option>
            <option value="placed" ' . (($status ?? '') == 'placed' ? 'selected' : '') . '>Placed</option>
            <option value="lead" ' . (($status ?? '') == 'lead' ? 'selected' : '') . '>Lead</option>
        </select>
    </div>
';
include ROOT_PATH . '/resources/views/partials/search_bar.php';
?>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Resume</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($candidates as $c): ?>
                    <tr>
                        <td>
                            <a href="/candidates/<?= $c['id'] ?>" class="fw-bold text-decoration-none">
                                <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($c['email']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($c['phone'] ?? '-') ?>
                        </td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= ucfirst($c['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($c['resume_path']): ?>
                                <a href="#" class="text-primary"><i class="bi bi-file-earmark"></i> View</a>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="/candidates/<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($candidates)): ?>
                    <tr>
                        <td colspan="6" class="text-center text-muted">No candidates found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>