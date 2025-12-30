<?php
require_once __DIR__ . '/../panel/modules/_common.php';

use ProConsultancy\Core\Database;
use ProConsultancy\Core\Validator;
use ProConsultancy\Core\FileUpload;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Mailer;

$jobCode = filter_input(INPUT_GET, 'job', FILTER_SANITIZE_STRING);

$db = Database::getInstance();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT * FROM jobs WHERE job_code = ? AND status = 'open' AND is_published = 1");
$stmt->bind_param("s", $jobCode);
$stmt->execute();
$job = $stmt->get_result()->fetch_assoc();

if (!$job) {
    header('Location: jobs.php');
    exit;
}

$success = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRFToken::verify($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission";
    } else {
        $validator = new Validator($_POST);
        if ($validator->validate([
            'name' => 'required|min:3',
            'email' => 'required|email',
            'phone' => 'required',
            'cover_letter' => 'required|min:50'
        ])) {
            // Handle resume upload
            $fileUpload = new FileUpload();
            $uploadResult = $fileUpload->upload('resume', CV_INBOX_PATH, [
                'allowed_types' => ['pdf', 'doc', 'docx'],
                'max_size' => 5 * 1024 * 1024 // 5MB
            ]);
            
            if ($uploadResult['success']) {
                // Save to CV inbox
                $stmt = $conn->prepare("
                    INSERT INTO cv_inbox (
                        applicant_name, applicant_email, applicant_phone,
                        cover_letter, resume_filename, resume_path,
                        source, job_code, status, received_at
                    ) VALUES (?, ?, ?, ?, ?, ?, 'website', ?, 'pending', NOW())
                ");
                
                $stmt->bind_param(
                    "sssssss",
                    $_POST['name'],
                    $_POST['email'],
                    $_POST['phone'],
                    $_POST['cover_letter'],
                    $uploadResult['filename'],
                    $uploadResult['path'],
                    $jobCode
                );
                
                if ($stmt->execute()) {
                    // Send confirmation to applicant
                    $mailer = new Mailer();
                    $mailer->send(
                        $_POST['email'],
                        "Application Received - " . $job['job_title'],
                        "<p>Dear " . htmlspecialchars($_POST['name']) . ",</p>
                         <p>Thank you for applying for the position of <strong>" . htmlspecialchars($job['job_title']) . "</strong>.</p>
                         <p>We have received your application and will review it shortly.</p>
                         <p>Best regards,<br>Pro Consultancy Team</p>"
                    );
                    
                    // Notify recruiter
                    if (!empty($job['created_by'])) {
                        $stmt = $conn->prepare("SELECT email FROM users WHERE user_code = ?");
                        $stmt->bind_param("s", $job['created_by']);
                        $stmt->execute();
                        $recruiter = $stmt->get_result()->fetch_assoc();
                        
                        if ($recruiter) {
                            $mailer->sendFromTemplate(
                                $recruiter['email'],
                                'cv_received',
                                [
                                    'applicant_email' => $_POST['email'],
                                    'applicant_name' => $_POST['name'],
                                    'job_title' => $job['job_title'],
                                    'subject' => 'Application for ' . $job['job_title'],
                                    'received_at' => date('Y-m-d H:i:s'),
                                    'inbox_url' => url('panel/modules/cv-inbox/')
                                ]
                            );
                        }
                    }
                    
                    $success = true;
                }
            } else {
                $errors[] = $uploadResult['error'];
            }
        } else {
            $errors = $validator->errors();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Apply for <?php echo htmlspecialchars($job['job_title']); ?> - Pro Consultancy</title>
    <!-- Same CSS -->
</head>
<body>
    <!-- Same header -->
    
    <div class="container my-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h1>Apply for: <?php echo htmlspecialchars($job['job_title']); ?></h1>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <h4>Application Submitted Successfully!</h4>
                        <p>Thank you for your application. We'll review it and get back to you soon.</p>
                        <a href="jobs.php" class="btn btn-primary">Browse More Jobs</a>
                    </div>
                <?php else: ?>
                
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h5>Please correct the following errors:</h5>
                            <ul>
                                <?php foreach ($errors as $field => $fieldErrors): ?>
                                    <?php if (is_array($fieldErrors)): ?>
                                        <?php foreach ($fieldErrors as $error): ?>
                                            <li><?php echo htmlspecialchars($error); ?></li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li><?php echo htmlspecialchars($fieldErrors); ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                
                    <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo CSRFToken::generate(); ?>">
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email Address *</label>
                            <input type="email" class="form-control" id="email" name="email" 
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" class="form-control" id="phone" name="phone" 
                                   value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                   placeholder="+32 XXX XX XX XX" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="cover_letter" class="form-label">Cover Letter * (minimum 50 characters)</label>
                            <textarea class="form-control" id="cover_letter" name="cover_letter" 
                                      rows="6" required minlength="50"><?php echo htmlspecialchars($_POST['cover_letter'] ?? ''); ?></textarea>
                            <div class="form-text">Tell us why you're a great fit for this position.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="resume" class="form-label">Resume/CV * (PDF, DOC, or DOCX - Max 5MB)</label>
                            <input type="file" class="form-control" id="resume" name="resume" 
                                   accept=".pdf,.doc,.docx" required>
                        </div>
                        
                        <div class="alert alert-info">
                            <strong>Privacy Notice:</strong> Your information will only be used for recruitment purposes 
                            and will be handled according to GDPR regulations.
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-send"></i> Submit Application
                        </button>
                        <a href="job-detail.php?code=<?php echo urlencode($jobCode); ?>" class="btn btn-secondary btn-lg">
                            Cancel
                        </a>
                    </form>
                    
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Same footer -->
</body>
</html>