<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0">Submit Candidate to Client</h4>
            </div>
            <div class="card-body">
                <form action="/submissions" method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                    <?php if ($candidate): ?>
                        <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">
                        <div class="mb-4 p-3 bg-light rounded">
                            <h5>Candidate: <strong>
                                    <?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
                                </strong></h5>
                            <p class="mb-0 text-muted">
                                <?= htmlspecialchars($candidate['email']) ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="mb-4">
                            <label class="form-label">Select Candidate <span class="text-danger">*</span></label>
                            <select name="candidate_id" class="form-select" required>
                                <option value="">-- Choose Candidate --</option>
                                <?php foreach ($candidates_list as $c): ?>
                                    <option value="<?= $c['id'] ?>">
                                        <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?>
                                        (<?= htmlspecialchars($c['email']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">
                                Don't see them? <a href="/candidates/create">Add new candidate</a>.
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Select Job Opportunity <span class="text-danger">*</span></label>
                        <select name="job_id" class="form-select" required>
                            <option value="">-- Select a Job --</option>
                            <?php foreach ($jobs as $job): ?>
                                <option value="<?= $job['id'] ?>" <?= ($selected_job_id == $job['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($job['title']) ?> at
                                    <?= htmlspecialchars($job['company_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Salary Expectation</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="text" name="salary_expectation" class="form-control"
                                    placeholder="e.g. 120,000">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Submission Notes (for Client)</label>
                        <textarea name="notes" class="form-control" rows="4"
                            placeholder="Why is this candidate a good fit?"></textarea>
                    </div>

                    <div class="d-flex justify-content-between">
                        <?php if ($candidate): ?>
                            <a href="/candidates/<?= $candidate['id'] ?>" class="btn btn-secondary">Cancel</a>
                        <?php else: ?>
                            <a href="/jobs" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" class="btn btn-success px-4">Submit Candidate</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>