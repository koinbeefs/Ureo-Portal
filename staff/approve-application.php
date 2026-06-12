<?php
/**
 * Approve Application Interface
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();
checkSessionTimeout();

$queue_number = $_GET['queue'] ?? '';

if (empty($queue_number)) {
    echo '<div class="alert alert-danger">Invalid request.</div>';
    exit();
}

$conn = getDBConnection();

// Get application details
$app_stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    echo '<div class="alert alert-danger">Application not found.</div>';
    exit();
}

closeDBConnection($conn);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            padding: 20px;
            background: transparent;
        }
        .review-option {
            border: 2px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .review-option:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .review-option.selected {
            border-color: #0d6efd;
            background-color: #e7f3ff;
        }
        .review-option input[type="radio"] {
            display: none;
        }
        .review-title {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 8px;
            color: #495057;
        }
        .review-description {
            color: #6c757d;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .review-icon {
            font-size: 24px;
            color: #0d6efd;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h5 class="mb-4"><i class="bi bi-check-circle"></i> Approve Application</h5>
            <p class="text-muted mb-4">Select the appropriate review level for this application:</p>

            <form id="approveForm" method="POST" action="process-approve-application.php">
                <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($queue_number); ?>">

                <!-- Exempt Review -->
                <div class="review-option" data-value="exempt">
                    <input type="radio" name="review_type" value="exempt" id="exempt">
                    <div class="text-center">
                        <i class="bi bi-shield-check review-icon"></i>
                        <div class="review-title">Exempt Review</div>
                        <div class="review-description">
                            Research that involves no more than minimal risk to participants and meets specific exempt criteria.
                        </div>
                    </div>
                </div>

                <!-- Expedited Review -->
                <div class="review-option" data-value="expedited">
                    <input type="radio" name="review_type" value="expedited" id="expedited">
                    <div class="text-center">
                        <i class="bi bi-speedometer2 review-icon"></i>
                        <div class="review-title">Expedited Review</div>
                        <div class="review-description">
                            Research that involves minimal risk and can be reviewed more quickly than full committee review.
                        </div>
                    </div>
                </div>

                <!-- Full Review -->
                <div class="review-option" data-value="full">
                    <input type="radio" name="review_type" value="full" id="full">
                    <div class="text-center">
                        <i class="bi bi-people review-icon"></i>
                        <div class="review-title">Full Review</div>
                        <div class="review-description">
                            Research that requires full committee review due to higher risk or complex ethical considerations.
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" onclick="window.parent.postMessage({type: 'closeApproveModal'}, '*')">
                        Cancel
                    </button>
                    <button type="submit" class="btn btn-success" id="submitBtn" disabled>
                        <i class="bi bi-check-circle"></i> Approve Application
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const reviewOptions = document.querySelectorAll('.review-option');
    const submitBtn = document.getElementById('submitBtn');

    reviewOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            reviewOptions.forEach(opt => opt.classList.remove('selected'));

            // Add selected class to clicked option
            this.classList.add('selected');

            // Check the corresponding radio button
            const radioValue = this.getAttribute('data-value');
            document.getElementById(radioValue).checked = true;

            // Enable submit button
            submitBtn.disabled = false;
        });
    });

    // Handle form submission
    document.getElementById('approveForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;

        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Processing...';

        fetch('process-approve-application.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Send success message to parent
                window.parent.postMessage({
                    type: 'applicationApproved',
                    success: true,
                    message: data.message,
                    review_type: data.review_type
                }, '*');
            } else {
                alert('Error: ' + data.message);
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            alert('Error processing approval: ' + error.message);
            // Re-enable button
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
    });
});
</script>

</body>
</html>