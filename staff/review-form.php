<?php
declare(strict_types=1);
/**
 * Unified Form Reviewer - Staff Portal
 * TAU-UREO Portal
 * Viewport shell that replicates the exact applicant form filler.
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: Review check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['staff', 'admin', 'urec'])) {
    header("Location: login.php");
    exit();
}

$queue_number = $_GET['queue'] ?? '';
$form_type = $_GET['type'] ?? '';

if (empty($queue_number) || empty($form_type)) {
    die("Invalid request: Queue number and Form type are required.");
}

$conn = getDBConnection();

// Fetch application details
$app_stmt = $conn->prepare("SELECT applicant_name, research_title FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

if (!$application) {
    die("Application not found.");
}

// Map form_type to applicant filler files
$form_map = [
    'qf01' => '../applicant/fill-qf01-form.php',
    'qf02' => '../applicant/fill-qf02-form.php',
    'human_checklist' => '../applicant/fill-Human-checklist.php',
    'animal_checklist' => '../applicant/fill-Animal-checklist.php',
    'engineering_checklist' => '../applicant/fill-Engineering-checklist.php',
    'food_checklist' => '../applicant/fill-Food-checklist.php',
    'plant_checklist' => '../applicant/fill-Plant-checklist.php'
];

$iframe_url = ($form_map[$form_type] ?? '../applicant/fill-category-form.php') . "?review=1&queue=" . urlencode($queue_number);

// Fetch existing remarks for the sidebar
$form_stmt = $conn->prepare("SELECT form_data FROM fillable_forms WHERE queue_number = ? AND form_type = ?");
$form_stmt->bind_param("ss", $queue_number, $form_type);
$form_stmt->execute();
$form_record = $form_stmt->get_result()->fetch_assoc();
$form_data = $form_record ? json_decode($form_record['form_data'], true) : [];
$gen_history = $form_data['general_remarks'] ?? [];

$page_title = "Review " . strtoupper($form_type) . " - " . $queue_number;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
            --shadow: 0 4px 24px rgba(15,41,66,0.10);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--navy);
            color: var(--navy);
            height: 100vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ── Header ── */
        .reviewer-header {
            background: var(--navy);
            padding: 12px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .reviewer-header .brand {
            color: var(--gold);
            font-family: 'Playfair Display', serif;
            font-size: 1rem;
            font-weight: 700;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .reviewer-header .meta {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
            text-align: right;
        }

        .reviewer-layout {
            display: grid;
            grid-template-columns: 1fr 360px;
            flex: 1;
            overflow: hidden;
        }

        /* ── Iframe Main View ── */
        .viewport-main {
            background: #cbd5e1; /* Blueprint-like background */
            position: relative;
            display: flex;
            justify-content: center;
            overflow-y: auto;
            padding: 20px;
        }

        #formIframe {
            width: 100%;
            height: 100%;
            border: none;
            background: white;
            box-shadow: var(--shadow);
            border-radius: 4px;
        }

        /* ── Sidebar ── */
        .sidebar {
            background: white;
            border-left: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
            box-shadow: -4px 0 24px rgba(0,0,0,0.05);
        }

        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-100);
        }

        .sidebar-content {
            padding: 20px;
            flex: 1;
            overflow-y: auto;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid var(--gray-200);
            background: var(--white);
        }

        .remark-thread {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .remark-card {
            padding: 12px;
            border-radius: 8px;
            font-size: 0.85rem;
            border: 1px solid var(--gray-200);
        }

        .remark-staff { background: var(--gray-100); border-left: 3px solid var(--navy); }
        .remark-applicant { background: #fffbeb; border-left: 3px solid var(--gold); }

        .remark-meta {
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            color: var(--gray-400);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
        }

        .label-style {
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--gray-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 8px;
            display: block;
        }

        textarea.form-control {
            width: 100%;
            padding: 12px;
            border: 1.5px solid var(--gray-200);
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.9rem;
            resize: none;
            outline: none;
            transition: border-color 0.2s;
        }

        textarea.form-control:focus {
            border-color: var(--gold);
        }

        .btn-finalize {
            background: var(--navy);
            color: var(--gold);
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-finalize:hover {
            background: #1a4a75;
            transform: translateY(-2px);
        }

        .btn-finalize:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* ── Responsive ── */
        @media (max-width: 1024px) {
            .reviewer-layout { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>

<header class="reviewer-header">
    <a href="view-application.php?queue=<?php echo $queue_number; ?>" class="brand">
        <i class="bi bi-shield-check"></i>
        <span>TAU REO · UNIFIED REVIEWER</span>
    </a>
    <div class="meta">
        <div style="font-weight: 600; color: white;">#<?php echo $queue_number; ?></div>
        <div style="font-size: 0.7rem;"><?php echo htmlspecialchars($application['research_title']); ?></div>
    </div>
</header>

<div class="reviewer-layout">
    <main class="viewport-main">
        <iframe src="<?php echo $iframe_url; ?>" id="formIframe" title="Form Review Content"></iframe>
    </main>

    <aside class="sidebar">
        <div class="sidebar-header">
            <h5 style="font-family: 'Playfair Display', serif; font-weight: 700;">Review Feedback</h5>
            <p style="font-size: 0.75rem; color: var(--gray-400);">Addressing cycle for <?php echo strtoupper($form_type); ?></p>
        </div>
        
        <div class="sidebar-content">
            <span class="label-style">Revision History</span>
            <div class="remark-thread">
                <?php if (empty($gen_history)): ?>
                    <p class="text-muted small italic">No previous remarks recorded.</p>
                <?php else: ?>
                    <?php foreach (array_reverse($gen_history) as $item): ?>
                        <div class="remark-card remark-<?php echo $item['role']; ?>">
                            <div class="remark-meta">
                                <span><?php echo strtoupper($item['role']); ?></span>
                                <span><?php echo date('M d, H:i', strtotime($item['timestamp'])); ?></span>
                            </div>
                            <?php echo htmlspecialchars($item['text']); ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <span class="label-style">Overall Verdict / Instructions</span>
            <textarea id="generalRemark" class="form-control" rows="8" placeholder="Type instructions for the applicant..."></textarea>
        </div>

        <div class="sidebar-footer">
            <button id="saveBtn" class="btn-finalize" onclick="saveAllRemarks()">
                <i class="bi bi-send-fill"></i>
                Finalize Marks
            </button>
            <p class="text-muted small mt-3" style="text-align: center; line-height: 1.4;">
                This will save all field-level and general remarks and notify the applicant.
            </p>
        </div>
    </aside>
</div>

<script>
async function saveAllRemarks() {
    const btn = document.getElementById('saveBtn');
    const originalContent = btn.innerHTML;
    const iframe = document.getElementById('formIframe');
    
    btn.disabled = true;
    btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';

    const formData = new FormData();
    
    // 1. Target and General Remarks
    formData.append('queue_number', '<?php echo $queue_number; ?>');
    formData.append('form_type', '<?php echo $form_type; ?>');
    formData.append('general_remark', document.getElementById('generalRemark').value);
    formData.append('action', 'save_remarks');

    // 2. Cross-Iframe extraction (Fields like crit_1_remarks in QF02)
    try {
        const iframeWindow = iframe.contentWindow;
        if (iframeWindow) {
            const fieldRemarks = iframeWindow.document.querySelectorAll('[name*="_remarks"]');
            fieldRemarks.forEach(el => {
                if (el.value.trim() !== '') {
                    formData.append(el.name, el.value);
                }
            });
        }
    } catch (e) {
        console.warn("Could not access iframe content for field remarks. Security policy or page not loaded.");
    }

    try {
        const response = await fetch('process-form-remarks.php', {
            method: 'POST',
            body: formData
        });
        const result = await response.json();
        
        if (result.success) {
            alert('Remarks saved successfully! The applicant has been notified.');
            window.location.reload();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (e) {
        alert('Communication error. Please check your connection.');
        console.error(e);
    } finally {
        btn.disabled = false;
        btn.innerHTML = originalContent;
    }
}
</script>

</body>
</html>
