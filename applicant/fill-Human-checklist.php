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
$formType = 'human_checklist';
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

  // --- Basic Review (Q1-20): YES / NO only ---
  $checkboxFields = [];
  for ($i = 1; $i <= 20; $i++) {
    $checkboxFields["H{$i}Y"] = isset($data["h{$i}_yes"]) && $data["h{$i}_yes"] === 'on' ? '✓' : '';
    $checkboxFields["H{$i}N"] = isset($data["h{$i}_no"])  && $data["h{$i}_no"]  === 'on' ? '✓' : '';
  }

  // --- Special Review (Q21-53): YES / NO / N/A ---
  for ($i = 21; $i <= 53; $i++) {
    $checkboxFields["H{$i}Y"]  = isset($data["h{$i}_yes"]) && $data["h{$i}_yes"] === 'on' ? '✓' : '';
    $checkboxFields["H{$i}N"]  = isset($data["h{$i}_no"])  && $data["h{$i}_no"]  === 'on' ? '✓' : '';
    $checkboxFields["H{$i}NA"] = isset($data["h{$i}_na"])  && $data["h{$i}_na"]  === 'on' ? '✓' : '';
  }

  // --- Informed Consent Checklist (IC1-10): YES / NO / N/A ---
  for ($i = 1; $i <= 10; $i++) {
    $checkboxFields["IC{$i}Y"]  = isset($data["ic{$i}_yes"]) && $data["ic{$i}_yes"] === 'on' ? '✓' : '';
    $checkboxFields["IC{$i}N"]  = isset($data["ic{$i}_no"])  && $data["ic{$i}_no"]  === 'on' ? '✓' : '';
    $checkboxFields["IC{$i}NA"] = isset($data["ic{$i}_na"])  && $data["ic{$i}_na"]  === 'on' ? '✓' : '';
  }

  $defaults = [
    'h_title' => '', 'h_objective' => '', 'h_start' => '', 'h_end' => '',
    'h_principal' => '', 'h_course' => '', 'h_members' => 'N/A',
    'h_email' => '', 'h_phone' => '', 'h_affiliation' => '',
    'h_adviser' => '', 'h_adviser_phone' => '', 'h_adviser_email' => '',
    'h_adviser_affiliation' => '',
    'h_sign_name' => '', 'h_date_filled' => '', 'h_adviser_sign_name' => '', 'h_date_signed' => '',
    'ic_explanation' => '',
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
    $stmt->execute();

    

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'message' => 'Human Use Checklist saved successfully!']);
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
<title>TAU REO · Ethical Guidelines — Human Use</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --navy:#0f2942;--gold:#c9993a;--cream:#faf8f3;--white:#ffffff;
    --gray-100:#f4f2ed;--gray-200:#e8e4da;--gray-400:#a09880;--gray-600:#5a5040;
    --blue-soft:#d0e4f0;--shadow:0 4px 24px rgba(15,41,66,.10);--shadow-lg:0 12px 48px rgba(15,41,66,.16);
  }
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'DM Sans',sans-serif;background:var(--cream);color:var(--navy);min-height:100vh}
  .page-header{background:var(--navy);padding:20px 32px;display:flex;align-items:center;gap:18px;position:sticky;top:0;z-index:100;box-shadow:0 2px 16px rgba(0,0,0,.25)}
  .page-header .brand{color:var(--gold);font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;letter-spacing:.02em;white-space:nowrap;border-right:1px solid rgba(255,255,255,.15);padding-right:18px}
  .page-header .page-title{color:rgba(255,255,255,.85);font-size:.9rem;font-weight:400}
  .form-wrapper{max-width:920px;margin:40px auto 80px;padding:0 24px}
  .form-heading{margin-bottom:36px;padding-bottom:24px;border-bottom:2px solid var(--gray-200)}
  .form-heading h1{font-family:'Playfair Display',serif;font-size:2rem;color:var(--navy);margin-bottom:8px}
  .form-heading p{color:var(--gray-400);font-size:.95rem;font-weight:300}
  .form-section{background:var(--white);border-radius:12px;padding:32px;margin-bottom:24px;box-shadow:var(--shadow);border:1px solid var(--gray-200)}
  .section-title{font-family:'Playfair Display',serif;font-size:1.15rem;color:var(--navy);margin-bottom:24px;padding-bottom:12px;border-bottom:1px solid var(--gray-200);display:flex;align-items:center;gap:10px}
  .section-badge{background:var(--navy);color:var(--gold);font-family:'DM Sans',sans-serif;font-size:.7rem;font-weight:600;padding:3px 10px;border-radius:20px;letter-spacing:.06em;text-transform:uppercase}
  .field-group{margin-bottom:20px}
  .field-group label{display:block;font-size:.82rem;font-weight:600;color:var(--gray-600);margin-bottom:6px;letter-spacing:.04em;text-transform:uppercase;display:flex;justify-content:space-between;align-items:center}
  .remark-indicator{color:#d32f2f;font-size:.7rem;font-weight:700;background:#ffebee;padding:2px 8px;border-radius:4px;display:flex;align-items:center;gap:4px}
  .feedback-banner{background:#fff8e1;border:1px solid #ffd54f;border-radius:12px;padding:20px;margin-bottom:30px;box-shadow:var(--shadow)}
  .feedback-banner h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:#795548;margin-bottom:12px;display:flex;align-items:center;gap:10px}
  .feedback-item{font-size:.9rem;background:white;padding:12px;border-radius:8px;margin-bottom:8px;border-left:4px solid #ffd54f}
  .feedback-meta{font-size:.7rem;color:#8d6e63;margin-bottom:4px;font-weight:600}
  .field-group input,.field-group textarea,.field-group select{width:100%;padding:12px 16px;border:1.5px solid var(--gray-200);border-radius:8px;font-family:'DM Sans',sans-serif;font-size:.95rem;color:var(--navy);background:var(--cream);transition:border-color .2s,box-shadow .2s;outline:none}
  .field-group input:focus,.field-group textarea:focus{border-color:var(--gold);box-shadow:0 0 0 3px rgba(201,153,58,.15);background:var(--white)}
  .field-group textarea{min-height:100px;resize:vertical;line-height:1.6}
  .field-row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
  .check-table{width:100%;border-collapse:collapse;font-size:.9rem}
  .check-table thead tr{background:var(--navy);color:var(--white)}
  .check-table thead th{padding:11px 16px;text-align:left;font-size:.75rem;font-weight:600;letter-spacing:.05em;text-transform:uppercase}
  .check-table thead th:not(:first-child){text-align:center;width:65px}
  .check-table tbody tr{border-bottom:1px solid var(--gray-200);transition:background .15s}
  .check-table tbody tr:hover{background:var(--gray-100)}
  .check-table tbody tr.section-row{background:var(--blue-soft)}
  .check-table tbody tr.section-row td{font-weight:600;font-size:.8rem;letter-spacing:.04em;text-transform:uppercase;color:var(--navy);padding:10px 16px}
  .check-table tbody tr.info-row td{font-size:.82rem;color:var(--gray-600);padding:10px 16px;font-style:italic;background:var(--gray-100);border-left:3px solid var(--gold)}
  .check-table td{padding:13px 16px;vertical-align:middle;color:var(--gray-600);line-height:1.45}
  .check-table td:not(:first-child){text-align:center}
  .check-table input[type="checkbox"]{width:17px;height:17px;cursor:pointer;accent-color:var(--navy)}
  .guideline-card{background:var(--navy);color:var(--white);border-radius:12px;padding:28px;margin-bottom:24px}
  .guideline-card h3{font-family:'Playfair Display',serif;color:var(--gold);font-size:1.1rem;margin-bottom:14px}
  .guideline-card ul{list-style:none;display:flex;flex-direction:column;gap:10px}
  .guideline-card ul li{font-size:.88rem;line-height:1.55;color:rgba(255,255,255,.82);padding-left:18px;position:relative}
  .guideline-card ul li::before{content:'›';position:absolute;left:0;color:var(--gold);font-size:1.1rem}
  .note-box{background:var(--blue-soft);border-left:4px solid var(--navy);padding:12px 16px;border-radius:0 8px 8px 0;font-size:.87rem;color:var(--navy);margin-bottom:20px;line-height:1.5}
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
  <div class="page-title">Ethical Review Checklist — Human Use</div>
</header>

<div class="form-wrapper">
  <?php
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
    <h1>Ethics Checklist</h1>
    <p>Research Ethics Office · To complete the Checklist please check the Yes or No box. If any of the answers checked are in bold (Yes or No), an ethical issue arises that requires you either to reconsider your procedures, or to provide additional information.</p>
  </div>

  <div class="guideline-card">
    <h3>Four Basic Ethical Principles</h3>
    <ul>
      <li>Voluntary participation — participation must be freely given and not coerced</li>
      <li>Informed consent — respondents must fully understand the research before consenting</li>
      <li>Safety and security of respondents — physical, social, and psychological safety at all times</li>
      <li>Confidentiality / Anonymity — identities and data must be protected throughout the study</li>
    </ul>
  </div>

  <form id="humanForm" method="post">

    <!-- ===== SECTION A: PROJECT INFORMATION ===== -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">A</span> Project Information</div>
      <div class="field-group"><label>Title of the Project <?php renderFieldRemark('h_title', $checklistSavedData); ?></label>
        <input type="text" name="h_title" value="<?php echo htmlspecialchars($checklistSavedData['h_title'] ?? $qf01Data['project_title'] ?? $research_title); ?>" placeholder="Enter the full title of the research project"></div>
      <div class="field-group"><label>Objective / Purpose <?php renderFieldRemark('h_objective', $checklistSavedData); ?></label>
        <textarea name="h_objective"><?php echo htmlspecialchars($checklistSavedData['h_objective'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Date to Start <?php renderFieldRemark('h_start', $checklistSavedData); ?></label><input type="date" name="h_start" value="<?php echo htmlspecialchars($checklistSavedData['h_start'] ?? $qf01Data['start_date'] ?? ''); ?>"></div>
        <div class="field-group"><label>Date to Finish <?php renderFieldRemark('h_end', $checklistSavedData); ?></label><input type="date" name="h_end" value="<?php echo htmlspecialchars($checklistSavedData['h_end'] ?? $qf01Data['end_date'] ?? ''); ?>"></div>
      </div>
    </div>

    <!-- ===== SECTION B: ADMINISTRATIVE INFORMATION ===== -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">B</span> Administrative Information</div>
      <div class="field-row">
        <div class="field-group"><label>Name of Principal Applicant <?php renderFieldRemark('h_principal', $checklistSavedData); ?></label>
          <input type="text" name="h_principal" value="<?php echo htmlspecialchars($checklistSavedData['h_principal'] ?? $qf01Data['principal_name'] ?? $applicant_name); ?>"></div>
        <div class="field-group"><label>Course / Department</label><input type="text" name="h_course" value="<?php echo htmlspecialchars($checklistSavedData['h_course'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Name of Research Members (if any)</label>
        <textarea name="h_members" style="min-height:70px" placeholder="List all co-researchers, separate by comma or N/A"><?php echo htmlspecialchars($checklistSavedData['h_members'] ?? $qf01Data['members'] ?? ''); ?></textarea></div>
      <div class="field-row">
        <div class="field-group"><label>Contact Email Address</label><input type="email" name="h_email" value="<?php echo htmlspecialchars($checklistSavedData['h_email'] ?? $qf01Data['email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Contact Number</label><input type="tel" name="h_phone" value="<?php echo htmlspecialchars($checklistSavedData['h_phone'] ?? $qf01Data['phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-group"><label>Affiliation and Address</label><input type="text" name="h_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['h_affiliation'] ?? $qf01Data['department'] ?? ''); ?>"></div>
      <div class="field-row">
        <div class="field-group"><label>Research Adviser</label><input type="text" name="h_adviser" value="<?php echo htmlspecialchars($checklistSavedData['h_adviser'] ?? $qf01Data['adviser_name'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Contact Number</label><input type="tel" name="h_adviser_phone" value="<?php echo htmlspecialchars($checklistSavedData['h_adviser_phone'] ?? ''); ?>"></div>
      </div>
      <div class="field-row">
        <div class="field-group"><label>Adviser Email Address</label><input type="email" name="h_adviser_email" value="<?php echo htmlspecialchars($checklistSavedData['h_adviser_email'] ?? $qf01Data['adviser_email'] ?? ''); ?>"></div>
        <div class="field-group"><label>Adviser Affiliation and Address</label><input type="text" name="h_adviser_affiliation" value="<?php echo htmlspecialchars($checklistSavedData['h_adviser_affiliation'] ?? ''); ?>"></div>
      </div>
    </div>

    <?php
    // =========================================================================
    // DATA — BASIC REVIEW Q1-20 (YES / NO only)
    // =========================================================================
    $basicReview = [
      1  => ['principle' => '1. Voluntary Participation',
              'text' => 'Will the subjects/participants be told that they can discontinue their participation at any time without incurring any penalties for doing so?'],
      2  => ['text' => 'Do you foresee that the subjects/participants might feel or perceive any degree of manipulation, coercion, constraint, or undue influence concerning any aspect of their participation in the study?'],
      3  => ['text' => 'Will there be any actual or perceived material inducements to participate that exceed reasonable compensations for such things as transportation, unusually lengthy time demands, etc.?'],
      4  => ['text' => 'Will there be any actual or perceived social inducements to participate that exceed such things as interest in the research, an interesting activity, etc.?'],
      5  => ['text' => 'Will there be any actual or perceived disincentives for not participating in the research?'],
      6  => ['principle' => '2. Informed Consent',
              'text' => 'Will the people studied be aware that they are the subjects of your research/scholarship?'],
      7  => ['text' => 'Does the study involve temporarily misleading the subjects/participants as to the study\'s purposes, incomplete disclosure of the study\'s purposes, or temporary concealment of other information (e.g., staged occurrences, having subjects/participants do one thing while in fact something else they do is being observed, etc.)?'],
      8  => ['text' => 'Will subjects\'/participants\' written consent be obtained, or if this is inappropriate, will an alternative method of obtaining informed consent be used?'],
      9  => ['text' => 'Will free and informed consent procedures be used both at the outset of the subject\'s participation, and thereafter throughout the study (e.g., by notifying subjects/participants of any later changes or developments that might influence informed consent, and seeking further consent to these)?'],
      10 => ['text' => 'Will informed consent information include a statement of the <strong>research purpose</strong>, the identity of the investigator(s), the <strong>expected duration and nature of participation</strong>, <strong>a description of research procedures</strong>, and <strong>a description of any foreseeable harms and benefits that may arise from participation</strong>?'],
      11 => ['text' => 'Before giving their consent to participate, will the subjects/participants be informed fully of the nature of their research involvement, and of all features of the research that will reasonably might be expected to influence their willingness to participate?'],
      12 => ['text' => 'Will the information describing the study and the materials used to seek consent be worded in language clearly comprehensible to the subjects/participants?'],
      13 => ['principle' => '3. Safety and Security of Participants',
              'text' => 'Does the study involve physical stress (or the expectation thereof) such as might result from heat, noise, electric shock, pain, sleep loss, physical deprivation, drugs, alcohol, etc.?'],
      14 => ['text' => 'Do you foresee that the study might result in the subject\'s/participant\'s experiencing mental discomfort (e.g., fear, anxiety, loss of self-esteem, shame, guilt, embarrassment, becoming aware of personal weaknesses)?'],
      15 => ['text' => 'Will the investigator attempt to induce long-term change in subjects\'/participants\' behavior or attitudes?'],
      16 => ['text' => 'Will any individually-identifiable information about subjects/participants be disclosed without their informed consent (e.g., to teachers, doctors, therapists, parents, employers, other researchers, etc.)?'],
      17 => ['text' => 'Could public presentation of the study\'s results possibly harm either the subject/participant, or his/her membership group?'],
      18 => ['text' => 'Has the investigator taken all possible steps in the design of the study to balance potential harms to the subjects/participants against potential benefits of the research/scholarship?'],
      19 => ['principle' => '4. Confidentiality and/or Anonymity',
              'text' => 'Is the confidentiality of the subject\'s/participant\'s identity positively ensured?'],
      20 => ['text' => 'Are there circumstances under which the subject\'s/participant\'s identity might be deduced by someone other than the investigator if the study results are presented publicly?<br><em>Note: There may be situations in which the subjects/participants agree to or even seek public identification. If this applies, explain, and also provide an assurance that you will obtain consent to revealing subjects\'/participants\' identities.</em>'],
    ];

    // =========================================================================
    // DATA — SPECIAL REVIEW Q21-53 (YES / NO / N/A)
    // =========================================================================
    $specialReview = [
      21 => ['text' => 'If the investigator plans to induce short-term behavioral or attitude change, will such change definitely be reversible?'],
      22 => ['text' => 'If private materials (documents, third-person interview contents, etc.) provided by the subject will be made public as a consequence of the scholarship/research, will due care be taken to obtain subjects\'/participants\' written consent, and otherwise to avoid infringing on the subjects\'/participants\' rights?'],
      23 => ['text' => 'If the study takes place within or in cooperation with an institution or agency (e.g., schools, day care centres, churches, seniors\' homes, hospitals, social work agencies, playgrounds, prisons, etc.), has written approval been obtained from its administrators?<br><em>Note: Attach copies. If no letters of approval can yet be provided (e.g., because agency approval is contingent on University ethics approval), attach an explanatory note undertaking not to begin research before you are in receipt of approval letters, and to submit copies of such letters to UREC immediately upon receipt. The requirement of approval from external institutions may not apply in instances where it would interfere with free inquiry. If so, explain.</em>'],
      24 => ['text' => 'If the subjects/participants are children (under age 18), will written parental or guardian consent be obtained?'],
      25 => ['text' => 'If a written consent form is used, will copies be given to the subjects/participants to retain?'],
      26 => ['text' => 'If the subjects/participants are legally or otherwise incompetent to provide informed consent, will the written consent of authorized third parties be obtained?'],
      27 => ['text' => 'If the subjects/participants are not legally competent, is there any other legally-competent group that could be studied in order to address the research question?'],
      28 => ['text' => 'If the subjects/participants are drawn from institutionalized or otherwise "captive or dependent" populations (e.g., in prisons, hospitals, psychiatric facilities, mandatory treatment programs, etc.), will special care be taken to ensure that consent is given freely, and that no actual or perceived coercion, constraint, or undue inducement to participate is present?<br><em>Note: In your project description, be sure to describe clearly how this will be achieved.</em>'],
      29 => ['text' => 'If the consent of a parent or an authorized third party is obtained, will each subject/participant also be informed independently of his/her right to decline to participate at any point in the study?'],
      30 => ['text' => 'If the study will be conducted in a country other than Philippines and/or under jurisdiction of an institution other than the TAU, and if an ethics review body that has jurisdiction in that country or institution exists, will the study undergo review by that ethics body before the research begins?<br><em>Note: If so, please attach documentation, or attach a note undertaking to provide documentation to Research Services immediately upon receipt.</em>'],
      31 => ['text' => 'If there is any possibility of physical danger or harm to the subjects/participants, will all necessary and prudent measures be taken to ensure their safety (e.g., from dangers such as electrical shock, lack of oxygen, falls, traffic or industrial accidents, the possibility of hearing or vision loss, etc.)?'],
      32 => ['text' => 'If subjects/participants have initially formed any false impressions about the purposes of the study or the nature of information collected, if the study purposes were not completely disclosed initially, or if any information was concealed temporarily, will full disclosure be made at the conclusion of data collection? Will the reasons for false impressions, concealment, or incomplete disclosure be explained; and will subjects/participants then be given the opportunity to withdraw their data/information, should they so choose? Will everything possible be done to re-establish trust and respect?'],
      33 => ['text' => 'If information on subjects/participants will be obtained from third parties (e.g., institutions, doctors, other researchers, etc.), will subjects/participants be so informed, and will their written consent be obtained?'],
      34 => ['text' => 'If any adverse subject responses to the study are anticipated, have procedures been devised to ameliorate such responses?'],
      35 => ['text' => 'If the possibility of commercialization of the research findings exists, will the subjects/participants be so informed?'],
      36 => ['text' => 'If there is any actual or apparent conflict of interest on the part of the investigator(s), their institutions, or their sponsors, will the participants be so informed?'],
      37 => ['text' => 'If the research/scholarship involves secondary uses of already-collected data or information regarding identifiable individuals, will appropriate measures be taken to ensure the privacy of the individuals and the confidentiality of the data, and to minimize potential harms to subjects/participants?'],
      38 => ['text' => 'If secondary use is to be made of already-collected data or information regarding identifiable individuals, will appropriate measures be taken to ensure the privacy of the individuals and the confidentiality of the data, and to minimize potential harms to subjects/participants?'],
      39 => ['text' => 'If the study concerns generic behaviors/characteristics that are not specific to particular, identifiable social or cultural groups (e.g., child poverty, access to legal services), will any persons be excluded from participation on the basis of culture, religion, race, ethnicity, mental or physical disability, sexual orientation, sex, or age?'],
      40 => ['text' => 'If information is to be presented to and/or collected from subjects/participants in a language that the investigator does not speak/understand fully, will every possible effort be made to ensure that translation is as clear and accurate as possible?'],
      41 => ['text' => 'Does the study include the use of personal health information? The Data Privacy Act outlines responsibilities of researchers to ensure safeguards that will protect personal health information. If yes, in an attachment to this checklist, please indicate provisions that will be made to comply with this Act.'],
      42 => ['note_before' => 'Answer Questions 42–47 if your project involves sub-cultural, cultural, national, ethnic, or religious group characteristics as a focus of study.',
              'text' => 'Will the investigator ensure that privacy (as defined from the standpoint of the subjects/participants) will be respected?'],
      43 => ['text' => 'Will the investigator ensure the accurate description of customs, community, and heritage?'],
      44 => ['text' => 'In the case of field work in which informed individual consent cannot be obtained because of cultural constraints, has the investigator devised methodological safeguards to protect the subjects/participants fully?<br><em>Note: If so, describe these fully in the project description or in an attached note.</em>'],
      45 => ['text' => 'If the study involves indigenous peoples or subcultural, cultural, national, ethnic, or religious groups, and if individuals are to be interviewed, will the investigator exercise caution in generalizing findings for these individuals to the culture or group as a whole?<br><em>Note: Explain fully how this will be done, e.g., by representing differing viewpoints that may exist within the community, and/or consulting community institutions and representatives, etc.</em>'],
      46 => ['text' => 'If the study involves indigenous peoples or subcultural, cultural, national, ethnic, or religious groups, will the investigator cooperate with community institutions, consult within the community, and/or otherwise ensure that the group has been informed and involved as fully as is appropriate and possible concerning the study?'],
      47 => ['text' => 'If the study involves indigenous peoples or subcultural, cultural, national, ethnic, or religious groups, will the investigator provide the community with an appropriate opportunity to react to the study\'s findings before they are presented publicly?<br><em>Note: If the community, or segments of it, disagree with the findings after considered discussion and exchange, the investigator should undertake to provide an opportunity to make the community\'s views known, and/or should report accurately the contents of such disagreements in any public presentations of the study.</em>'],
      48 => ['note_before' => 'Answer Questions 48–53 only if your study involves the purchase or acquisition of manuscripts, documents, or artifacts. Otherwise, indicate N/A for this section.',
              'text' => 'Will the investigator ensure that the acquisition of materials will be for the sole purpose of research/scholarship, and not for personal gain, private collection, or sale?'],
      49 => ['text' => 'Will the acquisition of materials meet the legal requirements of the country of origin?'],
      50 => ['text' => 'If legal ownership of materials is in doubt, will the investigator inform the proper authorities of the country concerned, and abide by their decision regarding disposition?'],
      51 => ['text' => 'Will the investigator ensure proper storage, protection, security, and cataloguing of acquired materials?'],
      52 => ['text' => 'If acquired materials are to be deaccessioned or discarded after use, will the investigator ensure that they are first offered to public or educational institutions in the area of origin, then offered to Philippine institutions, and/or otherwise made accessible in the public domain?'],
      53 => ['text' => 'If the acquired materials are publicly exhibited, discussed, or published, will the investigator attempt to ensure that no undue embarrassment is caused to the individuals, groups, or countries of the materials\' origin?'],
    ];

    // =========================================================================
    // DATA — INFORMED CONSENT CHECKLIST IC1-10 (YES / NO / N/A)
    // =========================================================================
    $icChecklist = [
      1  => 'The letterhead of TAU is used',
      2  => 'Identity of the researcher and contact information',
      3  => 'Research topic/question, nature of participation, duration, and research procedures',
      4  => 'Risks and benefits of participation',
      5  => 'State how feedback is provided to the participants',
      6  => 'Confidentiality and/or anonymity',
      7  => 'Point of withdrawal and refusal to answer questions',
      8  => 'Section for Consent/assent to participate in the study',
      9  => 'Parent/Guardian Assent Form',
      10 => 'Point of withdrawal in the Parent/Guardian Assent Form',
    ];
    ?>

    <!-- ===== SECTION C: BASIC REVIEW Q1-20 ===== -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">C</span> Basic Review — Questions 1–20</div>
      <div class="note-box">Check <strong>Yes</strong> or <strong>No</strong>. If any answer checked is in <strong>bold</strong> in the original checklist, an ethical issue arises requiring you to reconsider your procedures or provide additional written information.</div>
      <table class="check-table">
        <thead><tr><th>Indicator</th><th>YES</th><th>NO</th></tr></thead>
        <tbody>
          <?php foreach ($basicReview as $i => $q): ?>
            <?php if (!empty($q['principle'])): ?>
              <tr class="section-row"><td colspan="3">Principle <?php echo htmlspecialchars($q['principle']); ?></td></tr>
            <?php endif; ?>
            <tr>
              <td><?php echo $i; ?>. <?php echo $q['text']; ?></td>
              <td><input type="checkbox" name="h<?php echo $i; ?>_yes"
                <?php echo (isset($checklistSavedData["h{$i}_yes"]) && $checklistSavedData["h{$i}_yes"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'h<?php echo $i; ?>','yn')"></td>
              <td><input type="checkbox" name="h<?php echo $i; ?>_no"
                <?php echo (isset($checklistSavedData["h{$i}_no"]) && $checklistSavedData["h{$i}_no"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'h<?php echo $i; ?>','yn')"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ===== SECTION D: SPECIAL REVIEW Q21-53 ===== -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">D</span> Special Review — Questions 21–53</div>
      <div class="note-box">Check <strong>Yes</strong>, <strong>No</strong>, or <strong>N/A</strong> as applicable. If any answer checked is in <strong>bold</strong> in the original checklist, an ethical issue arises requiring you to reconsider your procedures or provide additional written information.</div>
      <table class="check-table">
        <thead><tr><th>Indicator</th><th>YES</th><th>NO</th><th>N/A</th></tr></thead>
        <tbody>
          <?php foreach ($specialReview as $i => $q): ?>
            <?php if (!empty($q['note_before'])): ?>
              <tr class="info-row"><td colspan="4"><?php echo htmlspecialchars($q['note_before']); ?></td></tr>
            <?php endif; ?>
            <tr>
              <td><?php echo $i; ?>. <?php echo $q['text']; ?></td>
              <td><input type="checkbox" name="h<?php echo $i; ?>_yes"
                <?php echo (isset($checklistSavedData["h{$i}_yes"]) && $checklistSavedData["h{$i}_yes"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'h<?php echo $i; ?>','yna')"></td>
              <td><input type="checkbox" name="h<?php echo $i; ?>_no"
                <?php echo (isset($checklistSavedData["h{$i}_no"]) && $checklistSavedData["h{$i}_no"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'h<?php echo $i; ?>','yna')"></td>
              <td><input type="checkbox" name="h<?php echo $i; ?>_na"
                <?php echo (isset($checklistSavedData["h{$i}_na"]) && $checklistSavedData["h{$i}_na"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'h<?php echo $i; ?>','yna')"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- ===== SECTION E: INFORMED CONSENT CHECKLIST IC1-10 ===== -->
    <div class="form-section">
      <div class="section-title"><span class="section-badge">E</span> Informed Consent Checklist</div>
      <div class="note-box">The following list ensures that all necessary elements of a Consent Form have been addressed. If you check <strong>No</strong> or <strong>N/A</strong> for any item, please provide a brief explanation in the area below.</div>
      <table class="check-table">
        <thead><tr><th>Element</th><th>YES</th><th>NO</th><th>N/A</th></tr></thead>
        <tbody>
          <?php foreach ($icChecklist as $i => $text): ?>
            <tr>
              <td><?php echo $i; ?>. <?php echo htmlspecialchars($text); ?></td>
              <td><input type="checkbox" name="ic<?php echo $i; ?>_yes"
                <?php echo (isset($checklistSavedData["ic{$i}_yes"]) && $checklistSavedData["ic{$i}_yes"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'ic<?php echo $i; ?>','yna')"></td>
              <td><input type="checkbox" name="ic<?php echo $i; ?>_no"
                <?php echo (isset($checklistSavedData["ic{$i}_no"]) && $checklistSavedData["ic{$i}_no"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'ic<?php echo $i; ?>','yna')"></td>
              <td><input type="checkbox" name="ic<?php echo $i; ?>_na"
                <?php echo (isset($checklistSavedData["ic{$i}_na"]) && $checklistSavedData["ic{$i}_na"] === 'on') ? 'checked' : ''; ?>
                onchange="toggleYN(this,'ic<?php echo $i; ?>','yna')"></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="field-group" style="margin-top:20px">
        <label>Explanation for any "No" or "N/A" items</label>
        <textarea name="ic_explanation" placeholder="Provide a brief explanation for any item checked No or N/A above."><?php echo htmlspecialchars($checklistSavedData['ic_explanation'] ?? ''); ?></textarea>
    <div class="submit-area">
      <button type="submit" class="btn-submit" id="submitBtn">Save &amp; Finalize Checklist</button>
      <div id="autosave-status" class="mt-2 text-muted small"></div>
    </div>
  </form>
</div>

<script>
/**
 * toggleYN — mutual exclusivity for YES/NO or YES/NO/N/A checkbox groups.
 * @param {HTMLInputElement} el   - The checkbox just changed
 * @param {string}           grp - Base name, e.g. 'h1' or 'ic3'
 * @param {string}           mode - 'yn' (yes/no only) or 'yna' (yes/no/n/a)
 */
function toggleYN(el, grp, mode) {
  if (!el.checked) return;
  const yes = document.querySelector(`[name="${grp}_yes"]`);
  const no  = document.querySelector(`[name="${grp}_no"]`);
  const na  = mode === 'yna' ? document.querySelector(`[name="${grp}_na"]`) : null;
  [yes, no, na].forEach(cb => { if (cb && cb !== el) cb.checked = false; });
}

function triggerAutosave() {
    const formData = new FormData(document.getElementById('humanForm'));
    formData.append('form_type', 'human_checklist');
    fetch('autosave-progress.php', { method: 'POST', body: formData })
        .then(r => r.json()).then(res => {
            if (res.success) document.getElementById('autosave-status').innerHTML = 'Draft saved at ' + res.timestamp;
        });
}

document.getElementById('humanForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const btn = document.getElementById('submitBtn');
  btn.disabled = true; btn.innerHTML = 'Saving…';
  fetch('fill-Human-checklist.php', { method: 'POST', body: new FormData(this) })
    .then(r => r.json()).then(data => {
      if (data.success) {
        alert(data.message);
        if (window.parent !== window) {
          window.parent.postMessage({ type: 'formCompleted', formType: 'human_checklist' }, '*');
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