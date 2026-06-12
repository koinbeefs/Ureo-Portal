<?php
/**
 * Applicant Category Form Interface
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$queue_number = $_GET['queue'] ?? '';
$token = $_GET['token'] ?? '';

if (empty($queue_number) || empty($token)) {
    header("HTTP/1.0 400 Bad Request");
    echo '<h1>Invalid Request</h1><p>Missing required parameters.</p>';
    exit();
}

$conn = getDBConnection();

// Validate token
$token_stmt = $conn->prepare("
    SELECT ff.form_data, a.applicant_name, a.applicant_email, a.research_title 
    FROM fillable_forms ff 
    JOIN applications a ON ff.queue_number = a.queue_number 
    WHERE ff.queue_number = ? AND ff.form_type = 'category_token' AND JSON_EXTRACT(ff.form_data, '$.token') = ? AND JSON_EXTRACT(ff.form_data, '$.expires_at') > NOW()
");
$token_stmt->bind_param("ss", $queue_number, $token);
$token_stmt->execute();
$token_result = $token_stmt->get_result();

if ($token_result->num_rows === 0) {
    header("HTTP/1.0 403 Forbidden");
    echo '<h1>Access Denied</h1><p>Invalid or expired token.</p>';
    exit();
}

$token_data = $token_result->fetch_assoc();
$token_info = json_decode($token_data['form_data'], true);

// Mark token as accessed if not already done
if (!isset($token_info['accessed_at'])) {
    $token_info['accessed_at'] = date('Y-m-d H:i:s');
    $access_stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ? WHERE queue_number = ? AND form_type = 'category_token'");
    $access_stmt->bind_param("ss", json_encode($token_info), $queue_number);
    $access_stmt->execute();
}

// Get category form data
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_form'");
$form_stmt->bind_param("s", $queue_number);
$form_stmt->execute();
$category_form_result = $form_stmt->get_result()->fetch_assoc();

if (!$category_form_result) {
    echo '<h1>Form Not Found</h1><p>Category form data not found.</p>';
    exit();
}

$category_form_data = json_decode($category_form_result['form_data'], true);
$category = $category_form_data['category'] ?? $token_info['category'] ?? 'human';
$review_type = $category_form_data['review_type'] ?? $token_info['review_type'] ?? 'expedited';

closeDBConnection($conn);

// Get category-specific form template
$form_template_path = "../assets/to_send/for_reply_to_categories/for_reply_to_" . $category . "/TAU-REO-" . strtoupper($category) . "-checklist.php";

if (!file_exists($form_template_path)) {
    echo '<h1>Form Template Not Found</h1><p>Category form template not available.</p>';
    exit();
}

$page_title = 'Complete ' . ucfirst($category) . ' Category Forms';
$base_url = '../';
$active_menu = '';
$is_modal = false;

include '../includes/applicant_header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="bi bi-clipboard-check me-2"></i>
                        <?php echo ucfirst($category); ?> Category Forms
                    </h4>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2"></i>
                            Application Information
                        </h6>
                        <p class="mb-1">
                            <strong>Queue Number:</strong> <?php echo htmlspecialchars($queue_number); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Applicant:</strong> <?php echo htmlspecialchars($token_data['applicant_name']); ?>
                        </p>
                        <p class="mb-1">
                            <strong>Research Title:</strong> <?php echo htmlspecialchars($token_data['research_title']); ?>
                        </p>
                        <p class="mb-0">
                            <strong>Review Type:</strong> <?php echo ucfirst($review_type); ?>
                        </p>
                    </div>

                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Please complete all required fields. Forms expire on <?php echo date('F j, Y, g:i A', strtotime($token_info['expires_at'])); ?>.
                    </div>

                    <form id="categoryForm" method="POST" action="../staff/process-category-form.php">
                        <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">
                        
                        <?php
                        // Include the category form template
                        // The template should contain form fields for the specific category
                        include $form_template_path;
                        ?>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                                        <i class="bi bi-arrow-left me-2"></i>Back
                                    </button>
                                    <button type="submit" class="btn btn-success btn-lg">
                                        <i class="bi bi-check-circle me-2"></i>Submit Forms
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('categoryForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Submitting...';
        
        const formData = new FormData(form);
        
        fetch('../staff/process-category-form.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success message
                alert('Category forms submitted successfully! You will receive a confirmation email shortly.');
                window.location.href = '../track-application.php?queue=' + encodeURIComponent('<?php echo $queue_number; ?>');
            } else {
                alert('Error: ' + data.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            alert('Error submitting forms: ' + error.message);
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});
</script>

<?php include '../includes/applicant_footer.php'; ?>
