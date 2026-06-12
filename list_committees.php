<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM urec_committees");
while($row = $result->fetch_assoc()) {
    echo "ID: " . $row['committee_id'] . " - Name: " . $row['committee_name'] . "\n";
}
closeDBConnection($conn);
