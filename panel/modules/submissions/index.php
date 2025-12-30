<?php
/**
 * Submissions Module Router
 * File: panel/modules/submissions/index.php
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Auth;

Permission::require('submissions', 'view');

$user = Auth::user();
$action = input('action', 'list');
$submissionCode = input('code', '');
$submissionId = (int)input('id', 0);

// Page configuration
$pageTitle = 'Candidate Submissions';
$breadcrumbs = [
    ['title' => 'Submissions', 'url' => '/panel/modules/submissions/index.php']
];

require_once __DIR__ . '/../../includes/header.php';

try {
    switch ($action) {
        case 'list':
            include __DIR__ . '/list.php';
            break;
            
        case 'create':
            Permission::require('submissions', 'create');
            include __DIR__ . '/create.php';
            break;
            
        case 'edit':
            Permission::require('submissions', 'edit');
            if (!$submissionCode && !$submissionId) {
                throw new Exception('Submission code or ID required');
            }
            include __DIR__ . '/edit.php';
            break;
            
        case 'view':
            if (!$submissionCode && !$submissionId) {
                throw new Exception('Submission code or ID required');
            }
            include __DIR__ . '/view.php';
            break;
            
        default:
            include __DIR__ . '/list.php';
            break;
    }
    
} catch (Exception $e) {
    ?>
    <div class="container-xxl flex-grow-1 container-p-y">
        <div class="alert alert-danger">
            <i class="bx bx-error-circle me-2"></i>
            <?= htmlspecialchars($e->getMessage()) ?>
        </div>
        <a href="/panel/modules/submissions/index.php" class="btn btn-primary">
            <i class="bx bx-arrow-back me-1"></i> Back to Submissions
        </a>
    </div>
    <?php
}

require_once __DIR__ . '/../../includes/footer.php';