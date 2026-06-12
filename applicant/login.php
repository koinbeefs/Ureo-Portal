<?php
/**
 * Applicant Login (OTP-based)
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';
$step = 'queue'; // queue or otp

// Check for error message from redirect
if (isset($_GET['error'])) {
    if ($_GET['error'] === 'application_not_found') {
        $error_message = 'Application not found. Please check your queue number.';
    } elseif ($_GET['error'] === 'session_expired') {
        $error_message = 'Your session has expired. Please login again.';
    }
}

// Check if already logged in
if (isApplicantLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Handle queue number submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_queue'])) {
    $queue_number = sanitizeInput($_POST['queue_number']);
    
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT queue_number, applicant_email FROM applications WHERE queue_number = ?");
    $stmt->bind_param("s", $queue_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $application = $result->fetch_assoc();
        
        // Generate OTP
        $otp = generateOTP();
        $expires_at = date('Y-m-d H:i:s', strtotime('+' . OTP_EXPIRY_MINUTES . ' minutes'));
        $ip = $_SERVER['REMOTE_ADDR'];
        
        // Store OTP
        $otp_stmt = $conn->prepare("INSERT INTO otp_sessions (queue_number, otp_code, expires_at, ip_address) VALUES (?, ?, ?, ?)");
        $otp_stmt->bind_param("ssss", $queue_number, $otp, $expires_at, $ip);
        $otp_stmt->execute();
        
        // Send OTP email
        $email_subject = "TAU-UREO Portal - Login OTP";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif;'>
            <h2>Login Verification</h2>
            <p>Your One-Time Password (OTP) for accessing the TAU-UREO Portal:</p>
            <div style='background: #f8f9fa; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0;'>
                <h1 style='color: #0d6efd; letter-spacing: 5px; margin: 0;'>$otp</h1>
            </div>
            <p>This OTP will expire in " . OTP_EXPIRY_MINUTES . " minutes.</p>
            <p>If you did not request this OTP, please ignore this email.</p>
            <hr>
            <p style='color: #6c757d; font-size: 12px;'>
                This is an automated email from TAU-UREO Portal. Please do not reply to this email.
            </p>
        </body>
        </html>
        ";
        
        sendEmail($application['applicant_email'], $email_subject, $email_body, $queue_number);
        
        $_SESSION['temp_queue_number'] = $queue_number;
        $step = 'otp';
    } else {
        $error_message = "Queue number not found. Please check and try again.";
    }
    
    closeDBConnection($conn);
}

// Handle OTP verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $otp = sanitizeInput($_POST['otp']);
    $queue_number = $_SESSION['temp_queue_number'] ?? '';
    
    if (empty($queue_number)) {
        $error_message = "Session expired. Please start over.";
        $step = 'queue';
    } else {
        $conn = getDBConnection();
        
        // Check OTP
        $stmt = $conn->prepare("SELECT session_id, attempts FROM otp_sessions WHERE queue_number = ? AND otp_code = ? AND verified = 0 AND expires_at > NOW() ORDER BY generated_at DESC LIMIT 1");
        $stmt->bind_param("ss", $queue_number, $otp);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $session = $result->fetch_assoc();
            
            // Mark as verified
            $update_stmt = $conn->prepare("UPDATE otp_sessions SET verified = 1 WHERE session_id = ?");
            $update_stmt->bind_param("i", $session['session_id']);
            $update_stmt->execute();
            
            // Set session
            $_SESSION['queue_number'] = $queue_number;
            $_SESSION['applicant_authenticated'] = true;
            $_SESSION['last_activity'] = time();
            
            unset($_SESSION['temp_queue_number']);
            
            closeDBConnection($conn);
            header("Location: dashboard.php");
            exit();
        } else {
            // Increment attempts
            $attempt_stmt = $conn->prepare("UPDATE otp_sessions SET attempts = attempts + 1 WHERE queue_number = ? AND otp_code = ?");
            $attempt_stmt->bind_param("ss", $queue_number, $otp);
            $attempt_stmt->execute();
            
            $error_message = "Invalid or expired OTP. Please try again.";
            $step = 'otp';
        }
        
        closeDBConnection($conn);
    }
}

// Handle back button - clear OTP session
if (isset($_POST['back_to_queue'])) {
    unset($_SESSION['temp_queue_number']);
    $step = 'queue';
}

// Check if returning to OTP step
if (isset($_SESSION['temp_queue_number'])) {
    $step = 'otp';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Login - TAU-UREO Portal</title>
    <link rel="icon" href="../assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/tau-logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/tau-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        .login-container {
            display: flex;
            min-height: 100vh;
        }
        .login-left {
            flex: 1;
            position: relative;
            background: linear-gradient(135deg, #006400 0%, #228B22 50%, #2d8b2d 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 60px 40px;
            color: white;
            overflow: hidden;
        }
        .login-left::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            z-index: 1;
        }
        .login-left > * {
            position: relative;
            z-index: 2;
        }
        .rotating-dots {
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: 0;
        }
        .dot {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.15);
            animation: rotate 20s linear infinite;
        }
        .dot:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 15%;
            animation-duration: 25s;
        }
        .dot:nth-child(2) {
            width: 60px;
            height: 60px;
            top: 60%;
            left: 10%;
            animation-duration: 30s;
            animation-direction: reverse;
        }
        .dot:nth-child(3) {
            width: 100px;
            height: 100px;
            top: 30%;
            right: 10%;
            animation-duration: 22s;
        }
        .dot:nth-child(4) {
            width: 70px;
            height: 70px;
            bottom: 20%;
            right: 20%;
            animation-duration: 28s;
            animation-direction: reverse;
        }
        .dot:nth-child(5) {
            width: 50px;
            height: 50px;
            top: 50%;
            left: 50%;
            animation-duration: 35s;
        }
        @keyframes rotate {
            0% {
                transform: rotate(0deg) translateX(50px) rotate(0deg);
            }
            100% {
                transform: rotate(360deg) translateX(50px) rotate(-360deg);
            }
        }
        .login-left img {
            width: 300px;
            height: 300px;
            margin-bottom: 30px;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.2));
        }
        .login-left h1 {
            font-size: 50px;
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
        }
        .login-left p {
            font-size: 20px;
            text-align: center;
            line-height: 1.6;
            margin: 5px 0;
        }
        .login-right {
            flex: 1;
            background: #f5f5f5 url('../assets/images/bg.jpg') center center / cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        .login-form-wrapper {
            width: 100%;
            max-width: 480px;
            background: white;
            padding: 50px 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        .login-form-wrapper h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            color: #1a1a1a;
            text-align: center;
        }
        .login-form-wrapper .subtitle {
            color: #666666;
            margin-bottom: 30px;
            font-size: 15px;
            text-align: center;
        }
        .form-label {
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }
        .form-control {
            padding: 12px 16px 12px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
        }
        .form-control.otp-input {
            text-align: center;
            letter-spacing: 0.5rem;
            font-size: 1.5rem;
            padding: 16px;
        }
        .form-control:focus {
            border-color: #006400;
            box-shadow: 0 0 0 3px rgba(0, 100, 0, 0.1);
        }
        .input-wrapper {
            position: relative;
            margin-bottom: 20px;
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            font-size: 18px;
            color: #666;
        }
        .helper-text {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #006400;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: #005000;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 100, 0, 0.3);
        }
        .btn-secondary-custom {
            width: 100%;
            padding: 14px;
            background: white;
            color: #006400;
            border: 2px solid #006400;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            margin-top: 10px;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-secondary-custom:hover {
            background: #f0f0f0;
            text-decoration: none;
        }
        .back-link {
            text-align: center;
            margin-top: 25px;
        }
        .back-link a {
            color: #006400;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
            }
            .login-left {
                padding: 40px 20px;
            }
            .login-left img {
                width: 120px;
                height: 120px;
            }
            .login-left h1 {
                font-size: 24px;
            }
            .login-form-wrapper {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <div class="rotating-dots">
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
                <div class="dot"></div>
            </div>
            <img src="../assets/images/tau-logo.png" alt="TAU Logo">
            <h1>Applicant Portal</h1>
            <p>University Research Ethics Office</p>
            <p>Department of Research and Development</p>
        </div>
        
        <div class="login-right">
            <div class="login-form-wrapper">
                <?php if ($step === 'queue'): ?>
                    <h2>Welcome</h2>
                    <p class="subtitle">Login with your queue number</p>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="queue_number" class="form-label">Queue Number</label>
                            <div class="input-wrapper">
                                <i class="bi bi-ticket-detailed input-icon"></i>
                                <input type="text" class="form-control" id="queue_number" name="queue_number" 
                                       placeholder="UREO-0000" required 
                                       pattern="UREO-\d{4}" title="Format: UREO-0000">
                            </div>
                            <div class="helper-text">Enter your queue number (Format: UREO-0000)</div>
                        </div>
                        
                        <button type="submit" name="submit_queue" class="btn-login">
                            <i class="bi bi-send"></i> Send OTP
                        </button>
                    </form>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div class="text-center">
                        <p class="mb-2 text-muted" style="font-size: 14px;">Don't have a queue number?</p>
                        
                        <a href="../submit-intent.php" class="btn-secondary-custom">
                            <i class="bi bi-file-earmark-text"></i> Submit Letter of Intent
                        </a>
                    </div>
                
                <?php else: ?>
                    <h2>Verify Code</h2>
                    <p class="subtitle">Enter the verification code sent to your email</p>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> A 6-digit OTP has been sent to your registered email address.
                    </div>
                    
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="verify_otp" value="1">
                        <div class="mb-3">
                            <label for="otp" class="form-label">One-Time Password</label>
                            <div class="input-wrapper">
                                <input type="text" class="form-control otp-input" id="otp" name="otp" 
                                       placeholder="000000" required 
                                       pattern="\d{6}" maxlength="6" title="6-digit OTP">
                            </div>
                            <div class="helper-text">Enter the 6-digit code sent to your email</div>
                        </div>

                        <button type="submit" name="verify_otp" class="btn-login">
                            <i class="bi bi-check-circle"></i> Verify & Login
                        </button>
                    </form>
                    
                    <form method="POST" action="">
                        <button type="submit" name="back_to_queue" class="btn-secondary-custom">
                            <i class="bi bi-arrow-left"></i> Back
                        </button>
                    </form>
                    
                    <hr style="margin: 30px 0;">
                    
                    <div class="text-center">
                        <p class="mb-2"><small class="text-muted">Didn't receive the code?</small></p>
                        <form method="POST" action="" class="d-inline">
                            <input type="hidden" name="queue_number" value="<?php echo htmlspecialchars($_SESSION['temp_queue_number'] ?? ''); ?>">
                            <button type="submit" name="submit_queue" class="btn btn-link" style="color: #006400; font-weight: 500;">
                                <i class="bi bi-arrow-clockwise"></i> Resend OTP
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <div class="back-link">
                    <a href="../index.php">
                        <i class="bi bi-arrow-left"></i> Back to UREO Website
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-format queue number input
        document.addEventListener('DOMContentLoaded', function() {
            const queueInput = document.getElementById('queue_number');
            if (queueInput) {
                queueInput.addEventListener('input', function(e) {
                    let value = e.target.value.toUpperCase().replace(/[^0-9]/g, '');
                    if (value.length > 0) {
                        e.target.value = 'UREO-' + value.slice(0, 4);
                    } else {
                        e.target.value = '';
                    }
                });
            }
            
            // Auto-submit on OTP input complete
            const otpInput = document.getElementById('otp');
            if (otpInput) {
                otpInput.addEventListener('input', function(e) {
                    // Remove non-digits
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Auto-submit when 6 digits entered
                    if (this.value.length === 6) {
                        setTimeout(() => this.form.submit(), 300);
                    }
                });
            }
        });
    </script>
</body>
</html>
