<?php
declare(strict_types=1);
/**
 * UREC Logout
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    logStaffActivity($_SESSION['user_id'], null, 'other', 'UREC Member Logged out');
}

// Unset all session variables specific to UREC
unset($_SESSION['authenticated']);
unset($_SESSION['user_id']);
unset($_SESSION['username']);
unset($_SESSION['user_role']);
unset($_SESSION['role']);
unset($_SESSION['full_name']);
unset($_SESSION['committee_designation']);
unset($_SESSION['committee_id']);

header("Location: login.php");
exit();
