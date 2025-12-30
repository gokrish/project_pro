<?php
/**
 * View CV Application
 * Resume preview, notes, conversion to candidate
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Auth;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\Logger;

// Check permission
Permission::require('cv_inbox', 'view');

$user = Auth::user();
$db = Database::getInstance();
$conn = $db->getConnection();

// Get CV ID
$id = (int)input('id');
if (!$id) {
    redirectWithMessage('/panel/modules/cv-inbox/index.php', 'CV application not found', 'error');
}

// Get CV application
$stmt = $conn->prepare("
    SELECT cv.*, j.job_title, j.job_code,
           u.name as assigned_to_name,
           c.candidate_code, c.candidate_name as converted_candidate
    FROM cv_inbox cv
    LEFT JOIN jobs j ON cv.job_code = j.job_code
    LEFT JOIN users u ON cv.assigned_to = u.user_code
    LEFT JOIN candidates c ON cv.converted_to_candidate = c.candidate_code
    WHERE cv.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$cv = $stmt->get_result()->fetch_assoc();

if (!$cv) {
    redirectWithMessage('/panel/modules/cv-inbox/index.php', 'CV application not found', 'error');
}

// Get notes
$stmt = $conn->prepare("
    SELECT n.*, u.name as created_by_name
    FROM cv_notes n
    LEFT JOIN users u ON n.created_by = u.user_code
    WHERE n.cv_id = ?
    ORDER BY n.created_at DESC
");
$stmt->bind_param("i", $id);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Mark as reviewed if status is new
if ($cv['status'] === 'new') {
    $stmt = $conn->prepare("UPDATE cv_inbox SET status = 'reviewed' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $cv['status'] = 'reviewed';
}

// Log view activity
Logger::getInstance()->logActivity('view', 'cv_inbox', $id, "Viewed CV application: {$cv['candidate_name']}");

// Page configuration
$pageTitle = 'CV - ' . $cv['candidate_name'];
$breadcrumbs = [
    ['title' => 'CV Inbox', 'url' => '/panel/modules/cv-inbox/index.php'],
    ['title' => $cv['candidate_name'], 'url' => '']
];
$customJS = ['/panel/assets/js/modules/cv-view.js'];

require_once __DIR__ . '/../../includes/header.php';
?>

<!-- Page Content -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <?= htmlspecialchars($cv['candidate_name']) ?>
                </h4>
                <div class="d-flex gap-2 align-items-center">
                    <?php
                    $statusClasses = [
                        'new' => 'warning',
                        'reviewed' => 'info',
                        'converted' => 'success',
                        'rejected' => 'danger'
                    ];
                    $statusClass = $statusClasses[$cv['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $statusClass ?>">
                        <?= ucfirst($cv['status']) ?>
                    </span>
                    
                    <?php
                    $sourceIcons = [
                        'website' => 'bx-globe',
                        'email' => 'bx-envelope',
                        'linkedin' => 'bxl-linkedin',
                        'referral' => 'bx-user-plus'
                    ];
                    $icon = $sourceIcons[$cv['source']] ?? 'bx-circle';
                    ?>
                    <span class="badge bg-label-secondary">
                        <i class="bx <?= $icon ?>"></i> <?= ucfirst($cv['source']) ?>
                    </span>
                    
                    <?php if ($cv['job_title']): ?>
                    <span class="badge bg-label-info">
                        Applied for: <?= htmlspecialchars($cv['job_title']) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <a href="/panel/modules/cv-inbox/index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="bx bx-arrow-back"></i> Back to Inbox
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: CV Preview & Details -->
    <div class="col-lg-8">
        <!-- Contact Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-user text-primary me-2"></i>
                    Contact Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <p class="mb-0">
                            <a href="mailto:<?= htmlspecialchars($cv['email']) ?>">
                                <i class="bx bx-envelope me-1"></i>
                                <?= htmlspecialchars($cv['email']) ?>
                            </a>
                        </p>
                    </div>
                    
                    <?php if (!empty($cv['phone'])): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <p class="mb-0">
                            <a href="tel:<?= htmlspecialchars($cv['phone']) ?>">
                                <i class="bx bx-phone me-1"></i>
                                <?= htmlspecialchars($cv['phone']) ?>
                            </a>
                        </p>
                    </div>
                    <?php endif; ?>
                    
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Received Date</label>
                        <p class="mb-0">
                            <i class="bx bx-calendar me-1"></i>
                            <?= formatDate($cv['received_at'], 'F j, Y g:i A') ?>
                        </p>
                    </div>
                    
                    <?php if (!empty($cv['assigned_to_name'])): ?>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Assigned To</label>
                        <p class="mb-0">
                            <i class="bx bx-user-pin me-1"></i>
                            <?= htmlspecialchars($cv['assigned_to_name']) ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Resume Preview -->
        <?php if (!empty($cv['resume_path'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bx bx-file text-primary me-2"></i>
                        Resume/CV
                    </h5>
                    <a href="<?= htmlspecialchars($cv['resume_path']) ?>" 
                       class="btn btn-sm btn-outline-primary" 
                       download>
                        <i class="bx bx-download"></i> Download
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php
                $fileExt = pathinfo($cv['resume_path'], PATHINFO_EXTENSION);
                if (strtolower($fileExt) === 'pdf'):
                ?>
                    <!-- PDF Preview -->
                    <iframe src="<?= htmlspecialchars($cv['resume_path']) ?>" 
                            width="100%" 
                            height="800" 
                            style="border: 1px solid #ddd; border-radius: 4px;">
                    </iframe>
                <?php else: ?>
                    <!-- Non-PDF file -->
                    <div class="text-center p-5">
                        <i class="bx bx-file" style="font-size: 64px; color: #999;"></i>
                        <p class="mt-3">
                            <?= strtoupper($fileExt) ?> file preview not available
                        </p>
                        <a href="<?= htmlspecialchars($cv['resume_path']) ?>" 
                           class="btn btn-primary" download>
                            <i class="bx bx-download"></i> Download to View
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Additional Documents -->
        <?php if (!empty($cv['cover_letter_path'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-file-blank text-primary me-2"></i>
                    Cover Letter
                </h5>
            </div>
            <div class="card-body">
                <a href="<?= htmlspecialchars($cv['cover_letter_path']) ?>" 
                   class="btn btn-outline-secondary" download>
                    <i class="bx bx-download"></i> Download Cover Letter
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Right Column: Actions & Notes -->
    <div class="col-lg-4">
        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <?php if ($cv['status'] === 'converted'): ?>
                    <!-- Already Converted -->
                    <div class="alert alert-success">
                        <i class="bx bx-check-circle me-2"></i>
                        <strong>Converted to Candidate</strong>
                        <br>
                        <a href="/panel/modules/candidates/view.php?code=<?= urlencode($cv['converted_to_candidate']) ?>" 
                           class="alert-link">
                            View Candidate Profile →
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Convert to Candidate -->
                    <a href="/panel/modules/cv-inbox/convert.php?id=<?= $id ?>" 
                       class="btn btn-success w-100 mb-2">
                        <i class="bx bx-transfer"></i> Convert to Candidate
                    </a>
                <?php endif; ?>
                
                <!-- Email Candidate -->
                <a href="mailto:<?= htmlspecialchars($cv['email']) ?>" 
                   class="btn btn-outline-primary w-100 mb-2">
                    <i class="bx bx-envelope"></i> Send Email
                </a>
                
                <!-- Call Candidate -->
                <?php if (!empty($cv['phone'])): ?>
                <a href="tel:<?= htmlspecialchars($cv['phone']) ?>" 
                   class="btn btn-outline-primary w-100 mb-2">
                    <i class="bx bx-phone"></i> Call
                </a>
                <?php endif; ?>
                
                <!-- Status Actions -->
                <?php if ($cv['status'] !== 'rejected'): ?>
                <button type="button" class="btn btn-outline-danger w-100 mb-2" id="rejectBtn">
                    <i class="bx bx-x"></i> Reject Application
                </button>
                <?php endif; ?>
                
                <!-- Delete -->
                <button type="button" class="btn btn-outline-dark w-100" id="deleteBtn">
                    <i class="bx bx-trash"></i> Delete
                </button>
            </div>
        </div>
        
        <!-- Notes -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bx bx-note text-primary me-2"></i>
                    Notes (<?= count($notes) ?>)
                </h5>
            </div>
            <div class="card-body">
                <!-- Add Note Form -->
                <form id="addNoteForm" class="mb-3">
                    <?= CSRFToken::field() ?>
                    <input type="hidden" name="cv_id" value="<?= $id ?>">
                    <textarea name="note" 
                              class="form-control mb-2" 
                              rows="3" 
                              placeholder="Add a note..." 
                              required></textarea>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="bx bx-plus"></i> Add Note
                    </button>
                </form>
                
                <!-- Notes List -->
                <div id="notesList">
                    <?php if (empty($notes)): ?>
                        <p class="text-muted text-center py-3">No notes yet</p>
                    <?php else: ?>
                        <?php foreach ($notes as $note): ?>
                        <div class="note-item mb-3 p-3 border rounded" id="note-<?= $note['id'] ?>">
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <strong><?= htmlspecialchars($note['created_by_name']) ?></strong>
                                    <br>
                                    <small class="text-muted">
                                        <?= formatDate($note['created_at'], 'M d, Y g:i A') ?>
                                    </small>
                                </div>
                                <?php if ($note['created_by'] === $user['user_code']): ?>
                                <button type="button" 
                                        class="btn btn-sm btn-icon btn-outline-danger delete-note" 
                                        data-note-id="<?= $note['id'] ?>">
                                    <i class="bx bx-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden data for JavaScript -->
<div id="cvData" 
     data-id="<?= $id ?>" 
     data-name="<?= htmlspecialchars($cv['candidate_name']) ?>"
     style="display: none;"></div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>