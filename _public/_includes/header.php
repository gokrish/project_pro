<?php
/**
 * Public Pages Header - Shared Component
 * Matches existing jobs.html design exactly
 */

// Set defaults if not provided
$pageTitle = $pageTitle ?? 'Pro Consultancy';
$pageDescription = $pageDescription ?? 'IT Consulting & Recruitment Services';
$pageKeywords = $pageKeywords ?? 'IT Consulting, Recruitment, Belgium';
$currentPage = $currentPage ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>">
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">

    <!-- Favicon -->
    <link href="/img/favicon.ico" rel="icon">

    <!-- Google Web Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&family=Rubik:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Icon Font Stylesheet -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.10.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.4.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Libraries Stylesheet -->
    <link href="/lib/owlcarousel/assets/owl.carousel.min.css" rel="stylesheet">
    <link href="/lib/animate/animate.min.css" rel="stylesheet">

    <!-- Customized Bootstrap Stylesheet -->
    <link href="/css/bootstrap.min.css" rel="stylesheet">

    <!-- Template Stylesheet -->
    <link href="/css/style.css" rel="stylesheet">
</head>

<body>
    <!-- Spinner Start -->
    <div id="spinner" class="show bg-white position-fixed translate-middle w-100 vh-100 top-50 start-50 d-flex align-items-center justify-content-center">
        <div class="spinner"></div>
    </div>
    <!-- Spinner End -->

    <!-- Topbar Start -->
    <div class="container-fluid bg-dark px-5 d-none d-lg-block">
        <div class="row gx-0">
            <div class="col-lg-8 text-center text-lg-start mb-2 mb-lg-0">
                <div class="d-inline-flex align-items-center" style="height: 45px;">
                    <small class="me-3 text-light">Pro Consultancy BV&nbsp;&nbsp;<i class="fa fa-map-marker-alt me-2"></i>Leuvensesteenweg 122 Zaventem 1932 Belgium.</small>
                    <small class="me-3 text-light"><i class="fa fa-phone-alt me-2"></i>+32-472849033</small>
                    <small class="text-light"><i class="fa fa-envelope-open me-2"></i>admin@proconsultancy.be</small>
                </div>
            </div>
            <div class="col-lg-4 text-center text-lg-end">
                <div class="d-inline-flex align-items-center" style="height: 45px;">
                    <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-twitter fw-normal"></i></a>
                    <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-facebook-f fw-normal"></i></a>
                    <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href="https://www.linkedin.com/company/proconsultancypteltd/"><i class="fab fa-linkedin-in fw-normal"></i></a>
                    <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle me-2" href="#"><i class="fab fa-instagram fw-normal"></i></a>
                    <a class="btn btn-sm btn-outline-light btn-sm-square rounded-circle" href="#"><i class="fab fa-youtube fw-normal"></i></a>
                </div>
            </div>
        </div>
    </div>
    <!-- Topbar End -->

    <!-- Navbar Start -->
    <div class="container-fluid position-relative p-0">
        <nav class="navbar navbar-expand-lg navbar-dark px-5 py-3 py-lg-0">
            <div class="d-flex">
                <a href="/index.html" class="navbar-brand p-0">
                    <img src="/img/pro-consultancy-logo.webp" alt="Pro Consultancy Logo" style="width: 80%;">
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse">
                    <span class="fa fa-bars"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <div class="navbar-nav ms-auto py-0">
                    <a href="/index.html" class="nav-item nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">Home</a>
                    <a href="/about.html" class="nav-item nav-link <?= $currentPage === 'about' ? 'active' : '' ?>">About</a>
                    <a href="/companies.html" class="nav-item nav-link <?= $currentPage === 'companies' ? 'active' : '' ?>">For Clients</a>
                    <a href="/candidates.html" class="nav-item nav-link <?= $currentPage === 'candidates' ? 'active' : '' ?>">For Candidates</a>
                    <a href="/public/jobs.php" class="nav-item nav-link <?= $currentPage === 'jobs' ? 'active' : '' ?>">Jobs Openings</a>                    
                    <a href="/contact.html" class="nav-item nav-link <?= $currentPage === 'contact' ? 'active' : '' ?>">Contact</a>
                </div>
                <a href="#" class="btn btn-primary py-2 px-4 ms-3" data-bs-toggle="modal" data-bs-target="#cvModal">Submit your CV</a>
                <a href="/panel/login.php" class="btn btn-outline-light py-2 px-4 ms-2">
                    <i class="bi bi-person-circle me-1"></i> Login
                </a>
            </div>
        </nav>
        
        <!-- CV Submission Modal -->
        <?php include __DIR__ . '/cv-modal.php'; ?>

        <!-- Page Header -->
        <?php if (isset($pageHeader) && $pageHeader): ?>
            <div class="container-fluid bg-primary py-5 bg-header" style="background: linear-gradient(rgba(9, 30, 62, .7), rgba(9, 30, 62, .7)), url(/img/<?= $headerImage ?? 'job.png' ?>) center center no-repeat; background-size: cover; margin-bottom: 90px;">
                <div class="row py-5">
                    <div class="col-12 pt-lg-5 mt-lg-5 text-center">
                        <h1 class="display-4 text-white animated zoomIn"><?= $headerTitle ?? '' ?></h1>
                        <?php if (isset($headerSubtitle)): ?>
                            <p class="h5 text-white"><?= $headerSubtitle ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <!-- Navbar End -->