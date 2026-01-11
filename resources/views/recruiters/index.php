<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Recruiters Team</h2>
    <!-- Maybe add 'Invite Recruiter' later -->
</div>

<div class="row">
    <?php foreach ($recruiters as $recruiter): ?>
        <div class="col-md-4 mb-4">
            <div class="card h-100 shadow-sm text-center p-3">
                <div class="card-body">
                    <?php if ($recruiter['avatar_path']): ?>
                        <img src="/uploads/avatars/<?= $recruiter['avatar_path'] ?>" class="rounded-circle mb-3 border"
                            width="80" height="80" style="object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-light text-primary fw-bold d-inline-flex align-items-center justify-content-center mb-3 border"
                            style="width: 80px; height: 80px; font-size: 2rem;">
                            <?= strtoupper(substr($recruiter['name'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>

                    <h5 class="card-title mb-1">
                        <?= htmlspecialchars($recruiter['name']) ?>
                    </h5>
                    <p class="text-muted small mb-3">
                        <?= htmlspecialchars($recruiter['email']) ?>
                    </p>

                    <div class="d-flex justify-content-center gap-3 mb-3">
                        <div class="text-center">
                            <strong class="d-block fs-5">
                                <?= $recruiter['active_jobs'] ?>
                            </strong>
                            <span class="small text-muted">Active Jobs</span>
                        </div>
                        <div class="border-end"></div>
                        <div class="text-center">
                            <strong class="d-block fs-5">
                                <?= $recruiter['total_submissions'] ?>
                            </strong>
                            <span class="small text-muted">Submissions</span>
                        </div>
                    </div>

                    <a href="/jobs?recruiter_id=<?= $recruiter['id'] ?>" class="btn btn-sm btn-outline-primary">View
                        Jobs</a>
                </div>
                <div class="card-footer bg-white border-0 text-muted small">
                    <?php if ($recruiter['last_activity']): ?>
                        Active
                        <?= date('M d', strtotime($recruiter['last_activity'])) ?>
                    <?php else: ?>
                        No recent activity
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($recruiters)): ?>
        <div class="col-12 text-center text-muted p-5">
            No active recruiters found.
        </div>
    <?php endif; ?>
</div>