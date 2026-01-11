<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Clients</h2>
    <a href="/clients/create" class="btn btn-primary">Add Client</a>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <table class="table table-hover">
            <thead>
                <tr>
                    <th>Company</th>
                    <th>Contact Person</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Start Date</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td class="fw-bold">
                            <?= htmlspecialchars($client['company_name']) ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($client['contact_person'] ?? '-') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($client['email'] ?? '-') ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($client['phone'] ?? '-') ?>
                        </td>
                        <td>
                            <?= date('M d, Y', strtotime($client['created_at'])) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($clients)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted">No clients found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>