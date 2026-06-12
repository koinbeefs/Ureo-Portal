<?php
require_once 'config/config.php';
$conn = getDBConnection();
$result = $conn->query('SELECT * FROM email_logs WHERE email_id = 33');
$row = $result->fetch_assoc();
print_r($row);
closeDBConnection($conn);
?>
