<?php
require_once __DIR__ . '/../_common.php';
use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;
use ProConsultancy\Core\FlashMessage;

Permission::require('contacts', 'convert');

$contactCode = $_GET['contact_code'] ?? null;
$db = Database::getInstance();
$conn = $db->getConnection();

if (!$contactCode) {
    FlashMessage::error('Contact code is required');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

// Fetch contact
$stmt = $conn->prepare("SELECT * FROM contacts WHERE contact_code = ? AND deleted_at IS NULL");
$stmt->bind_param("s", $contactCode);
$stmt->execute();
$contact = $stmt->get_result()->fetch_assoc();

if (!$contact) {
    FlashMessage::error('Contact not found');
    redirect(BASE_URL . '/panel/modules/contacts/list.php');
}

if ($contact['converted_to_candidate']) {
    FlashMessage::info('This contact has already been converted');
    redirect(BASE_URL . '/panel/modules/contacts/view.php?contact_code=' . $contactCode);
}

$pageTitle = 'Convert to Candidate';
$breadcrumbs = [
    ['title' => 'Contacts', 'url' => '/panel/modules/contacts/list.php'],
    ['title' => $contact['first_name'] . ' ' . $contact['last_name'], 'url' => '/panel/modules/contacts/view.php?contact_code=' . $contactCode],
    ['title' => 'Convert', 'url' => '']
];

require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bx bx-right-arrow-circle text-success me-2"></i>
                        Convert Contact to Candidate
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="bx bx-info-circle me-2"></i>
                        <strong>What happens when you convert:</strong>
                        <ul class="mb-0 mt-2">
                            <li>A new candidate profile will be created with all contact data</li>
                            <li>The contact will be marked as "converted"</li>
                            <li>You can still view the original contact record</li>
                            <li>The candidate will be linked to this contact</li>
                        </ul>
                    </div>

                    <h6 class="mt-4 mb-3">Contact Details to be Transferred:</h6>
                    <table class="table table-borderless">
                        <tr>
                            <td width="35%" class="text-muted"><strong>Name:</strong></td>
                            <td><?= escape($contact['first_name'] . ' ' . $contact['last_name']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><strong>Email:</strong></td>
                            <td><?= escape($contact['email']) ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted"><strong>Phone:</strong></td>
                            <td><?= escape($contact['phone'] ?: '-') ?></td>
                        </tr>
                        <?php if ($contact['current_company']): ?>
                        <tr>
                            <td class="text-muted"><strong>Company:</strong></td>
                            <td><?= escape($contact['current_company']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($contact['current_title']): ?>
                        <tr>
                            <td class="text-muted"><strong>Title:</strong></td>
                            <td><?= escape($contact['current_title']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($contact['years_of_experience']): ?>
                        <tr>
                            <td class="text-muted"><strong>Experience:</strong></td>
                            <td><?= $contact['years_of_experience'] ?> years</td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($contact['current_location']): ?>
                        <tr>
                            <td class="text-muted"><strong>Location:</strong></td>
                            <td><?= escape($contact['current_location']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($contact['skills']): ?>
                        <tr>
                            <td class="text-muted"><strong>Skills:</strong></td>
                            <td>
                                <?php
                                $skillsArray = json_decode($contact['skills'], true);
                                if ($skillsArray) {
                                    foreach ($skillsArray as $skill) {
                                        echo '<span class="badge bg-label-primary me-1">' . escape($skill) . '</span>';
                                    }
                                }
                                ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>

                    <form method="POST" action="handlers/convert_handler.php" class="mt-4">
                        <?= CSRFToken::field() ?>
                        <input type="hidden" name="contact_code" value="<?= $contactCode ?>">
                        
                        <div class="d-flex gap-2 justify-content-end">
                            <a href="view.php?contact_code=<?= $contactCode ?>" class="btn btn-outline-secondary">
                                <i class="bx bx-x me-1"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="bx bx-right-arrow-circle me-1"></i>
                                Convert to Candidate
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
