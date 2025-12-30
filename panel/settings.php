<?php
require_once __DIR__ . '/includes/_common.php';

if (!in_array(Auth::user()['level'], ['admin', 'super_admin'])) {
    header('Location: /panel/dashboard.php');
    exit();
}

$db = Database::getInstance();
$conn = $db->getConnection();
$user = Auth::user();

// Get all settings grouped by category
$settingsQuery = "SELECT * FROM system_settings ORDER BY setting_category, setting_key";
$allSettings = $conn->query($settingsQuery)->fetch_all(MYSQLI_ASSOC);

// Group by category
$settingsByCategory = [];
foreach ($allSettings as $setting) {
    $category = $setting['setting_category'];
    $settingsByCategory[$category][] = $setting;
}

$pageTitle = 'System Settings';
$breadcrumbs = ['Settings' => ''];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <i class="bx bx-cog me-2"></i> System Settings
    </h4>

    <div class="row">
        <div class="col-lg-3">
            <!-- Category Menu -->
            <div class="nav flex-column nav-pills" role="tablist">
                <?php 
                $first = true;
                foreach (array_keys($settingsByCategory) as $category): 
                ?>
                <a class="nav-link <?= $first ? 'active' : '' ?>" 
                   id="<?= $category ?>-tab" 
                   data-bs-toggle="pill" 
                   href="#<?= $category ?>-content" 
                   role="tab">
                    <i class="bx bx-cog me-2"></i>
                    <?= ucwords(str_replace('_', ' ', $category)) ?>
                </a>
                <?php 
                $first = false;
                endforeach; 
                ?>
            </div>
        </div>

        <div class="col-lg-9">
            <div class="tab-content">
                <?php 
                $first = true;
                foreach ($settingsByCategory as $category => $settings): 
                ?>
                <div class="tab-pane fade <?= $first ? 'show active' : '' ?>" 
                     id="<?= $category ?>-content" 
                     role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0"><?= ucwords(str_replace('_', ' ', $category)) ?> Settings</h5>
                        </div>
                        <div class="card-body">
                            <form id="settings-form-<?= $category ?>" method="POST" action="handlers/update_settings.php">
                                <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                                <input type="hidden" name="category" value="<?= $category ?>">
                                
                                <?php foreach ($settings as $setting): ?>
                                <div class="mb-3">
                                    <label class="form-label">
                                        <?= ucwords(str_replace('_', ' ', str_replace($category . '_', '', $setting['setting_key']))) ?>
                                    </label>
                                    
                                    <?php if ($setting['setting_type'] === 'boolean'): ?>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="settings[<?= $setting['setting_key'] ?>]"
                                                   value="1"
                                                   <?= $setting['setting_value'] == '1' ? 'checked' : '' ?>>
                                        </div>
                                    <?php elseif ($setting['setting_type'] === 'number'): ?>
                                        <input type="number" 
                                               class="form-control" 
                                               name="settings[<?= $setting['setting_key'] ?>]"
                                               value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                    <?php else: ?>
                                        <input type="text" 
                                               class="form-control" 
                                               name="settings[<?= $setting['setting_key'] ?>]"
                                               value="<?= htmlspecialchars($setting['setting_value']) ?>">
                                    <?php endif; ?>
                                    
                                    <?php if ($setting['description']): ?>
                                    <small class="text-muted"><?= htmlspecialchars($setting['description']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                                
                                <div class="pt-3 border-top">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bx bx-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <?php 
                $first = false;
                endforeach; 
                ?>
            </div>
        </div>
    </div>
</div>

<script>
$('[id^="settings-form-"]').on('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    try {
        const response = await fetch('handlers/update_settings.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('Settings updated successfully');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        alert('Network error');
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>