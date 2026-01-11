<div class="row">
    <div class="col-md-8">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-1">Submission #
                    <?= $submission['id'] ?>
                </h2>
                <span
                    class="badge bg-<?= $submission['status'] == 'placed' ? 'success' : ($submission['status'] == 'rejected' ? 'danger' : 'primary') ?> fs-6">
                    <?= ucfirst(str_replace('_', ' ', $submission['status'])) ?>
                </span>
            </div>
            <div>
                <a href="/candidates/<?= $submission['candidate_id'] ?>" class="btn btn-outline-secondary">View
                    Candidate</a>
                <a href="/jobs/<?= $submission['job_id'] ?>" class="btn btn-outline-secondary">View Job</a>
            </div>
        </div>

        <!-- Details Card -->
        <div class="card mb-4 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-uppercase">Candidate</label>
                        <h5 class="fw-bold">
                            <?= htmlspecialchars($submission['first_name'] . ' ' . $submission['last_name']) ?>
                        </h5>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-uppercase">Client</label>
                        <h5 class="fw-bold">
                            <?= htmlspecialchars($submission['company_name']) ?>
                        </h5>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-uppercase">Job Position</label>
                        <h5>
                            <?= htmlspecialchars($submission['job_title']) ?>
                        </h5>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-uppercase">Recruiter</label>
                        <h5>
                            <?= htmlspecialchars($submission['recruiter_name']) ?>
                        </h5>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="text-muted small text-uppercase">Salary Expectation</label>
                        <p>
                            <?= htmlspecialchars($submission['salary_expectation'] ?? 'N/A') ?>
                        </p>
                    </div>
                </div>

                <div class="mt-3 p-3 bg-light rounded">
                    <label class="text-muted small text-uppercase">Notes</label>
                    <p class="mb-0 fst-italic">"
                        <?= nl2br(htmlspecialchars($submission['notes'] ?? 'No notes provided.')) ?>"
                    </p>
                </div>
            </div>
        </div>

        <!-- Timeline / History -->
        <h4 class="mb-3">Activity Timeline</h4>
        <div class="list-group">
            <?php foreach ($history as $log): ?>
                <div class="list-group-item">
                    <div class="d-flex w-100 justify-content-between">
                        <h6 class="mb-1">
                            Status changed to
                            <span class="fw-bold text-primary">
                                <?= ucfirst(str_replace('_', ' ', $log['to_status'])) ?>
                            </span>
                        </h6>
                        <small class="text-muted">
                            <?= date('M d, g:i a', strtotime($log['created_at'])) ?>
                        </small>
                    </div>
                    <?php if ($log['from_status']): ?>
                        <p class="mb-1 small text-muted">From:
                            <?= ucfirst($log['from_status']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($log['comment']): ?>
                        <p class="mb-1 mt-2 p-2 bg-light rounded small">
                            <?= htmlspecialchars($log['comment']) ?>
                        </p>
                    <?php endif; ?>
                    <small class="text-secondary">Updated by
                        <?= htmlspecialchars($log['user_name']) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>

    </div>

    <!-- Sidebar Actions -->
    <div class="col-md-4">
        <div class="card shadow sticky-top" style="top: 20px;">
            <div class="card-header bg-white fw-bold">Update Status</div>
            <div class="card-body">
                <form action="/submissions/status" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <input type="hidden" name="submission_id" value="<?= $submission['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">New Status</label>
                        <select name="status" class="form-select">
                            <?php
                            $statuses = ['draft', 'submitted', 'client_review', 'interview', 'rejected', 'offer', 'placed'];
                            foreach ($statuses as $s):
                                ?>
                                <option value="<?= $s ?>" <?= $submission['status'] == $s ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $s)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Comment (Optional)</label>
                        <textarea name="comment" class="form-control" rows="3"
                            placeholder="Add context for this update..."></textarea>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>