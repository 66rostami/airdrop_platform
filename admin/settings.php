<?php
// admin/settings.php
require_once '../config.php';
require_once '../functions.php';

// بررسی دسترسی ادمین
if (!isAdmin()) {
    header('Location: ../index.php');
    exit;
}

// پردازش اکشن‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_general_settings':
                updateSettings([
                    'site_name' => $_POST['site_name'],
                    'site_description' => $_POST['site_description'],
                    'maintenance_mode' => isset($_POST['maintenance_mode']) ? 1 : 0,
                    'maintenance_message' => $_POST['maintenance_message']
                ]);
                break;

            case 'update_points_settings':
                updateSettings([
                    'daily_points_limit' => (int)$_POST['daily_points_limit'],
                    'min_withdrawal_points' => (int)$_POST['min_withdrawal_points'],
                    'points_per_token' => (float)$_POST['points_per_token'],
                    'level_multiplier' => (float)$_POST['level_multiplier']
                ]);
                break;

            case 'update_social_settings':
                updateSettings([
                    'telegram_group' => $_POST['telegram_group'],
                    'discord_server' => $_POST['discord_server'],
                    'twitter_account' => $_POST['twitter_account'],
                    'social_verification_required' => isset($_POST['social_verification_required']) ? 1 : 0
                ]);
                break;

            case 'update_security_settings':
                updateSettings([
                    'max_login_attempts' => (int)$_POST['max_login_attempts'],
                    'login_timeout' => (int)$_POST['login_timeout'],
                    'session_lifetime' => (int)$_POST['session_lifetime'],
                    'ip_check_enabled' => isset($_POST['ip_check_enabled']) ? 1 : 0,
                    'allowed_countries' => $_POST['allowed_countries']
                ]);
                break;
        }
    }
}

// دریافت تنظیمات فعلی
$settings = getAllSettings();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Platform Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include 'includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Platform Settings</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button class="btn btn-sm btn-primary" onclick="saveAllSettings()">
                            <i class="fas fa-save"></i> Save All Changes
                        </button>
                    </div>
                </div>

                <!-- Settings Tabs -->
                <ul class="nav nav-tabs mb-4" id="settingsTabs" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" id="general-tab" data-bs-toggle="tab" href="#general">
                            <i class="fas fa-cog"></i> General
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="points-tab" data-bs-toggle="tab" href="#points">
                            <i class="fas fa-coins"></i> Points & Rewards
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="social-tab" data-bs-toggle="tab" href="#social">
                            <i class="fas fa-share-alt"></i> Social Media
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" id="security-tab" data-bs-toggle="tab" href="#security">
                            <i class="fas fa-shield-alt"></i> Security
                        </a>
                    </li>
                </ul>

                <div class="tab-content" id="settingsContent">
                    <!-- General Settings -->
                    <div class="tab-pane fade show active" id="general">
                        <form id="generalSettingsForm" method="POST">
                            <input type="hidden" name="action" value="update_general_settings">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">General Settings</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Site Name</label>
                                        <input type="text" class="form-control" name="site_name" 
                                               value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Site Description</label>
                                        <textarea class="form-control" name="site_description" rows="3"><?php 
                                            echo htmlspecialchars($settings['site_description']); 
                                        ?></textarea>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="maintenance_mode" 
                                                   id="maintenance_mode" <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="maintenance_mode">
                                                Maintenance Mode
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Maintenance Message</label>
                                        <textarea class="form-control" name="maintenance_message" rows="3"><?php 
                                            echo htmlspecialchars($settings['maintenance_message']); 
                                        ?></textarea>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Points & Rewards Settings -->
                    <div class="tab-pane fade" id="points">
                        <form id="pointsSettingsForm" method="POST">
                            <input type="hidden" name="action" value="update_points_settings">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Points & Rewards Settings</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Daily Points Limit</label>
                                            <input type="number" class="form-control" name="daily_points_limit" 
                                                   value="<?php echo (int)$settings['daily_points_limit']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Minimum Withdrawal Points</label>
                                            <input type="number" class="form-control" name="min_withdrawal_points" 
                                                   value="<?php echo (int)$settings['min_withdrawal_points']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Points per Token</label>
                                            <input type="number" class="form-control" name="points_per_token" step="0.01" 
                                                   value="<?php echo (float)$settings['points_per_token']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Level Multiplier</label>
                                            <input type="number" class="form-control" name="level_multiplier" step="0.01" 
                                                   value="<?php echo (float)$settings['level_multiplier']; ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Social Media Settings -->
                    <div class="tab-pane fade" id="social">
                        <form id="socialSettingsForm" method="POST">
                            <input type="hidden" name="action" value="update_social_settings">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Social Media Settings</h5>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Telegram Group</label>
                                        <input type="text" class="form-control" name="telegram_group" 
                                               value="<?php echo htmlspecialchars($settings['telegram_group']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Discord Server</label>
                                        <input type="text" class="form-control" name="discord_server" 
                                               value="<?php echo htmlspecialchars($settings['discord_server']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Twitter Account</label>
                                        <input type="text" class="form-control" name="twitter_account" 
                                               value="<?php echo htmlspecialchars($settings['twitter_account']); ?>">
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="social_verification_required" 
                                                   id="social_verification_required" 
                                                   <?php echo $settings['social_verification_required'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="social_verification_required">
                                                Require Social Media Verification
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Security Settings -->
                    <div class="tab-pane fade" id="security">
                        <form id="securitySettingsForm" method="POST">
                            <input type="hidden" name="action" value="update_security_settings">
                            
                            <div class="card mb-4">
                                <div class="card-body">
                                    <h5 class="card-title">Security Settings</h5>
                                    
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label">Maximum Login Attempts</label>
                                            <input type="number" class="form-control" name="max_login_attempts" 
                                                   value="<?php echo (int)$settings['max_login_attempts']; ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Login Timeout (minutes)</label>
                                            <input type="number" class="form-control" name="login_timeout" 
                                                   value="<?php echo (int)$settings['login_timeout']; ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Session Lifetime (hours)</label>
                                        <input type="number" class="form-control" name="session_lifetime" 
                                               value="<?php echo (int)$settings['session_lifetime']; ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" name="ip_check_enabled" 
                                                   id="ip_check_enabled" <?php echo $settings['ip_check_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="ip_check_enabled">
                                                Enable IP Check
                                            </label>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Allowed Countries (comma-separated ISO codes)</label>
                                        <input type="text" class="form-control" name="allowed_countries" 
                                               value="<?php echo htmlspecialchars($settings['allowed_countries']); ?>">
                                        <small class="form-text text-muted">Leave empty to allow all countries</small>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/admin.js"></script>
    <script>
        function saveAllSettings() {
            // Submit all forms
            document.getElementById('generalSettingsForm').submit();
            document.getElementById('pointsSettingsForm').submit();
            document.getElementById('socialSettingsForm').submit();
            document.getElementById('securitySettingsForm').submit();
        }

        // Show success message after settings update
        <?php if (isset($_POST['action'])): ?>
        window.onload = function() {
            showAlert('Settings updated successfully', 'success');
        };
        <?php endif; ?>
    </script>
</body>
</html>