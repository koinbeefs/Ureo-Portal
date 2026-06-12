<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();
$result = $conn->query("SELECT * FROM urec_committees");
$committees = [];
while($row = $result->fetch_assoc()) {
    $committees[] = $row;
}
echo json_encode($committees, JSON_PRETTY_PRINT);
closeDBConnection($conn);
