<?php
/**
 * Authenticated Header Component (with Sidebar)
 * TAU-UREO Portal
 */

// Determine user type
$is_applicant = isset($_SESSION['applicant_authenticated']) && $_SESSION['applicant_authenticated'] === true;
$is_staff = isset($_SESSION['user_id']) && isset($_SESSION['role']) && !$is_applicant;
$user_role = $_SESSION['role'] ?? 'user';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>TAU-UREO Portal</title>
    <link rel="icon" href="../assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="../assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="../assets/images/tau-logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="../assets/images/tau-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo isset($base_url) ? $base_url : '../'; ?>assets/css/style.css">
    <style>
        body { 
            background: #F8F8F8; 
            margin: 0;
            padding: 0;
        }
        
        /* Sidebar Styles */
        .sidebar { 
            position: fixed;
            top: 70px;
            left: 0;
            width: 250px;
            height: calc(100vh - 70px);
            overflow-y: auto;
            background: #FFFFFF; 
            border-right: 1px solid #EAEAEA;
            padding: 0;
            z-index: 999;
        }
        
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: #F8F8F8;
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: #CCCCCC;
            border-radius: 3px;
        }
        .sidebar::-webkit-scrollbar-thumb:hover {
            background: #999999;
        }
        
        .sidebar .nav-link {
            color: #666666;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 500;
            border-left: 3px solid transparent;
            transition: all 0.2s;
        }
        .sidebar .nav-link:hover {
            background: #F8F8F8;
            color: #006400;
        }
        .sidebar .nav-link.active {
            color: #006400;
            font-weight: 600;
            background: #F8F8F8;
            border-left-color: #006400;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
            font-size: 16px;
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 250px;
            padding: 0;
            min-height: calc(100vh - 70px);
            width: calc(100% - 250px);
        }
        
        /* Responsive */
        @media (max-width: 767px) {
            .sidebar {
                position: relative;
                top: 0;
                width: 100%;
                height: auto;
                border-right: none;
                border-bottom: 1px solid #EAEAEA;
            }
            .main-content {
                margin-left: 0;
                width: 100%;
            }
        }
        
        .navbar-brand {
            font-size: 20px;
            font-weight: 700;
            color: #006400 !important;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08); position: sticky; top: 0; z-index: 1000;">
        <div class="container-fluid" style="padding: 0 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; height: 70px;">
                <a href="dashboard.php" class="navbar-brand" style="text-decoration: none; display: flex; align-items: center; gap: 8px;">
                     <img src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/tau-logo.png" alt="TAU Logo" style="height: 70px; width: auto;">
                    <span style="color: white;">TAU-UREO <?php echo $is_staff ? 'Staff' : ''; ?> Portal</span>
                </a>
                
                <?php if ($is_applicant): ?>
                    <div style="display: flex; align-items: center; gap: 20px;">
                        <span style="background: linear-gradient(135deg, #006400, #228B22); color: white; padding: 6px 16px; border-radius: 20px; font-size: 14px; font-weight: 600;">
                            <i class="bi bi-ticket-detailed"></i> <?php echo htmlspecialchars($_SESSION['queue_number']); ?>
                        </span>
                        <a href="logout.php" style="color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 500; padding: 8px 16px; border-radius: 6px; transition: all 0.2s;" onmouseover="this.style.background='#F8F8F8'; this.style.color='#006400'" onmouseout="this.style.background='transparent'; this.style.color='#666666'">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </div>
                <?php
elseif ($is_staff): ?>
                    <div class="dropdown">
                        <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" style="color: white; text-decoration: none; font-size: 14px; font-weight: 600; padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                            <i class="bi bi-person-circle" style="font-size: 24px; color: white;"></i>
                            <span><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); border: 1px solid #EAEAEA;">
                            <?php if ($user_role === 'admin'): ?>
                                <li><a class="dropdown-item" href="../admin/dashboard.php"><i class="bi bi-gear"></i> Admin Panel</a></li>
                                <li><hr class="dropdown-divider"></li>
                            <?php
    endif; ?>
                            <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </div>
                <?php
endif; ?>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0 m-0">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="py-3">
                    <?php if ($is_applicant): ?>
                        <!-- Applicant Menu -->
                        <ul class="nav flex-column">
                            <li class="nav-item">
                                <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'overview') ? 'active' : ''; ?>" href="dashboard.php">
                                    <i class="bi bi-speedometer2"></i> Overview
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'documents') ? 'active' : ''; ?>" href="documents.php">
                                    <i class="bi bi-file-earmark-text"></i> Documents
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'messages') ? 'active' : ''; ?>" href="messages.php">
                                    <i class="bi bi-chat-left-dots"></i> Messages
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'history') ? 'active' : ''; ?>" href="history.php">
                                    <i class="bi bi-clock-history"></i> History
                                </a>
                            </li>
                        </ul>
                    <?php
elseif ($is_staff): ?>
                        <!-- Detect if we're in admin section -->
                        <?php
    $is_admin_page = strpos($_SERVER['REQUEST_URI'], '/admin/') !== false;
    $staff_base = $is_admin_page ? '../staff/' : '';
    $admin_base = $is_admin_page ? '' : '../admin/';
?>
                        
                        <!-- Staff Menu -->
                        <?php if (!$is_admin_page): ?>
                            <ul class="nav flex-column">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'dashboard') ? 'active' : ''; ?>" href="dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'applications') ? 'active' : ''; ?>" href="applications.php">
                                        <i class="bi bi-folder"></i> All Applications
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'review') ? 'active' : ''; ?>" href="review-queue.php">
                                        <i class="bi bi-list-check"></i> Review Queue
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'reports') ? 'active' : ''; ?>" href="reports.php">
                                        <i class="bi bi-bar-chart"></i> Reports
                                    </a>
                                </li>
                                <?php if ($user_role === 'admin'): ?>
                                    <li><hr style="margin: 12px 0; border-color: #EAEAEA;"></li>
                                    <li class="nav-item">
                                        <a class="nav-link" href="../admin/dashboard.php">
                                            <i class="bi bi-gear"></i> Admin Panel
                                        </a>
                                    </li>
                                <?php
        endif; ?>
                            </ul>
                        <?php
    else: ?>
                            <!-- Admin Menu -->
                            <ul class="nav flex-column">
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'dashboard') ? 'active' : ''; ?>" href="dashboard.php">
                                        <i class="bi bi-speedometer2"></i> Dashboard
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'users') ? 'active' : ''; ?>" href="manage-users.php">
                                        <i class="bi bi-people"></i> Manage Users
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'settings') ? 'active' : ''; ?>" href="system-settings.php">
                                        <i class="bi bi-gear-fill"></i> System Settings
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'logs') ? 'active' : ''; ?>" href="activity-logs.php">
                                        <i class="bi bi-clock-history"></i> Activity Logs
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'email-logs') ? 'active' : ''; ?>" href="email-logs.php">
                                        <i class="bi bi-envelope-check"></i> Email Logs
                                    </a>
                                </li>
                                <li><hr style="margin: 12px 0; border-color: #EAEAEA;"></li>
                                <li class="nav-item">
                                    <a class="nav-link" href="../staff/dashboard.php">
                                        <i class="bi bi-arrow-left"></i> Back to Staff Portal
                                    </a>
                                </li>
                            </ul>
                        <?php
    endif; ?>
                    <?php
endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
