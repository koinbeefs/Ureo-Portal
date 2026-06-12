<?php
/**
 * Activity Logs Viewer
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

// Handle filters
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$action_filter = $_GET['action'] ?? '';
$staff_filter = $_GET['staff'] ?? '';
$limit = (int)($_GET['limit'] ?? 100);

// Build query
$query = "
    SELECT sl.*, u.full_name, u.username
    FROM staff_logs sl
    JOIN users u ON sl.staff_id = u.user_id
    WHERE DATE(sl.timestamp) BETWEEN ? AND ?
";
$params = [$date_from, $date_to];
$types = "ss";

if ($action_filter) {
    $query .= " AND sl.action_type = ?";
    $params[] = $action_filter;
    $types .= "s";
}

if ($staff_filter) {
    $query .= " AND sl.staff_id = ?";
    $params[] = (int)$staff_filter;
    $types .= "i";
}

$query .= " ORDER BY sl.timestamp DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

// Get unique action types
$action_types = $conn->query("SELECT DISTINCT action_type FROM staff_logs ORDER BY action_type");

// Get all staff users
$staff_users = $conn->query("SELECT user_id, full_name, username FROM users ORDER BY full_name");

// Get statistics
$stats = [];
$stats['total_logs'] = $conn->query("SELECT COUNT(*) as count FROM staff_logs")->fetch_assoc()['count'];
$stats['today_actions'] = $conn->query("SELECT COUNT(*) as count FROM staff_logs WHERE DATE(timestamp) = CURDATE()")->fetch_assoc()['count'];
$stats['unique_users'] = $conn->query("SELECT COUNT(DISTINCT staff_id) as count FROM staff_logs WHERE DATE(timestamp) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['count'];

closeDBConnection($conn);

$page_title = 'Activity Logs';
$base_url = '../';
$active_menu = 'logs';
include '../includes/auth_header.php';
?>

<style>
    :root {
        --tau-green-dark: #006400;
        --tau-green-primary: #228B22;
        --tau-green-light: #e8f5e9;
        --tau-accent: #ffd700;
    }

    .logs-header {
        background: white;
        padding: 1.5rem;
        border-radius: 12px;
        box-shadow: 0 2px 12px rgba(0,0,0,0.05);
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .logs-title {
        color: var(--tau-green-dark);
        font-weight: 700;
        margin-bottom: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .stat-card {
        border: none;
        border-radius: 12px;
        transition: transform 0.2s ease;
        background: white;
        overflow: hidden;
    }

    .stat-card:hover {
        transform: translateY(-3px);
    }

    .stat-icon-wrapper {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        margin-bottom: 1rem;
    }

    .filter-card {
        border: none;
        border-radius: 12px;
        background: white;
    }

    .logs-table-card {
        border: none;
        border-radius: 12px;
        background: white;
        overflow: hidden;
    }

    .table thead th {
        background-color: #f8f9fa;
        color: #555;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-top: none;
    }

    .table tbody td {
        padding: 1rem;
        vertical-align: middle;
    }

    .badge-action {
        padding: 0.5em 0.8em;
        font-weight: 500;
        border-radius: 6px;
        font-size: 0.75rem;
    }

    .staff-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .staff-avatar {
        width: 32px;
        height: 32px;
        background-color: var(--tau-green-light);
        color: var(--tau-green-dark);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 0.8rem;
    }

    .ip-code {
        background: #f1f3f5;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 0.85rem;
        color: #495057;
    }

    .queue-link {
        color: var(--tau-green-primary);
        text-decoration: none;
        font-weight: 600;
        border-bottom: 1px dashed var(--tau-green-primary);
    }

    .queue-link:hover {
        color: var(--tau-green-dark);
        border-bottom-style: solid;
    }

    .btn-export {
        border-radius: 8px;
        padding: 0.6rem 1.2rem;
        font-weight: 600;
        transition: all 0.2s;
    }

    .btn-filter {
        background-color: var(--tau-green-primary);
        border: none;
        border-radius: 8px;
        height: 42px;
        width: 100%;
        transition: all 0.2s;
    }

    .btn-filter:hover {
        background-color: var(--tau-green-dark);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }

    .form-control, .form-select {
        border-radius: 8px;
        border: 1px solid #dee2e6;
        padding: 0.55rem 0.75rem;
    }

    .form-control:focus, .form-select:focus {
        border-color: var(--tau-green-primary);
        box-shadow: 0 0 0 0.25rem rgba(34, 139, 34, 0.1);
    }

    .timestamp-cell {
        line-height: 1.2;
    }

    .details-text {
        color: #666;
        font-size: 0.85rem;
        max-width: 300px;
        display: inline-block;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        vertical-align: middle;
    }

    .modal-content {
        border: none;
        border-radius: 15px;
        overflow: hidden;
    }

    .modal-header {
        border-bottom: none;
        padding: 1.5rem;
    }

    .modal-body {
        padding: 1.5rem;
        background-color: #f8f9fa;
    }

    .details-pre {
        background: white;
        padding: 1.5rem;
        border-radius: 10px;
        border: 1px solid #e9ecef;
        font-family: 'Monaco', 'Consolas', monospace;
        font-size: 0.9rem;
        color: #333;
        margin-bottom: 0;
    }
</style>

<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="logs-header">
        <h2 class="logs-title">
            <i class="bi bi-clock-history"></i> Activity Logs
        </h2>
        <button type="button" class="btn btn-outline-success btn-export" onclick="exportLogs()">
            <i class="bi bi-download me-2"></i> Export Data
        </button>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                        <i class="bi bi-database-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo number_format($stats['total_logs']); ?></h3>
                    <p class="text-muted mb-0 small text-uppercase fw-semibold">Total Log Entries</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-lightning-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo number_format($stats['today_actions']); ?></h3>
                    <p class="text-muted mb-0 small text-uppercase fw-semibold">Actions Recorded Today</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3 class="fw-bold mb-1"><?php echo $stats['unique_users']; ?></h3>
                    <p class="text-muted mb-0 small text-uppercase fw-semibold">Active Staff (30 Days)</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Section -->
    <div class="card filter-card shadow-sm mb-4">
        <div class="card-body p-4">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted">DATE FROM</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted">DATE TO</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-lg-2 col-md-4">
                    <label class="form-label small fw-bold text-muted">ACTION TYPE</label>
                    <select name="action" class="form-select">
                        <option value="">All Actions</option>
                        <?php while ($action = $action_types->fetch_assoc()): ?>
                            <option value="<?php echo $action['action_type']; ?>" <?php echo $action_filter === $action['action_type'] ? 'selected' : ''; ?>>
                                <?php echo ucwords(str_replace('_', ' ', $action['action_type'])); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <label class="form-label small fw-bold text-muted">STAFF MEMBER</label>
                    <select name="staff" class="form-select">
                        <option value="">All Staff Members</option>
                        <?php while ($staff = $staff_users->fetch_assoc()): ?>
                            <option value="<?php echo $staff['user_id']; ?>" <?php echo $staff_filter == $staff['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($staff['full_name']); ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-1 col-md-3">
                    <label class="form-label small fw-bold text-muted">LIMIT</label>
                    <select name="limit" class="form-select">
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                        <option value="1000" <?php echo $limit == 1000 ? 'selected' : ''; ?>>1000</option>
                    </select>
                </div>
                <div class="col-lg-2 col-md-3">
                    <button type="submit" class="btn btn-success btn-filter">
                        <i class="bi bi-search me-2"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Logs Table Section -->
    <div class="card logs-table-card shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="logsTable">
                    <thead>
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Timestamp</th>
                            <th>Staff Member</th>
                            <th>Action</th>
                            <th>Reference</th>
                            <th>IP Address</th>
                            <th>Details</th>
                            <th class="pe-4 text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($logs->num_rows > 0): ?>
                            <?php while ($log = $logs->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4 text-muted small">#<?php echo $log['log_id']; ?></td>
                                    <td class="timestamp-cell">
                                        <div class="fw-bold small"><?php echo date('M d, Y', strtotime($log['timestamp'])); ?></div>
                                        <div class="text-muted" style="font-size: 0.75rem;"><?php echo date('h:i:s A', strtotime($log['timestamp'])); ?></div>
                                    </td>
                                    <td>
                                        <div class="staff-info">
                                            <div class="staff-avatar">
                                                <?php echo strtoupper(substr($log['full_name'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold small"><?php echo htmlspecialchars($log['full_name']); ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;">@<?php echo htmlspecialchars($log['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $badge_class = 'bg-secondary';
                                        $action = $log['action_type'];
                                        if (strpos($action, 'approved') !== false) $badge_class = 'bg-success';
                                        elseif (strpos($action, 'rejected') !== false) $badge_class = 'bg-danger';
                                        elseif (strpos($action, 'created') !== false) $badge_class = 'bg-primary';
                                        elseif (strpos($action, 'updated') !== false) $badge_class = 'bg-info';
                                        elseif (strpos($action, 'deleted') !== false) $badge_class = 'bg-dark';
                                        ?>
                                        <span class="badge badge-action <?php echo $badge_class; ?> text-capitalize">
                                            <?php echo str_replace('_', ' ', $action); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($log['queue_number']): ?>
                                            <a href="../staff/view-application.php?queue=<?php echo urlencode($log['queue_number']); ?>" class="queue-link small" target="_blank">
                                                <i class="bi bi-link-45deg"></i> <?php echo htmlspecialchars($log['queue_number']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="ip-code"><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></span></td>
                                    <td>
                                        <span class="details-text"><?php echo htmlspecialchars($log['action_details'] ?? ''); ?></span>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <button type="button" class="btn btn-sm btn-light border" onclick="showFullDetails('<?php echo addslashes($log['action_details']); ?>')">
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox display-4 d-block mb-2"></i>
                                    No activity logs found for the selected criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg">
            <div class="modal-header bg-success bg-gradient text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-info-circle me-2"></i> Log Entry Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2 small fw-bold text-muted text-uppercase">Raw Action Data</div>
                <pre id="detailsContent" class="details-pre" style="white-space: pre-wrap; word-wrap: break-word;"></pre>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showFullDetails(details) {
    document.getElementById('detailsContent').textContent = details || 'No additional details recorded.';
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

function exportLogs() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', '1');
    window.location.href = 'export-logs.php?' + params.toString();
}

// Add some interactivity to the table rows
document.querySelectorAll('#logsTable tbody tr').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', function(e) {
        // Don't trigger if clicking a link or button
        if (e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON' && !e.target.closest('button') && !e.target.closest('a')) {
            const detailsBtn = this.querySelector('button[onclick]');
            if (detailsBtn) detailsBtn.click();
        }
    });
});
</script>

<?php include '../includes/auth_footer.php'; ?>