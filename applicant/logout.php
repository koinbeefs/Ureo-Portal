<?php
/**
 * Applicant Logout
 * TAU-UREO Portal
 */

session_start();
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>
