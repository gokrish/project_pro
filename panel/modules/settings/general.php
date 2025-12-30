<?php
/**
 * General Settings
 * File: panel/modules/settings/general.php
 */

require_once __DIR__ . '/../_common.php';

use ProConsultancy\Core\Permission;
use ProConsultancy\Core\Database;
use ProConsultancy\Core\CSRFToken;

// Check permission
Permission::require('settings', 'edit_general');

$db = Database::getInstance();
$conn = $db->getConnection();

// Fetch current settings
$settingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'general_%'";
$result = $conn->query($settingsQuery);
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$pageTitle = 'General Settings';
require_once ROOT_PATH . '/panel/includes/header.php';
?>

<div class="container-xxl flex-grow-1 container-p-y">
    <h4 class="fw-bold py-3 mb-4">
        <span class="text-muted fw-light">Settings /</span> General
    </h4>

    <?php require_once ROOT_PATH . '/panel/includes/flash-messages.php'; ?>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="handlers/update_general.php">
                        <input type="hidden" name="csrf_token" value="<?= CSRFToken::generate() ?>">
                        
                        <!-- Application Name -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Application Name</label>
                            <div class="col-sm-9">
                                <input type="text" 
                                       class="form-control" 
                                       name="general_app_name" 
                                       value="<?= escape($settings['general_app_name'] ?? 'ProConsultancy ATS') ?>"
                                       required>
                                <small class="text-muted">Displayed in browser title and emails</small>
                            </div>
                        </div>

                        <!-- Timezone -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Timezone</label>
                            <div class="col-sm-9">
                                <select class="form-select" name="general_timezone">
                                    <option value="Europe/Brussels" <?= ($settings['general_timezone'] ?? '') === 'Europe/Brussels' ? 'selected' : '' ?>>Brussels (Europe/Brussels)</option>
                                    <option value="America/New_York" <?= ($settings['general_timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>New York (America/New_York)</option>
                                    <option value="America/Chicago" <?= ($settings['general_timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Chicago (America/Chicago)</option>
                                    <option value="America/Los_Angeles" <?= ($settings['general_timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Los Angeles (America/Los_Angeles)</option>
                                    <option value="Europe/London" <?= ($settings['general_timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London (Europe/London)</option>
                                    <option value="Asia/Dubai" <?= ($settings['general_timezone'] ?? '') === 'Asia/Dubai' ? 'selected' : '' ?>>Dubai (Asia/Dubai)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Date Format -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Date Format</label>
                            <div class="col-sm-9">
                                <select class="form-select" name="general_date_format">
                                    <option value="Y-m-d" <?= ($settings['general_date_format'] ?? '') === 'Y-m-d' ? 'selected' : '' ?>>YYYY-MM-DD</option>
                                    <option value="d/m/Y" <?= ($settings['general_date_format'] ?? '') === 'd/m/Y' ? 'selected' : '' ?>>DD/MM/YYYY</option>
                                    <option value="m/d/Y" <?= ($settings['general_date_format'] ?? '') === 'm/d/Y' ? 'selected' : '' ?>>MM/DD/YYYY</option>
                                    <option value="d-M-Y" <?= ($settings['general_date_format'] ?? '') === 'd-M-Y' ? 'selected' : '' ?>>DD-MMM-YYYY</option>
                                </select>
                            </div>
                        </div>

                        <!-- Time Format -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Time Format</label>
                            <div class="col-sm-9">
                                <select class="form-select" name="general_time_format">
                                    <option value="H:i" <?= ($settings['general_time_format'] ?? '') === 'H:i' ? 'selected' : '' ?>>24-hour (14:30)</option>
                                    <option value="g:i A" <?= ($settings['general_time_format'] ?? '') === 'g:i A' ? 'selected' : '' ?>>12-hour (2:30 PM)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Default Currency -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Default Currency</label>
                            <div class="col-sm-9">
                                <select class="form-select" name="general_currency">
                                    <option value="EUR" <?= ($settings['general_currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (€)</option>
                                    <option value="USD" <?= ($settings['general_currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD ($)</option>
                                    <option value="GBP" <?= ($settings['general_currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (£)</option>
                                    <option value="AED" <?= ($settings['general_currency'] ?? '') === 'AED' ? 'selected' : '' ?>>AED (د.إ)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Pagination -->
                        <div class="row mb-3">
                            <label class="col-sm-3 col-form-label">Items Per Page</label>
                            <div class="col-sm-9">
                                <input type="number" 
                                       class="form-control" 
                                       name="general_items_per_page" 
                                       value="<?= escape($settings['general_items_per_page'] ?? '25') ?>"
                                       min="10" max="100">
                                <small class="text-muted">Number of items to display per page in lists</small>
                            </div>
                        </div>

                        <!-- Save Button -->
                        <div class="row">
                            <div class="col-sm-9 offset-sm-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bx bx-save me-1"></i> Save Changes
                                </button>
                                <a href="/panel/settings.php" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/panel/includes/footer.php'; ?>