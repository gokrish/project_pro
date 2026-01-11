<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h4 class="mb-0">My Profile</h4>
            </div>
            <div class="card-body">
                <form action="/profile/update" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

                    <div class="text-center mb-4">
                        <?php if ($user['avatar_path']): ?>
                            <img src="/uploads/avatars/<?= $user['avatar_path'] ?>" class="rounded-circle mb-2" width="100"
                                height="100" style="object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-secondary text-white d-inline-flex align-items-center justify-content-center mb-2"
                                style="width: 100px; height: 100px; font-size: 2.5rem;">
                                <?= strtoupper(substr($user['name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>

                        <div class="mt-2">
                            <label class="btn btn-sm btn-outline-primary">
                                Change Avatar <input type="file" name="avatar" hidden>
                            </label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control"
                            value="<?= htmlspecialchars($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-select">
                            <option value="UTC" <?= $user['timezone'] == 'UTC' ? 'selected' : '' ?>>UTC</option>
                            <option value="America/New_York" <?= $user['timezone'] == 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                            <option value="America/Los_Angeles" <?= $user['timezone'] == 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                            <option value="Europe/London" <?= $user['timezone'] == 'Europe/London' ? 'selected' : '' ?>
                                >London</option>
                            <!-- Add more as needed -->
                        </select>
                    </div>

                    <hr class="my-4">

                    <h5 class="mb-3">Security</h5>
                    <div class="mb-3">
                        <label class="form-label">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" autocomplete="new-password">
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>