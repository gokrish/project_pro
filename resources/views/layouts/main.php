<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= $title ?? 'ProConsultancy ATS' ?>
    </title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }

        .sidebar {
            min-height: 100vh;
            background: #343a40;
            color: white;
        }

        .sidebar a {
            color: #rgba(255, 255, 255, 0.8);
            text-decoration: none;
            display: block;
            padding: 10px 20px;
        }

        .sidebar a:hover {
            background: #495057;
            color: white;
        }

        .sidebar .active {
            background: #0d6efd;
            color: white;
        }

        .content {
            padding: 20px;
        }
    </style>
</head>

<body>
    <div class="row g-0">
        <?php if (!isset($hideSidebar) || !$hideSidebar): ?>
            <div class="col-md-2 sidebar">
                <h4 class="text-center py-4 border-bottom border-secondary">ProConsultancy</h4>
                <nav>
                    <a href="/" class="<?= ($_SERVER['REQUEST_URI'] == '/') ? 'active' : '' ?>">Dashboard</a>
                    <a href="/jobs">Jobs</a>
                    <a href="/candidates">Candidates</a>
                    <a href="/clients">Clients</a>
                    <a href="/inbox">CV Inbox</a>
                    <a href="/reports">Reports</a>
                    <a href="/recruiters">Recruiters</a>
                    <hr class="border-secondary mx-3">
                    <a href="/logout">Logout</a>
                </nav>
            </div>
            <div class="col-md-10">
                <!-- Top Navbar -->
                <nav class="navbar navbar-light bg-white border-bottom px-4 py-2 justify-content-end">
                    <div class="dropdown">
                        <a href="#" class="d-flex align-items-center text-decoration-none dropdown-toggle" id="userDropdown"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                style="width: 32px; height: 32px;">
                                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
                            </div>
                            <span class="fw-bold text-dark"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="/profile">My Profile</a></li>
                            <li>
                                <hr class="dropdown-divider">
                            </li>
                            <li><a class="dropdown-item text-danger" href="/logout">Logout</a></li>
                        </ul>
                    </div>
                </nav>

                <div class="content p-4">
                    <?= $content ?>
                </div>
            </div>
        <?php else: ?>
            <div class="col-12 content">
                <?= $content ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>