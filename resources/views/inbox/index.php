<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>CV Inbox</h2>
    <!-- Upload Form -->
    <form action="/inbox/upload" method="POST" enctype="multipart/form-data" class="d-flex gap-2">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="file" name="resumes[]" multiple class="form-control" required>
        <button type="submit" class="btn btn-primary">Upload & Process</button>
    </form>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Parsed Data (Preview)</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $data = json_decode($item['parsed_data'], true);
                        $preview = $data ? ($data['first_name'] . ' ' . $data['last_name'] . ' (' . $data['email'] . ')') : 'Processing/Failed';
                        ?>
                        <tr>
                            <td>
                                <a href="/storage/resumes/<?= $item['file_path'] ?>" target="_blank">
                                    <?= htmlspecialchars($item['file_name']) ?>
                                </a>
                            </td>
                            <td>
                                <?= htmlspecialchars($preview) ?>
                            </td>
                            <td>
                                <span
                                    class="badge bg-<?= $item['status'] == 'converted' ? 'success' : ($item['status'] == 'parsed' ? 'info' : 'secondary') ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                            </td>
                            <td>
                                <?= date('M d H:i', strtotime($item['created_at'])) ?>
                            </td>
                            <td>
                                <?php if ($item['status'] == 'parsed'): ?>
                                    <a href="/inbox/convert/<?= $item['id'] ?>" class="btn btn-sm btn-success">Convert to
                                        Candidate</a>
                                <?php endif; ?>
                                <?php if ($item['status'] == 'converted'): ?>
                                    <a href="/candidates" class="btn btn-sm btn-outline-secondary">View Candidate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>