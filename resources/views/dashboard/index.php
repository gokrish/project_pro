<div class="row mb-4">
    <div class="col-12">
        <h2>Welcome,
            <?= htmlspecialchars($user['name']) ?>
        </h2>
        <p class="text-muted">Here's what's happening today.</p>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card text-white bg-primary mb-3">
            <div class="card-header">Total Jobs</div>
            <div class="card-body">
                <h3 class="card-title">
                    <?= $stats['jobs'] ?>
                </h3>
                <p class="card-text">Active positions</p>
                <a href="/jobs" class="text-white text-decoration-none stretched-link">View Jobs &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-success mb-3">
            <div class="card-header">Candidates</div>
            <div class="card-body">
                <h3 class="card-title">
                    <?= $stats['candidates'] ?>
                </h3>
                <p class="card-text">Total candidates</p>
                <a href="/candidates" class="text-white text-decoration-none stretched-link">View Candidates &rarr;</a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card text-white bg-info mb-3">
            <div class="card-header">Clients</div>
            <div class="card-body">
                <h3 class="card-title">
                    <?= $stats['clients'] ?>
                </h3>
                <p class="card-text">Active clients</p>
                <a href="/clients" class="text-white text-decoration-none stretched-link">View Clients &rarr;</a>
            </div>
        </div>
    </div>
</div>