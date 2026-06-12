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
$formType = 'plant_checklist';
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

  // Build checklist template vars: P1Y/P1N/P1NA and optional text fields PNifyes/PNifno
  $checklistVars = [];
  // Items 1-35: yes/no/na checkboxes
  for ($i = 1; $i <= 35; $i++) {
    $checklistVars["P{$i}Y"] = isset($data["p{$i}_yes"]) && $data["p{$i}_yes"] === 'on' ? '✓' : '';
    $checklistVars["P{$i}N"] = isset($data["p{$i}_no"]) && $data["p{$i}_no"] === 'on' ? '✓' : '';
    $checklistVars["P{$i}NA"] = isset($data["p{$i}_na"]) && $data["p{$i}_na"] === 'on' ? '✓' : '';
  }
  // Conditional text fields
  $ifFields = [2 => 'ifyes', 8 => 'ifno', 11 => 'ifno', 18 => 'ifyes', 19 => 'ifyes',
    20 => 'ifyes', 21 => 'ifno', 24 => 'ifno', 25 => 'ifyes', 29 => 'ifyes', 30 => 'ifyes', 35 => 'ifyes'];
  foreach ($ifFields as $num => $suffix) {
    $key = "P{$num}_" . strtoupper($suffix);
    $checklistVars[$key] = trim($data["p{$num}_{$suffix}"] ?? '');
  }

  $defaults = [
    'p_title' => '', 'p_objective' => '', 'p_duration' => '', 'p_start' => '', 'p_end' => '',
    'p_principal' => '', 'p_course' => '', 'p_members' => 'N/A',
    'p_email' => '', 'p_phone' => '', 'p_affiliation' => '',
    'p_adviser' => '', 'p_adviser_phone' => '', 'p_adviser_email' => '', 'p_adviser_affiliation' => '',
    'p_species' => '', 'p_scientific_name' => '', 'p_common_en' => '', 'p_common_local' => '',
    'p_source' => '', 'p_origin' => '', 'p_conservation' => '', 'p_reason' => '',
    'p_background' => '', 'p_methodology' => '',
    'p_non_lc_docs' => '', 'p_invasive_docs' => '', 'p_biodiversity_docs' => '', 'p_toxic_docs' => '',
    'p_sign_name' => '', 'p_date_filled' => '', 'p_adviser_sign_name' => '', 'p_date_signed' => '',
  ];
  $data = array_merge($defaults, $data);

  try {
    $formDataJson = json_encode($data);
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
    
    if ($stmt->execute()) {
        

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Plant Use Checklist saved successfully!']);
        exit;
    } else {
        throw new Exception("Failed to save to database");
    }
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
<title>TAU REO · Ethical Guidelines — Plant Use</title>
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
    --shadow: 0 4px 24px rgba(15,41,66,.10);
    --shadow-lg: 0 12px 48px rgba(15,41,66,.16);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'DM Sans', sans-serif;
    background: var(--cream);
    color: var(--navy);
    min-height: 100vh;
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

  .form-wrapper {
    max-width: 980px;
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
  .field-group textarea:focus,
  .field-group select:focus {
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

  .check-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.88rem;
  }

  .check-table thead tr {
    background: var(--navy);
    color: var(--white);
  }

  .check-table thead th {
    padding: 11px 16px;
    text-align: left;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    text-transform: uppercase;
  }

  .check-table thead th:not(:first-child) {
    text-align: center;
    width: 65px;
  }

  .check-table tbody tr {
    border-bottom: 1px solid var(--gray-200);
    transition: background 0.15s;
  }

  .check-table tbody tr:hover { background: var(--gray-100); }

  .check-table tbody tr.section-row {
    background: var(--blue-soft);
  }

  .check-table tbody tr.section-row td {
    font-weight: 600;
    font-size: 0.78rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--navy);
    padding: 10px 16px;
  }

  .check-table td {
    padding: 12px 16px;
    vertical-align: middle;
    color: var(--gray-600);
    line-height: 1.4;
  }

  .check-table td:not(:first-child) {
    text-align: center;
    vertical-align: middle;
  }

  .check-table input[type="checkbox"] {
    width: 17px;
    height: 17px;
    cursor: pointer;
    accent-color: var(--navy);
  }

  .iftext {
    width: 100%;
    padding: 8px 12px;
    border: 1.5px solid var(--gray-200);
    border-radius: 6px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.85rem;
    color: var(--navy);
    background: var(--cream);
    outline: none;
    margin-top: 6px;
    transition: border-color 0.2s;
  }

  .iftext:focus {
    border-color: var(--gold);
    background: var(--white);
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
  <div class="page-title">Ethical Review Checklist — Plant Use</div>
</header>

<div class="form-wrapper">
  <div class="form-heading">
    <h1>Ethics Review Checklist for Plant Use</h1>
    <p>UREC · Research Ethics Review Committee</p>
  </div>

  <form id="plantForm" method="post">

    <div class="form-section">
      <div class="section-title"><span class="section-badge">A</span> Project Information</div>
      <div class="field-group"><label>Title of the Project</label>
        <input type="text" name="p_title" value="<?php echo htmlspecialchars($checklistSavedData['p_title'] ?? $qf01Data['project_title'] ?? $research_title); ?>"></div>
      <div class="field-group"><label>Objective / Purpose</label><textarea name="p_objective"><?php echo htmlspecialchars($checklistSavedData['p_objective'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Project / Study Duration</label><input type="text" name="p_duration" value="<?php echo htmlspecialchars($checklistSavedData['p_duration'] ?? ''); ?>" placeholder="e.g. 12 months"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Date to Start</label><input type="date" name="p_start" value="<?php echo htmlspecialchars($checklistSavedData['p_start'] ?? $qf01Data['start_date'] ?? ''); ?>"></div>
        <div class="field-group"><label>Date to Finish</label><input type="date" name="p_end" value="<?php echo htmlspecialchars($checklistSavedData['p_end'] ?? $qf01Data['end_date'] ?? ''); ?>"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>
      <div class="field-row">
        <div class="field-group"><label>Name of Principal Applicant</label>
          <input type="text" name="p_principal" value="<?php echo htmlspecialchars($checklistSavedData['p_principal'] ?? $qf01Data['principal_name'] ?? $applicant_name); ?>"></div>
        <div class="field-group"><label>Course</label><input type="text" name="p_course" value="<?php echo htmlspecialchars($checklistSavedData['p_course'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Name of Research Members (if any)</label>
        <textarea name="p_members" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['p_members'] ?? $qf01Data['members'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Contact Email</label><input type="email" name="p_email" value="<?php echo htmlspecialchars($checklistSavedData['p_email'] ?? $qf01Data['email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Contact Number</label><input type="tel" name="p_phone" value="<?php echo htmlspecialchars($checklistSavedData['p_phone'] ?? $qf01Data['phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Affiliation and Address</label><input type="text" name="p_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['p_affiliation'] ?? $qf01Data['department'] ?? ''); ?>"></div>
      <div class="field-row">
        <div class="field-group"><label>Research Adviser</label><input type="text" name="p_adviser" value="<?php echo htmlspecialchars($checklistSavedData['p_adviser'] ?? $qf01Data['adviser_name'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Contact Number</label><input type="tel" name="p_adviser_phone" value="<?php echo htmlspecialchars($checklistSavedData['p_adviser_phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Adviser Email</label><input type="email" name="p_adviser_email" value="<?php echo htmlspecialchars($checklistSavedData['p_adviser_email'] ?? $qf01Data['adviser_email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Affiliation and Address</label><input type="text" name="p_adviser_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['p_adviser_affiliation'] ?? ''); ?>"></div>
      </div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">C</span> Project / Study Description</div>
      <div class="field-row">
        <div class="field-group"><label>Plant Species</label><input type="text" name="p_species" value="<?php echo htmlspecialchars($checklistSavedData['p_species'] ?? ''); ?>"></div>
        <div class="field-group"><label>Scientific Name</label><input type="text" name="p_scientific_name" value="<?php echo htmlspecialchars($checklistSavedData['p_scientific_name'] ?? ''); ?>"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>English Common Name</label><input type="text" name="p_common_en" value="<?php echo htmlspecialchars($checklistSavedData['p_common_en'] ?? ''); ?>"></div>
        <div class="field-group"><label>Vernacular Common Name (local dialect)</label><input type="text" name="p_common_local" value="<?php echo htmlspecialchars($checklistSavedData['p_common_local'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Sources of Planting Material (e.g. laboratory, micro facility)</label>
        <input type="text" name="p_source" value="<?php echo htmlspecialchars($checklistSavedData['p_source'] ?? ''); ?>"></div>
      <div class="field-group"><label>Origin of Plant Used</label>
        <select name="p_origin">
          <option value="">— Select origin type —</option>
          <option <?php echo(isset($checklistSavedData['p_origin']) && $checklistSavedData['p_origin'] === 'Native – indigenous plants, adapted to local soils and climate') ? 'selected' : ''; ?>>Native – indigenous plants, adapted to local soils and climate</option>
          <option <?php echo(isset($checklistSavedData['p_origin']) && $checklistSavedData['p_origin'] === 'Non-native – introduced plants, not necessarily threatening') ? 'selected' : ''; ?>>Non-native – introduced plants, not necessarily threatening</option>
          <option <?php echo(isset($checklistSavedData['p_origin']) && $checklistSavedData['p_origin'] === 'Invasive – non-native plants that spread rapidly and can cause harm') ? 'selected' : ''; ?>>Invasive – non-native plants that spread rapidly and can cause harm</option>
          <option <?php echo(isset($checklistSavedData['p_origin']) && $checklistSavedData['p_origin'] === 'Noxious Weed – designated as a threat to public health, agriculture, and ecology') ? 'selected' : ''; ?>>Noxious Weed – designated as a threat to public health, agriculture, and ecology</option>
        </select>
      </div>
      <div class="field-group"><label>Conservation Status (IUCN)</label>
        <select name="p_conservation">
          <option value="">— Select IUCN status —</option>
          <?php foreach (['LC – Least Concern', 'NT – Near Threatened', 'VU – Vulnerable', 'EN – Endangered', 'CR – Critically Endangered', 'EW – Extinct in the Wild', 'EX – Extinct', 'DD – Data Deficient', 'NE – Not Evaluated'] as $opt): ?>
            <option <?php echo(isset($checklistSavedData['p_conservation']) && $checklistSavedData['p_conservation'] === $opt) ? 'selected' : ''; ?>><?php echo $opt; ?></option>
          <?php
endforeach; ?>
        </select>
      </div>
      <div class="field-group"><label>Reason / Basis for Selecting the Species</label>
        <textarea name="p_reason" style="min-height:80px"><?php echo htmlspecialchars($checklistSavedData['p_reason'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>Background and Significance of the Research Procedure</label>
        <textarea name="p_background" style="min-height:110px"><?php echo htmlspecialchars($checklistSavedData['p_background'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>Methodology / Experimental Design</label>
        <textarea name="p_methodology" style="min-height:110px" placeholder="Describe the methodology with focus on data collection procedures."><?php echo htmlspecialchars($checklistSavedData['p_methodology'] ?? ''); ?></textarea></div>

      <div class="note-box" style="margin-top:24px">
        Provide supporting documents for the following where applicable:
      </div>
      <div class="field-group"><label>If NOT using plants with LC, DD, or NE conservation status — documents to support approval</label>
        <textarea name="p_non_lc_docs" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['p_non_lc_docs'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>If working with invasive species — justifications/documents (ref. DENR National Invasive Species Strategy and Action Plan)</label>
        <textarea name="p_invasive_docs" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['p_invasive_docs'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>If working on Biodiversity Assessment — documents/justifications (ref. DENR categories for wildlife/plant species)</label>
        <textarea name="p_biodiversity_docs" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['p_biodiversity_docs'] ?? ''); ?></textarea></div>
      <div class="field-group"><label>If working with toxic chemicals as main factor — documents (ref. PD 1144, RA 1660, RA 6969)</label>
        <textarea name="p_toxic_docs" style="min-height:70px"><?php echo htmlspecialchars($checklistSavedData['p_toxic_docs'] ?? ''); ?></textarea></div>
    </div>

    <div class="form-section">
      <div class="section-title"><span class="section-badge">D</span> Ethics Checklist</div>
      <div class="note-box">Answer YES, NO, or N/A for each criterion. For items requiring elaboration, fill in the text field that appears.</div>
      <table class="check-table">
        <thead><tr><th>Criteria</th><th>YES</th><th>NO</th><th>N/A</th></tr></thead>
        <tbody>
          <tr class="section-row"><td colspan="4">Research Purpose and Objectives</td></tr>
          <?php
$plantCriteria = [
  1 => "The research has a clearly defined and ethical purpose that justifies the use of crops or plants (e.g. food security, environmental sustainability, or crop improvement).",
  2 => "The research may directly or indirectly affect local communities (food security, agricultural livelihoods, social equity). If yes, state how.",
  3 => "The anticipated benefits to society, local communities, or the environment are clearly articulated and well-justified.",
  4 => "The use of the plant species is integral to achieving the research objective.",
  5 => "The research takes into account the economic feasibility and its potential benefit to small-scale farmers or marginalized populations.",
  6 => "Research benefits will be accessible to all farmers, particularly those in low-resource or rural areas.",
  7 => "The research involves collaboration between different disciplines (agriculture, ethics, environmental science) to address issues holistically.",
  8 => "The plants to be used are non-endangered and not subject to conservation regulations. If no, provide proper documents.",
  9 => "The plants are sourced sustainably (not from protected areas, illegal trade, or over-exploited ecosystems).",
  10 => "The sources of the plants to be used in the study are identified. (Attach written information on source)",
  11 => "The research follows DENR/International protocols for Biodiversity Assessment (if applicable). If no, provide specific detailed protocols.",
  12 => "For research involving private or indigenous land, informed consent has been obtained from landowners or communities.",
  13 => "If using traditional knowledge or crops from indigenous communities, permission has been obtained and intellectual property rights are respected.",
  14 => "An environmental impact assessment has been conducted (biodiversity, soil health, water use, potential cross-contamination).",
  15 => "Genetically modified crops are involved in the research.",
  16 => "If GMO crops are involved, potential ecological risks (e.g. gene flow to wild relatives) are evaluated and mitigated.",
  17 => "Appropriate containment measures are in place to prevent unintended spread of GM or invasive crops.",
  18 => "There are risks to the health of crops being studied or surrounding crops (disease, pest invasion). If yes, describe precautions.",
  19 => "The research potentially affects local biodiversity (non-native or GMO species spreading). If yes, describe precautions.",
  20 => "The research will use pesticides and fertilizers. If yes, list them (including type and chemical family).",
  21 => "The research will use registered pesticides and fertilizers (FPA list for inorganic; BAFS list for organic). If no, provide written justification.",
  22 => "In crop production studies with \"as needed\" pesticide use, the safest or least toxic options are chosen to minimize environmental risks.",
  23 => "Pesticides are used as a last resort after considering safer alternatives (organic methods, biological control, IPM).",
  24 => "For toxicology assays, there is a designated and safe working area within the University. If no, indicate the alternative areas.",
  25 => "Highly toxic materials will be used in toxicology assays. If yes, indicate chemicals and classify by mode of action, structure, toxicity, persistence, and application method.",
  26 => "The study is intended to develop biologically-based pesticide/fertilizer.",
  27 => "Methods for extracting and applying botanical pesticides/fertilizers are environmentally sustainable, minimizing waste and harmful byproducts.",
  28 => "Botanical extracts/plant products are being compared against conventional chemical pesticides/fertilizers for efficacy, cost-effectiveness, and environmental impact.",
  29 => "The study is intended for open agricultural fields. If yes, have extracts been rigorously tested in controlled conditions? Describe precautions to ensure researcher safety.",
  30 => "There are risks to local biodiversity from inorganic pesticides/fertilizers (harm to pollinators, beneficial insects). If yes, describe precautions.",
  31 => "Genetic modification (GM) or biotechnological techniques will be used.",
  32 => "Plants and methods are compliant with national and international regulations (e.g. biosafety regulations).",
  33 => "Measures are in place to monitor and contain GM plants to prevent spread or contamination with non-GMO crops or wild plant populations.",
  34 => "Proper safety protocols are in place to protect researchers handling plants, chemicals, GMOs, or hazardous materials (e.g. PPE, lab protocols, disposal site).",
  35 => "Animals will be used in any part of the crop research (e.g. pest management, pollination studies). If yes, state how welfare and ethical treatment are ensured per animal welfare guidelines."
];

$sections = [
  8 => "Plant Selection, Land Use, and Sourcing",
  14 => "Environmental Impact",
  18 => "Risk Assessment",
  35 => "Animal Welfare (if applicable)"
];

foreach ($plantCriteria as $i => $text):
  if (isset($sections[$i])): ?>
              <tr class="section-row"><td colspan="4"><?php echo $sections[$i]; ?></td></tr>
            <?php
  endif; ?>
            <tr>
              <td>
                <?php echo $text; ?>
                <?php
  $ifFields = [2 => 'ifyes', 8 => 'ifno', 11 => 'ifno', 18 => 'ifyes', 19 => 'ifyes', 20 => 'ifyes', 21 => 'ifno', 24 => 'ifno', 25 => 'ifyes', 29 => 'ifyes', 30 => 'ifyes', 35 => 'ifyes'];
  if (isset($ifFields[$i])): ?>
                  <input type="text" class="iftext" name="p<?php echo $i; ?>_<?php echo $ifFields[$i]; ?>" 
                         value="<?php echo htmlspecialchars($checklistSavedData["p{$i}_{$ifFields[$i]}"] ?? ''); ?>"
                         placeholder="If <?php echo strtoupper($ifFields[$i] === 'ifyes' ? 'YES' : 'NO'); ?> — provide details…">
                <?php
  endif; ?>
              </td>
              <td><input type="checkbox" name="p<?php echo $i; ?>_yes" <?php echo(isset($checklistSavedData["p{$i}_yes"]) && $checklistSavedData["p{$i}_yes"] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'p<?php echo $i; ?>')"></td>
              <td><input type="checkbox" name="p<?php echo $i; ?>_no" <?php echo(isset($checklistSavedData["p{$i}_no"]) && $checklistSavedData["p{$i}_no"] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'p<?php echo $i; ?>')"></td>
              <td><input type="checkbox" name="p<?php echo $i; ?>_na" <?php echo(isset($checklistSavedData["p{$i}_na"]) && $checklistSavedData["p{$i}_na"] === 'on') ? 'checked' : ''; ?> onchange="toggleYNA(this,'p<?php echo $i; ?>')"></td>
            </tr>
          <?php
endforeach; ?>
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
function updateDuration() {
  const start = document.querySelector('[name="p_start"]').value;
  const end = document.querySelector('[name="p_end"]').value;
  if (start && end) {
    const s = new Date(start);
    const e = new Date(end);
    if (e < s) return;
    let months = (e.getFullYear() - s.getFullYear()) * 12 + (e.getMonth() - s.getMonth());
    if (e.getDate() > s.getDate()) months++;
    document.querySelector('[name="p_duration"]').value = months + (months > 1 ? ' months' : ' month');
  }
}

function triggerAutosave() {
    const formData = new FormData(document.getElementById('plantForm'));
    formData.append('form_type', 'plant_checklist');
    fetch('autosave-progress.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
            if (res.success) document.getElementById('autosave-status').innerHTML = 'Draft saved at ' + res.timestamp;
        });
}

document.getElementById('plantForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.innerHTML = 'Saving…';
  fetch('fill-Plant-checklist.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => {
      if (data.success) {
        alert(data.message);
        if (window.parent !== window) {
          window.parent.postMessage({ type: 'formCompleted', formType: 'plant_checklist' }, '*');
        } else { location.href = 'documents.php?success=checklist_saved'; }
      } else {
        alert('Error: ' + data.message);
        btn.disabled = false; btn.innerHTML = 'Save &amp; Finalize Checklist';
      }
    });
});

document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[name="p_start"]').addEventListener('change', updateDuration);
    document.querySelector('[name="p_end"]').addEventListener('change', updateDuration);
    document.querySelectorAll('input, textarea, select').forEach(el => {
        el.addEventListener('change', triggerAutosave);
    });
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
