<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM urec_committees");
$output = "";
while($row = $result->fetch_assoc()) {
    $output .= "ID: " . $row['committee_id'] . " | Name: " . $row['committee_name'] . "\n";
}
file_put_contents('committees_list.txt', $output);
echo "Done. Committees found: " . $result->num_rows;
closeDBConnection($conn);
