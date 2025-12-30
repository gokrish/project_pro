<?php
// Load common bootstrap
require_once __DIR__ . '/../_common.php';

// Include header
require_once ROOT_PATH . '/panel/includes/header.php';

try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM user WHERE is_active = 1");
    $kpis['total'] = $stmt->fetch_assoc()['total'];
    
    $stmt = $conn->query("SELECT level, COUNT(*) as count FROM user WHERE is_active = 1 GROUP BY level");
    while ($row = $stmt->fetch_assoc()) $kpis[$row['level']] = $row['count'];
    
    $stmt = $conn->query("SELECT * FROM user WHERE last_login > DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY last_login DESC LIMIT 5");
    $recent = [];
    while ($row = $stmt->fetch_assoc()) $recent[] = $row;
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . $e->getMessage() . '</div>';
    return;
}
?>
<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">User Dashboard</h4>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5>Total Users</h5>
                    <h2><?php echo $kpis['total']; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5>Admins</h5>
                    <h2><?php echo $kpis['admin'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5>Recruiters</h5>
                    <h2><?php echo $kpis['user'] ?? 0; ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header">
            <h5>Recent Logins</h5>
        </div>
        <div class="card-body">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Last Login</th></tr></thead>
                <tbody>
                    <?php foreach ($recent as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo $user['last_login']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>