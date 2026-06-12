<?php
require_once 'config/config.php';

$conn = getDBConnection();
$result = $conn->query('SELECT email_id, subject, body_html FROM email_logs ORDER BY email_id DESC LIMIT 5');

echo "Recent emails:\n";
while ($row = $result->fetch_assoc()) {
    $body_status = $row['body_html'] ? 'HAS CONTENT (' . strlen($row['body_html']) . ' chars)' : 'NULL';
    echo "ID: {$row['email_id']} | Subject: {$row['subject']} | Body: {$body_status}\n";
}

closeDBConnection($conn);
?>
