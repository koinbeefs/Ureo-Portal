<?php
// fill-qf02-form.php

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';
use PhpOffice\PhpWord\TemplateProcessor;

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

// Check if current user is staff/admin
$is_staff = (isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff', 'admin', 'urec'])) || $is_review;

if (!$is_review) {
    requireApplicantLogin();
} else {
    // Basic role check for review mode
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin', 'urec'])) {
        die('Unauthorized access');
    }
}

// Get applicant data for auto-filling
$conn = getDBConnection();
$app_data = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_data->bind_param('s', $queue_number);
$app_data->execute();
$app_result = $app_data->get_result();
$application_data = $app_result->fetch_assoc();
$applicant_name = $application_data['applicant_name'] ?? '';
$research_title = $application_data['research_title'] ?? '';

// Load existing form data if in review mode
$checklistSavedData = [];
if ($is_review) {
    $load_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $load_stmt->bind_param('s', $queue_number);
    $load_stmt->execute();
    $load_res = $load_stmt->get_result()->fetch_assoc();
    if ($load_res) {
        $checklistSavedData = json_decode($load_res['form_data'], true);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    require_once '../vendor/autoload.php';

    $conn = getDBConnection();

    $data = $_POST;

    // Handle Yes/No checkboxes → ✓ or empty
    $criteria = range(1, 20);
    $yesNoFields = [];
    foreach ($criteria as $num) {
        $yesNoFields["{$num}Y"] = isset($data["crit_{$num}_yes"]) && $data["crit_{$num}_yes"] === 'on' ? '✓' : '';
        $yesNoFields["{$num}N"] = isset($data["crit_{$num}_no"])  && $data["crit_{$num}_no"]  === 'on' ? '✓' : '';
        
        // Handle remarks - support both string (legacy) and array (new) formats
        $remarks = $data["crit_{$num}_remarks"] ?? '';
        if (is_array($remarks)) {
            // New format: array of remarks, get the latest one
            $latestRemark = end($remarks);
            $yesNoFields["{$num}R"] = is_array($latestRemark) ? trim($latestRemark['text'] ?? '') : trim($latestRemark);
        } else {
            // Legacy format: simple string
            $yesNoFields["{$num}R"] = trim($remarks);
        }
    }

    try {
        // Store form data in fillable_forms table (Persistence-First)
        $formDataJson = json_encode($data);
        $formType = 'qf02';
        
        $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
        $checkStmt->bind_param('ss', $queue_number, $formType);
        $checkStmt->execute();
        $existing = $checkStmt->get_result()->fetch_assoc();
        
        if ($existing) {
            $updateStmt = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW(), file_generated = 0 WHERE queue_number = ? AND form_type = ?");
            $updateStmt->bind_param('sss', $formDataJson, $queue_number, $formType);
            $updateStmt->execute();
        } else {
            $insertStmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, file_generated) VALUES (?, ?, ?, 0)");
            $insertStmt->bind_param('sss', $queue_number, $formType, $formDataJson);
            $insertStmt->execute();
        }

        // Handle Signatures (Temporary images, no DB)
        $uploadDir = '../uploads/' . $queue_number . '/';
        $sigDir = $uploadDir . 'signatures/';
        if (!is_dir($sigDir)) mkdir($sigDir, 0777, true);

        if (isset($_FILES['signature_proponent']) && $_FILES['signature_proponent']['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($_FILES['signature_proponent']['tmp_name'], $sigDir . 'proponent_sig_qf02.png');
        }
        if (isset($_FILES['signature_adviser']) && $_FILES['signature_adviser']['error'] === UPLOAD_ERR_OK) {
            move_uploaded_file($_FILES['signature_adviser']['tmp_name'], $sigDir . 'adviser_sig_qf02.png');
        }

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'QF-02 form-filler submitted successfully!'
        ]);
        exit;

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
    <title>TAU REO · QF-02 Research Ethics Review Category</title>
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
        .page-header { background: var(--navy); padding: 20px 32px; display: flex; align-items: center; gap: 18px; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 16px rgba(0,0,0,0.25); }
        .remark-indicator{color:#d32f2f;font-size:.7rem;font-weight:700;background:#ffebee;padding:2px 8px;border-radius:4px;display:flex;align-items:center;gap:4px;margin-top:4px}
        .feedback-banner{background:#fff8e1;border:1px solid #ffd54f;border-radius:12px;padding:20px;margin-bottom:30px;box-shadow:var(--shadow)}
        .feedback-banner h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:#795548;margin-bottom:12px;display:flex;align-items:center;gap:10px}
        .feedback-item{font-size:.9rem;background:white;padding:12px;border-radius:8px;margin-bottom:8px;border-left:4px solid #ffd54f}
        .feedback-meta{font-size:.7rem;color:#8d6e63;margin-bottom:4px;font-weight:600}
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
        }

        /* ── Wrapper ── */
        .form-wrapper {
            max-width: 1080px;
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

        .field-group input[type="text"],
        .field-group input[type="date"],
        .field-group textarea {
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
            min-height: 90px;
            resize: vertical;
            line-height: 1.6;
        }

        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        /* ── Criteria table ── */
        .criteria-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }

        .criteria-table thead tr {
            background: var(--navy);
            color: var(--white);
        }
        .criteria-table thead th {
            padding: 11px 16px;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }
        .criteria-table thead th:not(:first-child) {
            text-align: center;
            width: <?php echo $is_staff ? '70px' : '80px'; ?>;
        }
        <?php if ($is_staff): ?>
        .criteria-table thead th.remarks-col { width: auto; text-align: left; }
        <?php endif; ?>

        .criteria-table tbody tr {
            border-bottom: 1px solid var(--gray-200);
            transition: background 0.15s;
        }
        .criteria-table tbody tr:hover { background: var(--gray-100); }

        .criteria-table td {
            padding: 13px 16px;
            vertical-align: middle;
            color: var(--gray-600);
            line-height: 1.45;
        }
        .criteria-table td:not(:first-child) { text-align: center; }

        .criteria-table input[type="checkbox"] {
            width: 17px;
            height: 17px;
            cursor: pointer;
            accent-color: var(--navy);
        }

        .criteria-table input[type="text"] {
            width: 100%;
            padding: 8px 12px;
            border: 1.5px solid var(--gray-200);
            border-radius: 6px;
            font-family: 'DM Sans', sans-serif;
            font-size: 0.88rem;
            color: var(--navy);
            background: var(--cream);
            outline: none;
            transition: border-color 0.2s;
        }
        .criteria-table input[type="text"]:focus {
            border-color: var(--gold);
            background: var(--white);
        }

        /* ── Flash messages ── */
        .flash {
            padding: 14px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 0.92rem;
        }
        .flash.success { background: #e8f5e9; color: #2e7d32; border-left: 4px solid #2e7d32; }
        .flash.error   { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }

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
    <div class="page-title">QF-02 · Research Ethics Review Category Form</div>
</header>

<div class="form-wrapper">
    <?php
    // Fetch saved data for remark rendering (Sync with autosave)
    $checkStmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf02'");
    $checkStmt->bind_param('s', $queue_number);
    $checkStmt->execute();
    $saved = $checkStmt->get_result()->fetch_assoc();
    $checklistSavedData = $saved ? json_decode($saved['form_data'], true) : [];

    // DISPLAY GENERAL REMARKS IF ANY
    $gen_remarks = $checklistSavedData['general_remarks'] ?? [];
    if (!empty($gen_remarks)): ?>
        <div class="feedback-banner">
            <h3><i class="bi bi-chat-dots-fill"></i> Staff Feedback & Revision History</h3>
            <?php foreach (array_reverse($gen_remarks) as $remark): ?>
                <div class="feedback-item">
                    <div class="feedback-meta">
                        <?php echo strtoupper($remark['role']); ?> • <?php echo $remark['timestamp']; ?>
                    </div>
                    <?php echo htmlspecialchars($remark['text']); ?>
                </div>
            <?php endforeach; ?>
            <p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle"></i> Please address the points above in your updated submission.</p>
        </div>
    <?php endif; ?>

    <div class="form-heading">
        <h1>Research Ethics Review Category</h1>
        <p>TAU-REO-QF-02 · Answer each criterion honestly. <?php echo $is_staff ? 'Staff remarks column is visible.' : 'Complete all sections before generating the document.'; ?></p>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <div class="flash <?= strpos($_GET['msg'], 'Error') !== false ? 'error' : 'success' ?>">
            <?= htmlspecialchars($_GET['msg']) ?>
        </div>
    <?php endif; ?>

    <form id="qf02Form" method="post">

        <!-- Basic Information -->
        <div class="form-section">
            <div class="section-title"><span class="section-badge">A</span> Basic Information</div>

            <div class="field-group">
                <label>Title of Study</label>
                <input type="text" name="title" value="<?php echo htmlspecialchars($research_title); ?>" required
                    placeholder="Enter the full title of the research">
            </div>

            <div class="field-group">
                <label>Participants / Subjects in the Study (if any)</label>
                <textarea name="participants"
                    placeholder="e.g. Undergraduate students affiliated with TAU Bulalayaw, LGBTQIA+ community members…"></textarea>
            </div>
        </div>

        <!-- Criteria Checklist -->
        <div class="form-section">
            <div class="section-title"><span class="section-badge">B</span> Criteria Checklist</div>
            <div class="note-box">For each criterion, check <strong>Yes</strong> or <strong>No</strong> as it applies to your research.<?php echo $is_staff ? ' Add remarks in the remarks column where needed.' : ''; ?></div>

            <table class="criteria-table">
                <thead>
                    <tr>
                        <th>Criteria</th>
                        <th>Yes</th>
                        <th>No</th>
                        <?php if ($is_staff): ?>
                        <th class="remarks-col">Remarks (REO Staff)</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php
                $criteriaList = [
                    1  => "The research involves interaction with human participants, such as surveys, interviews, or clinical tests.",
                    2  => "The research involves the collection of identifiable or sensitive data (e.g. health data, biometric information).",
                    3  => "The research involves a vulnerable population (e.g. minors, pregnant women, elderly, persons with disabilities).",
                    4  => "The research involves physical, psychological, social, legal, or economic risk.",
                    5  => "The study requires informed consent from the participants.",
                    6  => "The research involves live animals for experimentation.",
                    7  => "The research involves procedures that could cause pain, distress, or discomfort to the animal.",
                    8  => "The research involves working with endangered, protected, or non-domestic species.",
                    9  => "The research protocol is aligned with Bureau of Animal Industry (BAI) requirements for animal care and use.",
                    10 => "The research involves genetically modified organisms (GMOs) or new varieties.",
                    11 => "The research involves field trials, environmental release, or agricultural practices that may affect biodiversity.",
                    12 => "The research involves the importation, exportation, or propagation of plant materials.",
                    13 => "The research involves handling of pathogenic microorganisms or bio-hazardous materials.",
                    14 => "The research involves the use of microorganisms that have potential health, safety, or environmental risks.",
                    15 => "The research involves the collection of personal data (e.g. data from social media, health data, or private information).",
                    16 => "The research involves software development, algorithms, or IT or computer systems to be tested with human participants.",
                    17 => "The research involves cyber security, privacy concerns, or data protection issues.",
                    18 => "The research involves the development or testing of machinery, equipment, or prototypes that could have risks to users.",
                    19 => "The research may have a negative impact on the environment (e.g. waste management, emissions, or energy consumption).",
                    20 => "The research involves potentially hazardous food production techniques (e.g. chemical additive, genetic modification).",
                ];
                foreach ($criteriaList as $num => $text): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($text) ?>
                        <?php renderFieldRemark("crit_{$num}", $checklistSavedData); ?>
                    </td>
                    <td><input type="checkbox" name="crit_<?= $num ?>_yes" id="y<?= $num ?>" <?= (isset($checklistSavedData["crit_{$num}_yes"]) && $checklistSavedData["crit_{$num}_yes"] === 'on') ? 'checked' : '' ?>></td>
                    <td><input type="checkbox" name="crit_<?= $num ?>_no"  id="n<?= $num ?>" <?= (isset($checklistSavedData["crit_{$num}_no"]) && $checklistSavedData["crit_{$num}_no"] === 'on') ? 'checked' : '' ?>></td>
                    <?php if ($is_staff): ?>
                    <?php 
                    $remark_value = $checklistSavedData["crit_{$num}_remarks"] ?? '';
                    if (is_array($remark_value)) {
                        $latestRemark = end($remark_value);
                        $remark_value = is_array($latestRemark) ? $latestRemark['text'] ?? '' : $latestRemark;
                    }
                    ?>
                    <td><input type="text" name="crit_<?= $num ?>_remarks" value="<?= htmlspecialchars($remark_value) ?>" placeholder="Remarks (if applicable)"></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Declaration -->
        <div class="form-section">
            <div class="section-title">Declaration</div>

            <div class="field-row">
                <div class="field-group">
                    <label>Name of Proponent</label>
                    <input type="text" name="proponent_name" value="<?php echo htmlspecialchars($applicant_name); ?>"
                        required placeholder="Enter your full name">
                </div>
                <div class="field-group">
                    <label>Date Filled</label>
                    <input type="date" name="date_filled" required>
                </div>
            </div>

            <div class="field-group">
                <label>Proponent Signature</label>
                <input type="file" name="signature_proponent" accept="image/*" class="form-control-sm mb-2">
                <div id="sig_preview_proponent" class="sig-placeholder">Signature View</div>
            </div>

            <div class="field-row">
                <div class="field-group">
                    <label>Name of Adviser</label>
                    <input type="text" name="adviser_name" required placeholder="Adviser's full name">
                </div>
                <div class="field-group">
                    <label>Date Signed by Adviser</label>
                    <input type="date" name="date_signed" required>
                </div>
            </div>

            <div class="field-group">
                <label>Adviser Signature</label>
                <input type="file" name="signature_adviser" accept="image/*" class="form-control-sm mb-2">
                <div id="sig_preview_adviser" class="sig-placeholder">Signature View</div>
            </div>
        </div>

        <div class="submit-area">
            <button type="submit" class="btn-submit" id="submitBtn">Finalize &amp; Submit QF-02</button>
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

<script>
    // Prevent checking both Yes and No for the same criterion
    document.querySelectorAll('.criteria-table input[type="checkbox"]').forEach(cb => {
        cb.addEventListener('change', function () {
            const group = this.name.replace(/_yes|_no/, '');
            const yes = document.querySelector(`input[name="${group}_yes"]`);
            const no  = document.querySelector(`input[name="${group}_no"]`);
            if (this.checked) {
                if (this.name.endsWith('_yes')) no.checked  = false;
                if (this.name.endsWith('_no'))  yes.checked = false;
            }
        });
    });

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
        document.getElementById('autosave-status').innerHTML = 'Drafting...';
        autosaveTimer = setTimeout(() => {
            const formData = new FormData(document.getElementById('qf02Form'));
            formData.append('form_type', 'qf02');
            
            // Clean files for JSON autosave
            const dataForJson = new FormData();
            for (let [key, value] of formData.entries()) {
                if (!(value instanceof File)) dataForJson.append(key, value);
            }
            dataForJson.append('form_type', 'qf02');

            fetch('autosave-progress.php', { method: 'POST', body: dataForJson })
                .then(r => r.json())
                .then(res => {
                    if (res.success) document.getElementById('autosave-status').innerHTML = `Draft saved at ${res.timestamp}`;
                });
        }, 2000);
    }

    // AJAX submit
    document.getElementById('qf02Form').addEventListener('submit', function (e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        btn.disabled = true; btn.innerHTML = 'Submitting…';

        fetch('fill-qf02-form.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                if (window.parent !== window) {
                    window.parent.postMessage({ type: 'formCompleted', formType: 'qf02' }, '*');
                } else {
                    window.location.href = 'documents.php?success=qf02_submitted';
                }
            } else {
                alert('Error: ' + data.message);
                btn.disabled = false; btn.innerHTML = 'Finalize & Submit QF-02';
            }
        });
    });

    document.addEventListener('DOMContentLoaded', () => {
        // Signature listeners
        document.querySelector('[name="signature_proponent"]').addEventListener('change', function() {
            handleSignaturePreview(this, 'sig_preview_proponent');
        });
        document.querySelector('[name="signature_adviser"]').addEventListener('change', function() {
            handleSignaturePreview(this, 'sig_preview_adviser');
        });

        // Autosave listeners
        document.querySelectorAll('input, textarea').forEach(el => {
            el.addEventListener('input', triggerAutosave);
            el.addEventListener('change', triggerAutosave);
        });
    });
</script>

    <?php if ($is_review): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Disable all interactive elements
            document.querySelectorAll('input, textarea, select').forEach(el => {
                // DON'T disable remarks fields if we are staff
                if (!el.name || !el.name.includes('_remarks')) {
                    el.disabled = true;
                    el.readOnly = true;
                }
            });
            
            // Hide submit/action buttons
            document.querySelectorAll('.submit-area').forEach(el => el.style.display = 'none');
            
            // Refill data from saved_data
            const savedData = <?php echo json_encode($checklistSavedData); ?>;
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
                        } else if ($el.name.includes('_remarks')) {
                            // Handle remarks fields - support both string and array formats
                            if (Array.isArray(value)) {
                                // New format: array of remarks, get the latest one
                                const latestRemark = value[value.length - 1];
                                $el.value = (latestRemark && latestRemark.text) ? latestRemark.text : '';
                            } else {
                                // Legacy format: simple string
                                $el.value = value;
                            }
                        } else {
                            $el.value = value;
                        }
                    }
                });
            }

            // Load existing signatures for preview in review mode
            const sigProponent = "<?php echo file_exists("../uploads/{$queue_number}/signatures/proponent_sig_qf02.png") ? "../uploads/{$queue_number}/signatures/proponent_sig_qf02.png" : ""; ?>";
            const sigAdviser = "<?php echo file_exists("../uploads/{$queue_number}/signatures/adviser_sig_qf02.png") ? "../uploads/{$queue_number}/signatures/adviser_sig_qf02.png" : ""; ?>";
            
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

            // Specific UI adjustments for review mode
            if (window.self !== window.top) {
                document.querySelectorAll('.page-header').forEach(el => el.style.display = 'none'); // Hide header if in iframe
                document.querySelectorAll('.form-wrapper').forEach(el => el.style.marginTop = '0');
                document.body.style.background = 'white'; // Blend with paper
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
