<?php
/**
 * Staff Logout
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (isset($_SESSION['user_id'])) {
    logStaffActivity($_SESSION['user_id'], null, 'other', 'Logged out');
}

session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
