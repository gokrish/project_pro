<?php
/**
 * Website Queries/Inquiries List
 * Messages from public "Contact Us" form
 * 
 * @version 5.0
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\Pagination;

Permission::require('queries', 'view');

$db = Database::getInstance();
$conn = $db->getConnection();

// Filters
$search = input('search', '');
$dateFilter = input('date', '');

// Build query
$where = ['1=1'];
$params = [];
$types = '';

if (!empty($search)) {
    $where[] = "(qry_name LIKE ? OR qry_mail LIKE ? OR qry_subject LIKE ? OR qry_msg LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= 'ssss';
}

if (!empty($dateFilter)) {
    $where[] = "DATE(submission_date) = ?";
    $params[] = $dateFilter;
    $types .= 's';
}

$whereClause = implode(' AND ', $where);

// Count total
$countSQL = "SELECT COUNT(*) as total FROM queries WHERE {$whereClause}";
$countStmt = $conn->prepare($countSQL);
if (!empty($params)) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalRecords = $countStmt->get_result()->fetch_assoc()['total'];

// Pagination
$page = (int)input('page', 1);
$perPage = 25;
$pagination = new Pagination($totalRecords, $perPage, $page);

// Get queries
$sql = "
    SELECT *
    FROM queries
    WHERE {$whereClause}
    ORDER BY submission_date DESC
    {$pagination->getLimitClause()}
";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$queries = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Website Inquiries';
$breadcrumbs = [
    ['title' => 'Website Inquiries', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1">
                        <i class="bx bx-envelope text-primary me-2"></i>
                        Website Inquiries
                    </h4>
                    <p class="text-muted mb-0">
                        Messages from public contact form (<?= number_format($totalRecords) ?> total)
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Search name, email, subject, message..." 
                           value="<?= escape($search) ?>">
                </div>
                
                <div class="col-md-3">
                    <input type="date" class="form-control" name="date" 
                           value="<?= escape($dateFilter) ?>">
                </div>
                
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bx bx-search"></i> Filter
                    </button>
                </div>
                
                <?php if ($search || $dateFilter): ?>
                <div class="col-md-2">
                    <a href="list.php" class="btn btn-outline-secondary w-100">
                        <i class="bx bx-x"></i> Clear
                    </a>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Queries Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th width="80">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($queries)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bx bx-envelope bx-lg text-muted"></i>
                                <p class="text-muted mt-2">No inquiries found</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($queries as $query): ?>
                        <tr>
                            <td><?= escape($query['qry_name']) ?></td>
                            <td>
                                <a href="mailto:<?= escape($query['qry_mail']) ?>">
                                    <?= escape($query['qry_mail']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 200px;" 
                                      title="<?= escape($query['qry_subject']) ?>">
                                    <?= escape($query['qry_subject']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-truncate d-inline-block" style="max-width: 300px;" 
                                      title="<?= escape($query['qry_msg']) ?>">
                                    <?= escape($query['qry_msg']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= formatDate($query['submission_date'], 'M d, Y') ?></small>
                            </td>
                            <td>
                                <button type="button" 
                                        class="btn btn-sm btn-icon btn-outline-primary"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#queryModal<?= $query['query_id'] ?>">
                                    <i class="bx bx-show"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($totalRecords > $perPage): ?>
            <div class="card-footer">
                <?= $pagination->render() ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Modals -->
<?php foreach ($queries as $query): ?>
<div class="modal fade" id="queryModal<?= $query['query_id'] ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Inquiry from <?= escape($query['qry_name']) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <dl class="row mb-0">
                    <dt class="col-sm-3">Name</dt>
                    <dd class="col-sm-9"><?= escape($query['qry_name']) ?></dd>
                    
                    <dt class="col-sm-3">Email</dt>
                    <dd class="col-sm-9">
                        <a href="mailto:<?= escape($query['qry_mail']) ?>">
                            <?= escape($query['qry_mail']) ?>
                        </a>
                    </dd>
                    
                    <dt class="col-sm-3">Subject</dt>
                    <dd class="col-sm-9"><?= escape($query['qry_subject']) ?></dd>
                    
                    <dt class="col-sm-3">Message</dt>
                    <dd class="col-sm-9"><?= nl2br(escape($query['qry_msg'])) ?></dd>
                    
                    <dt class="col-sm-3">Received</dt>
                    <dd class="col-sm-9"><?= formatDate($query['submission_date'], 'F j, Y g:i A') ?></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <a href="mailto:<?= escape($query['qry_mail']) ?>" class="btn btn-primary">
                    <i class="bx bx-reply me-1"></i> Reply via Email
                </a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>