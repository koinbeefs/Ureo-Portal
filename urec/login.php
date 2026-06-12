<?php
declare(strict_types=1);
/**
 * UREC Login
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

$error_message = '';

// Check if already logged in (as UREC)
if (isset($_SESSION['user_id']) && (($_SESSION['user_role'] ?? '') === 'urec' || ($_SESSION['role'] ?? '') === 'urec')) {
    header("Location: dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];

    if (!empty($username) && !empty($password)) {
        $conn = getDBConnection();
        // Check for urec role in both potential columns
        $stmt = $conn->prepare("SELECT user_id, username, password_hash, user_role, role, full_name, active_status, committee_designation, committee_id FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();

            // Verify if user has UREC privileges
            $is_urec = ($user['user_role'] === 'urec' || $user['role'] === 'urec');

            if (!$is_urec) {
                $error_message = "Access denied. This portal is for UREC members only.";
            }
            elseif ($user['active_status'] == 0) {
                $error_message = "Your account has been deactivated. Please contact the administrator.";
            }
            elseif (password_verify($password, $user['password_hash'])) {
                // Set session
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['user_role'] ?: $user['role'];
                $_SESSION['role'] = $_SESSION['user_role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['committee_designation'] = $user['committee_designation'];
                $_SESSION['committee_id'] = $user['committee_id'];
                $_SESSION['last_activity'] = time();

                // Update last login
                $update_stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $update_stmt->bind_param("i", $user['user_id']);
                $update_stmt->execute();

                // Log activity
                logStaffActivity($user['user_id'], null, 'other', 'UREC Member Logged in');

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
    <title>UREC Login - TAU-UREO Portal</title>
    <link rel="icon" href="../assets/images/tau-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
            background: #F8F8F8;
        }
        .login-container {
            display: flex;
            min-height: 100vh;
        }
        .login-left {
            flex: 1;
            position: relative;
            /* Matches Staff portal gradient but with UREC context */
            background: linear-gradient(135deg, #004d00 0%, #006400 50%, #228b22 100%);
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
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(8px);
            z-index: 1;
        }
        .login-left > * { position: relative; z-index: 2; }
        
        .login-left img {
            width: 250px;
            height: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 12px rgba(0, 0, 0, 0.3));
        }
        .login-left h1 { font-size: 42px; font-weight: 800; margin-bottom: 10px; text-align: center; }
        .login-left p { font-size: 18px; text-align: center; margin: 2px 0; opacity: 0.9; }

        .login-right {
            flex: 1;
            background: #fdfdfd url('../assets/images/bg.jpg') center center / cover no-repeat;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px;
        }
        .login-form-wrapper {
            width: 100%;
            max-width: 450px;
            background: white;
            padding: 45px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .login-form-wrapper h2 { font-size: 28px; font-weight: 700; margin-bottom: 8px; text-align: center; color: #333; }
        .subtitle { color: #666; margin-bottom: 35px; text-align: center; font-size: 14px; }
        
        .form-label { font-weight: 600; font-size: 14px; color: #444; margin-bottom: 8px; }
        .input-wrapper { position: relative; margin-bottom: 20px; }
        .input-icon { position: absolute; left: 15px; top: 12px; font-size: 18px; color: #888; }
        .form-control {
            padding: 12px 15px 12px 45px;
            border: 2px solid #EEE;
            border-radius: 12px;
            transition: all 0.3s ease;
        }
        .form-control:focus { border-color: #006400; box-shadow: 0 0 0 4px rgba(0, 100, 0, 0.1); }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: #006400;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover { background: #005000; transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0, 100, 0, 0.2); }
        
        .back-link { text-align: center; margin-top: 25px; }
        .back-link a { color: #006400; text-decoration: none; font-size: 14px; font-weight: 500; }
        .back-link a:hover { text-decoration: underline; }
        
        @media (max-width: 991px) {
            .login-container { flex-direction: column; }
            .login-left { padding: 40px 20px; }
            .login-left img { width: 150px; }
            .login-left h1 { font-size: 30px; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <img src="../assets/images/tau-logo.png" alt="TAU Logo">
            <h1>UREC Portal</h1>
            <p>University Research Ethics Committee</p>
            <p>Ethics Evaluation & Review</p>
        </div>
        
        <div class="login-right">
            <div class="login-form-wrapper">
                <h2>Secure Login</h2>
                <p class="subtitle">Enter your credentials to access the ethics portal</p>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger border-0 small py-2">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-wrapper">
                            <i class="bi bi-person input-icon"></i>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Your UREC username" required autofocus>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-wrapper">
                            <i class="bi bi-lock input-icon"></i>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Your secure password" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn-login">
                        <i class="bi bi-shield-lock me-2"></i> Access Portal
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="../index.php">
                        <i class="bi bi-arrow-left"></i> Back to Main Website
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
