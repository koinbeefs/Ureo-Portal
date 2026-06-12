<?php
/**
 * System Settings
 * TAU-UREO Portal - Admin Only
 * Improved UI Version
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

// Check if user is admin
if ($_SESSION['role'] !== 'admin') {
    header("Location: ../staff/dashboard.php");
    exit();
}

$conn = getDBConnection();

// Get all settings
$settings_result = $conn->query("SELECT * FROM system_settings ORDER BY setting_key");
$settings = [];
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row;
}

closeDBConnection($conn);

$page_title = 'System Settings';
$base_url = '../';
$active_menu = 'settings';
include '../includes/auth_header.php';
?>

<style>
    :root {
        --tau-green-dark: #006400;
        --tau-green-primary: #228B22;
        --tau-green-light: #e8f5e9;
        --tau-accent: #ffd700;
    }

    .settings-header {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .settings-title {
        color: var(--tau-green-dark);
        font-weight: 700;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .settings-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        height: 100%;
        background: white;
    }

    .settings-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
    }

    .settings-card .card-header {
        background: linear-gradient(135deg, var(--tau-green-dark), var(--tau-green-primary));
        color: white;
        border: none;
        padding: 1.25rem;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .settings-card .card-body {
        padding: 1.5rem;
    }

    .form-label {
        font-weight: 600;
        color: #444;
        font-size: 0.9rem;
        margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
        border-radius: 8px;
        padding: 0.6rem 1rem;
        border: 1px solid #dee2e6;
        transition: all 0.2s;
    }

    .form-control:focus {
        border-color: var(--tau-green-primary);
        box-shadow: 0 0 0 0.25rem rgba(34, 139, 34, 0.15);
    }

    .btn-save {
        background-color: var(--tau-green-primary);
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        font-weight: 600;
        transition: all 0.2s;
        width: 100%;
        margin-top: 1rem;
    }

    .btn-save:hover {
        background-color: var(--tau-green-dark);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .nav-pills-custom .nav-link {
        color: #666;
        font-weight: 500;
        padding: 0.8rem 1.2rem;
        border-radius: 8px;
        transition: all 0.2s;
    }

    .nav-pills-custom .nav-link.active {
        background-color: var(--tau-green-light);
        color: var(--tau-green-dark);
        font-weight: 600;
    }

    .info-table tr td {
        padding: 0.75rem 0;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-table tr:last-child td {
        border-bottom: none;
    }

    .form-check-input:checked {
        background-color: var(--tau-green-primary);
        border-color: var(--tau-green-primary);
    }

    .status-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .hint-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }

    /* Tab Animation */
    .tab-pane {
        animation: fadeIn 0.3s ease-in-out;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(5px); }
        to { opacity: 1; transform: translateY(0); }
    }
</style>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="settings-header">
        <h2 class="settings-title">
            <i class="bi bi-gear-wide-connected"></i> System Configuration
        </h2>
        <div class="d-none d-md-block">
            <span class="text-muted small">Last updated: <?php echo date('M d, Y H:i'); ?></span>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-lg-3 mb-4">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-body p-3">
                    <div class="nav flex-column nav-pills nav-pills-custom" id="settingsTabs" role="tablist">
                        <button class="nav-link active text-start mb-2" data-bs-toggle="pill" data-bs-target="#tab-general" type="button">
                            <i class="bi bi-cpu me-2"></i> General & Queue
                        </button>
                        <button class="nav-link text-start mb-2" data-bs-toggle="pill" data-bs-target="#tab-email" type="button">
                            <i class="bi bi-envelope-at me-2"></i> Email Server
                        </button>
                        <button class="nav-link text-start mb-2" data-bs-toggle="pill" data-bs-target="#tab-app" type="button">
                            <i class="bi bi-window-stack me-2"></i> Application Logic
                        </button>
                        <button class="nav-link text-start mb-2" data-bs-toggle="pill" data-bs-target="#tab-notifications" type="button">
                            <i class="bi bi-bell-badge me-2"></i> Notifications
                        </button>
                        <button class="nav-link text-start mb-2" data-bs-toggle="pill" data-bs-target="#tab-maintenance" type="button">
                            <i class="bi bi-shield-lock me-2"></i> Security & Maintenance
                        </button>
                        <hr class="my-2">
                        <button class="nav-link text-start" data-bs-toggle="pill" data-bs-target="#tab-info" type="button">
                            <i class="bi bi-info-circle me-2"></i> System Info
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="col-lg-9">
            <div class="tab-content" id="settingsTabsContent">
                
                <!-- General & Queue Tab -->
                <div class="tab-pane fade show active" id="tab-general">
                    <div class="row g-4">
                        <div class="col-12">
                            <div class="card settings-card shadow-sm">
                                <div class="card-header">
                                    <i class="bi bi-hash"></i> Queue Configuration
                                </div>
                                <div class="card-body">
                                    <form id="queueForm">
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Current Queue Counter</label>
                                                <input type="number" name="queue_counter" class="form-control" 
                                                       value="<?php echo $settings['queue_counter']['setting_value'] ?? 0; ?>" required>
                                                <div class="hint-text">Next ID: <span class="badge bg-light text-dark border">UREO-<?php echo str_pad(($settings['queue_counter']['setting_value'] ?? 0) + 1, 4, '0', STR_PAD_LEFT); ?></span></div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Queue Format</label>
                                                <input type="text" name="queue_format" class="form-control" 
                                                       value="<?php echo $settings['queue_format']['setting_value'] ?? 'UREO-{YYYY}{NNNN}'; ?>" required>
                                                <div class="hint-text">Use <code>{YYYY}</code> for year, <code>{NNNN}</code> for sequence</div>
                                            </div>
                                        </div>
                                        <div class="row mt-3">
                                            <div class="col-md-4">
                                                <button type="submit" class="btn btn-success btn-save">
                                                    <i class="bi bi-check2-circle me-2"></i> Update Queue Settings
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Tab -->
                <div class="tab-pane fade" id="tab-email">
                    <div class="card settings-card shadow-sm">
                        <div class="card-header">
                            <i class="bi bi-envelope"></i> SMTP Configuration
                        </div>
                        <div class="card-body">
                            <form id="emailForm">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">SMTP Host</label>
                                        <input type="text" name="smtp_host" class="form-control" 
                                               value="<?php echo $settings['smtp_host']['setting_value'] ?? 'smtp.gmail.com'; ?>" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">SMTP Port</label>
                                        <input type="number" name="smtp_port" class="form-control" 
                                               value="<?php echo $settings['smtp_port']['setting_value'] ?? 587; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Username</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                            <input type="text" name="smtp_username" class="form-control" 
                                                   value="<?php echo $settings['smtp_username']['setting_value'] ?? ''; ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">SMTP Password</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-key"></i></span>
                                            <input type="password" name="smtp_password" class="form-control" 
                                                   placeholder="••••••••">
                                        </div>
                                        <div class="hint-text">Leave blank to keep current password</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">From Email Address</label>
                                        <input type="email" name="smtp_from_email" class="form-control" 
                                               value="<?php echo $settings['smtp_from_email']['setting_value'] ?? ''; ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">From Name</label>
                                        <input type="text" name="smtp_from_name" class="form-control" 
                                               value="<?php echo $settings['smtp_from_name']['setting_value'] ?? 'TAU-UREO Portal'; ?>" required>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-success btn-save">
                                            <i class="bi bi-send-check me-2"></i> Save Email Config
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Application Tab -->
                <div class="tab-pane fade" id="tab-app">
                    <div class="card settings-card shadow-sm">
                        <div class="card-header">
                            <i class="bi bi-sliders"></i> Application Configuration
                        </div>
                        <div class="card-body">
                            <form id="applicationForm">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Max File Upload Size (MB)</label>
                                        <div class="input-group">
                                            <input type="number" name="max_file_size" class="form-control" 
                                                   value="<?php echo $settings['max_file_size']['setting_value'] ?? 5; ?>" required min="1" max="50">
                                            <span class="input-group-text">MB</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Allowed File Types</label>
                                        <input type="text" name="allowed_file_types" class="form-control" 
                                               value="<?php echo $settings['allowed_file_types']['setting_value'] ?? 'pdf,doc,docx,jpg,jpeg,png'; ?>" required>
                                        <div class="hint-text">Comma-separated (e.g. pdf,jpg,png)</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">OTP Expiration</label>
                                        <div class="input-group">
                                            <input type="number" name="otp_expiration" class="form-control" 
                                                   value="<?php echo $settings['otp_expiration']['setting_value'] ?? 10; ?>" required min="5" max="60">
                                            <span class="input-group-text">minutes</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Session Timeout</label>
                                        <div class="input-group">
                                            <input type="number" name="session_timeout" class="form-control" 
                                                   value="<?php echo $settings['session_timeout']['setting_value'] ?? 30; ?>" required min="10" max="120">
                                            <span class="input-group-text">minutes</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-light p-3 rounded-3 mb-3 mt-2">
                                    <h6 class="mb-3 text-dark small fw-bold text-uppercase">System Behavior</h6>
                                    <div class="form-check form-switch mb-2">
                                        <input type="checkbox" name="allow_public_tracking" class="form-check-input" id="allowPublicTracking" 
                                               <?php echo ($settings['allow_public_tracking']['setting_value'] ?? 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="allowPublicTracking">
                                            Allow Public Application Tracking
                                        </label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="enable_auto_assignment" class="form-check-input" id="enableAutoAssignment" 
                                               <?php echo ($settings['enable_auto_assignment']['setting_value'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="enableAutoAssignment">
                                            Enable Automatic Staff Assignment
                                        </label>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-success btn-save">
                                            <i class="bi bi-save2 me-2"></i> Save App Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div class="tab-pane fade" id="tab-notifications">
                    <div class="card settings-card shadow-sm">
                        <div class="card-header">
                            <i class="bi bi-bell"></i> Notification Preferences
                        </div>
                        <div class="card-body">
                            <form id="notificationForm">
                                <div class="list-group list-group-flush mb-4">
                                    <div class="list-group-item px-0 py-3 border-0 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0">New Application Alerts</h6>
                                                <p class="text-muted small mb-0">Notify staff when a new application is submitted</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="notify_new_application" class="form-check-input" id="notifyNewApp" 
                                                       <?php echo ($settings['notify_new_application']['setting_value'] ?? 1) ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="list-group-item px-0 py-3 border-0 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0">Status Change Notifications</h6>
                                                <p class="text-muted small mb-0">Email applicants when their application status is updated</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="notify_status_change" class="form-check-input" id="notifyStatusChange" 
                                                       <?php echo ($settings['notify_status_change']['setting_value'] ?? 1) ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="list-group-item px-0 py-3 border-0 border-bottom">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0">Message Notifications</h6>
                                                <p class="text-muted small mb-0">Send email alerts for new internal messages</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="notify_new_message" class="form-check-input" id="notifyNewMsg" 
                                                       <?php echo ($settings['notify_new_message']['setting_value'] ?? 1) ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="list-group-item px-0 py-3 border-0">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-0">Document Upload Alerts</h6>
                                                <p class="text-muted small mb-0">Notify staff when applicants upload new documents</p>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input type="checkbox" name="notify_document_upload" class="form-check-input" id="notifyDocUpload" 
                                                       <?php echo ($settings['notify_document_upload']['setting_value'] ?? 1) ? 'checked' : ''; ?>>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Admin Notification Email</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="bi bi-envelope-exclamation"></i></span>
                                        <input type="email" name="admin_email" class="form-control" 
                                               value="<?php echo $settings['admin_email']['setting_value'] ?? ''; ?>" placeholder="admin@tau.edu.ph">
                                    </div>
                                    <div class="hint-text">Primary address for system-wide critical alerts</div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-success btn-save">
                                            <i class="bi bi-bell-check me-2"></i> Update Preferences
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Maintenance Tab -->
                <div class="tab-pane fade" id="tab-maintenance">
                    <div class="card settings-card shadow-sm border-danger-subtle">
                        <div class="card-header bg-danger bg-gradient">
                            <i class="bi bi-shield-lock"></i> System Maintenance
                        </div>
                        <div class="card-body">
                            <form id="maintenanceForm">
                                <div class="alert alert-warning border-0 shadow-sm d-flex align-items-center mb-4">
                                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                                    <div>
                                        <strong>Warning:</strong> Enabling maintenance mode will restrict applicant access to the portal immediately.
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" name="maintenance_mode" class="form-check-input" id="maintenanceMode" role="switch"
                                               <?php echo ($settings['maintenance_mode']['setting_value'] ?? 0) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-bold text-danger" for="maintenanceMode">
                                            ACTIVATE MAINTENANCE MODE
                                        </label>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Maintenance Message</label>
                                    <textarea name="maintenance_message" class="form-control" rows="4" placeholder="Describe why the system is down..."><?php echo $settings['maintenance_message']['setting_value'] ?? 'The portal is currently undergoing maintenance. Please try again later.'; ?></textarea>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-md-4">
                                        <button type="submit" class="btn btn-danger btn-save" style="background-color: #dc3545;">
                                            <i class="bi bi-power me-2"></i> Apply Security Settings
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- System Info Tab -->
                <div class="tab-pane fade" id="tab-info">
                    <div class="card settings-card shadow-sm">
                        <div class="card-header">
                            <i class="bi bi-info-circle"></i> Environment Information
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table info-table">
                                    <tr>
                                        <td class="text-muted" style="width: 40%;">PHP Version</td>
                                        <td class="fw-bold"><?php echo phpversion(); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">MySQL Version</td>
                                        <td class="fw-bold"><?php 
                                            $conn = getDBConnection();
                                            echo $conn->server_info;
                                            closeDBConnection($conn);
                                        ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Server Software</td>
                                        <td class="fw-bold"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Upload Max Size</td>
                                        <td><span class="badge bg-info text-dark"><?php echo ini_get('upload_max_filesize'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Post Max Size</td>
                                        <td><span class="badge bg-info text-dark"><?php echo ini_get('post_max_size'); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted">Memory Limit</td>
                                        <td><span class="badge bg-info text-dark"><?php echo ini_get('memory_limit'); ?></span></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Success Toast -->
<div class="toast-container position-fixed bottom-0 end-0 p-3">
    <div id="settingsToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="toast-header bg-success text-white">
            <i class="bi bi-check-circle me-2"></i>
            <strong class="me-auto">System Update</strong>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
        <div class="toast-body" id="toastMessage">
            Settings saved successfully!
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toastEl = document.getElementById('settingsToast');
    const toast = new bootstrap.Toast(toastEl);
    const toastMsg = document.getElementById('toastMessage');

    function showToast(message, isError = false) {
        toastMsg.textContent = message;
        toastEl.querySelector('.toast-header').className = isError ? 'toast-header bg-danger text-white' : 'toast-header bg-success text-white';
        toast.show();
    }

    function saveSettings(formData, settingName) {
        fetch('process-settings.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(settingName + ' saved successfully!');
                setTimeout(() => location.reload(), 1500);
            } else {
                showToast('Error: ' + data.message, true);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('An error occurred. Please try again.', true);
        });
    }

    // Event Listeners for Forms
    const forms = {
        'queueForm': 'Queue settings',
        'emailForm': 'Email settings',
        'applicationForm': 'Application settings',
        'notificationForm': 'Notification settings',
        'maintenanceForm': 'Maintenance settings'
    };

    Object.keys(forms).forEach(formId => {
        const form = document.getElementById(formId);
        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (formId === 'maintenanceForm') {
                    if (!confirm('Are you sure you want to change maintenance mode settings? This affects all users.')) {
                        return;
                    }
                }
                
                saveSettings(new FormData(this), forms[formId]);
            });
        }
    });
});
</script>

<?php include '../includes/auth_footer.php'; ?>