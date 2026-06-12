<?php
/**
 * fill-category-form.php
 * Category-Specific Checklist Filler — reads classification from
 * uploads/{queue_number}/ai_classification.json, then includes the
 * matching fill-{classification}-checklist.php inline.
 * TAU-UREO Portal
 */
declare(strict_types = 1)
;

session_start();
require_once '../config/config.php';
require_once '../includes/functions.php';

$is_review = isset($_GET['review']) && $_GET['review'] == 1;

if (!$is_review) {
    requireApplicantLogin();
}

if (!isset($_SESSION['queue_number']) && !isset($_GET['queue'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Session expired.']);
        exit;
    }
    die('Error: No queue number in session.');
}

$queue_number = $_SESSION['queue_number'] ?? $_GET['queue'] ?? null;
$conn = getDBConnection();

// ── Resolve classification from ai_classification.json ────────────────────────
$ai_json_path = __DIR__ . '/../uploads/' . $queue_number . '/ai_classification.json';
$classification = null;

if (file_exists($ai_json_path)) {
    $ai_data = json_decode(file_get_contents($ai_json_path), true);

    // ai_prediction holds the final classification the AI assigned.
    // It may be a string like "human", "animal", "plant", "engineering", "food"
    // or a structured array — normalise to a simple lowercase slug.
    $raw = $ai_data['ai_prediction'] ?? $ai_data['classification'] ?? null;
    if (is_array($raw)) {
        // e.g. ['label' => 'Human Use', 'slug' => 'human']
        $raw = $raw['slug'] ?? $raw['label'] ?? reset($raw);
    }
    if ($raw) {
        // Normalise to slug: lowercase, strip spaces/underscores
        $slug = strtolower(trim((string)$raw));
        $slug = preg_replace('/\s+/', '_', $slug);

        // Map common label variants → slug
        $map = [
            'human_use' => 'human',
            'human' => 'human',
            'animal_welfare' => 'animal',
            'animal' => 'animal',
            'plant_use' => 'plant',
            'plant' => 'plant',
            'engineering' => 'engineering',
            'food_technology_use' => 'food',
            'food_tech' => 'food',
            'food' => 'food',
        ];
        $classification = $map[$slug] ?? $slug;
    }
}

$valid_classifications = ['human', 'animal', 'plant', 'engineering', 'food'];

if (!$classification || !in_array($classification, $valid_classifications)) {
    closeDBConnection($conn);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Classification not yet assigned. Please wait for the AI review to complete.',
        ]);
        exit;
    }
    die('Classification not yet assigned for your application. Please wait for the AI review to complete or contact UREO staff.');
}

// ── Get applicant info for pre-fill ──────────────────────────────────────────
$app_stmt = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_stmt->bind_param('s', $queue_number);
$app_stmt->execute();
$app_data = $app_stmt->get_result()->fetch_assoc();
$applicant_name = $app_data['applicant_name'] ?? '';
$research_title = $app_data['research_title'] ?? '';

// ── Load any existing submission ─────────────────────────────────────────────
$existing_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_checklist'");
$existing_stmt->bind_param('s', $queue_number);
$existing_stmt->execute();
$existing_row = $existing_stmt->get_result()->fetch_assoc();
$saved_data = $existing_row ? (json_decode($existing_row['form_data'], true) ?? []) : [];

// ── POST: the individual checklist PHP posts directly here ────────────────────
// Each fill-{classification}-checklist.php AJAX-posts to itself (its own URL).
// This endpoint only handles a secondary "wrapper" save if needed, but the
// child forms post to themselves and fire postMessage on success.
// We keep a POST handler here for the wrapper form (no docx, just DB save).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = $_POST;
    $form_data['queue_number'] = $queue_number;
    $form_data['classification'] = $classification;
    $form_data['submitted_at'] = date('Y-m-d H:i:s');
    $form_json = json_encode($form_data);

    $check_stmt = $conn->prepare("SELECT form_id FROM fillable_forms WHERE queue_number = ? AND form_type = 'category_checklist'");
    $check_stmt->bind_param('s', $queue_number);
    $check_stmt->execute();
    $exists = $check_stmt->get_result()->fetch_assoc();

    if ($exists) {
        $upd = $conn->prepare("UPDATE fillable_forms SET form_data = ?, completed_at = NOW(), file_generated = 1 WHERE queue_number = ? AND form_type = 'category_checklist'");
        $upd->bind_param('ss', $form_json, $queue_number);
        $upd->execute();
    }
    else {
        $ins = $conn->prepare("INSERT INTO fillable_forms (queue_number, form_type, form_data, file_generated) VALUES (?, 'category_checklist', ?, 1)");
        $ins->bind_param('ss', $queue_number, $form_json);
        $ins->execute();
    }

    // Nudge application status if still waiting for category forms
    $nudge = $conn->prepare("UPDATE applications SET current_status = 'CATEGORY_FORMS_SUBMITTED' WHERE queue_number = ? AND current_status = 'CATEGORY_FORMS_REQUIRED'");
    $nudge->bind_param('s', $queue_number);
    $nudge->execute();

    closeDBConnection($conn);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => ucfirst($classification) . ' category checklist submitted successfully!',
        'formType' => 'category_checklist',
    ]);
    exit;
}

closeDBConnection($conn);

// ── Resolve the child checklist file ─────────────────────────────────────────
// Files live in the same applicant-facing directory as fill-qf01-form.php.
// Naming convention:  fill-{classification}-checklist.php
$checklist_file = __DIR__ . '/fill-' . ucfirst($classification) . '-checklist.php';
$checklist_exists = file_exists($checklist_file);

// ── Guideline documents (kept for display in the banner) ─────────────────────
$guideline_dir = __DIR__ . '/../assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/';
$guideline_pdf = null;
$guideline_docx = null;
if (is_dir($guideline_dir)) {
    foreach (scandir($guideline_dir) as $gf) {
        $ext = strtolower(pathinfo($gf, PATHINFO_EXTENSION));
        if ($ext === 'pdf')
            $guideline_pdf = 'assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/' . $gf;
        if ($ext === 'docx')
            $guideline_docx = 'assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/' . $gf;
    }
}

$page_title = ucfirst($classification) . ' Category Checklist';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TAU REO · <?php echo htmlspecialchars($page_title); ?></title>
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
        body { font-family: 'DM Sans', sans-serif; background: var(--cream); color: var(--navy); min-height: 100vh; }

        /* ── Sticky header ── */
        .page-header {
            background: var(--navy); padding: 20px 32px; display: flex;
            align-items: center; gap: 18px; position: sticky; top: 0;
            z-index: 100; box-shadow: 0 2px 16px rgba(0,0,0,.25);
        }
        .page-header .brand {
            color: var(--gold); font-family: 'Playfair Display', serif;
            font-size: 1rem; font-weight: 700; letter-spacing: .02em;
            white-space: nowrap; border-right: 1px solid rgba(255,255,255,.15);
            padding-right: 18px;
        }
        .page-header .page-title { color: rgba(255,255,255,.85); font-size: .9rem; font-weight: 400; }

        /* ── Wrapper ── */
        .form-wrapper { max-width: 960px; margin: 40px auto 80px; padding: 0 24px; }
        .form-heading { margin-bottom: 36px; padding-bottom: 24px; border-bottom: 2px solid var(--gray-200); }
        .form-heading h1 { font-family: 'Playfair Display', serif; font-size: 2rem; color: var(--navy); margin-bottom: 8px; }
        .form-heading p { color: var(--gray-400); font-size: .95rem; font-weight: 300; }

        /* ── Info banner ── */
        .info-banner {
            background: var(--white); border-radius: 12px; padding: 20px 24px;
            margin-bottom: 24px; box-shadow: var(--shadow); border: 1px solid var(--gray-200);
            display: flex; gap: 0; align-items: flex-start; flex-wrap: wrap;
        }
        .info-item { flex: 1; min-width: 140px; padding: 0 20px; }
        .info-item:first-child { padding-left: 0; }
        .info-item + .info-item { border-left: 1px solid var(--gray-200); }
        .bi-label { font-size: .72rem; font-weight: 700; color: var(--gray-400); letter-spacing: .08em; text-transform: uppercase; margin-bottom: 2px; }
        .bi-val { font-size: .93rem; color: var(--navy); font-weight: 500; }

        /* ── Guideline bar ── */
        .guideline-bar {
            background: var(--blue-soft); border-left: 4px solid var(--navy);
            border-radius: 0 12px 12px 0; padding: 16px 20px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 16px; margin-bottom: 24px; flex-wrap: wrap;
        }
        .guideline-bar span { font-size: .88rem; color: var(--navy); font-weight: 500; }
        .btn-guide {
            background: var(--navy); color: var(--white); border: none;
            padding: 9px 20px; border-radius: 7px; font-family: 'DM Sans', sans-serif;
            font-size: .82rem; font-weight: 600; cursor: pointer;
            text-decoration: none; letter-spacing: .03em;
            display: inline-flex; align-items: center; gap: 6px; transition: background .2s;
        }
        .btn-guide:hover { background: var(--gold); color: var(--navy); }

        /* ── Status boxes ── */
        .already-done {
            background: #e8f5e9; border-left: 4px solid #2e7d32;
            padding: 14px 20px; border-radius: 0 8px 8px 0;
            margin-bottom: 24px; font-size: .92rem; color: #2e7d32;
        }
        .flash { padding: 14px 20px; border-radius: 8px; margin-bottom: 24px; font-weight: 500; font-size: .92rem; }
        .flash.error { background: #ffebee; color: #c62828; border-left: 4px solid #c62828; }

        /* ── Shared form section styles (inherited by included checklist) ── */
        .form-section { background: var(--white); border-radius: 12px; padding: 32px; margin-bottom: 24px; box-shadow: var(--shadow); border: 1px solid var(--gray-200); }
        .section-title { font-family: 'Playfair Display', serif; font-size: 1.15rem; color: var(--navy); margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid var(--gray-200); display: flex; align-items: center; gap: 10px; }
        .section-title.sub { font-size: 1rem; margin-top: 24px; margin-bottom: 16px; }
        .section-badge { background: var(--navy); color: var(--gold); font-family: 'DM Sans', sans-serif; font-size: .7rem; font-weight: 600; padding: 3px 10px; border-radius: 20px; letter-spacing: .06em; text-transform: uppercase; }
        .field-group { margin-bottom: 20px; }
        .field-group label { display: block; font-size: .82rem; font-weight: 600; color: var(--gray-600); margin-bottom: 6px; letter-spacing: .04em; text-transform: uppercase; }
        .field-group input, .field-group textarea, .field-group select { width: 100%; padding: 12px 16px; border: 1.5px solid var(--gray-200); border-radius: 8px; font-family: 'DM Sans', sans-serif; font-size: .95rem; color: var(--navy); background: var(--cream); transition: border-color .2s, box-shadow .2s; outline: none; }
        .field-group input:focus, .field-group textarea:focus, .field-group select:focus { border-color: var(--gold); box-shadow: 0 0 0 3px rgba(201,153,58,.15); background: var(--white); }
        .field-group textarea { min-height: 100px; resize: vertical; line-height: 1.6; }
        .field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .field-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        .check-table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        .check-table thead tr { background: var(--navy); color: var(--white); }
        .check-table thead th { padding: 11px 16px; text-align: left; font-size: .72rem; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; }
        .check-table thead th:not(:first-child) { text-align: center; width: 65px; }
        .check-table tbody tr { border-bottom: 1px solid var(--gray-200); transition: background .15s; }
        .check-table tbody tr:hover { background: var(--gray-100); }
        .check-table tbody tr.section-row { background: var(--blue-soft); }
        .check-table tbody tr.section-row td { font-weight: 600; font-size: .78rem; letter-spacing: .04em; text-transform: uppercase; color: var(--navy); padding: 10px 16px; }
        .check-table td { padding: 12px 16px; vertical-align: middle; color: var(--gray-600); line-height: 1.4; }
        .check-table td:not(:first-child) { text-align: center; vertical-align: middle; }
        .check-table input[type="checkbox"] { width: 17px; height: 17px; cursor: pointer; accent-color: var(--navy); }
        .iftext { width: 100%; padding: 8px 12px; border: 1.5px solid var(--gray-200); border-radius: 6px; font-family: 'DM Sans', sans-serif; font-size: .85rem; color: var(--navy); background: var(--cream); outline: none; margin-top: 6px; transition: border-color .2s; }
        .iftext:focus { border-color: var(--gold); background: var(--white); }
        .specimen-row { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 8px; }
        .specimen-chip { display: flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1.5px solid var(--gray-200); border-radius: 20px; font-size: .85rem; cursor: pointer; transition: all .2s; background: var(--cream); }
        .specimen-chip:hover { border-color: var(--gold); background: var(--white); }
        .specimen-chip input[type="checkbox"] { accent-color: var(--navy); width: 14px; height: 14px; }
        .guideline-card { background: var(--navy); color: var(--white); border-radius: 12px; padding: 28px; margin-bottom: 24px; }
        .guideline-card h3 { font-family: 'Playfair Display', serif; color: var(--gold); font-size: 1.1rem; margin-bottom: 14px; }
        .guideline-card ul { list-style: none; display: flex; flex-direction: column; gap: 10px; }
        .guideline-card ul li { font-size: .88rem; line-height: 1.55; color: rgba(255,255,255,.82); padding-left: 18px; position: relative; }
        .guideline-card ul li::before { content: '›'; position: absolute; left: 0; color: var(--gold); font-size: 1.1rem; }
        .note-box { background: var(--blue-soft); border-left: 4px solid var(--navy); padding: 12px 16px; border-radius: 0 8px 8px 0; font-size: .87rem; color: var(--navy); margin-bottom: 20px; line-height: 1.5; }
        .references { font-size: .78rem; color: var(--gray-400); margin-top: 14px; line-height: 1.6; font-style: italic; }

        /* ── Submit ── */
        .submit-area { text-align: center; margin-top: 36px; }
        .btn-submit { background: var(--navy); color: var(--white); border: none; padding: 16px 52px; font-family: 'DM Sans', sans-serif; font-size: .95rem; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; border-radius: 8px; cursor: pointer; transition: all .25s; box-shadow: var(--shadow); }
        .btn-submit:hover { background: var(--gold); color: var(--navy); box-shadow: var(--shadow-lg); transform: translateY(-1px); }
        .btn-submit:disabled { opacity: .55; cursor: not-allowed; transform: none; }

        @media (max-width: 640px) {
            .form-wrapper { padding: 0 12px; }
            .form-section { padding: 20px; }
            .field-row, .field-row-3 { grid-template-columns: 1fr; }
            .info-banner { flex-direction: column; }
            .info-item + .info-item { border-left: none; border-top: 1px solid var(--gray-200); padding-left: 0; padding-top: 12px; margin-top: 12px; }
        }
    </style>
</head>
<body>

<header class="page-header">
    <div class="brand">TAU · REO</div>
    <div class="page-title"><?php echo htmlspecialchars(ucfirst($classification)); ?> · Category Ethics Checklist</div>
</header>

<div class="form-wrapper">

    <div class="form-heading">
        <h1><?php echo htmlspecialchars(ucfirst($classification)); ?> Ethics Checklist</h1>
        <p>TAU-REO · Complete all required fields. Your answers are saved and visible to the review committee.</p>
    </div>


    <?php if (!empty($saved_data)): ?>
    <div class="already-done">
        ✅ <strong>Previously submitted.</strong> Your answers are shown below. You may update and re-submit at any time.
    </div>
    <?php
endif; ?>

    <?php if (!$checklist_exists): ?>
    <div class="flash error">
        ⚠️ The checklist for "<strong><?php echo htmlspecialchars(ucfirst($classification)); ?></strong>" could not be found
        (<code>fill-<?php echo htmlspecialchars($classification); ?>-checklist.php</code>).
        Please contact UREO staff.
    </div>
    <?php
else: ?>

    <?php
    /*
     * Include the matching fill-{classification}-checklist.php.
     *
     * That file is a complete, self-contained PHP+HTML page with its own
     * POST handler (AJAX → JSON response → postMessage to parent).
     * When included here, its <html>/<head>/<body> tags and its own
     * session/DB bootstrap will re-run inside this page's output buffer,
     * which is messy.  Instead we use output buffering to capture only
     * the <form> inner content from the child file and echo it here.
     *
     * Convention: the child files all have a single <form id="…Form">
     * wrapping everything from sections through the submit button.
     * We extract that block, swap the form's action to point to the
     * child file (so AJAX fetch() in the child's own JS still works),
     * and render it inside our wrapper page.
     *
     * Variables the child files expect to be in scope:
     *   $applicant_name, $research_title, $queue_number
     * These are already set above.
     */

    ob_start();
    // Suppress any "headers already sent" from session_start inside child
    include $checklist_file;
    $child_output = ob_get_clean();

    // Extract just the <form …>…</form> block from the child output
    if (preg_match('/<form\s[^>]*id="[^"]*Form"[^>]*>.*<\/form>/si', $child_output, $m)) {
        echo $m[0];
    }
    else {
        // Fallback: echo everything between <body> and </body>
        if (preg_match('/<body[^>]*>(.*)<\/body>/si', $child_output, $bm)) {
            echo $bm[1];
        }
        else {
            echo $child_output;
        }
    }
?>

    <?php
endif; ?>
</div>

<script>
/*
 * Intercept postMessage from the child form's JS so the parent
 * documents.php (which embeds THIS page in an iframe) gets notified.
 */
window.addEventListener('message', function(e) {
    if (e.data && e.data.type === 'formCompleted') {
        // Bubble up to grandparent (documents.php iframe listener)
        if (window.parent !== window) {
            window.parent.postMessage(e.data, '*');
        }
    }
});

/*
 * Also intercept the child form's own submit event in case it was
 * rendered without its own AJAX handler (safety net).
 */
document.addEventListener('DOMContentLoaded', function () {
    const forms = document.querySelectorAll('form[id$="Form"]');
    forms.forEach(function (form) {
        // Only attach if the form does NOT already have an AJAX submit listener
        // (child files wire their own — we just ensure postMessage bubbles).
        form.addEventListener('submit', function () {
            // nothing extra needed — child JS handles fetch + postMessage
        });
    });
});
</script>

<?php if ($is_review): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Disable all interactive elements
        document.querySelectorAll('input, textarea, select').forEach(el => {
            el.disabled = true;
            el.readOnly = true;
        });
        
        // Hide submit/action buttons
        document.querySelectorAll('.submit-area, .btn-gold, .btn-success').forEach(el => {
            if (el) el.style.display = 'none';
        });

        // Hide specific UI elements for iframe display
        if (window.self !== window.top) {
            document.querySelectorAll('.page-header').forEach(el => el.style.display = 'none');
            document.querySelectorAll('.form-wrapper').forEach(el => el.style.marginTop = '0');
            document.body.style.background = 'white';
        }
    });
</script>
<?php endif; ?></body>
</html>