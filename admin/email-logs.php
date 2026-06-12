<?php
/**
 * Email Logs Viewer
 * TAU-UREO Portal - Admin Only
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
$date_from = $_GET['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$status_filter = $_GET['status'] ?? '';
$limit = (int)($_GET['limit'] ?? 100);

// Build query
$query = "
    SELECT el.*, a.applicant_name, a.applicant_email
    FROM email_logs el
    LEFT JOIN applications a ON el.queue_number = a.queue_number
    WHERE DATE(el.sent_at) BETWEEN ? AND ?
";
$params = [$date_from, $date_to];
$types = "ss";

if ($status_filter) {
    $query .= " AND el.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$query .= " ORDER BY el.sent_at DESC LIMIT ?";
$params[] = $limit;
$types .= "i";

$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$logs = $stmt->get_result();

// Get statistics
$stats = [];
$stats['total_sent'] = $conn->query("SELECT COUNT(*) as count FROM email_logs WHERE status = 'sent'")->fetch_assoc()['count'];
$stats['total_failed'] = $conn->query("SELECT COUNT(*) as count FROM email_logs WHERE status = 'failed'")->fetch_assoc()['count'];
$stats['today_sent'] = $conn->query("SELECT COUNT(*) as count FROM email_logs WHERE status = 'sent' AND DATE(sent_at) = CURDATE()")->fetch_assoc()['count'];
$stats['success_rate'] = $stats['total_sent'] + $stats['total_failed'] > 0 
    ? round(($stats['total_sent'] / ($stats['total_sent'] + $stats['total_failed'])) * 100, 1)
    : 0;

closeDBConnection($conn);

$page_title = 'Email Logs';
$base_url = '../';
$active_menu = 'email-logs';
include '../includes/auth_header.php';
?>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-envelope-check"></i> Email Logs
    </h2>

    <!-- Statistics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-envelope-check-fill display-4 text-success"></i>
                    <h3 class="mt-2"><?php echo number_format($stats['total_sent']); ?></h3>
                    <small class="text-muted">Total Sent</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-envelope-x-fill display-4 text-danger"></i>
                    <h3 class="mt-2"><?php echo number_format($stats['total_failed']); ?></h3>
                    <small class="text-muted">Failed</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-calendar-check display-4" style="color: #006400;"></i>
                    <h3 class="mt-2"><?php echo number_format($stats['today_sent']); ?></h3>
                    <small class="text-muted">Sent Today</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up display-4" style="color: #228B22;"></i>
                    <h3 class="mt-2"><?php echo $stats['success_rate']; ?>%</h3>
                    <small class="text-muted">Success Rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="sent" <?php echo $status_filter === 'sent' ? 'selected' : ''; ?>>Sent</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Limit</label>
                    <select name="limit" class="form-select">
                        <option value="50" <?php echo $limit == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $limit == 100 ? 'selected' : ''; ?>>100</option>
                        <option value="500" <?php echo $limit == 500 ? 'selected' : ''; ?>>500</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-success w-100">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Email Logs Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Sent At</th>
                            <th>Recipient</th>
                            <th>Subject</th>
                            <th>Queue</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($log = $logs->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $log['email_id'] ?? 'N/A'; ?></td>
                                <td>
                                    <small><?php echo date('M d, Y', strtotime($log['sent_at'] ?? '')); ?></small><br>
                                    <small class="text-muted"><?php echo date('h:i:s A', strtotime($log['sent_at'] ?? '')); ?></small>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($log['recipient_email'] ?? ''); ?></strong>
                                    <?php if ($log['applicant_name'] ?? ''): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($log['applicant_name']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars(substr($log['subject'] ?? '', 0, 50)); ?></td>
                                <td>
                                    <?php if ($log['queue_number'] ?? ''): ?>
                                        <a href="../staff/view-application.php?queue=<?php echo urlencode($log['queue_number']); ?>" target="_blank">
                                            <?php echo htmlspecialchars($log['queue_number']); ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (($log['status'] ?? '') === 'sent'): ?>
                                        <span class="badge bg-success">Sent</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Failed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="viewEmail(<?php echo $log['email_id'] ?? 0; ?>)">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Email View Modal -->
<div class="modal fade" id="emailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white;">
                <h5 class="modal-title">Email Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="emailContent">
                <div class="text-center">
                    <div class="spinner-border text-success" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function viewEmail(emailId) {
    const modal = new bootstrap.Modal(document.getElementById('emailModal'));
    modal.show();
    
    fetch('get-email-details.php?id=' + emailId)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Invalid JSON response:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(data => {
            if (data.success) {
                const email = data.email;
                document.getElementById('emailContent').innerHTML = `
                    <table class="table table-bordered">
                        <tr>
                            <th width="150">Recipient:</th>
                            <td>${email.recipient_email || 'N/A'}</td>
                        </tr>
                        <tr>
                            <th>Subject:</th>
                            <td>${email.subject || 'N/A'}</td>
                        </tr>
                        <tr>
                            <th>Sent At:</th>
                            <td>${email.sent_at || 'N/A'}</td>
                        </tr>
                        <tr>
                            <th>Status:</th>
                            <td><span class="badge ${email.status === 'sent' ? 'bg-success' : 'bg-danger'}">${email.status || 'unknown'}</span></td>
                        </tr>
                        ${email.error_message ? `
                        <tr>
                            <th>Error:</th>
                            <td class="text-danger">${email.error_message}</td>
                        </tr>` : ''}
                    </table>
                    <div class="mt-3">
                        <strong>Email Body:</strong>
                        <div class="border p-3 mt-2" style="max-height: 400px; overflow-y: auto; background: #f8f9fa;">
                            ${email.body_html || '<em class="text-muted">No content stored (email sent before body logging was enabled)</em>'}
                        </div>
                    </div>
                `;
            } else {
                document.getElementById('emailContent').innerHTML = '<div class="alert alert-danger">Failed to load email details: ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('emailContent').innerHTML = '<div class="alert alert-danger">An error occurred: ' + error.message + '</div>';
        });
}
</script>

<?php include '../includes/auth_footer.php'; ?>
