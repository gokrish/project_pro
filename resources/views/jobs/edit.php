<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Job:
        <?= htmlspecialchars($job['title']) ?>
    </h2>
    <a href="/jobs" class="btn btn-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/jobs/<?= $job['id'] ?>/update" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="row">
                <div class="col-md-8 mb-3">
                    <label class="form-label">Job Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" required
                        value="<?= htmlspecialchars($job['title']) ?>">
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Client</label>
                    <select name="client_id" class="form-select">
                        <option value="">-- Internal / No Client --</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= $client['id'] ?>" <?= $job['client_id'] == $client['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['company_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Assigned Recruiter</label>
                    <select name="recruiter_id" class="form-select">
                        <option value="">-- Unassigned --</option>
                        <?php foreach ($recruiters as $rec): ?>
                            <option value="<?= $rec['id'] ?>" <?= ($job['recruiter_id'] == $rec['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($rec['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-control"
                        value="<?= htmlspecialchars($job['location'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Salary Range</label>
                    <input type="text" name="salary_range" class="form-control"
                        value="<?= htmlspecialchars($job['salary_range'] ?? '') ?>">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control"
                        rows="5"><?= htmlspecialchars($job['description'] ?? '') ?></textarea>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="draft" <?= $job['status'] == 'draft' ? 'selected' : '' ?>>Draft</option>
                        <option value="open" <?= $job['status'] == 'open' ? 'selected' : '' ?>>Open</option>
                        <option value="filled" <?= $job['status'] == 'filled' ? 'selected' : '' ?>>Filled</option>
                        <option value="closed" <?= $job['status'] == 'closed' ? 'selected' : '' ?>>Closed</option>
                    </select>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update Job</button>
            </div>
        </form>
    </div>
</div>