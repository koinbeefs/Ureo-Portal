<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';

$conn = getDBConnection();

$body = '<p>Greetings from TAU-REO!</p>

<p>Please read the general guidelines for research ethics review and evaluation. Then, kindly accomplish the following documents:</p>
<p>
✓&nbsp;&nbsp;&nbsp;Application form TAU-REO-QF-01 <em>(see attached file)</em><br>
✓&nbsp;&nbsp;&nbsp;Research Ethics Review Category Form TAU-REO-QF-02 <em>(see attached file)</em><br>
✓&nbsp;&nbsp;&nbsp;CV of proponents<br>
✓&nbsp;&nbsp;&nbsp;Research proposal/Thesis/Dissertation Outline
</p>

<p>Send the digital copy of the fully accomplished documents through this email. <strong>As you reply to this email with the requirements, please CC your adviser.</strong> Thank you.</p>

<p>Please take note of the following:</p>
<p>
<em>*Do not change the format and font style of the ISO registered forms</em><br>
<em>*Do not make another email thread to send your requirements. Only reply to this email thread for easy tracking and to avoid confusion on our part.</em><br>
<em>*Please be guided with our process cycle time (see the General Guidelines for more info). We process applications during working days only.</em>
</p>

<p>--Best regards,<br>
TAU-REO</p>';

$stmt = $conn->prepare("UPDATE email_templates SET body = ? WHERE template_code = 'REPLY_INTENT'");
$stmt->bind_param("s", $body);

if ($stmt->execute()) {
    echo "✅ Template updated successfully!\n";
    echo "The REPLY_INTENT template now has proper HTML formatting.\n";
} else {
    echo "❌ Failed to update template: " . $conn->error . "\n";
}

closeDBConnection($conn);
