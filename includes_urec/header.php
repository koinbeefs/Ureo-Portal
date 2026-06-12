<?php
declare(strict_types=1);
/**
 * UREC Header Component
 * TAU-UREO Portal
 */

// Basic session check if not already handled
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if not logged in or not UREC role
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] !== 'urec' && $_SESSION['role'] !== 'urec')) {
    header("Location: " . BASE_URL . "urec/login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'UREC Member';
$committee_designation = $_SESSION['committee_designation'] ?? '';
$is_chairperson = (stripos($committee_designation, 'Chair') !== false || stripos($committee_designation, 'Head') !== false);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>UREC Portal | TAU-UREO</title>
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/images/tau-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css">
    <style>
        body { 
            background: #F8F8F8; 
            margin: 0;
            padding: 0;
        }
        
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
        
        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: calc(100vh - 70px);
            width: calc(100% - 250px);
        }
        
        @media (max-width: 991px) {
            .sidebar {
                width: 70px;
            }
            .sidebar .nav-link span {
                display: none;
            }
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
        }
        
        .navbar-brand {
            font-size: 20px;
            font-weight: 700;
            color: #FFFFFF !important;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <nav style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.08); position: sticky; top: 0; z-index: 1000;">
        <div class="container-fluid" style="padding: 0 20px;">
            <div style="display: flex; align-items: center; justify-content: space-between; height: 70px;">
                <a href="<?php echo BASE_URL; ?>urec/dashboard.php" class="navbar-brand" style="text-decoration: none; display: flex; align-items: center; gap: 8px;">
                    <img src="<?php echo BASE_URL; ?>assets/images/tau-logo.png" alt="TAU Logo" style="height: 50px; width: auto;">
                    <span>TAU-UREO UREC Portal</span>
                </a>
                
                <div class="dropdown">
                    <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" style="color: white; text-decoration: none; font-size: 14px; font-weight: 600; padding: 8px 16px; display: flex; align-items: center; gap: 8px;">
                        <i class="bi bi-person-circle" style="font-size: 24px; color: white;"></i>
                        <span><?php echo htmlspecialchars($full_name); ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15); border: 1px solid #EAEAEA;">
                        <li><div class="dropdown-header small text-muted"><?php echo htmlspecialchars($committee_designation ?: 'UREC Member'); ?></div></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>urec/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid p-0 m-0">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="py-3">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'dashboard') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>urec/dashboard.php">
                            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'review') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>urec/review-queue.php">
                            <i class="bi bi-list-check"></i> <span>Review Queue</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'applications') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>urec/applications.php">
                            <i class="bi bi-folder"></i> <span>All Applications</span>
                        </a>
                    </li>
                    
                    <?php if ($is_chairperson): ?>
                        <li><hr style="margin: 12px 0; border-color: #EAEAEA;"></li>
                        <li class="nav-item px-3 mb-2">
                            <span class="text-uppercase small fw-bold text-muted">Committee Management</span>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'assign') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>urec/urec_admin/assign-application.php">
                                <i class="bi bi-person-plus"></i> <span>Assign Evaluators</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <li class="nav-item">
                        <a class="nav-link <?php echo(isset($active_menu) && $active_menu == 'reports') ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>urec/reports.php">
                            <i class="bi bi-bar-chart"></i> <span>Reports</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
