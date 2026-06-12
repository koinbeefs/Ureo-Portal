<?php
/**
 * AJAX Applications Endpoint
 * Returns filtered applications data in JSON format
 */

try {
    require_once '../config/config.php';
    require_once '../includes/functions.php';

    requireLogin();
    checkSessionTimeout();

    header('Content-Type: application/json');

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
        $query .= " AND a.current_status IN ('INITIAL_REVIEW', 'COMPLIANCE_REVIEW', 'UNDER_ETHICAL_REVIEW', 'COMPLIANCE_PENDING')";
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

$query .= " ORDER BY a.last_updated DESC LIMIT 100";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$applications = $stmt->get_result();

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

// Format applications data
$apps_data = [];
while ($app = $applications->fetch_assoc()) {
    $apps_data[] = [
        'queue_number' => $app['queue_number'],
        'applicant_name' => $app['applicant_name'],
        'applicant_email' => $app['applicant_email'],
        'applicant_type' => $app['applicant_type'],
        'research_title' => $app['research_title'],
        'current_status' => $app['current_status'],
        'status_display' => getStatusDisplayName($app['current_status']),
        'category' => $app['category'],
        'formatted_date' => formatDate($app['submission_timestamp']),
        'time_ago' => timeAgo($app['last_updated']),
        'last_updated_formatted' => formatDate($app['last_updated']),
        'doc_count' => $app['doc_count'],
        'unread_messages' => $app['unread_messages'],
        'assigned_staff_id' => $app['assigned_staff_id'],
        'assigned_staff_name' => $app['assigned_staff_name'],
        'has_additional_requirements' => $app['has_additional_requirements'],
        'completion_attempts' => $app['completion_attempts']
    ];
}

    closeDBConnection($conn);

    // Return JSON response
    echo json_encode([
        'success' => true,
        'applications' => $apps_data,
        'status_counts' => $status_counts,
        'total_count' => count($apps_data)
    ]);

} catch (Exception $e) {
    // Return JSON error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>