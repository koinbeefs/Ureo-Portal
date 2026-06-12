<?php
require_once 'config/config.php';
$conn = getDBConnection();
$res = $conn->query("SELECT current_status FROM applications WHERE queue_number='UREO-0001'");
echo $res->fetch_assoc()['current_status'];
closeDBConnection($conn);
