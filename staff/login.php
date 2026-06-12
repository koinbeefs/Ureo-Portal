<?php
/**
 * Staff Login
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';

// Check if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("SELECT user_id, username, password_hash, role, full_name, active_status FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            if ($user['active_status'] == 0) {
                $error_message = "Your account has been deactivated. Please contact the administrator.";
            }
            elseif (password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['last_activity'] = time();

                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();

                // Log activity
                logStaffActivity($user['user_id'], null, 'other', 'Logged in');

                closeDBConnection($conn);
                header("Location: dashboard.php");
                exit();
            }
            else {
                $error_message = "Invalid username or password.";
            }
        }
        else {
            $error_message = "Invalid username or password.";
        }

        closeDBConnection($conn);
    }
    else {
        $error_message = "Please enter both username and password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Login - TAU-UREO Portal</title>
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
            margin-bottom: 15px;
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
            line-height: 1;
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
            <h1>Staff Portal</h1>
            <p>University Research Ethics Office</p>
            <p>Department of Research and Development</p>
        </div>
        
        <div class="login-right">
            <div class="login-form-wrapper">
                <h2>Welcome Back</h2>
                <p class="subtitle">Sign in to access the administration panel</p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="bi bi-exclamation-circle"></i> <?php echo $error_message; ?>
                    </div>
                <?php
endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Enter your username" required autofocus>
                        </div>
                        <div class="helper-text">Enter your admin username</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Enter your password" required>
                        </div>
                        <div class="helper-text">Enter your admin password</div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> Sign In
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="../index.php">
                        <i class="bi bi-arrow-left"></i> Back to UREO Website
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
