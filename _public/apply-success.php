<?php
$pageTitle = 'Application Submitted - ProConsultancy';
$currentPage = 'jobs';
require_once '_includes/header.php';
?>

<div class="container my-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-0 shadow-lg">
                <div class="card-body text-center py-5">
                    <div class="mb-4">
                        <i class='bx bx-check-circle text-success' style="font-size: 5rem;"></i>
                    </div>
                    <h2 class="mb-3">Application Submitted Successfully!</h2>
                    <p class="text-muted mb-4">
                        Thank you for applying. We have received your application and will review it shortly.
                        You will receive a confirmation email at the address you provided.
                    </p>
                    <div class="d-flex gap-3 justify-content-center">
                        <a href="/public/jobs.php" class="btn btn-primary">
                            <i class='bx bx-list-ul'></i> View More Jobs
                        </a>
                        <a href="/" class="btn btn-outline-secondary">
                            <i class='bx bx-home'></i> Back to Home
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '_includes/footer.php'; ?>