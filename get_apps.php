<?php
require_once 'config/config.php';
$conn = getDBConnection();
$r = $conn->query("SELECT * FROM applications");
$out = "";
while($row = $r->fetch_assoc()) {
    $out .= $row['queue_number'] . " - " . $row['current_status'] . PHP_EOL;
}
file_put_contents('apps.txt', $out);
echo "done";
