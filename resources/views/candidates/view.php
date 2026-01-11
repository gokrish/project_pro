<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h2>
            <?= htmlspecialchars($candidate['first_name'] . ' ' . $candidate['last_name']) ?>
        </h2>
        <p class="text-muted mb-0">
            <?= htmlspecialchars($candidate['email']) ?> |
            <?= htmlspecialchars($candidate['phone'] ?? '') ?>
        </p>
    </div>
    <div>
        <a href="/candidates" class="btn btn-outline-secondary">Back to List</a>
        <a href="/candidates/<?= $candidate['id'] ?>/edit" class="btn btn-primary">Edit</a>
    </div>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white fw-bold">Professional Summary</div>
            <div class="card-body">
                <p>
                    <?= nl2br(htmlspecialchars($candidate['summary'] ?? 'No summary provided.')) ?>
                </p>
            </div>
        </div>

        <?php if (!empty($candidate['skills_text'])): ?>
            <div class="card mb-4 shadow-sm">
                <div class="card-header bg-white fw-bold">Parsed Skills</div>
                <div class="card-body">
                    <p>
                        <?= nl2br(htmlspecialchars($candidate['skills_text'])) ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-white fw-bold">Details</div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <li class="mb-2"><strong>Status:</strong> <span class="badge bg-info">
                            <?= ucfirst($candidate['status']) ?>
                        </span></li>
                    <li class="mb-2"><strong>Source:</strong>
                        <?= htmlspecialchars($candidate['source']) ?>
                    </li>
                    <li class="mb-2"><strong>Added:</strong>
                        <?= date('M d, Y', strtotime($candidate['created_at'])) ?>
                    </li>
                    <?php if ($candidate['resume_path']): ?>
                        <li class="mt-3">
                            <a href="/storage/resumes/<?= $candidate['resume_path'] ?>" target="_blank"
                                class="btn btn-sm btn-outline-primary w-100">
                                Download Resume
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <hr>
                <div class="d-grid gap-2">
                    <a href="/submissions/create?candidate_id=<?= $candidate['id'] ?>" class="btn btn-primary">
                        Submit to Client (Enterprise)
                    </a>
                    <button type="button" class="btn btn-success" data-bs-toggle="modal"
                        data-bs-target="#assignJobModal">
                        Quick Assign (Internal)
                    </button>
                </div>
            </div>
        </div>

        <!-- applications list -->
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Active Applications</div>
            <ul class="list-group list-group-flush">
                <?php
                // Verify if applications data is passed, if not, we might need to fetch it in Controller or View
                if (!empty($applications)):
                    foreach ($applications as $app):
                        ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong><?= htmlspecialchars($app['job_title']) ?></strong><br>
                                <small class="text-muted"><?= date('M d', strtotime($app['created_at'])) ?></small>
                            </div>
                            <span
                                class="badge bg-<?= $app['status'] == 'hired' ? 'success' : 'secondary' ?>"><?= ucfirst($app['status']) ?></span>
                        </li>
                    <?php endforeach; else: ?>
                    <li class="list-group-item text-muted text-center">No active applications</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="assignJobModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="/applications" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign to Job</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= \App\Core\Session::generateCsrfToken() ?>">
                    <input type="hidden" name="candidate_id" value="<?= $candidate['id'] ?>">

                    <label class="form-label">Select Job Position</label>
                    <select name="job_id" class="form-select" required>
                        <!-- Populate with open jobs -->
                        <?php if (!empty($openJobs)):
                            foreach ($openJobs as $job): ?>
                                <option value="<?= $job['id'] ?>"><?= htmlspecialchars($job['title']) ?></option>
                            <?php endforeach; endif; ?>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Assign</button>
                </div>
            </form>
        </div>
    </div>
</div>