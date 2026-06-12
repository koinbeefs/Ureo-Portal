<?php
declare(strict_types=1);
/**
 * UREC All Applications
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user_id = $_SESSION['user_id'];
$committee_id = $_SESSION['committee_id'] ?? null;
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);

$conn = getDBConnection();

$status_filter = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';

// Build query - restrict to committee if ID is set
$query = "
    SELECT a.*, 
           u.full_name as evaluator_name,
           (SELECT COUNT(*) FROM documents WHERE queue_number = a.queue_number) as doc_count
    FROM applications a
    LEFT JOIN users u ON a.urec_reviewed_by = u.user_id
    WHERE 1=1
";

$params = [];
$types = '';

// Only show applications that have reached UREC stage
$urec_statuses = [
    STATUS_FORWARDED_TO_UREC,
    STATUS_ASSIGNING_UREC_EVALUATOR,
    STATUS_UNDER_ETHICAL_REVIEW,
    STATUS_COMPLIANCE_PENDING,
    STATUS_COMPLIANCE_REVIEW,
    STATUS_UREC_REVIEW_REQUIRED,
    STATUS_APPROVED,
    STATUS_REJECTED
];

if (!$is_chairperson) {
    // Regular members only see what's assigned to them OR what is in ethical review in their committee
    $query .= " AND (a.urec_reviewed_by = ? OR (a.urec_committee_id = ? AND a.current_status IN ('" . implode("','", $urec_statuses) . "')))";
    $params[] = $user_id;
    $params[] = $committee_id;
    $types .= 'ii';
} else {
    // Chairpersons see everything in their committee
    if ($committee_id) {
        $query .= " AND a.urec_committee_id = ?";
        $params[] = $committee_id;
        $types .= 'i';
    }
}

// Apply filters
if ($status_filter !== 'all') {
    $query .= " AND a.current_status = ?";
    $params[] = $status_filter;
    $types .= 's';
}

if (!empty($search)) {
    $query .= " AND (a.queue_number LIKE ? OR a.applicant_name LIKE ? OR a.research_title LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

$query .= " ORDER BY a.last_updated DESC";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

closeDBConnection($conn);

$page_title = 'All Applications';
$active_menu = 'applications';
require_once __DIR__ . '/../includes_urec/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold text-dark"><i class="bi bi-folder2-open me-2 text-success"></i> All Applications</h2>
        <div class="d-flex gap-2">
            <form method="GET" class="d-flex gap-2">
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>">
                <select name="status" class="form-select form-select-sm">
                    <option value="all">All Status</option>
                    <option value="<?php echo STATUS_FORWARDED_TO_UREC; ?>" <?php echo $status_filter === STATUS_FORWARDED_TO_UREC ? 'selected' : ''; ?>>Forwarded</option>
                    <option value="<?php echo STATUS_UNDER_ETHICAL_REVIEW; ?>" <?php echo $status_filter === STATUS_UNDER_ETHICAL_REVIEW ? 'selected' : ''; ?>>In Review</option>
                    <option value="<?php echo STATUS_APPROVED; ?>" <?php echo $status_filter === STATUS_APPROVED ? 'selected' : ''; ?>>Approved</option>
                </select>
                <button type="submit" class="btn btn-sm btn-success px-3">Filter</button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <?php if ($applications->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light text-muted small text-uppercase">
                            <tr>
                                <th class="ps-4">Reference No.</th>
                                <th>Research Title</th>
                                <th>Applicant</th>
                                <th>Status</th>
                                <th>Evaluator</th>
                                <th class="pe-4 text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($app = $applications->fetch_assoc()): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark small"><?php echo htmlspecialchars($app['queue_number']); ?></div>
                                        <small class="text-muted"><?php echo formatDate($app['last_updated']); ?></small>
                                    </td>
                                    <td>
                                        <div class="small fw-bold text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($app['research_title']); ?>">
                                            <?php echo htmlspecialchars($app['research_title']); ?>
                                        </div>
                                        <?php if ($app['category']): ?>
                                            <span class="badge bg-light text-dark border-0 x-small" style="font-size: 0.65rem;">
                                                <?php echo strtoupper($app['category']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="small"><?php echo htmlspecialchars($app['applicant_name']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo getStatusBadgeClass($app['current_status']); ?> small">
                                            <?php echo getStatusDisplayName($app['current_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($app['evaluator_name']): ?>
                                            <div class="small"><i class="bi bi-person-check-fill text-success me-1"></i><?php echo htmlspecialchars($app['evaluator_name']); ?></div>
                                        <?php else: ?>
                                            <small class="text-warning"><i class="bi bi-person-dash me-1"></i>Unassigned</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="pe-4 text-end">
                                        <a href="view-application.php?queue=<?php echo urlencode($app['queue_number']); ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="bi bi-inbox text-muted display-4 mb-3"></i>
                    <p class="text-muted">No applications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes_urec/footer.php'; ?>
