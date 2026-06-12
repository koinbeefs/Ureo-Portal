<?php
/**
 * Public Header Component
 * TAU-UREO Portal
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>TAU-UREO Portal</title>
    <link rel="icon" href="assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="32x32" href="assets/images/tau-logo.png">
    <link rel="icon" type="image/png" sizes="16x16" href="assets/images/tau-logo.png">
    <link rel="apple-touch-icon" sizes="180x180" href="assets/images/tau-logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo isset($base_url) ? $base_url : ''; ?>assets/css/style.css">
</head>
<body style="font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #F8F8F8; min-height: 100vh; display: flex; flex-direction: column;">
    <!-- Navigation -->
    <nav style="background: linear-gradient(135deg, #006400 0%, #228B22 45%, #0f2a0f 100%); box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2); position: sticky; top: 0; z-index: 1000;">
        <div class="container-fluid" style="padding: 0 20px;">
            <div style="display: flex; align-items: center; min-height: 85px; padding: 12px 0;">
                <a href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php" style="text-decoration: none; display: flex; align-items: center; gap: 16px;">
                    <img src="<?php echo isset($base_url) ? $base_url : ''; ?>assets/images/tau-logo.png" alt="TAU Logo" style="height: 65px; width: auto;">
                    <div style="display: flex; flex-direction: column; gap: 4px;">
                        <span style="color: #FFFFFF; font-weight: 700; font-size: 24px; line-height: 1; white-space: nowrap;">Tarlac Agricultural University</span>
                        <span style="color: #FFFFFF; font-weight: 400; font-size: 14px; letter-spacing: 0.8px; text-transform: uppercase; white-space: nowrap;">University Research Ethics Office</span>
                    </div>
                </a>
                <button class="d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" style="border: none; background: none; padding: 8px; margin-left: auto;">
                    <i class="bi bi-list" style="font-size: 24px; color: #FFFFFF;"></i>
                </button>
                <div class="collapse navbar-collapse d-lg-flex" id="mainNav" style="flex-grow: 1; justify-content: flex-end;">
                    <ul class="navbar-nav" style="display: flex; flex-direction: row; gap: 4px; list-style: none; margin: 0; padding: 0 16px 0 0; align-items: center;">
                        <li class="nav-item"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>index.php" style="padding: 8px 16px; color: <?php echo (isset($active_page) && $active_page == 'home') ? '#FFFFFF' : 'rgba(255, 255, 255, 0.85)'; ?>; font-weight: <?php echo (isset($active_page) && $active_page == 'home') ? '600' : '500'; ?>; text-decoration: none; border-radius: 4px; transition: all 0.2s; font-size: 14px; display: block;" onmouseover="this.style.color='#FFFFFF'; this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.color='rgba(255, 255, 255, 0.85)'; this.style.background='transparent'">Home</a></li>
                        <li class="nav-item"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>track-application.php" style="padding: 8px 16px; color: <?php echo (isset($active_page) && $active_page == 'track') ? '#FFFFFF' : 'rgba(255, 255, 255, 0.85)'; ?>; font-weight: <?php echo (isset($active_page) && $active_page == 'track') ? '600' : '500'; ?>; text-decoration: none; border-radius: 4px; transition: all 0.2s; font-size: 14px; display: block;" onmouseover="this.style.color='#FFFFFF'; this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.color='rgba(255, 255, 255, 0.85)'; this.style.background='transparent'">Track Application</a></li>
                        <li class="nav-item"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>submit-intent.php" style="padding: 8px 16px; color: <?php echo (isset($active_page) && $active_page == 'submit') ? '#FFFFFF' : 'rgba(255, 255, 255, 0.85)'; ?>; font-weight: <?php echo (isset($active_page) && $active_page == 'submit') ? '600' : '500'; ?>; text-decoration: none; border-radius: 4px; transition: all 0.2s; font-size: 14px; display: block;" onmouseover="this.style.color='#FFFFFF'; this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.color='rgba(255, 255, 255, 0.85)'; this.style.background='transparent'">Submit Application</a></li>
                        <li class="nav-item"><a href="<?php echo isset($base_url) ? $base_url : ''; ?>applicant/login.php" style="padding: 8px 16px; color: rgba(255, 255, 255, 0.85); font-weight: 500; text-decoration: none; border-radius: 4px; transition: all 0.2s; font-size: 14px; display: block;" onmouseover="this.style.color='#FFFFFF'; this.style.background='rgba(255, 255, 255, 0.1)'" onmouseout="this.style.color='rgba(255, 255, 255, 0.85)'; this.style.background='transparent'">Applicant Login</a></li>
                    </ul>
                    <a href="<?php echo isset($base_url) ? $base_url : ''; ?>staff/login.php" style="padding: 10px 24px; background: rgba(255, 255, 255, 0.25); color: white; font-weight: 600; text-decoration: none; border-radius: 8px; transition: all 0.2s; font-size: 14px; border: 1px solid rgba(255, 255, 255, 0.4); display: block; margin-left: 12px;" onmouseover="this.style.background='rgba(255, 255, 255, 0.35)'; this.style.borderColor='rgba(255, 255, 255, 0.6)'" onmouseout="this.style.background='rgba(255, 255, 255, 0.25)'; this.style.borderColor='rgba(255, 255, 255, 0.4)'">Staff Portal</a>
                </div>
            </div>
        </div>
    </nav>
