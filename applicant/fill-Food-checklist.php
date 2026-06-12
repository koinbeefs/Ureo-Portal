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
$formType = 'food_checklist';
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

// Mapping QF01 to Food Checklist
$autofill = [
    'f_title' => $research_title,
    'f_principal' => $applicant_name,
    'f_members' => $qf01Data['members'] ?? '',
    'f_email' => $qf01Data['email'] ?? '',
    'f_phone' => $qf01Data['phone'] ?? '',
    'f_affiliation' => $qf01Data['department'] ?? '',
    'f_adviser' => $qf01Data['adviser_name'] ?? '',
    'f_adviser_email' => $qf01Data['adviser_email'] ?? '',
    'f_start' => $qf01Data['start_date'] ?? '',
    'f_end' => $qf01Data['end_date'] ?? '',
    'f_exec_summary' => $qf01Data['exec_summary'] ?? ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_once '../vendor/autoload.php';


  $data = $_POST;

  // Build checklist vars F1Y/F1N/F1NA + conditional text fields
  $checklistVars = [];
  for ($i = 1; $i <= 18; $i++) {
    $checklistVars["F{$i}Y"] = isset($data["f{$i}_yes"]) && $data["f{$i}_yes"] === 'on' ? '✓' : '';
    $checklistVars["F{$i}N"] = isset($data["f{$i}_no"]) && $data["f{$i}_no"] === 'on' ? '✓' : '';
    $checklistVars["F{$i}NA"] = isset($data["f{$i}_na"]) && $data["f{$i}_na"] === 'on' ? '✓' : '';
  }
  $checklistVars["F7_IFYES"] = trim($data['f7_ifyes'] ?? '');

  $defaults = [
    'f_title' => '',
    'f_objective' => '',
    'f_start' => '',
    'f_end' => '',
    'f_principal' => '',
    'f_course' => '',
    'f_members' => 'N/A',
    'f_email' => '',
    'f_phone' => '',
    'f_affiliation' => '',
    'f_adviser' => '',
    'f_adviser_phone' => '',
    'f_adviser_email' => '',
    'f_adviser_affiliation' => '',
    'f_exec_summary' => '',
    'f_sign_name' => '',
    'f_date_filled' => '',
    'f_adviser_sign_name' => '',
    'f_date_signed' => '',
  ];
  $data = array_merge($defaults, $data);

  try {
    $templateProcessor = new TemplateProcessor('food-checklist.docx');
    $templateProcessor->setValues(array_merge($checklistVars, [
      'F_TITLE' => $data['f_title'],
      'F_OBJECTIVE' => $data['f_objective'],
      'F_START' => formatDate($data['f_start']),
      'F_END' => formatDate($data['f_end']),
      'F_PRINCIPAL' => $data['f_principal'],
      'F_COURSE' => $data['f_course'],
      'F_MEMBERS' => $data['f_members'],
      'F_EMAIL' => $data['f_email'],
      'F_PHONE' => $data['f_phone'],
      'F_AFFILIATION' => $data['f_affiliation'],
      'F_ADVISER' => $data['f_adviser'],
      'F_ADVISER_PHONE' => $data['f_adviser_phone'],
      'F_ADVISER_EMAIL' => $data['f_adviser_email'],
      'F_ADVISER_AFFILIATION' => $data['f_adviser_affiliation'],
      'F_BACKGROUND' => $data['f_background'],
      'F_METHODOLOGY' => $data['f_methodology'],
      'F_SAMPLES' => $data['f_samples'],
      'F_SOURCE' => $data['f_source'],
      'F_HANDLING' => $data['f_handling'],
      'F_STORAGE' => $data['f_storage'],
      'F_WASTE' => $data['f_waste'],
      'F_SAFETY' => $data['f_safety'],
      'F_EMERGENCY' => $data['f_emergency'],
      'F_SIGN_NAME' => $data['f_sign_name'],
      'F_DATE_FILLED' => date('F d, Y'),
      'F_ADVISER_SIGN_NAME' => $data['f_adviser_sign_name'],
      'F_DATE_SIGNED' => formatDate($data['f_date_signed']),
    ]));

    $outputFile = 'TAU-REO-FOOD-Checklist_Filled_' . $queue_number . '.docx';
    $templateProcessor->saveAs($outputFile);

    $formDataJson = json_encode($data);
    $formType = 'food_checklist';
    $checkStmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
    $checkStmt->bind_param('ss', $queue_number, $formType);
    $checkStmt->execute();

    

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Food Science Checklist saved successfully!']);
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
  <title>TAU REO · Ethics Checklist — Food Science &amp; Technology</title>
  <link
    href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap"
    rel="stylesheet">
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
      --shadow: 0 4px 24px rgba(15, 41, 66, .10);
      --shadow-lg: 0 12px 48px rgba(15, 41, 66, .16)
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0
    }

    body {
      font-family: 'DM Sans', sans-serif;
      background: var(--cream);
      color: var(--navy);
      min-height: 100vh
    }

    .page-header {
      background: var(--navy);
      padding: 20px 32px;
      display: flex;
      align-items: center;
      gap: 18px;
      position: sticky;
      top: 0;
      z-index: 100;
      box-shadow: 0 2px 16px rgba(0, 0, 0, .25)
    }

    .page-header .brand {
      color: var(--gold);
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
      font-weight: 700;
      letter-spacing: .02em;
      white-space: nowrap;
      border-right: 1px solid rgba(255, 255, 255, .15);
      padding-right: 18px
    }

    .page-header .page-title {
      color: rgba(255, 255, 255, .85);
      font-size: .9rem;
      font-weight: 400
    }

    .form-wrapper {
      max-width: 980px;
      margin: 40px auto 80px;
      padding: 0 24px
    }

    .form-heading {
      margin-bottom: 36px;
      padding-bottom: 24px;
      border-bottom: 2px solid var(--gray-200)
    }

    .form-heading h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      color: var(--navy);
      margin-bottom: 8px
    }

    .form-heading p {
      color: var(--gray-400);
      font-size: .95rem;
      font-weight: 300
    }

    .form-section {
      background: var(--white);
      border-radius: 12px;
      padding: 32px;
      margin-bottom: 24px;
      box-shadow: var(--shadow);
      border: 1px solid var(--gray-200)
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
      gap: 10px
    }

    .section-badge {
      background: var(--navy);
      color: var(--gold);
      font-family: 'DM Sans', sans-serif;
      font-size: .7rem;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      letter-spacing: .06em;
      text-transform: uppercase
    }

    .field-group {
      margin-bottom: 20px
    }

    .field-group label {
      display: block;
      font-size: .82rem;
      font-weight: 600;
      color: var(--gray-600);
      margin-bottom: 6px;
      letter-spacing: .04em;
      text-transform: uppercase
    }

    .field-group input,
    .field-group textarea {
      width: 100%;
      padding: 12px 16px;
      border: 1.5px solid var(--gray-200);
      border-radius: 8px;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      color: var(--navy);
      background: var(--cream);
      transition: border-color .2s, box-shadow .2s;
      outline: none
    }

    .field-group input:focus,
    .field-group textarea:focus {
      border-color: var(--gold);
      box-shadow: 0 0 0 3px rgba(201, 153, 58, .15);
      background: var(--white)
    }

    .field-group textarea {
      min-height: 100px;
      resize: vertical;
      line-height: 1.6
    }

    .field-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 16px
    }

    .check-table {
      width: 100%;
      border-collapse: collapse;
      font-size: .88rem
    }

    .check-table thead tr {
      background: var(--navy);
      color: var(--white)
    }

    .check-table thead th {
      padding: 11px 16px;
      text-align: left;
      font-size: .72rem;
      font-weight: 600;
      letter-spacing: .05em;
      text-transform: uppercase
    }

    .check-table thead th:not(:first-child) {
      text-align: center;
      width: 65px
    }

    .check-table tbody tr {
      border-bottom: 1px solid var(--gray-200);
      transition: background .15s
    }

    .check-table tbody tr:hover {
      background: var(--gray-100)
    }

    .check-table tbody tr.section-row {
      background: var(--blue-soft)
    }

    .check-table tbody tr.section-row td {
      font-weight: 600;
      font-size: .78rem;
      letter-spacing: .04em;
      text-transform: uppercase;
      color: var(--navy);
      padding: 10px 16px
    }

    .check-table td {
      padding: 12px 16px;
      vertical-align: middle;
      color: var(--gray-600);
      line-height: 1.4
    }

    .check-table td:not(:first-child) {
      text-align: center;
      vertical-align: middle
    }

    .check-table input[type="checkbox"] {
      width: 17px;
      height: 17px;
      cursor: pointer;
      accent-color: var(--navy)
    }

    .iftext {
      width: 100%;
      padding: 8px 12px;
      border: 1.5px solid var(--gray-200);
      border-radius: 6px;
      font-family: 'DM Sans', sans-serif;
      font-size: .85rem;
      color: var(--navy);
      background: var(--cream);
      outline: none;
      margin-top: 6px;
      transition: border-color .2s
    }

    .iftext:focus {
      border-color: var(--gold);
      background: var(--white)
    }

    .note-box {
      background: var(--blue-soft);
      border-left: 4px solid var(--navy);
      padding: 12px 16px;
      border-radius: 0 8px 8px 0;
      font-size: .87rem;
      color: var(--navy);
      margin-bottom: 20px;
      line-height: 1.5
    }

    .submit-area {
      text-align: center;
      margin-top: 36px
    }

    .btn-submit {
      background: var(--navy);
      color: var(--white);
      border: none;
      padding: 16px 52px;
      font-family: 'DM Sans', sans-serif;
      font-size: .95rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      border-radius: 8px;
      cursor: pointer;
      transition: all .25s;
      box-shadow: var(--shadow)
    }

    .btn-submit:hover {
      background: var(--gold);
      color: var(--navy);
      box-shadow: var(--shadow-lg);
      transform: translateY(-1px)
    }

    .btn-submit:disabled {
      opacity: .55;
      cursor: not-allowed;
      transform: none
    }

    @media(max-width:640px) {
      .field-row {
        grid-template-columns: 1fr
      }

      .form-wrapper {
        padding: 0 12px
      }

      .form-section {
        padding: 20px
      }
    }
  </style>
</head>

<body>

  <header class="page-header">
    <div class="brand">TAU · REO</div>
    <div class="page-title">Ethics Checklist — Food Science &amp; Technology</div>
  </header>

  <div class="form-wrapper">
    <div class="form-heading">
      <h1>Ethics Checklist — Food Science &amp; Technology</h1>
      <p>Research Ethics Review Committee · Food Science and Technology Use</p>
    </div>

    <form id="foodForm" method="post">

      <div class="form-section">
        <div class="section-title"><span class="section-badge">A</span> Project Information</div>
        <div class="field-group"><label>Title of the Project</label>
          <input type="text" name="f_title" value="<?= htmlspecialchars($research_title) ?>">
        </div>
        <div class="field-group"><label>Objective / Purpose</label><textarea name="f_objective"></textarea></div>
        <div class="field-row">
          <div class="field-group"><label>Date to Start</label><input type="date" name="f_start"></div>
          <div class="field-group"><label>Date to Finish</label><input type="date" name="f_end"></div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>
        <div class="field-row">
          <div class="field-group"><label>Name of Principal Applicant</label>
            <input type="text" name="f_principal" value="<?= htmlspecialchars($applicant_name) ?>">
          </div>
          <div class="field-group"><label>Course</label><input type="text" name="f_course"></div>
        </div>
        <div class="field-group"><label>Name of Research Members (if any)</label>
          <textarea name="f_members" style="min-height:70px"></textarea>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Contact Email</label><input type="email" name="f_email"></div>
          <div class="field-group"><label>Contact Number</label><input type="tel" name="f_phone"></div>
        </div>
        <div class="field-group"><label>Affiliation and Address</label><input type="text" name="f_affiliation"></div>
        <div class="field-row">
          <div class="field-group"><label>Research Adviser</label><input type="text" name="f_adviser"></div>
          <div class="field-group"><label>Adviser Contact Number</label><input type="tel" name="f_adviser_phone"></div>
        </div>
        <div class="field-row">
          <div class="field-group"><label>Adviser Email</label><input type="email" name="f_adviser_email"></div>
          <div class="field-group"><label>Adviser Affiliation and Address</label><input type="text"
              name="f_adviser_affiliation"></div>
        </div>
      </div>

      <div class="form-section">
        <div class="section-title"><span class="section-badge">C</span> Executive Summary</div>
        <div class="note-box">Maximum of 300 words. Describe the rationale, purpose, and key food science/technology
          methods of the study.</div>
        <div class="field-group">
          <textarea name="f_exec_summary" style="min-height:160px"
            placeholder="Provide the executive summary here…"><?= htmlspecialchars($checklistSavedData['f_exec_summary'] ?? $autofill['f_exec_summary']) ?></textarea>
        </div>
      </div>

      <div class="form-section">
        <div class="section-title"><span class="section-badge">D</span> Ethics Checklist</div>
        <div class="note-box">Answer YES, NO, or N/A for each criterion. For items requiring elaboration, fill in the
          text field that appears.</div>
        <table class="check-table">
          <thead>
            <tr>
              <th>Criteria</th>
              <th>YES</th>
              <th>NO</th>
              <th>N/A</th>
            </tr>
          </thead>
          <tbody>
            <tr class="section-row">
              <td colspan="4">Human Participants (if applicable)</td>
            </tr>
            <tr>
              <td>The study involves human participants (e.g. sensory evaluation, consumer acceptance tests).</td>
              <td><input type="checkbox" name="f1_yes" onchange="toggleYNA(this,'f1')"></td>
              <td><input type="checkbox" name="f1_no" onchange="toggleYNA(this,'f1')"></td>
              <td><input type="checkbox" name="f1_na" onchange="toggleYNA(this,'f1')"></td>
            </tr>
            <tr>
              <td>Informed consent has been obtained from all human participants involved in sensory or consumption
                tests.</td>
              <td><input type="checkbox" name="f2_yes" onchange="toggleYNA(this,'f2')"></td>
              <td><input type="checkbox" name="f2_no" onchange="toggleYNA(this,'f2')"></td>
              <td><input type="checkbox" name="f2_na" onchange="toggleYNA(this,'f2')"></td>
            </tr>
            <tr>
              <td>Participants have been informed of any potential allergens or risks associated with the food products
                being tested.</td>
              <td><input type="checkbox" name="f3_yes" onchange="toggleYNA(this,'f3')"></td>
              <td><input type="checkbox" name="f3_no" onchange="toggleYNA(this,'f3')"></td>
              <td><input type="checkbox" name="f3_na" onchange="toggleYNA(this,'f3')"></td>
            </tr>
            <tr>
              <td>Vulnerable populations (e.g. children, elderly, pregnant women) involved in the study have additional
                safeguards in place.</td>
              <td><input type="checkbox" name="f4_yes" onchange="toggleYNA(this,'f4')"></td>
              <td><input type="checkbox" name="f4_no" onchange="toggleYNA(this,'f4')"></td>
              <td><input type="checkbox" name="f4_na" onchange="toggleYNA(this,'f4')"></td>
            </tr>

            <tr class="section-row">
              <td colspan="4">Food Safety and Quality</td>
            </tr>
            <tr>
              <td>The research adheres to applicable food safety standards (e.g. GMP, HACCP, FDA regulations).</td>
              <td><input type="checkbox" name="f5_yes" onchange="toggleYNA(this,'f5')"></td>
              <td><input type="checkbox" name="f5_no" onchange="toggleYNA(this,'f5')"></td>
              <td><input type="checkbox" name="f5_na" onchange="toggleYNA(this,'f5')"></td>
            </tr>
            <tr>
              <td>Potential food safety hazards (biological, chemical, physical) have been identified and measures are
                in place to control them.</td>
              <td><input type="checkbox" name="f6_yes" onchange="toggleYNA(this,'f6')"></td>
              <td><input type="checkbox" name="f6_no" onchange="toggleYNA(this,'f6')"></td>
              <td><input type="checkbox" name="f6_na" onchange="toggleYNA(this,'f6')"></td>
            </tr>
            <tr>
              <td>Potentially hazardous food production techniques are involved (e.g. chemical additives, genetic
                modification). If yes, describe safety measures.
                <input type="text" class="iftext" name="f7_ifyes" placeholder="If YES — describe safety measures…">
              </td>
              <td><input type="checkbox" name="f7_yes" onchange="toggleYNA(this,'f7')"></td>
              <td><input type="checkbox" name="f7_no" onchange="toggleYNA(this,'f7')"></td>
              <td><input type="checkbox" name="f7_na" onchange="toggleYNA(this,'f7')"></td>
            </tr>
            <tr>
              <td>Proper labeling and documentation of food ingredients, additives, and processing methods are
                maintained throughout the study.</td>
              <td><input type="checkbox" name="f8_yes" onchange="toggleYNA(this,'f8')"></td>
              <td><input type="checkbox" name="f8_no" onchange="toggleYNA(this,'f8')"></td>
              <td><input type="checkbox" name="f8_na" onchange="toggleYNA(this,'f8')"></td>
            </tr>

            <tr class="section-row">
              <td colspan="4">Chemical and Additive Use</td>
            </tr>
            <tr>
              <td>Food additives, preservatives, or chemical agents used are approved by relevant regulatory agencies
                (e.g. FDA, Codex Alimentarius).</td>
              <td><input type="checkbox" name="f9_yes" onchange="toggleYNA(this,'f9')"></td>
              <td><input type="checkbox" name="f9_no" onchange="toggleYNA(this,'f9')"></td>
              <td><input type="checkbox" name="f9_na" onchange="toggleYNA(this,'f9')"></td>
            </tr>
            <tr>
              <td>Concentrations and application methods of chemical additives comply with allowable limits.</td>
              <td><input type="checkbox" name="f10_yes" onchange="toggleYNA(this,'f10')"></td>
              <td><input type="checkbox" name="f10_no" onchange="toggleYNA(this,'f10')"></td>
              <td><input type="checkbox" name="f10_na" onchange="toggleYNA(this,'f10')"></td>
            </tr>
            <tr>
              <td>Proper storage, handling, and disposal of chemicals and food samples are implemented according to
                safety protocols.</td>
              <td><input type="checkbox" name="f11_yes" onchange="toggleYNA(this,'f11')"></td>
              <td><input type="checkbox" name="f11_no" onchange="toggleYNA(this,'f11')"></td>
              <td><input type="checkbox" name="f11_na" onchange="toggleYNA(this,'f11')"></td>
            </tr>

            <tr class="section-row">
              <td colspan="4">Environmental Impact and Waste Management</td>
            </tr>
            <tr>
              <td>The environmental impact of the research is considered, including waste generation and energy
                consumption.</td>
              <td><input type="checkbox" name="f12_yes" onchange="toggleYNA(this,'f12')"></td>
              <td><input type="checkbox" name="f12_no" onchange="toggleYNA(this,'f12')"></td>
              <td><input type="checkbox" name="f12_na" onchange="toggleYNA(this,'f12')"></td>
            </tr>
            <tr>
              <td>Food waste generated during the research is properly managed and disposed of according to applicable
                regulations.</td>
              <td><input type="checkbox" name="f13_yes" onchange="toggleYNA(this,'f13')"></td>
              <td><input type="checkbox" name="f13_no" onchange="toggleYNA(this,'f13')"></td>
              <td><input type="checkbox" name="f13_na" onchange="toggleYNA(this,'f13')"></td>
            </tr>
            <tr>
              <td>Sustainable sourcing of raw materials and ingredients is considered in the research design.</td>
              <td><input type="checkbox" name="f14_yes" onchange="toggleYNA(this,'f14')"></td>
              <td><input type="checkbox" name="f14_no" onchange="toggleYNA(this,'f14')"></td>
              <td><input type="checkbox" name="f14_na" onchange="toggleYNA(this,'f14')"></td>
            </tr>

            <tr class="section-row">
              <td colspan="4">Researcher Safety</td>
            </tr>
            <tr>
              <td>Proper safety protocols (PPE, lab practices, emergency procedures) are in place to protect researchers
                handling food, chemicals, or microorganisms.</td>
              <td><input type="checkbox" name="f15_yes" onchange="toggleYNA(this,'f15')"></td>
              <td><input type="checkbox" name="f15_no" onchange="toggleYNA(this,'f15')"></td>
              <td><input type="checkbox" name="f15_na" onchange="toggleYNA(this,'f15')"></td>
            </tr>
            <tr>
              <td>Microbiological work (if applicable) is conducted in appropriate biosafety conditions, following
                relevant guidelines.</td>
              <td><input type="checkbox" name="f16_yes" onchange="toggleYNA(this,'f16')"></td>
              <td><input type="checkbox" name="f16_no" onchange="toggleYNA(this,'f16')"></td>
              <td><input type="checkbox" name="f16_na" onchange="toggleYNA(this,'f16')"></td>
            </tr>

            <tr class="section-row">
              <td colspan="4">Intellectual Property and Conflict of Interest</td>
            </tr>
            <tr>
              <td>Any proprietary formulations, processes, or trade secrets involved are properly disclosed and
                appropriate permissions obtained.</td>
              <td><input type="checkbox" name="f17_yes" onchange="toggleYNA(this,'f17')"></td>
              <td><input type="checkbox" name="f17_no" onchange="toggleYNA(this,'f17')"></td>
              <td><input type="checkbox" name="f17_na" onchange="toggleYNA(this,'f17')"></td>
            </tr>
            <tr>
              <td>Any potential conflicts of interest (e.g. industry sponsorship influencing results) are disclosed.
              </td>
              <td><input type="checkbox" name="f18_yes" onchange="toggleYNA(this,'f18')"></td>
              <td><input type="checkbox" name="f18_no" onchange="toggleYNA(this,'f18')"></td>
              <td><input type="checkbox" name="f18_na" onchange="toggleYNA(this,'f18')"></td>
            </tr>
          </tbody>
        </table>
      </div>

      <div class="submit-area">
        <button type="submit" class="btn-submit" id="submitBtn">Save &amp; Finalize Checklist</button>
        <div id="autosave-status" class="mt-2 text-muted small"></div>
      </div>
    </form>
  </div>

  <script>
    function toggleYNA(el, group) {
      ['yes', 'no', 'na'].forEach(s => {
        const cb = document.querySelector(`[name="${group}_${s}"]`);
        if (cb && cb !== el) cb.checked = false;
      });
    }
    function triggerAutosave() {
      const formData = new FormData(document.getElementById('foodForm'));
      formData.append('form_type', 'food_checklist');
      fetch('autosave-progress.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
          if (res.success) document.getElementById('autosave-status').innerHTML = 'Draft saved at ' + res.timestamp;
        });
    }

    document.getElementById('foodForm').addEventListener('submit', function (e) {
      e.preventDefault();
      const btn = document.getElementById('submitBtn');
      btn.disabled = true; btn.innerHTML = 'Saving…';
      fetch('fill-food-checklist.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json()).then(data => {
          if (data.success) {
            alert(data.message);
            if (window.parent !== window) {
              window.parent.postMessage({ type: 'formCompleted', formType: 'food_checklist' }, '*');
            } else { location.href = 'documents.php?success=checklist_saved'; }
          } else {
            alert('Error: ' + data.message);
            btn.disabled = false; btn.innerHTML = 'Save &amp; Finalize Checklist';
          }
        });
    });

    document.querySelectorAll('input, textarea, select').forEach(el => {
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