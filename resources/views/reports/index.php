<h2 class="mb-4">Reports & Analytics</h2>

<div class="row mb-4">
    <!-- Funnel / Pipeline -->
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Submission Pipeine</div>
            <div class="card-body">
                <div class="row text-center">
                    <?php
                    $stages = ['submitted', 'client_review', 'interview', 'offer', 'placed'];
                    $colors = ['primary', 'info', 'warning', 'success', 'success'];
                    foreach ($stages as $i => $stage):
                        $count = $funnelData[$stage] ?? 0;
                        ?>
                        <div class="col">
                            <h2 class="fw-bold text-<?= $colors[$i] ?>">
                                <?= $count ?>
                            </h2>
                            <span class="text-uppercase small text-muted">
                                <?= ucfirst(str_replace('_', ' ', $stage)) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recruiter Performance -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Recruiter Performance</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Recruiter</th>
                            <th class="text-end">Submissions</th>
                            <th class="text-end">Interviews</th>
                            <th class="text-end">Placed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recruiterStats as $stat): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($stat['name']) ?>
                                </td>
                                <td class="text-end">
                                    <?= $stat['total_submissions'] ?>
                                </td>
                                <td class="text-end">
                                    <?= $stat['interviews'] ?>
                                </td>
                                <td class="text-end">
                                    <?= $stat['placements'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Client Activity -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white fw-bold">Top Clients Data</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th class="text-end">Open Jobs</th>
                            <th class="text-end">Submissions</th>
                            <th class="text-end">Last Activity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clientStats as $stat): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($stat['company_name']) ?>
                                </td>
                                <td class="text-end">
                                    <?= $stat['open_jobs'] ?>
                                </td>
                                <td class="text-end">
                                    <?= $stat['total_submissions'] ?>
                                </td>
                                <td class="text-end small text-muted">
                                    <?= $stat['last_activity'] ? date('M d', strtotime($stat['last_activity'])) : '-' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Hot Jobs -->
    <div class="col-md-12">
        <div class="card shadow-sm">
            <div class="card-header bg-white fw-bold">Hot Jobs (Most Submitted)</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Job Title</th>
                            <th>Company</th>
                            <th class="text-end">Active Submissions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($jobStats as $stat): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($stat['title']) ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($stat['company_name']) ?>
                                </td>
                                <td class="text-end fw-bold">
                                    <?= $stat['submission_count'] ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>