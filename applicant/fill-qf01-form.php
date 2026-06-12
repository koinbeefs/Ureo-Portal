<?php
use PhpOffice\PhpWord\TemplateProcessor;

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

$queue_number = $_SESSION['queue_number'] ?? $_GET['queue'] ?? null;
$is_review = isset($_GET['review']) && $_GET['review'] == 1;

if (!$queue_number) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No queue number provided']);
        exit;
    }
    die('Error: No queue number provided');
}

// In review mode, we usually come from staff/urec which has different session logic
if (!$is_review) {
    requireApplicantLogin();
} else {
    // Basic role check for review mode
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin', 'urec'])) {
        die('Unauthorized access');
    }
}

// Check if QF-02 is completed to auto-check the category form
$qf02_completed = false;
$conn = getDBConnection();
$check_qf02 = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02' AND file_generated = 1");
$check_qf02->bind_param('s', $queue_number);
$check_qf02->execute();
$qf02_completed = $check_qf02->get_result()->num_rows > 0;

$app_data = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_data->bind_param('s', $queue_number);
$app_data->execute();
$app_result = $app_data->get_result();
$application_data = $app_result->fetch_assoc();
$applicant_name = $application_data['applicant_name'] ?? '';
$research_title = $application_data['research_title'] ?? '';

// Load existing form data if in review mode
$saved_data = [];
if ($is_review) {
    $load_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf01'");
    $load_stmt->bind_param('s', $queue_number);
    $load_stmt->execute();
    $load_res = $load_stmt->get_result()->fetch_assoc();
    if ($load_res) {
        $saved_data = json_decode($load_res['form_data'], true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Load Composer autoloader (adjust path if needed)
    require_once '../vendor/autoload.php';
    
    // Get database connection for file uploads
    $conn = getDBConnection();

    // Collect and sanitize posted data
    $data = $_POST;

    // Handle checkboxes → ☑ or ☐
    $checkboxes = [
        'human_use', 'animal_welfare', 'plant_use', 'microbio_use',
        'engineering', 'it_use', 'food_tech', 'review_yes', 'review_no', 
        'attached_outline', 'attached_category_form', 'attached_cv', 
        'attached_consent_form', 'attached_other_forms',
    ];

    foreach ($checkboxes as $cb) {
        $data[$cb] = isset($data[$cb]) && $data[$cb] === 'on' ? '☑' : '☐';
    }

    // Set defaults for missing/empty fields
    $defaults = [
        'duration'                 => '',
        'start_date'               => '',
        'end_date'                 => '',
        'members'                  => 'N/A',
        'department'               => '',
        'email'                    => '',
        'phone'                    => '',
        'adviser_email'            => '',
        'funding'                  => 'N/A',
        'review_status'            => '',
        'exec_summary'             => '',
        'problem_objectives'       => '',
        'justification'            => '',
        'data_collection_analysis' => '',
        'pilot_or_part'            => '',
        'location'                 => '',
        'human_role'               => '',
    ];

    $data = array_merge($defaults, $data);

    try {
        // Load the template
        $templateProcessor = new TemplateProcessor('qf01.docx');

        // Replace all variables
        $templateProcessor->setValues([
            'PROJECT_TITLE'            => $data['project_title'] ?? '',
            'DURATION'                 => $data['duration'],
            'START_DATE'               => formatDate($data['start_date']),
            'END_DATE'                 => formatDate($data['end_date']),

            'HUMAN_USE'                => $data['human_use'],
            'ANIMAL_WELFARE'           => $data['animal_welfare'],
            'PLANT_USE'                => $data['plant_use'],
            'MICROBIO_USE'             => $data['microbio_use'],
            'ENGINEERING'              => $data['engineering'],
            'IT_USE'                   => $data['it_use'],
            'FOOD_TECH'                => $data['food_tech'],

            'PRINCIPAL_NAME'           => $data['principal_name'] ?? '',
            'MEMBERS'                  => $data['members'],
            'DEPARTMENT'               => $data['department'],
            'EMAIL'                    => $data['email'],
            'PHONE'                    => $data['phone'],
            'ADVISER_EMAIL'            => $data['adviser_email'],
            'FUNDING'                  => $data['funding'],
            'REVIEW_YES'               => $data['review_yes'],
            'REVIEW_NO'                => $data['review_no'],
            'REVIEW_STATUS'            => $data['review_status'],

            'EXEC_SUMMARY'             => $data['exec_summary'],
            'PROBLEM_OBJECTIVES'       => $data['problem_objectives'],
            'JUSTIFICATION'            => $data['justification'],
            'DATA_COLLECTION_ANALYSIS' => $data['data_collection_analysis'],
            'PILOT_OR_PART'            => $data['pilot_or_part'],
            'LOCATION'                 => $data['location'],
            'HUMAN_ROLE'               => $data['human_role'],

            'OT'        => $data['attached_outline'],
            'CY'        => $data['attached_category_form'],
            'CV'        => $data['attached_cv'],
            'CF'        => $data['attached_consent_form'],
            'OS'        => $data['attached_other_forms'],

            'REQUESTOR_NAME' => $data['requestor_name'] ?? '',
            'DATE_FILLED'    => date('F d, Y'),
            'ADVISER_NAME'   => $data['adviser_name'],
            'DATE_SIGNED'    => formatDate($data['date_signed'])
        ]);

        // Note: Signature logic for document generation will be handled in the final-packet generator.
        
        // Generate output filename
        $outputFile = 'TAU-REO-QF-01_Filled_' . $queue_number . '.docx';

        // Save the filled document
        $templateProcessor->saveAs($outputFile);
        
        // Store form data in fillable_forms table
        $formDataJson = json_encode($data);
        $formType = 'qf01';
        
        // Check if form already exists
        $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
        $checkStmt->bind_param('ss', $queue_number, $formType);
        $checkStmt->execute();
        $existingForm = $checkStmt->get_result()->fetch_assoc();
        
        if ($existingForm) {
            $updateStmt = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW(), file_generated = 1 WHERE queue_number = ? AND form_type = ?");
            $updateStmt->bind_param('sss', $formDataJson, $queue_number, $formType);
            $updateStmt->execute();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, file_generated) VALUES (?, ?, ?, 1)");
            $insertStmt->bind_param('sss', $queue_number, $formType, $formDataJson);
            $insertStmt->execute();
        }
        
        // Handle file uploads for attached documents
        $uploadDir = '../uploads/' . $queue_number . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $uploadMapping = [
            'file_outline'       => ['type' => 'proposal',      'name' => 'Research proposal/Thesis/Dissertation Outline'],
            'file_category_form' => ['type' => 'qf02',          'name' => 'TAU-REO-QF-02'],
            'file_cv'            => ['type' => 'cv',             'name' => 'CV of proponents'],
            'file_consent_form'  => ['type' => 'consent_form',  'name' => 'Informed Consent Form'],
            'file_other_forms'   => ['type' => 'validation_cert','name' => 'Certificate of Instrument Validation']
        ];
        
        $uploadedFiles = [];
        foreach ($uploadMapping as $fileKey => $docInfo) {
            // Handle multiple CVs
            if ($fileKey === 'file_cv' && isset($_FILES['file_cv'])) {
                $files = $_FILES['file_cv'];
                if (is_array($files['name'])) {
                    for ($i = 0; $i < count($files['name']); $i++) {
                        if ($files['error'][$i] === UPLOAD_ERR_OK) {
                            $tmpName = $files['tmp_name'][$i];
                            $fileName = basename($files['name'][$i]);
                            $fileSize = $files['size'][$i];
                            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                            $uniqueName = 'cv_' . time() . '_' . uniqid() . '.' . $fileExt;
                            $targetPath = $uploadDir . $uniqueName;
                            if (move_uploaded_file($tmpName, $targetPath)) {
                                $stmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, file_size) VALUES (?, 'cv', ?, ?, ?)");
                                $relPath = 'uploads/' . $queue_number . '/' . $uniqueName;
                                $stmt->bind_param('sssi', $queue_number, $fileName, $relPath, $fileSize);
                                $stmt->execute();
                            }
                        }
                    }
                    continue; // Skip the default handling for single file_cv
                }
            }

            if (isset($_FILES[$fileKey]) && $_FILES[$fileKey]['error'] === UPLOAD_ERR_OK) {
                $tmpName  = $_FILES[$fileKey]['tmp_name'];
                $fileName = basename($_FILES[$fileKey]['name']);
                $fileSize = $_FILES[$fileKey]['size'];
                $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                $uniqueName = $docInfo['type'] . '_' . time() . '_' . uniqid() . '.' . $fileExt;
                $targetPath = $uploadDir . $uniqueName;
                
                if (move_uploaded_file($tmpName, $targetPath)) {
                    $stmt = $conn->prepare("INSERT INTO documents (queue_number, document_type, document_name, file_path, file_size) VALUES (?, ?, ?, ?, ?)");
                    $relPath = 'uploads/' . $queue_number . '/' . $uniqueName;
                    $stmt->bind_param('ssssi', $queue_number, $docInfo['type'], $docInfo['name'], $relPath, $fileSize);
                    $stmt->execute();
                    $uploadedFiles[] = $docInfo['name'];
                }
            }
        }

        // Handle Signatures (Temporary images, no DB)
        $sigDir = $uploadDir . 'signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0777, true);

        if (isset($_FILES['signature_proponent']) && $_FILES['signature_proponent']['error'] === UPLOAD_ERR_OK) {
            $path = $sigDir . 'proponent_sig_qf01.png'; // Enforce unique naming per form
            move_uploaded_file($_FILES['signature_proponent']['tmp_name'], $path);
        }
        if (isset($_FILES['signature_adviser']) && $_FILES['signature_adviser']['error'] === UPLOAD_ERR_OK) {
            $path = $sigDir . 'adviser_sig_qf01.png';
            move_uploaded_file($_FILES['signature_adviser']['tmp_name'], $path);
        }

        // Call PHP AI classifier
        $aiResult = null;
        try {
            require_once 'automation/TrainableEthicsClassifier.php';
            $classifier = new TrainableEthicsClassifier();

            $sectionC = trim(
                ($data['exec_summary'] ?? '') . ' ' .
                ($data['problem_objectives'] ?? '') . ' ' .
                ($data['justification'] ?? '') . ' ' .
                ($data['data_collection_analysis'] ?? '') . ' ' .
                ($data['pilot_or_part'] ?? '') . ' ' .
                ($data['location'] ?? '') . ' ' .
                ($data['human_role'] ?? '')
            );

            $originalTypes = [];
            $categoryMap = [
                'human_use'     => 'Human Use',
                'animal_welfare'=> 'Animal Welfare',
                'plant_use'     => 'Plant Use',
                'microbio_use'  => 'Microbiological/Biotechnological Use',
                'engineering'   => 'Engineering',
                'it_use'        => 'Information Technology Use',
                'food_tech'     => 'Food Technology Use'
            ];
            foreach ($categoryMap as $key => $label) {
                if ($data[$key] === '☑') {
                    $originalTypes[] = $label;
                }
            }

            $aiResult = $classifier->classify($sectionC, $originalTypes);

            $aiResultFile = $uploadDir . 'ai_classification.json';
            file_put_contents($aiResultFile, json_encode([
                'timestamp'       => date('Y-m-d H:i:s'),
                'section_c_text'  => $sectionC,
                'section_c_fields'=> [
                    'exec_summary'             => $data['exec_summary'] ?? '',
                    'problem_objectives'       => $data['problem_objectives'] ?? '',
                    'justification'            => $data['justification'] ?? '',
                    'data_collection_analysis' => $data['data_collection_analysis'] ?? '',
                    'pilot_or_part'            => $data['pilot_or_part'] ?? '',
                    'location'                 => $data['location'] ?? '',
                    'human_role'               => $data['human_role'] ?? ''
                ],
                'original_types' => $originalTypes,
                'ai_prediction'  => $aiResult,
                'staff_reviewed' => false,
                'staff_feedback' => null
            ], JSON_PRETTY_PRINT));

            $formDataFile = $uploadDir . 'form_data.json';
            file_put_contents($formDataFile, json_encode($data, JSON_PRETTY_PRINT));
        } catch (Exception $e) {
            error_log('PHP AI Classification Error: ' . $e->getMessage());
        }
        
        if (file_exists($outputFile)) {
            $fileContent = base64_encode(file_get_contents($outputFile));
            unlink($outputFile);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success'           => true,
                'message'           => 'Form completed successfully!',
                'filename'          => basename($outputFile),
                'fileContent'       => $fileContent,
                'uploadedFiles'     => $uploadedFiles,
                'ai_classification' => $aiResult ? 'completed' : 'unavailable'
            ]);
            exit;
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error: Could not create the document.']);
            exit;
        }

    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . htmlspecialchars($e->getMessage())]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAU REO · QF-01 Ethics Review Form</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --navy: #0f2942;
            --gold: #c9993a;
            --cream: #faf8f3;
            --white: #ffffff;
            --gray-100: #f4f2ed;
            --gray-200: #e8e4da;
            --gray-400: #a09880;
            --gray-600: #5a5040;
            --blue-soft: #d0e4f0;
            --shadow: 0 4px 24px rgba(15,41,66,0.10);
            --shadow-lg: 0 12px 48px rgba(15,41,66,0.16);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--cream);
            color: var(--navy);
            min-height: 100vh;
        }

        /* ── Page header ── */
        .page-header {
            background: var(--navy);
            padding: 20px 32px;
            display: flex;
            align-items: center;
            gap: 18px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 16px rgba(0,0,0,0.25);
        }
        .page-header .brand {
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.15);
            padding-right: 18px;
        }
        .page-header .page-title {
            color: rgba(255,255,255,0.85);
            font-size: 0.9rem;
            font-weight: 400;
            letter-spacing: 0.02em;
        }

        /* ── Outer wrapper ── */
        .form-wrapper {
            max-width: 920px;
            margin: 40px auto 80px;
            padding: 0 24px;
        }

        .form-heading {
            margin-bottom: 36px;
            padding-bottom: 24px;
            border-bottom: 2px solid var(--gray-200);
        }
        .form-heading h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2rem;
            color: var(--navy);
            margin-bottom: 8px;
        }
        .form-heading p {
            color: var(--gray-400);
            font-size: 0.95rem;
            font-weight: 300;
        }

        /* ── Sections ── */
        .form-section {
            background: var(--white);
            border-radius: 12px;
            padding: 32px;
            margin-bottom: 24px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }

        .section-title {
            font-family: 'Playfair Display', serif;
            font-size: 1.15rem;
            color: var(--navy);
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .section-badge {
            background: var(--navy);
            color: var(--gold);
            font-family: 'DM Sans', sans-serif;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        /* ── Fields ── */
        .field-group { margin-bottom: 20px; }

        .field-group label {
            display: block;
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--gray-600);
            margin-bottom: 6px;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .field-group input,
        .field-group textarea,
        .field-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            color: var(--navy);
            background: var(--cream);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none;
        }
        .field-group input:focus,
        .field-group textarea:focus {
            border-color: var(--gold);
            box-shadow: 0 0 0 3px rgba(201,153,58,0.15);
            background: var(--white);
        }
        .field-group textarea {
            min-height: 110px;
            resize: vertical;
            line-height: 1.6;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── Checkbox list ── */
        .check-list-group { display: flex; flex-direction: column; gap: 8px; margin-top: 4px; }

        .check-list-item {
            display: flex;
            align-items: flex-start;
            gap: 14px;
            padding: 13px 16px;
            border-radius: 8px;
            border: 1.5px solid var(--gray-200);
            background: var(--cream);
            cursor: pointer;
            transition: all 0.2s;
        }
        .check-list-item:hover {
            border-color: var(--gold);
            background: var(--white);
        }
        .check-list-item input[type="checkbox"] {
            width: 17px;
            height: 17px;
            margin-top: 2px;
            flex-shrink: 0;
            accent-color: var(--navy);
            cursor: pointer;
        }
        .check-list-item span {
            font-size: 0.93rem;
            color: var(--gray-600);
            line-height: 1.5;
        }

        .check-inline-group { display: flex; gap: 12px; margin-top: 4px; }
        .check-inline-group .check-list-item { flex: 1; justify-content: center; }

        /* ── Upload section ── */
        .upload-reveal {
            display: none;
            margin-top: 8px;
            margin-left: 32px;
            padding: 14px 18px;
            background: var(--blue-soft);
            border-left: 3px solid var(--navy);
            border-radius: 0 8px 8px 0;
        }
        .upload-reveal.show { display: block; }
        .upload-reveal label {
            font-size: 0.8rem;
            font-weight: 600;
            color: var(--navy);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .upload-reveal input[type="file"] {
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            color: var(--navy);
        }

        /* ── Note box ── */
        .note-box {
            background: var(--blue-soft);
            border-left: 4px solid var(--navy);
            padding: 12px 16px;
            border-radius: 0 8px 8px 0;
            font-size: 0.87rem;
            color: var(--navy);
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* ── Submit ── */
        .submit-area { text-align: center; margin-top: 36px; }

        .btn-submit {
            background: var(--navy);
            color: var(--white);
            border: none;
            padding: 16px 52px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.25s;
            box-shadow: var(--shadow);
        }
        .btn-submit:hover {
            background: var(--gold);
            color: var(--navy);
            box-shadow: var(--shadow-lg);
            transform: translateY(-1px);
        }
        .btn-submit:disabled {
            opacity: 0.55;
            cursor: not-allowed;
            transform: none;
        }

        .group-label {
            font-size: 0.82rem;
            font-weight: 600;
            color: var(--gray-600);
            letter-spacing: 0.04em;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: block;
        }

        @media (max-width: 640px) {
            .field-row { grid-template-columns: 1fr; }
            .form-wrapper { padding: 0 12px; }
            .form-section { padding: 20px; }
        }
    </style>
</head>
<body>

<header class="page-header">
    <div class="brand">TAU · REO</div>
    <div class="page-title">QF-01 · Research Ethics Review Application Form</div>
</header>

<div class="form-wrapper">

    <div class="form-heading">
        <h1>Research Ethics Review Form</h1>
        <p>TAU-REO-QF-01 · Complete all sections and attach the required documents before submission.</p>
    </div>

    <form method="post" enctype="multipart/form-data" id="qf01Form">

        <!-- A. Preliminaries -->
        <div class="form-section">
            <div class="section-title"><span class="section-badge">A</span> Preliminaries</div>

            <div class="field-group">
                <label>Project / Study Title</label>
                <input type="text" name="project_title" value="<?php echo htmlspecialchars($research_title); ?>" required>
            </div>

            <div class="field-group">
                <label>Project / Study Duration</label>
                <input type="text" name="duration" placeholder="e.g. 12 months">
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>Planned Start Date</label>
                    <input type="date" name="start_date">
                </div>
                <div class="field-group">
                    <label>Planned End Date</label>
                    <input type="date" name="end_date">
                </div>
            </div>

            <div class="field-group">
                <span class="group-label">Type of Ethics Review</span>
                <div class="check-list-group">
                    <label class="check-list-item"><input type="checkbox" name="human_use"><span>Human Use</span></label>
                    <label class="check-list-item"><input type="checkbox" name="animal_welfare"><span>Animal Welfare</span></label>
                    <label class="check-list-item"><input type="checkbox" name="plant_use"><span>Plant Use</span></label>
                    <label class="check-list-item"><input type="checkbox" name="microbio_use"><span>Microbiological / Biotechnological Use</span></label>
                    <label class="check-list-item"><input type="checkbox" name="engineering"><span>Engineering</span></label>
                    <label class="check-list-item"><input type="checkbox" name="it_use"><span>Information Technology Use</span></label>
                    <label class="check-list-item"><input type="checkbox" name="food_tech"><span>Food Technology Use</span></label>
                </div>
            </div>
        </div>

        <!-- B. Administrative Information -->
        <div class="form-section">
            <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>

            <div class="field-group">
                <label>Name of Principal Applicant</label>
                <input type="text" name="principal_name" value="<?php echo htmlspecialchars($applicant_name); ?>" required>
            </div>

            <div class="field-group">
                <label>Name of Research Members (if any)</label>
                <textarea name="members" placeholder="Separate names with commas or write N/A"></textarea>
            </div>

            <div class="field-group">
                <label>Office / University / Department</label>
                <input type="text" name="department">
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>Contact Email Address</label>
                    <input type="email" name="email">
                </div>
                <div class="field-group">
                    <label>Contact Telephone Number</label>
                    <input type="tel" name="phone">
                </div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>Email Address of Adviser</label>
                    <input type="email" name="adviser_email">
                </div>
                <div class="field-group">
                    <label>Source of Funding (if any)</label>
                    <input type="text" name="funding" placeholder="e.g. DOST, self-funded, N/A">
                </div>
            </div>

            <div class="field-group">
                <span class="group-label">Has the project/study undergone research ethics review?</span>
                <div class="check-inline-group">
                    <label class="check-list-item"><input type="checkbox" name="review_yes"><span>YES</span></label>
                    <label class="check-list-item"><input type="checkbox" name="review_no"><span>NO</span></label>
                </div>
            </div>

            <div class="field-group">
                <label>If yes — indicate where and status of review</label>
                <input type="text" name="review_status" placeholder="e.g. Certificate of Validation">
            </div>
        </div>

        <!-- C. Project/Study Description -->
        <div class="form-section">
            <div class="section-title"><span class="section-badge">C</span> Project / Study Description</div>

            <div class="field-group">
                <label>C.1 Executive Summary <span style="font-weight:300; text-transform:none;">(max 300 words)</span></label>
                <textarea name="exec_summary" placeholder="Rationale and purpose of the study..."></textarea>
            </div>

            <div class="field-group">
                <label>C.2 Statement of the Problem / Objectives</label>
                <textarea name="problem_objectives"></textarea>
            </div>

            <div class="field-group">
                <label>Justification of the Study</label>
                <textarea name="justification"></textarea>
            </div>

            <div class="field-group">
                <label>Plan / Procedure for Data Collection and Analysis</label>
                <textarea name="data_collection_analysis"></textarea>
            </div>

            <div class="field-group">
                <label>Pilot Study / Continuation / Part of a Larger Project?</label>
                <textarea name="pilot_or_part"></textarea>
            </div>

            <div class="field-group">
                <label>C.3 Location / Site of the Study</label>
                <input type="text" name="location">
            </div>

            <div class="field-group">
                <label>Role of Human Subjects (if any)</label>
                <textarea name="human_role"></textarea>
            </div>
        </div>

        <!-- Applications Attached -->
        <div class="form-section">
            <div class="section-title">Applications Attached</div>
            <div class="note-box">Check each item that is included with this application and upload the corresponding file.</div>

            <div class="check-list-group">

                <label class="check-list-item">
                    <input type="checkbox" name="attached_outline" id="check_outline" onchange="toggleUpload('outline')">
                    <span>Full copy of the research proposal / thesis outline including instruments to be used</span>
                </label>
                <div class="upload-reveal" id="upload_outline">
                    <label>Upload Research Proposal / Thesis Outline (PDF only)</label>
                    <input type="file" name="file_outline" accept=".pdf">
                </div>

                <label class="check-list-item">
                    <input type="checkbox" name="attached_category_form" id="check_category_form" onchange="toggleUpload('category_form')" <?php echo $qf02_completed ? 'checked' : ''; ?>>
                    <span>Accomplished Research Ethics Category Form (TAU-REO-QF-02)</span>
                </label>
                <div class="upload-reveal" id="upload_category_form">
                    <label>Upload TAU-REO-QF-02 (PDF only)</label>
                    <input type="file" name="file_category_form" accept=".pdf">
                </div>

                <label class="check-list-item">
                    <input type="checkbox" name="attached_cv" id="check_cv" onchange="toggleUpload('cv')">
                    <span>Curriculum Vitae (Upload multiple if required)</span>
                </label>
                <div class="upload-reveal" id="upload_cv">
                    <div id="cv_upload_container">
                        <div class="field-group mb-2 d-flex gap-2 align-items-center">
                            <input type="file" name="file_cv[]" accept=".pdf" class="form-control-sm">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="addCVField()">+ Add More</button>
                        </div>
                    </div>
                </div>

                <label class="check-list-item">
                    <input type="checkbox" name="attached_consent_form" id="check_consent_form" onchange="toggleUpload('consent_form')">
                    <span>Informed Consent Form</span>
                </label>
                <div class="upload-reveal" id="upload_consent_form">
                    <label>Upload Informed Consent Form (PDF only)</label>
                    <input type="file" name="file_consent_form" accept=".pdf">
                </div>

                <label class="check-list-item">
                    <input type="checkbox" name="attached_other_forms" id="check_other_forms" onchange="toggleUpload('other_forms')">
                    <span>Certificate of Instrument Validation / Guardian or Parental Consent</span>
                </label>
                <div class="upload-reveal" id="upload_other_forms">
                    <label>Upload Certificate / Consent (PDF only)</label>
                    <input type="file" name="file_other_forms" accept=".pdf">
                </div>

            </div>
        </div>

        <!-- Declaration -->
        <div class="form-section">
            <div class="section-title">Declaration</div>

            <div class="field-row">
                <div class="field-group">
                    <label>Name of the Principal Applicant</label>
                    <input type="text" name="requestor_name" placeholder="Print your full name">
                </div>
                <div class="field-group">
                    <label>Date Filled</label>
                    <input type="date" name="date_filled">
                </div>
            </div>

            <div class="field-group">
                <label>Principal Applicant Signature</label>
                <input type="file" name="signature_proponent" accept="image/*" class="form-control-sm mb-2">
                <div id="sig_preview_proponent" class="sig-placeholder">Signature View</div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>Name of Adviser</label>
                    <input type="text" name="adviser_name" placeholder="Adviser's full name">
                </div>
                <div class="field-group">
                    <label>Date Signed</label>
                    <input type="date" name="date_signed">
                </div>
            </div>

            <div class="field-group">
                <label>Adviser Signature</label>
                <input type="file" name="signature_adviser" accept="image/*" class="form-control-sm mb-2">
                <div id="sig_preview_adviser" class="sig-placeholder">Signature View</div>
            </div>
        </div>

        <div class="submit-area">
            <button type="submit" class="btn-submit" id="submitBtn">Finalize &amp; Submit Formfiller</button>
            <div id="autosave-status" class="mt-2 text-muted small"></div>
        </div>

        <style>
            .sig-placeholder {
                width: 200px;
                height: 80px;
                border: 2px dashed var(--gray-200);
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--gray-400);
                font-size: 0.8rem;
                background: var(--cream);
                border-radius: 8px;
            }
            .sig-placeholder img {
                max-width: 100%;
                max-height: 100%;
            }
        </style>

    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
function toggleUpload(type) {
    const checkbox     = document.getElementById('check_' + type);
    const uploadSection = document.getElementById('upload_' + type);
    if (!uploadSection) return;
    const fileInputs   = uploadSection.querySelectorAll('input[type="file"]');

    if (checkbox.checked) {
        uploadSection.classList.add('show');
        fileInputs.forEach(i => i.required = true);
    } else {
        uploadSection.classList.remove('show');
        fileInputs.forEach(i => {
            i.required = false;
            i.value = '';
        });
    }
}

function addCVField() {
    const container = document.getElementById('cv_upload_container');
    const div = document.createElement('div');
    div.className = 'field-group mb-2 d-flex gap-2 align-items-center';
    div.innerHTML = `
        <input type="file" name="file_cv[]" accept=".pdf" class="form-control-sm" required>
        <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.parentElement.remove()">Remove</button>
    `;
    container.appendChild(div);
}

function updateDuration() {
    const start = document.querySelector('[name="start_date"]').value;
    const end = document.querySelector('[name="end_date"]').value;
    if (start && end) {
        const s = new Date(start);
        const e = new Date(end);
        if (e < s) return;
        let months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth());
        if (e.getDate() > s.getDate()) months++;
        document.querySelector('[name="duration"]').value = months + (months > 1 ? ' months' : ' month');
    }
}

function handleSignaturePreview(input, previewId) {
    const preview = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.innerHTML = `<img src="${e.target.result}" alt="Signature">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Debounced Autosave
let autosaveTimer;
function triggerAutosave() {
    clearTimeout(autosaveTimer);
    document.getElementById('autosave-status').innerHTML = '<i class="bi bi-clock"></i> Drafting...';
    autosaveTimer = setTimeout(() => {
        const formData = new FormData(document.getElementById('qf01Form'));
        formData.append('form_type', 'qf01');
        
        const dataForJson = new FormData();
        for (let [key, value] of formData.entries()) {
            if (!(value instanceof File)) {
                dataForJson.append(key, value);
            }
        }
        dataForJson.append('form_type', 'qf01');

        fetch('autosave-progress.php', { method: 'POST', body: dataForJson })
            .then(r => r.json())
            .then(res => {
                if (res.success) {
                    document.getElementById('autosave-status').innerHTML = `<i class="bi bi-check2"></i> Draft saved at ${res.timestamp}`;
                }
            })
            .catch(() => {
                document.getElementById('autosave-status').innerHTML = '<i class="bi bi-exclamation-triangle"></i> Autosave failed';
            });
    }, 2000);
}

// Handle form submission
document.getElementById('qf01Form').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.innerHTML = 'Finalizing Submission…';

    fetch('fill-qf01-form.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'formCompleted', formType: 'qf01' }, '*');
                } else {
                    window.location.href = 'documents.php?success=form_submitted';
                }
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false; btn.innerHTML = 'Finalize & Submit Formfiller';
            }
        });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[name="start_date"]').addEventListener('change', updateDuration);
    document.querySelector('[name="end_date"]').addEventListener('change', updateDuration);
    
    document.querySelector('[name="signature_proponent"]').addEventListener('change', function() {
        handleSignaturePreview(this, 'sig_preview_proponent');
    });
    document.querySelector('[name="signature_adviser"]').addEventListener('change', function() {
        handleSignaturePreview(this, 'sig_preview_adviser');
    });

    document.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('input', triggerAutosave);
        el.addEventListener('change', triggerAutosave);
    });

    ['outline', 'category_form', 'cv', 'consent_form', 'other_forms'].forEach(type => {
        const checkbox = document.getElementById('check_' + type);
        if (checkbox && checkbox.checked) toggleUpload(type);
    });

    <?php if ($is_review): ?>
    // Disable all interactive elements
    document.querySelectorAll('input, textarea, select').forEach(el => {
        el.disabled = true;
        el.readOnly = true;
    });
    
    // Hide submit/action buttons
    document.querySelectorAll('.btn-submit, .sig-actions').forEach(el => {
        if(el) el.style.display = 'none';
    });
    
    // Refill data from saved_data
    const savedData = <?php echo json_encode($saved_data); ?>;
    if (savedData) {
        Object.keys(savedData).forEach(key => {
            const value = savedData[key];
            const $el = document.querySelector('[name="' + key + '"]');
            
            if ($el) {
                if ($el.type === 'checkbox') {
                    $el.checked = (value === 'on' || value === '☑' || value === '✓' || value === true);
                } else if ($el.type === 'radio') {
                    const radio = document.querySelector('[name="' + key + '"][value="' + value + '"]');
                    if (radio) radio.checked = true;
                } else {
                    $el.value = value;
                }
            }
        });
    }

    // Load existing signatures for preview in review mode
    const sigProponent = "<?php echo file_exists("../uploads/{$queue_number}/signatures/proponent_sig_qf01.png") ? "../uploads/{$queue_number}/signatures/proponent_sig_qf01.png" : ""; ?>";
    const sigAdviser = "<?php echo file_exists("../uploads/{$queue_number}/signatures/adviser_sig_qf01.png") ? "../uploads/{$queue_number}/signatures/adviser_sig_qf01.png" : ""; ?>";
    
    if (sigProponent) {
        const sprop = document.getElementById('sig_preview_proponent');
        if(sprop) sprop.innerHTML = `<img src="${sigProponent}?t=${Date.now()}" style="max-height: 80px; pointer-events: none; user-select: none;" oncontextmenu="return false;" ondragstart="return false;">`;
        const iprop = document.querySelector('input[name="signature_proponent"]');
        if(iprop) iprop.style.display = 'none';
    }
    if (sigAdviser) {
        const sadv = document.getElementById('sig_preview_adviser');
        if(sadv) sadv.innerHTML = `<img src="${sigAdviser}?t=${Date.now()}" style="max-height: 80px; pointer-events: none; user-select: none;" oncontextmenu="return false;" ondragstart="return false;">`;
        const iadv = document.querySelector('input[name="signature_adviser"]');
        if(iadv) iadv.style.display = 'none';
    }

    if (window.self !== window.top) {
        document.querySelectorAll('.page-header').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.form-wrapper').forEach(el => el.style.marginTop = '0');
    }
    <?php endif; ?>
});
</script>
</body>
</html>