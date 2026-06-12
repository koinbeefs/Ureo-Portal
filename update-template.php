<?php
require_once 'config/config.php';
require_once 'includes/functions.php';

$conn = getDBConnection();

$sql = "UPDATE email_templates 
SET body = 'Greetings from TAU-REO!

Please read the general guidelines for research ethics review and evaluation. Then, kindly accomplish the following documents:

✓ Application form TAU-REO-QF-01 (see attached file)
✓ Research Ethics Review Category Form TAU-REO-QF-02 (see attached file)
✓ CV of proponents
✓ Research proposal/Thesis/Dissertation Outline

Send the digital copy of the fully accomplished documents through this portal. As you upload the requirements, please ensure all forms are properly filled and signed. Thank you.

Please take note of the following:

* Do not change the format and font style of the ISO registered forms
* Please fill out the forms completely before uploading
* Please be guided with our process cycle time (see the General Guidelines for more info). We process applications during working days only.

Best regards,
TAU-REO'
WHERE template_code = 'REPLY_INTENT'";

if ($conn->query($sql)) {
    echo "Template updated successfully!";
} else {
    echo "Error: " . $conn->error;
}

closeDBConnection($conn);
