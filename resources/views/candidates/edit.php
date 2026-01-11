<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Edit Candidate:
        <?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
    </h2>
    <a href="/candidates/<?= $candidate['id'] ?>" class="btn btn-secondary">Back</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <form action="/candidates/<?= $candidate['id'] ?>/update" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                        value="<?= htmlspecialchars($candidate['first_name']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="last_name" class="form-control"
                        value="<?= htmlspecialchars($candidate['last_name'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control" required
                        value="<?= htmlspecialchars($candidate['email']) ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control"
                        value="<?= htmlspecialchars($candidate['phone'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">LinkedIn URL</label>
                    <input type="url" name="linkedin_url" class="form-control"
                        value="<?= htmlspecialchars($candidate['linkedin_url'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Resume (Leave empty to keep current)</label>
                    <input type="file" name="resume" class="form-control">
                    <?php if ($candidate['resume_path']): ?>
                        <small class="text-muted">Current: <a href="/storage/resumes/<?= $candidate['resume_path'] ?>"
                                target="_blank">View Resume</a></small>
                    <?php endif; ?>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <?php
                        $statuses = ['new', 'screening', 'interview', 'offer', 'hired', 'rejected'];
                        foreach ($statuses as $status): ?>
                            <option value="<?= $status ?>" <?= $candidate['status'] === $status ? 'selected' : '' ?>>
                                <?= ucfirst($status) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label">Summary</label>
                    <textarea name="summary" class="form-control"
                        rows="4"><?= htmlspecialchars($candidate['summary'] ?? '') ?></textarea>
                </div>
            </div>

            <div class="mt-3">
                <button type="submit" class="btn btn-primary">Update Candidate</button>
            </div>
        </form>
    </div>
</div>