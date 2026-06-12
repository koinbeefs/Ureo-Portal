<?php
/**
 * Review Queue
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$staff_id = $_SESSION['user_id'];
$conn = getDBConnection();

// Pagination setup
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

// Get total count for pagination
$count_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM applications a
    WHERE a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED')
");
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_applications = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_applications / $per_page);

// Get applications requiring review
$apps_stmt = $conn->prepare("
    SELECT a.*,
           u.full_name as assigned_staff_name,
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count,
           (SELECT COUNT(*) FROM messages WHERE queue_number = a.queue_number AND read_status = 0 AND sender_type = 'applicant') as unread_messages,
           DATEDIFF(NOW(), a.submission_timestamp) as days_pending
    FROM applications a
    LEFT JOIN users u ON a.assigned_staff_id = u.user_id
    WHERE a.current_status NOT IN ('APPROVED', 'REJECTED', 'CERTIFICATE_ISSUED', 'CLOSED')
    ORDER BY a.submission_timestamp ASC
    LIMIT ? OFFSET ?
");
$apps_stmt->bind_param("ii", $per_page, $offset);
$apps_stmt->execute();
$applications = $apps_stmt->get_result();

// Get stats for current page
$temp_apps = $applications->fetch_all(MYSQLI_ASSOC);
$urgent_count = 0;
$total_days = 0;
foreach ($temp_apps as $app) {
    if ($app['days_pending'] > 7) $urgent_count++;
    $total_days += $app['days_pending'];
}
$avg_days = count($temp_apps) > 0 ? round($total_days / count($temp_apps), 1) : 0;

closeDBConnection($conn);

$page_title = 'Review Queue';
$base_url = '../';
$active_menu = 'review';
include '../includes/auth_header.php';
?>

<style>
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

.review-card {
    transition: all 0.3s ease;
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.review-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
}

.review-card.urgent {
    border-left: 4px solid #DC3545 !important;
    background: linear-gradient(135deg, rgba(220, 53, 69, 0.05), rgba(220, 53, 69, 0.02));
}

.review-card.high-priority {
    border-left: 4px solid #FFC107 !important;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.05), rgba(255, 193, 7, 0.02));
}

.review-card .card-header {
    border-radius: 0.375rem 0.375rem 0 0 !important;
}

.review-card .card-footer {
    border-radius: 0 0 0.375rem 0.375rem !important;
}

.stats-card {
    transition: all 0.3s ease;
}

.stats-card:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1) !important;
}

.priority-badge {
    position: absolute;
    top: -8px;
    right: 10px;
    z-index: 10;
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

    .review-card {
        margin-bottom: 1rem;
    }

    .review-card .card-header {
        padding: 0.5rem !important;
    }

    .review-card .card-body {
        padding: 0.5rem !important;
    }

    .priority-badge {
        top: -6px;
        right: 8px;
        font-size: 0.65rem;
    }
}
</style>

<div class="container-fluid py-4">
    <h2 class="mb-4" style="color: #006400; font-weight: 600;">
        <i class="bi bi-list-check"></i> Review Queue
    </h2>

    <!-- Queue Statistics -->
    <div class="section-card mb-4" style="color: #006400; font-weight: 700;">
        <div class="card-header">
            <i class="bi bi-bar-chart"></i> Queue Statistics
        </div>
        <div class="card-body p-4">
            <div class="alert alert-info border-0 shadow-sm mb-3">
                <i class="bi bi-info-circle"></i> Applications are sorted by submission date (oldest first). Priority is given to applications with longer wait times.
            </div>
            <div class="row g-3">
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-hourglass-split text-primary"></i>
                                <h5 class="text-primary"><?php echo count($temp_apps); ?></h5>
                                <small class="text-muted fw-medium">Total in Queue</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-exclamation-triangle-fill text-danger"></i>
                                <h5 class="text-danger"><?php echo $urgent_count; ?></h5>
                                <small class="text-muted fw-medium">Urgent (>7 days)</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card h-100 border rounded">
                        <div class="card-body">
                            <div class="d-flex flex-column align-items-center">
                                <i class="bi bi-clock-history text-info"></i>
                                <h5 class="text-info"><?php echo $avg_days; ?> days</h5>
                                <small class="text-muted fw-medium">Avg. Wait Time</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Review Queue -->
    <div class="section-card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2" style="color: #006400; font-weight: 700;">
                <i class="bi bi-list-check"></i>
                <span class="fw-bold">Review Queue</span>
                <span class="badge bg-primary"><?php echo count($temp_apps); ?> applications</span>
            </div>

        </div>
        <div class="card-body">
            <div id="review-queue-container">
                <?php if (count($temp_apps) > 0): ?>
                    <?php foreach ($temp_apps as $index => $app):
                        $priority_class = '';
                        $priority_badge = '';
                        if ($app['days_pending'] > 7) {
                            $priority_class = 'urgent';
                            $priority_badge = '<span class="badge bg-danger priority-badge">URGENT</span>';
                        } elseif ($app['days_pending'] > 3) {
                            $priority_class = 'high-priority';
                            $priority_badge = '<span class="badge bg-warning priority-badge">HIGH</span>';
                        } else {
                            $priority_badge = '<span class="badge bg-secondary priority-badge">NORMAL</span>';
                        }
                    ?>
                        <div class="list-view-item">
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
                                            <div class="assignment-info">
                                                <?php if ($app['assigned_staff_name']): ?>
                                                    <small class="text-muted"><i class="bi bi-person-check"></i> <?php echo htmlspecialchars(substr($app['assigned_staff_name'], 0, 8) . (strlen($app['assigned_staff_name']) > 8 ? '...' : '')); ?></small>
                                                <?php else: ?>
                                                    <small class="text-warning"><i class="bi bi-person-dash"></i> Unassigned</small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="time-info">
                                                <i class="bi bi-clock"></i> <?php echo $app['days_pending']; ?> days
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Review queue pagination" class="mt-4">
                            <ul class="pagination justify-content-center mb-0">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>" aria-label="Previous">
                                            <span aria-hidden="true">&laquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                 
                                <?php 
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                 
                                if ($start_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=1">1</a>
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
                                            <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
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
                                        <a class="page-link" href="?page=<?php echo $total_pages; ?>"><?php echo $total_pages; ?></a>
                                    </li>
                                <?php endif; ?>
                                 
                                <?php if ($page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>" aria-label="Next">
                                            <span aria-hidden="true">&raquo;</span>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center text-muted py-5" id="no-applications">
                        <i class="bi bi-check-circle display-4 text-success"></i>
                        <p class="mt-3 h5">All applications have been reviewed!</p>
                        <p class="text-muted">No applications are currently waiting for review.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>



<?php include '../includes/auth_footer.php'; ?>
