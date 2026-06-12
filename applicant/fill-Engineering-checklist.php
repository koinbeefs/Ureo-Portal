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

// In review mode, allow staff access
if (!$is_review) {
    requireApplicantLogin();
} else {
    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin', 'urec'])) {
        die('Unauthorized access');
    }
}
$conn = getDBConnection();

// Auto-fill applicant data
$app_data = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_data->bind_param('s', $queue_number);
$app_data->execute();
$application_data = $app_data->get_result()->fetch_assoc();
$applicant_name = $application_data['applicant_name'] ?? '';
$research_title = $application_data['research_title'] ?? '';

// Get saved form data if exists
$checklistSavedData = [];
$formType = 'engineering_checklist';
$checkStmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
$checkStmt->bind_param('ss', $queue_number, $formType);
$checkStmt->execute();
$existing = $checkStmt->get_result()->fetch_assoc();
if ($existing && $existing['form_data']) {
  $checklistSavedData = json_decode($existing['form_data'], true) ?? [];
}

// Get QF01 data for deeper auto-fill
$qf01Data = [];
$qfStmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'qf01'");
$qfStmt->bind_param('s', $queue_number);
$qfStmt->execute();
$qfRes = $qfStmt->get_result()->fetch_assoc();
if ($qfRes && $qfRes['form_data']) {
  $qf01Data = json_decode($qfRes['form_data'], true) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once '../vendor/autoload.php';

  $data = $_POST;

  // Build checklist vars E1Y/E1N/E1NA + conditional text fields
  $checklistVars = [];
  for ($i = 1; $i <= 24; $i++) {
    $checklistVars["E{$i}Y"] = isset($data["e{$i}_yes"]) && $data["e{$i}_yes"] === 'on' ? '✓' : '';
    $checklistVars["E{$i}N"] = isset($data["e{$i}_no"]) && $data["e{$i}_no"] === 'on' ? '✓' : '';
    $checklistVars["E{$i}NA"] = isset($data["e{$i}_na"]) && $data["e{$i}_na"] === 'on' ? '✓' : '';
  }
  // Conditional text fields: ifyes = 5,6,7,16,21,22,23 | ifno = 24
  $ifYesFields = [5, 6, 7, 16, 21, 22, 23];
  foreach ($ifYesFields as $n) {
    $checklistVars["E{$n}_IFYES"] = trim($data["e{$n}_ifyes"] ?? '');
  }
  $checklistVars["E24_IFNO"] = trim($data['e24_ifno'] ?? '');

  $defaults = [
    'e_title' => '', 'e_objective' => '', 'e_start' => '', 'e_end' => '',
    'e_principal' => '', 'e_course' => '', 'e_members' => 'N/A',
    'e_email' => '', 'e_phone' => '', 'e_affiliation' => '',
    'e_adviser' => '', 'e_adviser_phone' => '', 'e_adviser_email' => '', 'e_adviser_affiliation' => '',
    'e_exec_summary' => '',
    'e_sign_name' => '', 'e_date_filled' => '', 'e_adviser_sign_name' => '', 'e_date_signed' => '',
  ];
  $data = array_merge($defaults, $data);

  try {
    $formDataJson = json_encode($data);
    $formType = 'engineering_checklist';
    $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
    $checkStmt->bind_param('ss', $queue_number, $formType);
    $checkStmt->execute();
    $existing = $checkStmt->get_result()->fetch_assoc();
    if ($existing) {
      $stmt = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW(), file_generated = 1 WHERE queue_number = ? AND form_type = ?");
      $stmt->bind_param('sss', $formDataJson, $queue_number, $formType);
    }
    else {
      $stmt = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, file_generated) VALUES (?, ?, ?, 1)");
      $stmt->bind_param('sss', $queue_number, $formType, $formDataJson);
    }
    $stmt->execute();

    

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Engineering Checklist saved successfully!']);
    exit;
  }
  catch (Exception $e) {
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
<title>TAU REO · Ethical Guidelines — Engineering</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root{--navy:#0f2942;--gold:#c9993a;--cream:#faf8f3;--white:#ffffff;--gray-100:#f4f2ed;--gray-200:#e8e4da;--gray-400:#a09880;--gray-600:#5a5040;--blue-soft:#d0e4f0;--shadow:0 4px 24px rgba(15,41,66,.10);--shadow-lg:0 12px 48px rgba(15,41,66,.16)}
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--navy);min-height:100vh}
  .page-header{background:var(--navy);padding:20px 32px;display:flex;align-items:center;gap:18px;position:sticky;top:0;z-index:100;box-shadow:0 2px 16px rgba(0,0,0,.25)}
  .page-header .brand{color:var(--gold);font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;letter-spacing:.02em;white-space:nowrap;border-right:1px solid rgba(255,255,255,.15);padding-right:18px}
  .page-header .page-title{color:rgba(255,255,255,.85);font-size:.9rem;font-weight:400}
  .form-wrapper{max-width:980px;margin:40px auto 80px;padding:0 24px}
  .form-heading{margin-bottom:36px;padding-bottom:24px;border-bottom:2px solid var(--gray-200)}
  .form-heading h1{font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);margin-bottom:8px;font-weight:700}
  .form-heading p{color:var(--gray-400);font-size:.95rem;font-weight:300}
  .form-section{background:var(--white);border-radius:12px;padding:32px;margin-bottom:24px;box-shadow:var(--shadow);border:1px solid var(--gray-200)}
  .section-title{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--navy);margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:10px}
  .section-badge{background:var(--navy);color:var(--gold);font-family:'DM Sans',sans-serif;font-size:.7rem;font-weight:600;padding:3px 10px;border-radius:20px;letter-spacing:.06em;text-transform:uppercase}
  .field-group{margin-bottom:20px}
  .field-group label{display:block;font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:6px;letter-spacing:.04em;text-transform:uppercase}
  .field-group input,.field-group textarea{width:100%;padding:12px 16px;border:1.5px solid var(--gray-200);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.95rem;color:var(--navy);background:var(--cream);transition:border-color .2s,box-shadow .2s;outline:none}
  .field-group input:focus,.field-group textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,153,58,.15);background:var(--white)}
  .field-group textarea{min-height:100px;resize:vertical;line-height:1.6}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .guideline-card{background:var(--navy);color:var(--white);border-radius:12px;padding:28px;margin-bottom:24px}
  .guideline-card h3{font-family:'Playfair Display',serif;color:var(--gold);font-size:1.1rem;margin-bottom:14px}
  .guideline-card ul{list-style:none;display:flex;flex-direction:column;gap:10px}
  .guideline-card ul li{font-size:.88rem;line-height:1.55;color:rgba(255,255,255,.82);padding-left:18px;position:relative}
  .guideline-card ul li::before{content:'›';position:absolute;left:0;color:var(--gold);font-size:1.1rem}
  .check-table{width:100%;border-collapse:collapse;font-size:.88rem}
  .check-table thead tr{background:var(--navy);color:var(--white)}
  .check-table thead th{padding:11px 16px;text-align:left;font-size:.72rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase}
  .check-table thead th:not(:first-child){text-align:center;width:65px}
  .check-table tbody tr{border-bottom:1px solid var(--gray-200);transition:background .15s}
  .check-table tbody tr:hover{background:var(--gray-100)}
  .check-table tbody tr.section-row{background:var(--blue-soft)}
  .check-table tbody tr.section-row td{font-weight:600;font-size:.78rem;letter-spacing:.04em;text-transform:uppercase;color:var(--navy);padding:10px 16px}
  .check-table td{padding:12px 16px;vertical-align:middle;color:var(--gray-600);line-height:1.4}
  .check-table td:not(:first-child){text-align:center;vertical-align:middle}
  .check-table input[type="checkbox"]{width:17px;height:17px;cursor:pointer;accent-color:var(--navy)}
  .iftext{width:100%;padding:8px 12px;border:1.5px solid var(--gray-200);border-radius:6px;font-family:'DM Sans',sans-serif;font-size:.85rem;color:var(--navy);background:var(--cream);outline:none;margin-top:6px;transition:border-color .2s}
  .iftext:focus{border-color:var(--gold);background:var(--white)}
  .note-box{background:var(--blue-soft);border-left:4px solid var(--navy);padding:12px 16px;border-radius:0 8px 8px 0;font-size:.87rem;color:var(--navy);margin-bottom:20px;line-height:1.5}
  .references{font-size:.78rem;color:var(--gray-400);margin-top:14px;line-height:1.6;font-style:italic}
  .submit-area{text-align:center;margin-top:36px}
  .btn-submit{background:var(--navy);color:var(--white);border:none;padding:16px 52px;font-family:'DM Sans',sans-serif;font-size:.95rem;font-weight:600;letter-spacing:.06em;text-transform:uppercase;border-radius:8px;cursor:pointer;transition:all .25s;box-shadow:var(--shadow)}
  .btn-submit:hover{background:var(--gold);color:var(--navy);box-shadow:var(--shadow-lg);transform:translateY(-1px)}
  .btn-submit:disabled{opacity:.55;cursor:not-allowed;transform:none}
  @media(max-width:640px){.field-row{grid-template-columns:1fr}.form-wrapper{padding:0 12px}.form-section{padding:20px}}
</style>

</head>
<body>

<header class="page-header">
  <div class="brand">TAU · REO</div>
  <div class="page-title">Ethical Review Checklist — Engineering</div>
</header>

<div class="form-wrapper">
  <div class="form-heading">
    <h1>Ethics Review Checklist for Engineering</h1>
    <p>Research Ethics Review Committee · Ethical Guidelines for Responsible Conduct</p>
  </div>

  <div class="guideline-card">
    <h3>Ethical Guidelines for Engineering Research</h3>
    <ul>
      <li><strong>Research Integrity:</strong> Conduct research with honesty, transparency, and proper attribution. Avoid plagiarism. Obtain permissions for proprietary data.</li>
      <li><strong>Informed Consent & Privacy:</strong> Obtain informed consent, protect participant privacy, and allow withdrawal at any time.</li>
      <li><strong>Environmental Impact:</strong> Minimize adverse environmental consequences. Comply with environmental regulations.</li>
      <li><strong>Plant, Animal & Human Subjects:</strong> Minimize harm. Adhere to humane treatment and good laboratory practices.</li>
      <li><strong>Safety & Risk:</strong> Prioritize safety of all involved. Identify and mitigate risks. Comply with safety regulations.</li>
      <li><strong>Conflict of Interest:</strong> Disclose potential conflicts that may compromise objectivity or integrity.</li>
    </ul>
  </div>

  <form id="engForm" method="post">

    <div class="form-section">
      <div class="section-title"><span class="section-badge">A</span> Project Information</div>
      <div class="field-group"><label>Title of the Project</label>
        <input type="text" name="e_title" value="<?php echo htmlspecialchars($checklistSavedData['e_title'] ?? $qf01Data['project_title'] ?? $research_title); ?>"></div>
      <div class="field-group"><label>Objective / Purpose</label><textarea name="e_objective"><?php echo htmlspecialchars($checklistSavedData['e_objective'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Date to Start</label><input type="date" name="e_start" value="<?php echo htmlspecialchars($checklistSavedData['e_start'] ?? $qf01Data['start_date'] ?? ''); ?>"></div>
        <div class="field-group"><label>Date to Finish</label><input type="date" name="e_end" value="<?php echo htmlspecialchars($checklistSavedData['e_end'] ?? $qf01Data['end_date'] ?? ''); ?>"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>
      <div class="field-row">
        <div class="field-group"><label>Name of Principal Applicant</label>
          <input type="text" name="e_principal" value="<?php echo htmlspecialchars($checklistSavedData['e_principal'] ?? $qf01Data['principal_name'] ?? $applicant_name); ?>"></div>
        <div class="field-group"><label>Course</label><input type="text" name="e_course" value="<?php echo htmlspecialchars($checklistSavedData['e_course'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Name of Research Members (if any)</label>
        <textarea name="e_members" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['e_members'] ?? $qf01Data['members'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Contact Email</label><input type="email" name="e_email" value="<?php echo htmlspecialchars($checklistSavedData['e_email'] ?? $qf01Data['email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Contact Number</label><input type="tel" name="e_phone" value="<?php echo htmlspecialchars($checklistSavedData['e_phone'] ?? $qf01Data['phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Affiliation and Address</label><input type="text" name="e_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['e_affiliation'] ?? $qf01Data['department'] ?? ''); ?>"></div>
      <div class="field-row">
        <div class="field-group"><label>Research Adviser</label><input type="text" name="e_adviser" value="<?php echo htmlspecialchars($checklistSavedData['e_adviser'] ?? $qf01Data['adviser_name'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Contact Number</label><input type="tel" name="e_adviser_phone" value="<?php echo htmlspecialchars($checklistSavedData['e_adviser_phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Adviser Email</label><input type="email" name="e_adviser_email" value="<?php echo htmlspecialchars($checklistSavedData['e_adviser_email'] ?? $qf01Data['adviser_email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Affiliation and Address</label><input type="text" name="e_adviser_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['e_adviser_affiliation'] ?? ''); ?>"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">C</span> Executive Summary</div>
      <div class="note-box">Maximum of 300 words. Indicate the rationale and purpose. If the study involves a machine/structure, attach the design.</div>
      <div class="field-group">
        <textarea name="e_exec_summary" style="min-height:160px" placeholder="Provide the executive summary here…"><?php echo htmlspecialchars($checklistSavedData['e_exec_summary'] ?? ''); ?></textarea></div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">D</span> Ethics Checklist</div>
      <div class="note-box">Answer YES, NO, or N/A for each criterion. For items requiring elaboration, fill in the text field that appears.</div>
      <table class="check-table">
        <thead><tr><th>Criteria</th><th>YES</th><th>NO</th><th>N/A</th></tr></thead>
        <tbody>
          <tr class="section-row"><td colspan="4">Informed Consent</td></tr>
          <tr><td>The purpose of the study, potential risks, benefits, and voluntary nature of participation is clearly explained to participants.</td>
            <td><input type="checkbox" name="e1_yes" <?php echo(isset($checklistSavedData['e1_yes']) && $checklistSavedData['e1_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e1')"></td>
            <td><input type="checkbox" name="e1_no" <?php echo(isset($checklistSavedData['e1_no']) && $checklistSavedData['e1_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e1')"></td>
            <td><input type="checkbox" name="e1_na" <?php echo(isset($checklistSavedData['e1_na']) && $checklistSavedData['e1_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e1')"></td></tr>
          <tr><td>Participants have the option to inquire about the study and leave at any time.</td>
            <td><input type="checkbox" name="e2_yes" <?php echo(isset($checklistSavedData['e2_yes']) && $checklistSavedData['e2_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e2')"></td>
            <td><input type="checkbox" name="e2_no" <?php echo(isset($checklistSavedData['e2_no']) && $checklistSavedData['e2_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e2')"></td>
            <td><input type="checkbox" name="e2_na" <?php echo(isset($checklistSavedData['e2_na']) && $checklistSavedData['e2_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e2')"></td></tr>

          <tr class="section-row"><td colspan="4">Privacy and Confidentiality</td></tr>
          <tr><td>There is a section in the study that describes procedures to ensure the data privacy and confidentiality of participants.</td>
            <td><input type="checkbox" name="e3_yes" <?php echo(isset($checklistSavedData['e3_yes']) && $checklistSavedData['e3_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e3')"></td>
            <td><input type="checkbox" name="e3_no" <?php echo(isset($checklistSavedData['e3_no']) && $checklistSavedData['e3_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e3')"></td>
            <td><input type="checkbox" name="e3_na" <?php echo(isset($checklistSavedData['e3_na']) && $checklistSavedData['e3_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e3')"></td></tr>
          <tr><td>Steps are taken to ensure that participants' identities are kept confidential and their data is stored safely.</td>
            <td><input type="checkbox" name="e4_yes" <?php echo(isset($checklistSavedData['e4_yes']) && $checklistSavedData['e4_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e4')"></td>
            <td><input type="checkbox" name="e4_no" <?php echo(isset($checklistSavedData['e4_no']) && $checklistSavedData['e4_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e4')"></td>
            <td><input type="checkbox" name="e4_na" <?php echo(isset($checklistSavedData['e4_na']) && $checklistSavedData['e4_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e4')"></td></tr>

          <tr class="section-row"><td colspan="4">General Safety and Risk Assessment</td></tr>
          <tr><td>Any possible safety risks relating to the study have been noted. If yes, list the possible safety risk(s).
            <input type="text" class="iftext" name="e5_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e5_ifyes'] ?? ''); ?>" placeholder="If YES — list safety risks…"></td>
            <td><input type="checkbox" name="e5_yes" <?php echo(isset($checklistSavedData['e5_yes']) && $checklistSavedData['e5_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e5')"></td>
            <td><input type="checkbox" name="e5_no" <?php echo(isset($checklistSavedData['e5_no']) && $checklistSavedData['e5_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e5')"></td>
            <td><input type="checkbox" name="e5_na" <?php echo(isset($checklistSavedData['e5_na']) && $checklistSavedData['e5_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e5')"></td></tr>
          <tr><td>Necessary safety measures have been taken to lessen such risks. If yes, list the safety measure(s).
            <input type="text" class="iftext" name="e6_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e6_ifyes'] ?? ''); ?>" placeholder="If YES — list safety measures…"></td>
            <td><input type="checkbox" name="e6_yes" <?php echo(isset($checklistSavedData['e6_yes']) && $checklistSavedData['e6_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e6')"></td>
            <td><input type="checkbox" name="e6_no" <?php echo(isset($checklistSavedData['e6_no']) && $checklistSavedData['e6_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e6')"></td>
            <td><input type="checkbox" name="e6_na" <?php echo(isset($checklistSavedData['e6_na']) && $checklistSavedData['e6_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e6')"></td></tr>
          <tr><td>Adherence to safety laws, ordinances, and guidelines is considered. If yes, list the laws/ordinances/guidelines.
            <input type="text" class="iftext" name="e7_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e7_ifyes'] ?? ''); ?>" placeholder="If YES — list laws, ordinances, or guidelines…"></td>
            <td><input type="checkbox" name="e7_yes" <?php echo(isset($checklistSavedData['e7_yes']) && $checklistSavedData['e7_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e7')"></td>
            <td><input type="checkbox" name="e7_no" <?php echo(isset($checklistSavedData['e7_no']) && $checklistSavedData['e7_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e7')"></td>
            <td><input type="checkbox" name="e7_na" <?php echo(isset($checklistSavedData['e7_na']) && $checklistSavedData['e7_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e7')"></td></tr>

          <tr class="section-row"><td colspan="4">Agricultural Machinery Safety and Risk Assessment (if applicable)</td></tr>
          <tr><td>The design is clear and can be easily understood by the fabricators.</td>
            <td><input type="checkbox" name="e8_yes" <?php echo(isset($checklistSavedData['e8_yes']) && $checklistSavedData['e8_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e8')"></td>
            <td><input type="checkbox" name="e8_no" <?php echo(isset($checklistSavedData['e8_no']) && $checklistSavedData['e8_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e8')"></td>
            <td><input type="checkbox" name="e8_na" <?php echo(isset($checklistSavedData['e8_na']) && $checklistSavedData['e8_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e8')"></td></tr>
          <tr><td>The design has considered safety for both operators and bystanders.</td>
            <td><input type="checkbox" name="e9_yes" <?php echo(isset($checklistSavedData['e9_yes']) && $checklistSavedData['e9_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e9')"></td>
            <td><input type="checkbox" name="e9_no" <?php echo(isset($checklistSavedData['e9_no']) && $checklistSavedData['e9_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e9')"></td>
            <td><input type="checkbox" name="e9_na" <?php echo(isset($checklistSavedData['e9_na']) && $checklistSavedData['e9_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e9')"></td></tr>
          <tr><td>The design adheres to governmental and technical standards.</td>
            <td><input type="checkbox" name="e10_yes" <?php echo(isset($checklistSavedData['e10_yes']) && $checklistSavedData['e10_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e10')"></td>
            <td><input type="checkbox" name="e10_no" <?php echo(isset($checklistSavedData['e10_no']) && $checklistSavedData['e10_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e10')"></td>
            <td><input type="checkbox" name="e10_na" <?php echo(isset($checklistSavedData['e10_na']) && $checklistSavedData['e10_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e10')"></td></tr>
          <tr><td>The machine design considered ease of maintenance and repair to extend its lifespan.</td>
            <td><input type="checkbox" name="e11_yes" <?php echo(isset($checklistSavedData['e11_yes']) && $checklistSavedData['e11_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e11')"></td>
            <td><input type="checkbox" name="e11_no" <?php echo(isset($checklistSavedData['e11_no']) && $checklistSavedData['e11_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e11')"></td>
            <td><input type="checkbox" name="e11_na" <?php echo(isset($checklistSavedData['e11_na']) && $checklistSavedData['e11_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e11')"></td></tr>

          <tr class="section-row"><td colspan="4">Agricultural Structure Safety and Risk Assessment (if applicable)</td></tr>
          <tr><td>The structural design is clear and can be easily understood by contractors.</td>
            <td><input type="checkbox" name="e12_yes" <?php echo(isset($checklistSavedData['e12_yes']) && $checklistSavedData['e12_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e12')"></td>
            <td><input type="checkbox" name="e12_no" <?php echo(isset($checklistSavedData['e12_no']) && $checklistSavedData['e12_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e12')"></td>
            <td><input type="checkbox" name="e12_na" <?php echo(isset($checklistSavedData['e12_na']) && $checklistSavedData['e12_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e12')"></td></tr>
          <tr><td>The design adheres to governmental and technical standards.</td>
            <td><input type="checkbox" name="e13_yes" <?php echo(isset($checklistSavedData['e13_yes']) && $checklistSavedData['e13_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e13')"></td>
            <td><input type="checkbox" name="e13_no" <?php echo(isset($checklistSavedData['e13_no']) && $checklistSavedData['e13_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e13')"></td>
            <td><input type="checkbox" name="e13_na" <?php echo(isset($checklistSavedData['e13_na']) && $checklistSavedData['e13_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e13')"></td></tr>
          <tr><td>The design considers the safety and well-being of workers and users of agricultural structures.</td>
            <td><input type="checkbox" name="e14_yes" <?php echo(isset($checklistSavedData['e14_yes']) && $checklistSavedData['e14_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e14')"></td>
            <td><input type="checkbox" name="e14_no" <?php echo(isset($checklistSavedData['e14_no']) && $checklistSavedData['e14_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e14')"></td>
            <td><input type="checkbox" name="e14_na" <?php echo(isset($checklistSavedData['e14_na']) && $checklistSavedData['e14_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e14')"></td></tr>
          <tr><td>Safety measures were considered to prevent accidents and injuries.</td>
            <td><input type="checkbox" name="e15_yes" <?php echo(isset($checklistSavedData['e15_yes']) && $checklistSavedData['e15_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e15')"></td>
            <td><input type="checkbox" name="e15_no" <?php echo(isset($checklistSavedData['e15_no']) && $checklistSavedData['e15_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e15')"></td>
            <td><input type="checkbox" name="e15_na" <?php echo(isset($checklistSavedData['e15_na']) && $checklistSavedData['e15_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e15')"></td></tr>

          <tr class="section-row"><td colspan="4">Plant and Animal Use Safety and Risk Assessment (if applicable)</td></tr>
          <tr><td>The study involves manipulation of the plant and animal environment. If yes, describe the manipulation.
            <input type="text" class="iftext" name="e16_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e16_ifyes'] ?? ''); ?>" placeholder="If YES — describe the manipulation…"></td>
            <td><input type="checkbox" name="e16_yes" <?php echo(isset($checklistSavedData['e16_yes']) && $checklistSavedData['e16_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e16')"></td>
            <td><input type="checkbox" name="e16_no" <?php echo(isset($checklistSavedData['e16_no']) && $checklistSavedData['e16_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e16')"></td>
            <td><input type="checkbox" name="e16_na" <?php echo(isset($checklistSavedData['e16_na']) && $checklistSavedData['e16_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e16')"></td></tr>
          <tr><td>The study ensures proper care, housing, and handling of animals involved in research to minimize risk.</td>
            <td><input type="checkbox" name="e17_yes" <?php echo(isset($checklistSavedData['e17_yes']) && $checklistSavedData['e17_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e17')"></td>
            <td><input type="checkbox" name="e17_no" <?php echo(isset($checklistSavedData['e17_no']) && $checklistSavedData['e17_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e17')"></td>
            <td><input type="checkbox" name="e17_na" <?php echo(isset($checklistSavedData['e17_na']) && $checklistSavedData['e17_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e17')"></td></tr>
          <tr><td>The study ensures proper crop management practices for plants involved in research to minimize risk.</td>
            <td><input type="checkbox" name="e18_yes" <?php echo(isset($checklistSavedData['e18_yes']) && $checklistSavedData['e18_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e18')"></td>
            <td><input type="checkbox" name="e18_no" <?php echo(isset($checklistSavedData['e18_no']) && $checklistSavedData['e18_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e18')"></td>
            <td><input type="checkbox" name="e18_na" <?php echo(isset($checklistSavedData['e18_na']) && $checklistSavedData['e18_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e18')"></td></tr>

          <tr class="section-row"><td colspan="4">Food Processing Safety and Risk Assessment (if applicable)</td></tr>
          <tr><td>The research adheres to food safety standards and guidelines (e.g. GMP, HACCP).</td>
            <td><input type="checkbox" name="e19_yes" <?php echo(isset($checklistSavedData['e19_yes']) && $checklistSavedData['e19_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e19')"></td>
            <td><input type="checkbox" name="e19_no" <?php echo(isset($checklistSavedData['e19_no']) && $checklistSavedData['e19_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e19')"></td>
            <td><input type="checkbox" name="e19_na" <?php echo(isset($checklistSavedData['e19_na']) && $checklistSavedData['e19_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e19')"></td></tr>
          <tr><td>The research identifies potential hazards in food processing operations and develops precautionary plans to minimize risks.</td>
            <td><input type="checkbox" name="e20_yes" <?php echo(isset($checklistSavedData['e20_yes']) && $checklistSavedData['e20_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e20')"></td>
            <td><input type="checkbox" name="e20_no" <?php echo(isset($checklistSavedData['e20_no']) && $checklistSavedData['e20_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e20')"></td>
            <td><input type="checkbox" name="e20_na" <?php echo(isset($checklistSavedData['e20_na']) && $checklistSavedData['e20_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e20')"></td></tr>

          <tr class="section-row"><td colspan="4">Environmental Impact</td></tr>
          <tr><td>The effect of the research on the environment is considered. If yes, list the considerations.
            <input type="text" class="iftext" name="e21_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e21_ifyes'] ?? ''); ?>" placeholder="If YES — list considerations…"></td>
            <td><input type="checkbox" name="e21_yes" <?php echo(isset($checklistSavedData['e21_yes']) && $checklistSavedData['e21_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e21')"></td>
            <td><input type="checkbox" name="e21_no" <?php echo(isset($checklistSavedData['e21_no']) && $checklistSavedData['e21_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e21')"></td>
            <td><input type="checkbox" name="e21_na" <?php echo(isset($checklistSavedData['e21_na']) && $checklistSavedData['e21_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e21')"></td></tr>
          <tr><td>Applicable environmental laws and regulations are included in the study. If yes, list them.
            <input type="text" class="iftext" name="e22_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e22_ifyes'] ?? ''); ?>" placeholder="If YES — list applicable laws/regulations…"></td>
            <td><input type="checkbox" name="e22_yes" <?php echo(isset($checklistSavedData['e22_yes']) && $checklistSavedData['e22_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e22')"></td>
            <td><input type="checkbox" name="e22_no" <?php echo(isset($checklistSavedData['e22_no']) && $checklistSavedData['e22_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e22')"></td>
            <td><input type="checkbox" name="e22_na" <?php echo(isset($checklistSavedData['e22_na']) && $checklistSavedData['e22_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e22')"></td></tr>

          <tr class="section-row"><td colspan="4">Conflict of Interest</td></tr>
          <tr><td>Any potential conflicts of interest that might affect the impartiality or integrity of the study are disclosed. If yes, list them.
            <input type="text" class="iftext" name="e23_ifyes" value="<?php echo htmlspecialchars($checklistSavedData['e23_ifyes'] ?? ''); ?>" placeholder="If YES — list conflicts of interest…"></td>
            <td><input type="checkbox" name="e23_yes" <?php echo(isset($checklistSavedData['e23_yes']) && $checklistSavedData['e23_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e23')"></td>
            <td><input type="checkbox" name="e23_no" <?php echo(isset($checklistSavedData['e23_no']) && $checklistSavedData['e23_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e23')"></td>
            <td><input type="checkbox" name="e23_na" <?php echo(isset($checklistSavedData['e23_na']) && $checklistSavedData['e23_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e23')"></td></tr>
          <tr><td>Involvement with individuals or factors that could create conflicts of interest or compromise objectivity is avoided. If no, list the contributing factors.
            <input type="text" class="iftext" name="e24_ifno" value="<?php echo htmlspecialchars($checklistSavedData['e24_ifno'] ?? ''); ?>" placeholder="If NO — list factors contributing to conflict of interest…"></td>
            <td><input type="checkbox" name="e24_yes" <?php echo(isset($checklistSavedData['e24_yes']) && $checklistSavedData['e24_yes'] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'e24')"></td>
            <td><input type="checkbox" name="e24_no" <?php echo(isset($checklistSavedData['e24_no']) && $checklistSavedData['e24_no'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e24')"></td>
            <td><input type="checkbox" name="e24_na" <?php echo(isset($checklistSavedData['e24_na']) && $checklistSavedData['e24_na'] === 'on') ? 'checked' : ''; ?>  onchange="toggleYNA(this,'e24')"></td></tr>
        </tbody>
      </table>
      <p class="references">Checklist adopted from: ASEE Virtual Conference (2020); De La Salle University RERC General Research Ethics Checklist (2017); University of Aberdeen Physical Sciences &amp; Engineering Research Ethics Review Policy.</p>
    </div>

    <div class="submit-area">
      <button type="submit" class="btn-submit" id="submitBtn">Save &amp; Finalize Checklist</button>
      <div id="autosave-status" class="mt-2 text-muted small"></div>
    </div>
  </form>

<script>
function toggleYNA(el, group) {
  ['yes','no','na'].forEach(s => {
    const cb = document.querySelector(`[name="${group}_${s}"]`);
    if (cb && cb !== el) cb.checked = false;
  });
}
function triggerAutosave() {
    const formData = new FormData(document.getElementById('engForm'));
    formData.append('form_type', 'engineering_checklist');
    fetch('autosave-progress.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
            if (res.success) document.getElementById('autosave-status').innerHTML = 'Draft saved at ' + res.timestamp;
        });
}

document.getElementById('engForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.innerHTML = 'Saving…';
  fetch('fill-Engineering-checklist.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => {
      if (data.success) {
        alert(data.message);
        if (window.parent !== window) {
          window.parent.postMessage({ type: 'formCompleted', formType: 'engineering_checklist' }, '*');
        } else { location.href = 'documents.php?success=checklist_saved'; }
      } else {
        alert('Error: ' + data.message);
        btn.disabled = false; btn.innerHTML = 'Save &amp; Finalize Checklist';
      }
    });
});

document.querySelectorAll('input, textarea').forEach(el => {
    el.addEventListener('change', triggerAutosave);
});
</script>
    
    <style>
        .sig-placeholder {
            width: 200px;
            height: 80px;
            border: 2px dashed #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #888;
            font-size: 0.8rem;
            background: #fafafa;
            border-radius: 8px;
            margin-top: 10px;
        }
        .sig-placeholder img {
            max-width: 100%;
            max-height: 100%;
        }
    </style>
</body>
</html>

