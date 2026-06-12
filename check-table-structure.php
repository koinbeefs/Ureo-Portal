<?php
require_once 'config/config.php';
$conn = getDBConnection();
echo "Checking status_history table structure:\n";
$result = $conn->query('DESCRIBE status_history');
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . $row['Null'] . ' - ' . $row['Default'] . "\n";
}
$conn->close();
?>
