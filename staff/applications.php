<?php
/**
 * All Applications
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$staff_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query
$query = "
    SELECT a.*, 
           u.full_name as assigned_staff_name,
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count,
           (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') as unread_messages
    FROM applications a
    LEFT JOIN users u ON a.assigned_staff_id = u.user_id
    WHERE 1=1
";

$params = [];
$types = '';

if ($status_filter !== 'all') {
    if ($status_filter === 'in_review') {
        // Special case for "In Review" which combines multiple statuses
        $query .= " AND a.current_status IN ('INITIAL_REVIEW', 'COMPLIANCE_REVIEW', 'UNDER_ETHICAL_REVIEW', 'COMPLIANCE_PENDING', '" . STATUS_CHECKLIST_SUBMITTED . "')";
    } else {
        $query .= " AND a.current_status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }
}

if (!empty($search)) {
    $query .= " AND (a.queue_number LIKE ? OR a.applicant_name LIKE ? OR a.research_title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

$query .= " ORDER BY a.last_updated DESC LIMIT ? OFFSET ?";
$params[] = $per_page;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

// Get total count for pagination
$counts_query = "
    SELECT 
        COUNT(*) as total
    FROM applications
    WHERE 1=1
";

$counts_params = [];
$counts_types = '';

if ($status_filter !== 'all') {
    if ($status_filter === 'in_review') {
        // Special case for "In Review" which combines multiple statuses
        $counts_query .= " AND current_status IN ('INITIAL_REVIEW', 'COMPLIANCE_REVIEW', 'UNDER_ETHICAL_REVIEW', 'COMPLIANCE_PENDING', '" . STATUS_CHECKLIST_SUBMITTED . "')";
    } else {
        $counts_query .= " AND current_status = ?";
        $counts_params[] = $status_filter;
        $counts_types .= 's';
    }
}

if (!empty($search)) {
    $counts_query .= " AND (queue_number LIKE ? OR applicant_name LIKE ? OR research_title LIKE ?)";
    $search_param = "%$search%";
    $counts_params[] = $search_param;
    $counts_params[] = $search_param;
    $counts_params[] = $search_param;
    $counts_types .= 'sss';
}

$counts_stmt = $conn->prepare($counts_query);
if (!empty($counts_params)) {
    $counts_stmt->bind_param($counts_types, ...$counts_params);
}
$counts_stmt->execute();
$total_result = $counts_stmt->get_result();
$total_applications = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_applications / $per_page);

// Get status counts (respecting the same filters as the main query)
$counts_query = "
    SELECT 
        current_status,
        COUNT(*) as count
    FROM applications
    WHERE 1=1
";

$counts_params = [];
$counts_types = '';

if ($status_filter !== 'all') {
    if ($status_filter === 'in_review') {
        // Special case for "In Review" which combines multiple statuses
        $counts_query .= " AND current_status IN ('INITIAL_REVIEW', 'COMPLIANCE_REVIEW', 'UNDER_ETHICAL_REVIEW', 'COMPLIANCE_PENDING')";
    } else {
        $counts_query .= " AND current_status = ?";
        $counts_params[] = $status_filter;
        $counts_types .= 's';
    }
}

if (!empty($search)) {
    $counts_query .= " AND (queue_number LIKE ? OR applicant_name LIKE ? OR research_title LIKE ?)";
    $search_param = "%$search%";
    $counts_params[] = $search_param;
    $counts_params[] = $search_param;
    $counts_params[] = $search_param;
    $counts_types .= 'sss';
}

$counts_query .= " GROUP BY current_status";

$counts_stmt = $conn->prepare($counts_query);
if (!empty($counts_params)) {
    $counts_stmt->bind_param($counts_types, ...$counts_params);
}
$counts_stmt->execute();
$counts_result = $counts_stmt->get_result();

$status_counts = [];
while ($row = $counts_result->fetch_assoc()) {
    $status_counts[$row['current_status']] = $row['count'];
}

closeDBConnection($conn);

$page_title = 'All Applications';
$base_url = '../';
$active_menu = 'applications';
include '../includes/auth_header.php';
?>

<style>
.status-summary-card {
    transition: all 0.3s ease;
}

.status-summary-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
}

.filter-card {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

/* Section Card Design */
.section-card {
    border: none;
    border-radius: 16px;
    background: white;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
    margin-bottom: 1.5rem;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.section-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(0,0,0,0.12);
}

.section-card .card-header {
    background: white;
    border-bottom: 2px solid #f8f9fa;
    padding: 1.5rem 2rem;
    font-weight: 700;
    color: var(--tau-green-dark);
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 1.1rem;
}

.info-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.info-value {
    font-weight: 600;
    color: #333;
    margin-bottom: 0;
}

.clickable-card {
    cursor: pointer;
    transition: all 0.2s ease;
}

.clickable-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 35px rgba(0,0,0,0.15);
}


.list-view-item {
    border: none;
    border-radius: 8px;
    background: white;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    padding: 0.75rem 1rem;
    margin-bottom: 0.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.list-view-item:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
}

.list-view-item .row {
    align-items: center;
}

.list-view-item .queue-info {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.list-view-item .queue-info i {
    color: var(--tau-green-primary);
    font-size: 1.1rem;
}

.list-view-item .queue-details strong {
    color: #333;
    font-size: 0.9rem;
}

.list-view-item .queue-details small {
    color: #666;
    font-size: 0.8rem;
}

.list-view-item .research-title {
    color: #555;
    font-size: 0.85rem;
    line-height: 1.3;
}

.list-view-item .status-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

.list-view-item .actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 0.75rem;
}

.list-view-item .action-buttons {
    display: flex;
    gap: 0.5rem;
}

.list-view-item .assignment-info {
    display: flex;
    gap: 0.5rem;
}

.list-view-item .time-info {
    font-size: 0.8rem;
    color: #888;
    white-space: nowrap;
}

.list-view-item .unread-indicator {
    background: #dc3545;
    color: white;
    font-size: 0.7rem;
    padding: 0.2rem 0.4rem;
    border-radius: 4px;
    font-weight: 600;
}

@media (max-width: 768px) {
    .list-view-item .col-md-3,
    .list-view-item .col-md-4,
    .list-view-item .col-md-2,
    .list-view-item .col-md-3 {
        padding: 0.25rem;
    }

    .list-view-item .queue-info {
        gap: 0.5rem;
    }

    .list-view-item .queue-info i {
        font-size: 1rem;
    }

    .list-view-item .research-title {
        font-size: 0.8rem;
        line-height: 1.2;
    }

    .list-view-item .actions {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
    }

    .list-view-item .action-buttons {
        width: 100%;
        justify-content: space-between;
    }

    .list-view-item .time-info {
        font-size: 0.75rem;
        align-self: flex-end;
    }
}

@media (max-width: 768px) {
    .section-card {
        margin-bottom: 1rem;
    }

    .section-card .card-header {
        padding: 0.5rem !important;
    }

    .section-card .card-body {
        padding: 0.5rem !important;
    }

    .status-summary-card .card-body {
        padding: 0.75rem !important;
    }

    .status-summary-card i {
        font-size: 1.25rem !important;
        margin-bottom: 0.5rem !important;
    }

    .status-summary-card h5 {
        font-size: 1rem !important;
    }
}
</style>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-folder"></i> All Applications
        <?php if ($status_filter !== 'all' || !empty($search)): ?>
            <small class="text-muted">
                <?php if ($status_filter !== 'all'): ?>
                    <span class="badge bg-primary"><?php echo getStatusDisplayName($status_filter); ?></span>
                <?php endif; ?>
                <?php if (!empty($search)): ?>
                    <span class="badge bg-info">"<?php echo htmlspecialchars($search); ?>"</span>
                <?php endif; ?>
            </small>
        <?php endif; ?>
    </h2>

    <!-- Filter Bar -->
    <div class="card border-0 shadow-sm mb-4 filter-card">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-bold">
                        <i class="bi bi-search me-1"></i>Search Applications
                    </label>
                    <input type="text" name="search" class="form-control" placeholder="Queue number, applicant name, or research title..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-bold">
                        <i class="bi bi-funnel me-1"></i>Filter by Status
                    </label>
                    <select name="status" class="form-select">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                        <option value="INTENT_RECEIVED" <?php echo $status_filter === 'INTENT_RECEIVED' ? 'selected' : ''; ?>>Intent Received</option>
                        <option value="INITIAL_REVIEW" <?php echo $status_filter === 'INITIAL_REVIEW' ? 'selected' : ''; ?>>Initial Review</option>
                        <option value="UNDER_STAFF_REVIEW" <?php echo $status_filter === 'UNDER_STAFF_REVIEW' ? 'selected' : ''; ?>>Under Staff Review</option>
                        <option value="COMPLIANCE_REVIEW" <?php echo $status_filter === 'COMPLIANCE_REVIEW' ? 'selected' : ''; ?>>Compliance Review</option>
                        <option value="APPROVED" <?php echo $status_filter === 'APPROVED' ? 'selected' : ''; ?>>Approved</option>
                        <option value="REJECTED" <?php echo $status_filter === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="REVISIONS_REQUIRED" <?php echo $status_filter === 'REVISIONS_REQUIRED' ? 'selected' : ''; ?>>Revisions Required</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn text-white me-2" style="background: linear-gradient(135deg, #006400, #228B22); border: none;">
                        <i class="bi bi-search"></i> Apply Filters
                    </button>
                    <a href="applications.php" class="btn btn-outline-secondary">
                        <i class="bi bi-x-circle"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Status Summary -->
    <div class="section-card mb-4">
        <div class="card-header" style="color: #006400; font-weight: 700;">
            <i class="bi bi-bar-chart"></i>
            Application Status Summary
        </div>
        <div class="card-body">
            <div class="row g-2 g-md-3">
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="all" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-folder text-primary mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #006400; font-size: 1.25rem;"><?php echo array_sum($status_counts); ?></h5>
                                    <small class="text-muted fw-medium">Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="UNDER_STAFF_REVIEW" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-hourglass-split text-warning mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #228B22; font-size: 1.25rem;"><?php echo $status_counts['UNDER_STAFF_REVIEW'] ?? 0; ?></h5>
                                    <small class="text-muted fw-medium">Pending Review</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="in_review" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-search text-info mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #228B22; font-size: 1.25rem;"><?php echo ($status_counts['INITIAL_REVIEW'] ?? 0) + ($status_counts['COMPLIANCE_REVIEW'] ?? 0) + ($status_counts['UNDER_ETHICAL_REVIEW'] ?? 0) + ($status_counts['COMPLIANCE_PENDING'] ?? 0); ?></h5>
                                    <small class="text-muted fw-medium">In Review</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="APPROVED" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-check-circle text-success mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #006400; font-size: 1.25rem;"><?php echo $status_counts['APPROVED'] ?? 0; ?></h5>
                                    <small class="text-muted fw-medium">Approved</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="REVISIONS_REQUIRED" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-arrow-repeat text-warning mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #FFA500; font-size: 1.25rem;"><?php echo $status_counts['REVISIONS_REQUIRED'] ?? 0; ?></h5>
                                    <small class="text-muted fw-medium">Revisions</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-sm-4 col-md-2">
                    <div class="card border-0 shadow-sm text-center status-summary-card h-100 status-filter-card" data-status="REJECTED" style="cursor: pointer; transition: transform 0.2s;">
                        <div class="card-body py-3">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-x-circle text-danger mb-2" style="font-size: 1.5rem;"></i>
                                <div class="text-center">
                                    <h5 class="mb-0 fw-bold" style="color: #DC3545; font-size: 1.25rem;"><?php echo $status_counts['REJECTED'] ?? 0; ?></h5>
                                    <small class="text-muted fw-medium">Rejected</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Applications Grid -->
    <div class="section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2" style="color: #006400; font-weight: 700;">
                <i class="bi bi-grid"></i>
                <span class="fw-bold" >Applications List</span>
                <span class="badge bg-primary"><?php echo $applications->num_rows; ?> applications</span>
            </div>

        </div>
        <div class="card-body">
            <div id="applications-container">
                <?php while ($app = $applications->fetch_assoc()): ?>
                    <div class="list-view-item"
                         data-queue="<?php echo htmlspecialchars($app['queue_number']); ?>"
                         data-status="<?php echo htmlspecialchars($app['current_status']); ?>"
                         data-applicant="<?php echo htmlspecialchars(strtolower($app['applicant_name'])); ?>"
                         data-title="<?php echo htmlspecialchars(strtolower($app['research_title'])); ?>">
                        <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="text-decoration-none text-dark">
                            <div class="row">
                                <div class="col-md-3">
                                    <div class="queue-info">
                                        <i class="bi bi-ticket-detailed"></i>
                                        <div class="queue-details">
                                            <strong><?php echo htmlspecialchars($app['queue_number']); ?></strong>
                                            <br>
                                            <small><?php echo htmlspecialchars($app['applicant_name']); ?></small>
                                            <?php if ($app['unread_messages'] > 0): ?>
                                                <br><span class="unread-indicator"><?php echo $app['unread_messages']; ?> new</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="research-title text-truncate" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                        <?php echo htmlspecialchars(substr($app['research_title'], 0, 60) . (strlen($app['research_title']) > 60 ? '...' : '')); ?>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <span class="badge status-badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?>">
                                        <?php
                                        $short_status = [
                                            'INTENT_RECEIVED' => 'Intent Rcvd',
                                            'REQUIREMENTS_SENT' => 'Req. Sent',
                                            'REQUIREMENTS_PENDING' => 'Req. Pending',
                                            'UNDER_AUTO_REVIEW' => 'Auto Review',
                                            'STAFF_REVIEW_REQUIRED' => 'Staff Review',
                                            'REQUIREMENTS_INCOMPLETE' => 'Incomplete',
                                            'REGISTERED' => 'Registered',
                                            'UNDER_STAFF_REVIEW' => 'Under Review',
                                            'REVISIONS_REQUIRED' => 'Revisions',
                                            'CATEGORIZED' => 'Categorized',
                                            'FORWARDED_TO_UREC' => 'To UREC',
                                            'UNDER_ETHICAL_REVIEW' => 'Ethical Review',
                                            'COMPLIANCE_PENDING' => 'Compliance',
                                            'COMPLIANCE_REVIEW' => 'Compliance Review',
                                            'APPROVED' => 'Approved',
                                            'CERTIFICATE_ISSUED' => 'Certified',
                                            'REJECTED' => 'Rejected'
                                        ];
                                        echo $short_status[$app['current_status']] ?? substr(getStatusDisplayName($app['current_status']), 0, 12) . '...';
                                        ?>
                                    </span>
                                    <?php if ($app['category']): ?>
                                        <br><small class="text-muted"><?php echo ucfirst($app['category']); ?> Review</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <div class="actions">
                                        <div class="action-buttons">
                                            <?php if ($app['assigned_staff_name']): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-person-check"></i> <?php echo htmlspecialchars(substr($app['assigned_staff_name'], 0, 8) . (strlen($app['assigned_staff_name']) > 8 ? '...' : '')); ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-warning">
                                                    <i class="bi bi-person-dash"></i> Unassigned
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="time-info">
                                            <i class="bi bi-clock"></i> <?php echo formatDate($app['last_updated']); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>
                <?php endwhile; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Applications pagination" class="mt-4">
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>" aria-label="Previous">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php 
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=1">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <?php if ($i == $page): ?>
                                <li class="page-item active" aria-current="page">
                                    <span class="page-link"><?php echo $i; ?></span>
                                </li>
                            <?php else: ?>
                                <li class="page-item">
                                    <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>" aria-label="Next">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>

            <?php if ($applications->num_rows == 0): ?>
                <div class="text-center text-muted py-5" id="no-applications">
                    <i class="bi bi-inbox display-4"></i>
                    <p class="mt-3">No applications found matching your criteria.</p>
                    <a href="applications.php" class="btn btn-outline-primary">
                        <i class="bi bi-arrow-counterclockwise"></i> Clear Filters
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// AJAX filtering functionality
document.addEventListener('DOMContentLoaded', function() {
    // Handle status card clicks - use direct filtering for better performance
    document.querySelectorAll('.status-filter-card').forEach(card => {
        card.addEventListener('click', function() {
            const status = this.getAttribute('data-status');
            filterCards(status, '');
            updateURL(status, '');
        });
    });

    // Handle search form submission
    const filterForm = document.querySelector('form[method="GET"]');
    if (filterForm) {
        filterForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const statusSelect = document.querySelector('select[name="status"]');
            const searchInput = document.querySelector('input[name="search"]');
            const status = statusSelect ? statusSelect.value : 'all';
            const search = searchInput ? searchInput.value : '';
            applyFilter(status, search);
        });
    }
});

function applyFilter(status, search) {
    // For status filtering, filter existing cards directly
    if (status !== 'all' && !search) {
        filterCards(status, '');
        updateURL(status, '');
        return;
    }

    // For search or complex filters, use AJAX
    // Update URL without page reload
    const url = new URL(window.location);
    if (status && status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    window.history.pushState({}, '', url);

    // Make AJAX request for search functionality
    fetch('ajax-applications.php?' + url.searchParams.toString())
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text();
        })
        .then(text => {
            try {
                const data = JSON.parse(text);
                if (data.success === false) {
                    throw new Error(data.error || 'Server error occurred');
                }
                updateApplications(data);
                updateStatusCounts(data.status_counts);
            } catch (e) {
                if (text.includes('login') || text.includes('Login') || text.includes('Staff Login') || text.includes('<form') || text.includes('username') || text.includes('password')) {
                    throw new Error('Authentication required. Please refresh the page and log in again.');
                } else {
                    console.log('Response text (first 500 chars):', text.substring(0, 500));
                    throw new Error('Invalid response from server');
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            const container = document.getElementById('applications-container');
            if (container) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'alert alert-danger mt-3';
                errorDiv.innerHTML = `
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error loading applications: ${error.message}
                    <button class="btn btn-sm btn-outline-danger ms-2" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                `;
                container.insertBefore(errorDiv, container.firstChild);
                setTimeout(() => errorDiv.remove(), 5000);
            }
        });
}

function filterCards(status, search) {
    const cards = document.querySelectorAll('.application-card, .list-view-item');
    let visibleCount = 0;

    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status');
        const cardApplicant = card.getAttribute('data-applicant') || '';
        const cardTitle = card.getAttribute('data-title') || '';
        const cardQueue = card.getAttribute('data-queue') || '';

        let showCard = true;

        // Status filter
        if (status !== 'all') {
            if (status === 'in_review') {
                showCard = ['INITIAL_REVIEW', 'COMPLIANCE_REVIEW', 'UNDER_ETHICAL_REVIEW', 'COMPLIANCE_PENDING'].includes(cardStatus);
            } else {
                showCard = cardStatus === status;
            }
        }

        // Search filter (basic client-side search)
        if (search && showCard) {
            const searchLower = search.toLowerCase();
            showCard = cardApplicant.includes(searchLower) ||
                      cardTitle.includes(searchLower) ||
                      cardQueue.includes(searchLower);
        }

        if (showCard) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    // Update the badge count
    const badge = document.querySelector('.badge.bg-primary');
    if (badge) {
        badge.textContent = visibleCount + ' applications';
    }

    // Show "no results" message if needed
    const noResults = document.getElementById('no-applications');
    if (noResults) {
        if (visibleCount === 0) {
            noResults.style.display = 'block';
        } else {
            noResults.style.display = 'none';
        }
    }
}

function updateURL(status, search) {
    const url = new URL(window.location);
    if (status && status !== 'all') {
        url.searchParams.set('status', status);
    } else {
        url.searchParams.delete('status');
    }
    if (search) {
        url.searchParams.set('search', search);
    } else {
        url.searchParams.delete('search');
    }
    window.history.pushState({}, '', url);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function updateApplications(data) {
    const container = document.getElementById('applications-container');
    if (!container) {
        console.error('Applications container not found');
        return;
    }

    if (data.applications.length === 0) {
        container.innerHTML = `
            <div class="text-center text-muted py-5" id="no-applications">
                <i class="bi bi-inbox display-4"></i>
                <p class="mt-3">No applications found matching your criteria.</p>
                <button class="btn btn-outline-primary" onclick="clearFilters()">
                    <i class="bi bi-arrow-counterclockwise"></i> Clear Filters
                </button>
            </div>
        `;
        return;
    }

    let html = '<div class="row g-4" id="applications-grid" style="opacity: 0; transition: opacity 0.3s ease;">';

    data.applications.forEach(app => {
        const statusBadgeClass = getStatusBadgeClass(app.current_status);
        const shortStatus = getShortStatus(app.current_status);

        html += `
            <div class="col-xl-4 col-lg-6 col-md-6 application-card"
                 data-queue="${escapeHtml(app.queue_number)}"
                 data-status="${escapeHtml(app.current_status)}"
                 data-applicant="${escapeHtml(app.applicant_name.toLowerCase())}"
                 data-title="${escapeHtml(app.research_title.toLowerCase())}">
                <a href="view-application.php?queue=${encodeURIComponent(app.queue_number)}" class="text-decoration-none">
                    <div class="section-card h-100 clickable-card">
                        <div class="card-header" style="background: linear-gradient(135deg, #006400, #228B22); color: white; border: none;">
                            <div class="row g-2 align-items-start">
                                <div class="col-7">
                                    <div class="info-label opacity-75 mb-1" style="color: rgba(255,255,255,0.8); font-size: 0.7rem;">
                                        <i class="bi bi-ticket-detailed me-1"></i>${escapeHtml(app.queue_number)}
                                    </div>
                                    <small class="opacity-75" style="font-size: 0.75rem;">
                                        <i class="bi bi-calendar-event me-1"></i>${app.formatted_date}
                                    </small>
                                </div>
                                <div class="col-5 text-end">
                                    <span class="badge bg-${statusBadgeClass} mb-1 small text-truncate d-block" style="max-width: 100%; font-size: 0.7rem;" title="${escapeHtml(app.status_display)}">
                                        ${shortStatus}
                                    </span>
                                    ${app.category ? `<small class="badge bg-light text-dark small d-block" style="font-size: 0.65rem;">${escapeHtml(app.category.charAt(0).toUpperCase() + app.category.slice(1))} Review</small>` : ''}
                                    ${app.unread_messages > 0 ? `<small class="badge bg-danger small d-block mt-1" style="font-size: 0.6rem;"><i class="bi bi-envelope-exclamation me-1"></i>${app.unread_messages} new</small>` : ''}
                                </div>
                            </div>
                        </div>

                        <div class="card-body p-3">
                            <!-- Applicant Info -->
                            <div class="mb-3">
                                <div class="info-label">Applicant</div>
                                <div class="info-value small mb-1">${escapeHtml(app.applicant_name)}</div>
                                <div class="small text-muted">
                                    <i class="bi bi-envelope me-1"></i>${escapeHtml(app.applicant_email)}
                                </div>
                                <div class="small text-muted">
                                    <i class="bi bi-person me-1"></i>${escapeHtml(app.applicant_type.charAt(0).toUpperCase() + app.applicant_type.slice(1))}
                                </div>
                            </div>

                            <!-- Research Title -->
                            <div class="mb-3">
                                <div class="info-label">Research Title</div>
                                <div class="info-value small text-truncate" title="${escapeHtml(app.research_title)}" style="max-width: 100%;">
                                    ${escapeHtml(app.research_title.length > 50 ? app.research_title.substring(0, 50) + '...' : app.research_title)}
                                </div>
                            </div>

                            <!-- Status & Assignment -->
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="text-center p-2 bg-light rounded small">
                                        <i class="bi bi-person-check d-block text-primary mb-1" style="font-size: 1rem;"></i>
                                        <div class="info-label text-center mb-1" style="font-size: 0.65rem;">Assigned</div>
                                        ${app.assigned_staff_name ? `<div class="fw-bold text-truncate small" style="max-width: 100px; margin: 0 auto;" title="${escapeHtml(app.assigned_staff_name)}">${escapeHtml(app.assigned_staff_name.length > 10 ? app.assigned_staff_name.substring(0, 10) + '...' : app.assigned_staff_name)}</div>` : '<div class="text-warning small">Unassigned</div>'}
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="text-center p-2 bg-light rounded small">
                                        <i class="bi bi-file-earmark d-block text-info mb-1" style="font-size: 1rem;"></i>
                                        <div class="info-label text-center mb-1" style="font-size: 0.65rem;">Documents</div>
                                        <span class="badge bg-info small">${app.doc_count}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Messages & Updates -->
                            <div class="d-flex justify-content-between align-items-center small mb-3">
                                <div>
                                    ${app.unread_messages > 0 ? `<small class="text-danger"><i class="bi bi-envelope-exclamation me-1"></i>${app.unread_messages} unread messages</small>` : '<small class="text-muted"><i class="bi bi-envelope me-1"></i>No new messages</small>'}
                                </div>
                                <small class="text-muted">
                                    <i class="bi bi-clock me-1"></i>${app.last_updated_formatted}
                                </small>
                            </div>

                            <!-- Additional Info -->
                            ${(app.has_additional_requirements || app.completion_attempts > 0) ? `
                                <div class="mb-3">
                                    ${app.has_additional_requirements ? '<span class="badge bg-warning text-dark me-1 small"><i class="bi bi-exclamation-triangle me-1"></i>Additional Req.</span>' : ''}
                                    ${app.completion_attempts > 0 ? `<span class="badge bg-secondary small"><i class="bi bi-arrow-repeat me-1"></i>${app.completion_attempts} attempts</span>` : ''}
                                </div>
                            ` : ''}
                        </div>
                    </div>
                </a>
            </div>

            <!-- List View Item -->
            <div class="list-view-item"
                 data-queue="${escapeHtml(app.queue_number)}"
                 data-status="${escapeHtml(app.current_status)}"
                 data-applicant="${escapeHtml(app.applicant_name.toLowerCase())}"
                 data-title="${escapeHtml(app.research_title.toLowerCase())}">
                <a href="view-application.php?queue=${encodeURIComponent(app.queue_number)}" class="text-decoration-none text-dark">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="queue-info">
                                <i class="bi bi-ticket-detailed"></i>
                                <div class="queue-details">
                                    <strong>${escapeHtml(app.queue_number)}</strong>
                                    <br>
                                    <small>${escapeHtml(app.applicant_name)}</small>
                                    ${app.unread_messages > 0 ? `<br><span class="unread-indicator">${app.unread_messages} new</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="research-title text-truncate" title="${escapeHtml(app.research_title)}">
                                ${escapeHtml(app.research_title.length > 60 ? app.research_title.substring(0, 60) + '...' : app.research_title)}
                            </div>
                        </div>
                        <div class="col-md-2">
                            <span class="badge status-badge bg-${statusBadgeClass}">
                                ${shortStatus}
                            </span>
                            ${app.category ? `<br><small class="text-muted">${escapeHtml(app.category.charAt(0).toUpperCase() + app.category.slice(1))} Review</small>` : ''}
                        </div>
                        <div class="col-md-3">
                            <div class="actions">
                                <div class="assignment-info">
                                    ${app.assigned_staff_name ? `<small class="text-muted"><i class="bi bi-person-check"></i> ${escapeHtml(app.assigned_staff_name.length > 8 ? app.assigned_staff_name.substring(0, 8) + '...' : app.assigned_staff_name)}</small>` : '<small class="text-warning"><i class="bi bi-person-dash"></i> Unassigned</small>'}
                                </div>
                                <div class="time-info">
                                    <i class="bi bi-clock"></i> ${app.last_updated_formatted}
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;

    // Fade in the new content
    setTimeout(() => {
        const grid = document.getElementById('applications-grid');
        if (grid) {
            grid.style.opacity = '1';
        }
    }, 50);
}

function updateStatusCounts(counts) {
    // Update the status summary numbers with null checks
    const allElement = document.querySelector('[data-status="all"] h5');
    if (allElement) allElement.textContent = Object.values(counts).reduce((a, b) => a + b, 0);

    const staffReviewElement = document.querySelector('[data-status="UNDER_STAFF_REVIEW"] h5');
    if (staffReviewElement) staffReviewElement.textContent = counts.UNDER_STAFF_REVIEW || 0;

    const inReviewElement = document.querySelector('[data-status="in_review"] h5');
    if (inReviewElement) inReviewElement.textContent = (counts.INITIAL_REVIEW || 0) + (counts.COMPLIANCE_REVIEW || 0) + (counts.UNDER_ETHICAL_REVIEW || 0) + (counts.COMPLIANCE_PENDING || 0);

    const approvedElement = document.querySelector('[data-status="APPROVED"] h5');
    if (approvedElement) approvedElement.textContent = counts.APPROVED || 0;

    const revisionsElement = document.querySelector('[data-status="REVISIONS_REQUIRED"] h5');
    if (revisionsElement) revisionsElement.textContent = counts.REVISIONS_REQUIRED || 0;

    const rejectedElement = document.querySelector('[data-status="REJECTED"] h5');
    if (rejectedElement) rejectedElement.textContent = counts.REJECTED || 0;
}

function clearFilters() {
    filterCards('all', '');
    updateURL('all', '');
}

function getStatusBadgeClass(status) {
    const classes = {
        'APPROVED': 'success',
        'REJECTED': 'danger',
        'REVISIONS_REQUIRED': 'warning',
        'UNDER_STAFF_REVIEW': 'warning',
        'INITIAL_REVIEW': 'info',
        'COMPLIANCE_REVIEW': 'info',
        'UNDER_ETHICAL_REVIEW': 'info',
        'COMPLIANCE_PENDING': 'info'
    };
    return classes[status] || 'secondary';
}

function getShortStatus(status) {
    const shortStatuses = {
        'INTENT_RECEIVED': 'Intent Rcvd',
        'REQUIREMENTS_SENT': 'Req. Sent',
        'REQUIREMENTS_PENDING': 'Req. Pending',
        'UNDER_AUTO_REVIEW': 'Auto Review',
        'STAFF_REVIEW_REQUIRED': 'Staff Review',
        'REQUIREMENTS_INCOMPLETE': 'Incomplete',
        'REGISTERED': 'Registered',
        'UNDER_STAFF_REVIEW': 'Under Review',
        'REVISIONS_REQUIRED': 'Revisions',
        'CATEGORIZED': 'Categorized',
        'FORWARDED_TO_UREC': 'To UREC',
        'UNDER_ETHICAL_REVIEW': 'Ethical Review',
        'COMPLIANCE_PENDING': 'Compliance',
        'COMPLIANCE_REVIEW': 'Compliance Review',
        'APPROVED': 'Approved',
        'CERTIFICATE_ISSUED': 'Certified',
        'REJECTED': 'Rejected'
    };
    return shortStatuses[status] || status.substring(0, 12) + '...';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}


</script>

<?php include '../includes/auth_footer.php'; ?>
