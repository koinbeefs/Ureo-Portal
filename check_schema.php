<?php
require_once 'config/config.php';
require_once 'includes/functions.php';
$conn = getDBConnection();
$res = $conn->query("DESCRIBE system_documents");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Error: " . $conn->error;
}
closeDBConnection($conn);
