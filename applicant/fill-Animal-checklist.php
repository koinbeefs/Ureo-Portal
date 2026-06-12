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
$formType = 'animal_checklist';
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

  // Build checklist vars A1Y/A1N/A1NA + conditional text fields
  $checklistVars = [];
  for ($i = 1; $i <= 15; $i++) {
    $checklistVars["A{$i}Y"] = isset($data["a{$i}_yes"]) && $data["a{$i}_yes"] === 'on' ? '✓' : '';
    $checklistVars["A{$i}N"] = isset($data["a{$i}_no"]) && $data["a{$i}_no"] === 'on' ? '✓' : '';
    $checklistVars["A{$i}NA"] = isset($data["a{$i}_na"]) && $data["a{$i}_na"] === 'on' ? '✓' : '';
  }

  $defaults = [
    'a_project_title' => '', 'a_objective' => '', 'a_start' => '', 'a_end' => '',
    'a_principal' => '', 'a_course' => '', 'a_members' => 'N/A',
    'a_email' => '', 'a_phone' => '', 'a_affiliation' => '',
    'a_adviser' => '', 'a_adviser_phone' => '', 'a_adviser_email' => '', 'a_adviser_affiliation' => '',
    'a_background' => '', 'a_species' => '', 'a_source' => '', 'a_reason' => '', 'a_sex' => '', 'a_age' => '', 'a_number' => '',
    'a_quarantine' => '', 'a_cage_type' => '', 'a_per_cage' => '', 'a_cleaning' => '', 'a_temp' => '', 'a_humidity' => '',
    'a_lighting' => '', 'a_ventilation' => '', 'a_diet' => '', 'a_feeding' => '', 'a_watering' => '', 'a_manipulation' => '',
    'a_exp_route' => '', 'a_exp_freq' => '', 'a_exp_vol' => '', 'a_pos_route' => '', 'a_pos_freq' => '', 'a_pos_vol' => '',
    'a_neg_route' => '', 'a_neg_freq' => '', 'a_neg_vol' => '', 'a_restraint' => '', 'a_expected' => '', 'a_pos_treatment' => '',
    'a_neg_treatment' => '', 'a_collect_freq' => '', 'a_collect_vol' => '', 'a_collect_restraint' => '', 'a_exam' => '',
    'a_anesthetics' => '', 'a_surgical' => '', 'a_surgery_loc' => '', 'a_surgery_care' => '', 'a_surgery_complications' => '',
    'a_surgeons' => '', 'a_euthanasia' => '',
    'a_sign_name' => '', 'a_date_filled' => '', 'a_adviser_sign_name' => '', 'a_date_signed' => '',
  ];
  $data = array_merge($defaults, $data);

  try {
    $formDataJson = json_encode($data);
    $formType = 'animal_checklist';
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
    echo json_encode(['success' => true, 'message' => 'Animal Use Checklist saved successfully!']);
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
<title>TAU REO · Ethical Guidelines — Animal Welfare</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>

  :root {
    --navy: #0f2942;
    --gold: #c9993a;
    --gold-light: #f0d78e;
    --cream: #faf8f3;
    --white: #ffffff;
    --gray-100: #f4f2ed;
    --gray-200: #e8e4da;
    --gray-400: #a09880;
    --gray-600: #5a5040;
    --green: #2d6a4f;
    --red: #9b2226;
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

  /* Body spacing */
  body { padding-top: 0; }

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
    font-weight: 700;
  }

  .form-heading p {
    color: var(--gray-400);
    font-size: 0.95rem;
    font-weight: 300;
  }

  /* ===== SECTIONS ===== */
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

  /* ===== FORM FIELDS ===== */
  .field-group {
    margin-bottom: 20px;
  }

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
    min-height: 100px;
    resize: vertical;
    line-height: 1.6;
  }

  .field-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
  }

  .field-row-3 {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 16px;
  }

  /* ===== CHECKBOX TABLE ===== */
  .check-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
  }

  .check-table thead tr {
    background: var(--navy);
    color: var(--white);
  }

  .check-table thead th {
    padding: 10px 14px;
    text-align: left;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .check-table thead th:not(:first-child) {
    text-align: center;
    width: 60px;
  }

  .check-table tbody tr {
    border-bottom: 1px solid var(--gray-200);
    transition: background 0.15s;
  }

  .check-table tbody tr:hover { background: var(--gray-100); }

  .check-table tbody tr.section-header {
    background: var(--blue-soft);
  }

  .check-table tbody tr.section-header td {
    font-weight: 600;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--navy);
    padding: 10px 14px;
  }

  .check-table td {
    padding: 12px 14px;
    vertical-align: middle;
    line-height: 1.45;
    color: var(--gray-600);
  }

  .check-table td:not(:first-child) {
    text-align: center;
  }

  .check-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--navy);
  }

  /* sub-notes under some checklist items */
  .check-note {
    font-size: 0.78rem;
    color: var(--gray-400);
    margin-top: 4px;
    font-style: italic;
  }

  /* ===== CHECKLIST (for non-table style) ===== */
  .check-list-item {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 14px 16px;
    border-radius: 8px;
    margin-bottom: 8px;
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
    width: 18px;
    height: 18px;
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

  /* ===== SPECIMEN CHECKBOXES ===== */
  .specimen-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 8px;
  }

  .specimen-chip {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border: 1.5px solid var(--gray-200);
    border-radius: 20px;
    font-size: 0.85rem;
    cursor: pointer;
    transition: all 0.2s;
    background: var(--cream);
  }

  .specimen-chip:hover {
    border-color: var(--gold);
    background: var(--white);
  }

  .specimen-chip input[type="checkbox"] {
    accent-color: var(--navy);
    width: 14px;
    height: 14px;
  }

  /* ===== GUIDELINES CARD ===== */
  .guideline-card {
    background: var(--navy);
    color: var(--white);
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 16px;
  }

  .guideline-card h3 {
    font-family: 'Playfair Display', serif;
    color: var(--gold);
    font-size: 1.1rem;
    margin-bottom: 14px;
  }

  .guideline-card ul {
    list-style: none;
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .guideline-card ul li {
    font-size: 0.88rem;
    line-height: 1.55;
    color: rgba(255,255,255,0.82);
    padding-left: 18px;
    position: relative;
  }

  .guideline-card ul li::before {
    content: '›';
    position: absolute;
    left: 0;
    color: var(--gold);
    font-size: 1.1rem;
  }

  /* ===== SUBMIT BUTTON ===== */
  .submit-area {
    text-align: center;
    margin-top: 36px;
  }

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

  /* ===== NOTE BOX ===== */
  .note-box {
    background: var(--blue-soft);
    border-left: 4px solid var(--navy);
    padding: 14px 18px;
    border-radius: 0 8px 8px 0;
    font-size: 0.87rem;
    color: var(--navy);
    margin-bottom: 20px;
    line-height: 1.5;
  }

  /* ===== REFERENCES ===== */
  .references {
    font-size: 0.78rem;
    color: var(--gray-400);
    margin-top: 12px;
    line-height: 1.6;
    font-style: italic;
  }

  /* scroll behavior */
  html { scroll-behavior: smooth; }

  @media (max-width: 640px) {
    .field-row, .field-row-3 { grid-template-columns: 1fr; }
    .panel { padding: 0 12px; }
    .form-section { padding: 20px; }
    .brand { font-size: 0.85rem; padding: 14px 16px; }
    .tab-btn { padding: 14px 12px; font-size: 0.72rem; }
  }


  /* Standalone header */
  .page-header {
    background: var(--navy);
    padding: 20px 32px;
    display: flex;
    align-items: center;
    gap: 18px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 16px rgba(0,0,0,.25);
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
</style>



</head>
<body>

<div class="form-wrapper">
  <div class="form-heading">
    <h1>IACUC Ethical Review Checklist</h1>
    <p>Institutional Animal Care and Use Committee · Animal Welfare Research Ethics</p>
  </div>

  <form id="animalForm" method="post">
  <div id="form-container">
    <!-- Project Info -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">A</span> Project Information</div>
      <div class="field-group">
        <label>Title of the Project</label>
        <input type="text" name="a_project_title" value="<?php echo htmlspecialchars($checklistSavedData['a_project_title'] ?? $qf01Data['project_title'] ?? $research_title); ?>">
      </div>
      <div class="field-group">
        <label>Objective / Purpose</label>
        <textarea name="a_objective"><?php echo htmlspecialchars($checklistSavedData['a_objective'] ?? ''); ?></textarea>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Date to Start</label><input type="date" name="a_start" value="<?php echo htmlspecialchars($checklistSavedData['a_start'] ?? $qf01Data['start_date'] ?? ''); ?>"></div>
        <div class="field-group"><label>Date to Finish</label><input type="date" name="a_end" value="<?php echo htmlspecialchars($checklistSavedData['a_end'] ?? $qf01Data['end_date'] ?? ''); ?>"></div>
      </div>
    </div>

    <!-- Admin Info -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>
      <div class="field-row">
        <div class="field-group"><label>Name of Principal Applicant</label><input type="text" name="a_principal" value="<?php echo htmlspecialchars($checklistSavedData['a_principal'] ?? $qf01Data['principal_name'] ?? $applicant_name); ?>"></div>
        <div class="field-group"><label>Course</label><input type="text" name="a_course" value="<?php echo htmlspecialchars($checklistSavedData['a_course'] ?? ''); ?>"></div>
      </div>
      <div class="field-group">
        <label>Name of Research Members (if any)</label>
        <textarea name="a_members" style="min-height:70px;"><?php echo htmlspecialchars($checklistSavedData['a_members'] ?? $qf01Data['members'] ?? ''); ?></textarea>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Contact Email Address</label><input type="email" name="a_email" value="<?php echo htmlspecialchars($checklistSavedData['a_email'] ?? $qf01Data['email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Contact Number</label><input type="tel" name="a_phone" value="<?php echo htmlspecialchars($checklistSavedData['a_phone'] ?? $qf01Data['phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Affiliation and Address</label><input type="text" name="a_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['a_affiliation'] ?? $qf01Data['department'] ?? ''); ?>"></div>
      <div class="field-row">
        <div class="field-group"><label>Research Adviser</label><input type="text" name="a_adviser" value="<?php echo htmlspecialchars($checklistSavedData['a_adviser'] ?? $qf01Data['adviser_name'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Contact Number</label><input type="tel" name="a_adviser_phone" value="<?php echo htmlspecialchars($checklistSavedData['a_adviser_phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Adviser Email Address</label><input type="email" name="a_adviser_email" value="<?php echo htmlspecialchars($checklistSavedData['a_adviser_email'] ?? $qf01Data['adviser_email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Affiliation and Address</label><input type="text" name="a_adviser_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['a_adviser_affiliation'] ?? ''); ?>"></div>
      </div>
    </div>

    <!-- Background -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">C</span> Project / Study Description</div>
      <div class="field-group">
        <label>Background and Significance of the Research Procedure</label>
        <textarea name="a_background" placeholder="Describe the biomedical characteristics of the animals essential to the proposed procedure and provide evidence of experience with the proposed animal model." style="min-height:130px;"><?php echo htmlspecialchars($checklistSavedData['a_background'] ?? ''); ?></textarea>
      </div>
    </div>

    <!-- Methodology -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">D</span> Experimental / Animal Manipulation Method</div>
      <div class="note-box">This section should establish that the proposed procedures/research is well designed scientifically and ethically.</div>

      <div class="field-row">
        <div class="field-group"><label>Animal Species</label><input type="text" name="a_species" value="<?php echo htmlspecialchars($checklistSavedData['a_species'] ?? ''); ?>"></div>
        <div class="field-group"><label>Source of the Animal</label><input type="text" name="a_source" value="<?php echo htmlspecialchars($checklistSavedData['a_source'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Reason / Basis for Selecting the Animal Species</label><textarea name="a_reason" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_reason'] ?? ''); ?></textarea></div>
      <div class="field-row-3">
        <div class="field-group"><label>Sex of Animals</label><input type="text" name="a_sex" value="<?php echo htmlspecialchars($checklistSavedData['a_sex'] ?? ''); ?>" placeholder="e.g. Male, Female, Both"></div>
        <div class="field-group"><label>Age of Animals</label><input type="text" name="a_age" value="<?php echo htmlspecialchars($checklistSavedData['a_age'] ?? ''); ?>"></div>
        <div class="field-group"><label>Number of Animals to be Used</label><input type="number" name="a_number" value="<?php echo htmlspecialchars($checklistSavedData['a_number'] ?? ''); ?>" min="1"></div>
      </div>
      <div class="field-group"><label>Duration of Quarantine / Acclimatization / Conditioning</label><input type="text" name="a_quarantine" value="<?php echo htmlspecialchars($checklistSavedData['a_quarantine'] ?? ''); ?>"></div>

      <div class="section-title" style="font-size:1rem; margin-top:24px;">Animal Care Procedures</div>
      <div class="field-row">
        <div class="field-group"><label>Cage Type and Size</label><input type="text" name="a_cage_type" value="<?php echo htmlspecialchars($checklistSavedData['a_cage_type'] ?? ''); ?>"></div>
        <div class="field-group"><label>Number of Animals per Cage</label><input type="number" name="a_per_cage" value="<?php echo htmlspecialchars($checklistSavedData['a_per_cage'] ?? ''); ?>" min="1"></div>
      </div>
      <div class="field-group"><label>Cage Cleaning Method</label><input type="text" name="a_cleaning" value="<?php echo htmlspecialchars($checklistSavedData['a_cleaning'] ?? ''); ?>"></div>
      <div class="field-row-3">
        <div class="field-group"><label>Room Temperature</label><input type="text" name="a_temp" value="<?php echo htmlspecialchars($checklistSavedData['a_temp'] ?? ''); ?>"></div>
        <div class="field-group"><label>Humidity</label><input type="text" name="a_humidity" value="<?php echo htmlspecialchars($checklistSavedData['a_humidity'] ?? ''); ?>"></div>
        <div class="field-group"><label>Lighting</label><input type="text" name="a_lighting" value="<?php echo htmlspecialchars($checklistSavedData['a_lighting'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Ventilation</label><input type="text" name="a_ventilation" value="<?php echo htmlspecialchars($checklistSavedData['a_ventilation'] ?? ''); ?>"></div>
      <div class="field-row">
        <div class="field-group"><label>Animal Diet</label><input type="text" name="a_diet" value="<?php echo htmlspecialchars($checklistSavedData['a_diet'] ?? ''); ?>"></div>
        <div class="field-group"><label>Feeding Method</label><input type="text" name="a_feeding" value="<?php echo htmlspecialchars($checklistSavedData['a_feeding'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Watering Method</label><input type="text" name="a_watering" value="<?php echo htmlspecialchars($checklistSavedData['a_watering'] ?? ''); ?>"></div>

      <div class="section-title" style="font-size:1rem; margin-top:24px;">Manipulation Details</div>
      <div class="field-group"><label>1. General Description of Animal Manipulation Methods (including conditioning)</label><textarea name="a_manipulation" style="min-height:90px;"><?php echo htmlspecialchars($checklistSavedData['a_manipulation'] ?? ''); ?></textarea></div>

      <div class="note-box" style="margin-top:16px;">Dosing Method(s)</div>
      <div class="field-row-3">
        <div class="field-group"><label>Experimental Dose(s) — Route</label><input type="text" name="a_exp_route" value="<?php echo htmlspecialchars($checklistSavedData['a_exp_route'] ?? ''); ?>"></div>
        <div class="field-group"><label>Frequency</label><input type="text" name="a_exp_freq" value="<?php echo htmlspecialchars($checklistSavedData['a_exp_freq'] ?? ''); ?>"></div>
        <div class="field-group"><label>Volume</label><input type="text" name="a_exp_vol" value="<?php echo htmlspecialchars($checklistSavedData['a_exp_vol'] ?? ''); ?>"></div>
      </div>
      <div class="field-row-3">
        <div class="field-group"><label>Positive (+) Control — Route</label><input type="text" name="a_pos_route" value="<?php echo htmlspecialchars($checklistSavedData['a_pos_route'] ?? ''); ?>"></div>
        <div class="field-group"><label>Frequency</label><input type="text" name="a_pos_freq" value="<?php echo htmlspecialchars($checklistSavedData['a_pos_freq'] ?? ''); ?>"></div>
        <div class="field-group"><label>Volume</label><input type="text" name="a_pos_vol" value="<?php echo htmlspecialchars($checklistSavedData['a_pos_vol'] ?? ''); ?>"></div>
      </div>
      <div class="field-row-3">
        <div class="field-group"><label>Negative (–) Control — Route</label><input type="text" name="a_neg_route" value="<?php echo htmlspecialchars($checklistSavedData['a_neg_route'] ?? ''); ?>"></div>
        <div class="field-group"><label>Frequency</label><input type="text" name="a_neg_freq" value="<?php echo htmlspecialchars($checklistSavedData['a_neg_freq'] ?? ''); ?>"></div>
        <div class="field-group"><label>Volume</label><input type="text" name="a_neg_vol" value="<?php echo htmlspecialchars($checklistSavedData['a_neg_vol'] ?? ''); ?>"></div>
      </div>

      <div class="field-group" style="margin-top:16px;"><label>3. Methods of Restraint</label><input type="text" name="a_restraint" value="<?php echo htmlspecialchars($checklistSavedData['a_restraint'] ?? ''); ?>"></div>
      <div class="field-group"><label>4. Expected Outcome or Effects</label><textarea name="a_expected" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_expected'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>5a. Positive Control Treatment</label><input type="text" name="a_pos_treatment" value="<?php echo htmlspecialchars($checklistSavedData['a_pos_treatment'] ?? ''); ?>"></div>
        <div class="field-group"><label>5b. Negative Control Treatment</label><input type="text" name="a_neg_treatment" value="<?php echo htmlspecialchars($checklistSavedData['a_neg_treatment'] ?? ''); ?>"></div>
      </div>

      <div class="field-group" style="margin-top:16px;"><label>6. Specimen / Biological Agent (select all that apply)</label>
        <div class="specimen-row">
          <label class="specimen-chip"><input type="checkbox" name="a_specimen_blood" <?php echo(isset($checklistSavedData['a_specimen_blood']) && $checklistSavedData['a_specimen_blood'] === 'on') ? 'checked' : ''; ?>> Blood</label>
          <label class="specimen-chip"><input type="checkbox" name="a_specimen_urine" <?php echo(isset($checklistSavedData['a_specimen_urine']) && $checklistSavedData['a_specimen_urine'] === 'on') ? 'checked' : ''; ?>> Urine</label>
          <label class="specimen-chip"><input type="checkbox" name="a_specimen_feces" <?php echo(isset($checklistSavedData['a_specimen_feces']) && $checklistSavedData['a_specimen_feces'] === 'on') ? 'checked' : ''; ?>> Feces</label>
          <label class="specimen-chip"><input type="checkbox" name="a_specimen_other" <?php echo(isset($checklistSavedData['a_specimen_other']) && $checklistSavedData['a_specimen_other'] === 'on') ? 'checked' : ''; ?>> Others</label>
        </div>
      </div>

      <div class="field-row-3" style="margin-top:8px;">
        <div class="field-group"><label>7. Collection Frequency</label><input type="text" name="a_collect_freq" value="<?php echo htmlspecialchars($checklistSavedData['a_collect_freq'] ?? ''); ?>"></div>
        <div class="field-group"><label>Collection Volume</label><input type="text" name="a_collect_vol" value="<?php echo htmlspecialchars($checklistSavedData['a_collect_vol'] ?? ''); ?>"></div>
        <div class="field-group"><label>Collection Restraint Method</label><input type="text" name="a_collect_restraint" value="<?php echo htmlspecialchars($checklistSavedData['a_collect_restraint'] ?? ''); ?>"></div>
      </div>

      <div class="field-group" style="margin-top:8px;"><label>8. Animal Examination Procedures and Frequency (including restraining methods)</label><textarea name="a_exam" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_exam'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>9. Use of Anesthetics (drug, dosage, frequency)</label><textarea name="a_anesthetics" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_anesthetics'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>10. Surgical Procedures, Type and Purpose (if any)</label><textarea name="a_surgical" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_surgical'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>10a. Where will surgery be performed?</label><input type="text" name="a_surgery_loc" value="<?php echo htmlspecialchars($checklistSavedData['a_surgery_loc'] ?? ''); ?>"></div>
      <div class="field-group"><label>10b. Supportive care and monitoring procedures during and after surgery</label><textarea name="a_surgery_care" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_surgery_care'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>10c. Measures for possible post-surgical complications</label><textarea name="a_surgery_complications" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_surgery_complications'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>10d. Name(s) of Surgeon(s), Qualifications, and Relevant Experiences</label><textarea name="a_surgeons" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_surgeons'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>11. Euthanasia Method (if animals will be euthanized)</label><textarea name="a_euthanasia" style="min-height:80px;"><?php echo htmlspecialchars($checklistSavedData['a_euthanasia'] ?? ''); ?></textarea></div>
    </div>

    <!-- Declaration -->
    <div class="form-section">
      <div class="section-title">Declaration</div>
      <div class="field-row">
        <div class="field-group"><label>Name of Principal Applicant</label><input type="text" name="a_sign_name" value="<?php echo htmlspecialchars($checklistSavedData['a_sign_name'] ?? $applicant_name); ?>"></div>
        <div class="field-group"><label>Date Filled</label><input type="date" name="a_date_filled" value="<?php echo htmlspecialchars($checklistSavedData['a_date_filled'] ?? ''); ?>"></div>
      </div>
      
      <div class="field-group">
        <label>Principal Applicant Signature</label>
        <input type="file" name="signature_proponent" accept="image/*" class="form-control-sm mb-2">
        <div id="sig_preview_proponent" class="sig-placeholder">Signature View</div>
      </div>

      <div class="field-row">
        <div class="field-group"><label>Name of Adviser</label><input type="text" name="a_adviser_sign_name" value="<?php echo htmlspecialchars($checklistSavedData['a_adviser_sign_name'] ?? ''); ?>"></div>
        <div class="field-group"><label>Date Signed by Adviser</label><input type="date" name="a_date_signed" value="<?php echo htmlspecialchars($checklistSavedData['a_date_signed'] ?? ''); ?>"></div>
      </div>

      <div class="field-group">
        <label>Research Adviser Signature</label>
        <input type="file" name="signature_adviser" accept="image/*" class="form-control-sm mb-2">
        <div id="sig_preview_adviser" class="sig-placeholder">Signature View</div>
      </div>
    </div>
    
  </div>
</div>

<div class="submit-area">
  <button type="submit" class="btn-submit" id="submitBtn">Generate &amp; Download Filled Form</button>
</div>
</form>

<script>

  // Toggle YES/NO (2-option)
  function toggleCheck(el, group) {
    const yes = document.querySelector(`[name="${group}_yes"]`);
    const no  = document.querySelector(`[name="${group}_no"]`);
    if (el.checked) {
      if (el.name.endsWith('_yes')) no.checked = false;
      if (el.name.endsWith('_no'))  yes.checked = false;
    }
  }

  // Toggle YES/NO/NA (3-option)
  function toggleCheck3(el, group) {
    const yes = document.querySelector(`[name="${group}_yes"]`);
    const no  = document.querySelector(`[name="${group}_no"]`);
    const na  = document.querySelector(`[name="${group}_na"]`);
    if (!yes || !no || !na) return;
    if (el.checked) {
      if (el.name.endsWith('_yes')) { no.checked = false; na.checked = false; }
      if (el.name.endsWith('_no'))  { yes.checked = false; na.checked = false; }
      if (el.name.endsWith('_na'))  { yes.checked = false; no.checked = false; }
    }
  }

  

    function triggerAutosave() {
    const formData = new FormData(document.getElementById('animalForm'));
    formData.append('form_type', 'animal_checklist');
    fetch('autosave-progress.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
            if (res.success) document.getElementById('autosave-status').innerHTML = 'Draft saved at ' + res.timestamp;
        });
}

document.getElementById('animalForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.innerHTML = 'Saving…';
  fetch('fill-Animal-checklist.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => {
      if (data.success) {
        alert(data.message);
        if (window.parent !== window) {
          window.parent.postMessage({ type: 'formCompleted', formType: 'animal_checklist' }, '*');
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

