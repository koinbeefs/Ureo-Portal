<?php
/**
 * Document Management
 * TAU-UREO Portal
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireApplicantLogin();
checkSessionTimeout();

// Ensure session has queue_number
if (!isset($_SESSION['queue_number']) || empty($_SESSION['queue_number'])) {
    session_destroy();
    header("Location: login.php");
    exit();
}

$queue_number = $_SESSION['queue_number'];
$conn = getDBConnection();

// Get application details
$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$application = $stmt->get_result()->fetch_assoc();

if (!$application) {
    session_destroy();
    closeDBConnection($conn);
    header("Location: login.php?error=application_not_found");
    exit();
}

// Check if application is in revision status
$is_pending_revision = ($application['current_status'] === STATUS_REVISIONS_REQUIRED);

// ── Resolve classification from ai_classification.json ───────────────────────
// Priority: staff_feedback.final_category (if staff_reviewed) → ai_prediction.predicted
$classification = null;
$ai_json_path = __DIR__ . '/../uploads/' . $queue_number . '/ai_classification.json';

if (file_exists($ai_json_path)) {
    $ai_data = json_decode(file_get_contents($ai_json_path), true);

    // Use staff-confirmed category when available, otherwise fall back to AI prediction
    $raw = null;
    if (!empty($ai_data['staff_reviewed']) && !empty($ai_data['staff_feedback']['final_category'])) {
        $raw = $ai_data['staff_feedback']['final_category'];
    }
    elseif (!empty($ai_data['ai_prediction']['predicted'])) {
        $raw = $ai_data['ai_prediction']['predicted'];
    }

    if ($raw) {
        $slug = strtolower(trim((string)$raw));
        $slug = preg_replace('/\s+/', '_', $slug);
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
        $classification = $map[$slug] ?? null;
    }
}

$valid_classifications = ['human', 'animal', 'plant', 'engineering', 'food'];
if (!in_array($classification, $valid_classifications, true)) {
    $classification = null; // treat as unclassified
}

// Get revision details if in revision status
$revision_documents = [];
if ($is_pending_revision) {
    // Get the latest revision message
    $rev_stmt = $conn->prepare("SELECT * FROM system_messages WHERE queue_number = ? AND message_type = 'update' ORDER BY created_at DESC LIMIT 1");
    $rev_stmt->bind_param("s", $queue_number);
    $rev_stmt->execute();
    $revision_message = $rev_stmt->get_result()->fetch_assoc();

    if ($revision_message) {
        $message_body = $revision_message['message_body'];
        $document_types = ['qf01', 'qf02', 'cv', 'proposal', 'consent_form', 'validation_cert'];
        foreach ($document_types as $doc_type) {
            if (stripos($message_body, $doc_type) !== false) {
                $revision_documents[] = $doc_type;
            }
        }
    }
}

// Get system messages (email responses)
$msg_stmt = $conn->prepare("SELECT * FROM system_messages WHERE queue_number = ? ORDER BY created_at DESC");
$msg_stmt->bind_param("s", $queue_number);
$msg_stmt->execute();
$messages = $msg_stmt->get_result();

// Get system documents (provided templates/guidelines)
$sys_doc_stmt = $conn->prepare("SELECT * FROM system_documents WHERE queue_number = ? ORDER BY provided_at DESC");
$sys_doc_stmt->bind_param("s", $queue_number);
$sys_doc_stmt->execute();
$system_documents = $sys_doc_stmt->get_result();

// Get staff-generated documents from uploads folder
$staff_documents = [];
$annotated_path = null;
$uploads_path = '../uploads/' . $queue_number . '/';
if (is_dir($uploads_path)) {
    $files = scandir($uploads_path);
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && strpos($file, 'Annotated') !== false) {
            $annotated_path = 'uploads/' . $queue_number . '/' . $file;
            break;
        }
    }

    $temp_staff_docs = [];
    $latest_proposal_time = 0;
    $latest_proposal_index = -1;

    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
            if (strpos($file, 'Annotated') !== false || stripos($file, 'QF-02_Remark') !== false || stripos($file, 'QF-02_staff') !== false) {
                continue;
            }

            $file_path = $uploads_path . $file;
            $display_name = $file;
            $doc_type = 'Staff Generated';
            $description = 'Document provided for your review';

            if (strpos($file, 'qf02_') === 0) {
                $display_name = 'TAU-REO-QF02-ANNOTATED/REVIEWED';
                $doc_type = 'QF02 Form';
                $description = 'Research Ethics Review Category Form (with potential remarks)';
            }
            if (strpos($file, 'qf01_') === 0) {
                $display_name = 'TAU-REO-QF01';
                $doc_type = 'QF01 Form';
            }
            if (strpos($file, 'proposal_') === 0) {
                $display_name = 'Research proposal/Thesis/Dissertation Outline';
                $doc_type = 'Research proposal/Thesis/Dissertation Outline';
                $description = 'Research proposal/Thesis/Dissertation Outline';
            }
            if (strpos($file, 'consent_form_') === 0) {
                $display_name = 'Informed Consent Form';
                $doc_type = 'Informed Consent Form';
                $description = 'Informed Consent Form';
            }
            if (strpos($file, 'cv_') === 0) {
                $display_name = 'Curriculum Vitae';
                $doc_type = 'Curriculum Vitae';
                $description = 'Curriculum Vitae';
            }
            if (strpos($file, 'validation_cert_') === 0) {
                $display_name = 'Validation Certificate';
                $doc_type = 'Validation Certificate';
                $description = 'Validation Certificate';
            }
            elseif (strpos($file, 'Category') !== false) {
                $display_name = 'Category Form';
                $doc_type = 'Category Form';
                $description = 'Category-specific review forms';
            }
            elseif (strpos($file, 'Approval') !== false) {
                $display_name = 'Approval Letter';
                $doc_type = 'Approval Letter';
                $description = 'Official correspondence from UREO staff';
            }

            $doc_data = [
                'file_name' => $file,
                'display_name' => $display_name,
                'file_path' => 'uploads/' . $queue_number . '/' . $file,
                'download_path' => (strpos($file, 'qf02_') === 0 && $annotated_path) ? $annotated_path : 'uploads/' . $queue_number . '/' . $file,
                'file_size' => filesize($file_path),
                'created_at' => date('Y-m-d H:i:s', filemtime($file_path)),
                'document_type' => $doc_type,
                'description' => $description,
                'filemtime' => filemtime($file_path),
            ];

            if (strpos($file, 'proposal_') === 0) {
                if ($doc_data['filemtime'] > $latest_proposal_time) {
                    if ($latest_proposal_index !== -1) {
                        unset($temp_staff_docs[$latest_proposal_index]);
                    }
                    $latest_proposal_time = $doc_data['filemtime'];
                    $latest_proposal_index = count($temp_staff_docs);
                    $temp_staff_docs[] = $doc_data;
                }
            }
            else {
                $temp_staff_docs[] = $doc_data;
            }
        }
    }
    $staff_documents = array_values($temp_staff_docs);
}

// Get uploaded documents
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? ORDER BY upload_timestamp DESC");
$doc_stmt->bind_param("s", $queue_number);
$doc_stmt->execute();
$documents = $doc_stmt->get_result();

// Get uploaded document types
$uploaded_types = [];
$doc_stmt2 = $conn->prepare("SELECT document_type FROM documents WHERE queue_number = ?");
$doc_stmt2->bind_param("s", $queue_number);
$doc_stmt2->execute();
$result = $doc_stmt2->get_result();
while ($row = $result->fetch_assoc()) {
    $uploaded_types[] = $row['document_type'];
}

// Check fillable forms completion status
$stmt = $conn->prepare("SELECT form_type, form_data, file_generated FROM fillable_forms WHERE queue_number = ?");
$stmt->bind_param("s", $queue_number);
$stmt->execute();
$result = $stmt->get_result();
$fillable_forms_status = [];
while ($row = $result->fetch_assoc()) {
    $fillable_forms_status[$row['form_type']] = [
        'completed' => true,
        'data' => json_decode($row['form_data'], true),
    ];
}

// Define required documents
$required_documents = [
    ['type' => 'qf01', 'name' => 'TAU-REO-QF-01', 'fillable' => true],
    ['type' => 'qf02', 'name' => 'TAU-REO-QF-02', 'fillable' => true],
    ['type' => 'cv', 'name' => 'CV of proponents', 'fillable' => false],
    ['type' => 'proposal', 'name' => 'Research proposal/Thesis/Dissertation Outline', 'fillable' => false],
];

// Add additional required documents from QF-01 form if completed
if (isset($fillable_forms_status['qf01']['data'])) {
    $qf01_data = $fillable_forms_status['qf01']['data'];
    if (isset($qf01_data['attached_consent_form']) && $qf01_data['attached_consent_form'] === '☑') {
        $required_documents[] = ['type' => 'consent_form', 'name' => 'Informed Consent Form', 'fillable' => false];
    }
    if (isset($qf01_data['attached_other_forms']) && $qf01_data['attached_other_forms'] === '☑') {
        $required_documents[] = ['type' => 'validation_cert', 'name' => 'Certificate of Instrument Validation', 'fillable' => false];
    }
}

// Check if all required documents are uploaded
$all_uploaded = true;
foreach ($required_documents as $req) {
    if (!in_array($req['type'], $uploaded_types)) {
        $all_uploaded = false;
        break;
    }
}

closeDBConnection($conn);

$page_title = 'Documents';
$base_url = '../';
$active_menu = 'documents';
include '../includes/auth_header.php';
?>

<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card shadow-lg border-0" style="background: linear-gradient(135deg, #006400, #228B22); border-radius: 15px;">
                <div class="card-body p-4">
                    <div class="row align-items-center">
                        <div class="col-lg-8">
                            <h2 class="mb-2 text-white">
                                <i class="bi bi-file-earmark-text me-3"></i>Document Management
                                <?php if ($is_pending_revision): ?>
                                    <span class="badge bg-warning text-dark ms-2">Revision Mode</span>
                                <?php
endif; ?>
                            </h2>
                            <p class="mb-0 text-white opacity-90 fs-6">
                                <i class="bi bi-person-circle me-2"></i>
                                Application: <?php echo htmlspecialchars($application['queue_number']); ?>
                            </p>
                        </div>
                        <div class="col-lg-4 text-lg-end">
                            <?php if (!$is_pending_revision): ?>
                                <?php if (!$classification): ?>
                                    <button class="btn btn-light shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                        <i class="bi bi-cloud-upload me-2"></i>Upload Document
                                    </button>
                                <?php
    else: ?>
                                    <button class="btn btn-warning text-dark shadow-sm" onclick="openFillableForm('category_checklist')">
                                        <i class="bi bi-clipboard-check me-2"></i>Fill Category Checklist
                                    </button>
                                <?php
    endif; ?>
                            <?php
endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (!$is_pending_revision): ?>
    <!-- Application Requirements Section -->
    <div class="card mb-4 border-0 shadow-lg <?php echo $all_uploaded ? 'collapsed-drawer' : ''; ?>" id="applicationRequirementsSection" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #006400; <?php echo $all_uploaded ? 'cursor: pointer;' : ''; ?>" <?php if ($all_uploaded)
        echo 'onclick="toggleDrawer(this)"'; ?>>
            <div class="d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-1" style="color: #006400; font-weight: 600;">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Initial Submission &amp; Provided Documents
                    </h5>
                    <small class="text-muted">The initial documents you provided for review</small>
                </div>
                <?php if ($all_uploaded): ?>
                <div class="text-end">
                    <span class="badge bg-success px-3 py-2 mb-2">
                        <i class="bi bi-check-circle me-1"></i>All Completed
                    </span>
                    <br>
                    <i class="bi bi-chevron-down drawer-icon text-muted fs-5"></i>
                </div>
                <?php
    endif; ?>
            </div>
        </div>
        <div class="drawer-content">
            <div class="card-body p-4">
                <div class="alert alert-info border-0 shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #d1ecf1, #bee5eb);">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-info-circle fs-5 me-3 text-info"></i>
                        <div>
                            <strong>Important:</strong> Please complete and upload all required documents below to proceed with your application.
                        </div>
                    </div>
                </div>

                <!-- System Provided Documents -->
                <?php if ($system_documents->num_rows > 0): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-file-text text-primary me-2 fs-5"></i>
                        <h5 class="mb-0 text-primary fw-bold">Provided Documents</h5>
                    </div>
                    <div class="row g-3">
                        <?php
        $system_documents->data_seek(0);
        while ($sys_doc = $system_documents->fetch_assoc()):
?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; transition: transform 0.2s;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start">
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                            <i class="bi bi-file-earmark-pdf text-primary fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold text-truncate" style="font-size: 0.9rem;">
                                                <?php echo htmlspecialchars($sys_doc['document_name']); ?>
                                            </h6>
                                            <small class="text-muted d-block">
                                                <?php echo ucfirst($sys_doc['document_type']); ?> •
                                                <?php echo date('M d, Y', strtotime($sys_doc['provided_at'])); ?>
                                            </small>
                                            <button class="btn btn-outline-primary btn-sm mt-2" onclick="viewOrDownloadDocument('<?php echo htmlspecialchars($sys_doc['document_path']); ?>', '<?php echo htmlspecialchars($sys_doc['document_name']); ?>')">
                                                <i class="bi bi-eye me-1"></i>View
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
        endwhile; ?>
                    </div>
                </div>
                <?php
    endif; ?>

                <!-- Required Documents Checklist -->
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-list-check text-success me-2 fs-5"></i>
                        <h5 class="mb-0 text-success fw-bold">Required Documents</h5>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($required_documents as $req_doc):
        $is_uploaded = in_array($req_doc['type'], $uploaded_types);
        $form_completed = isset($fillable_forms_status[$req_doc['type']]) && $fillable_forms_status[$req_doc['type']]['completed'];
?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm <?php echo $is_uploaded ? 'border-success' : 'border-warning'; ?>" style="border-radius: 10px; transition: all 0.3s ease; <?php echo $is_uploaded ? 'border-left: 4px solid #198754;' : 'border-left: 4px solid #ffc107;'; ?>">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start justify-content-between mb-2">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-2 fw-bold" style="font-size: 0.95rem; color: #2c5530;">
                                                <?php echo htmlspecialchars($req_doc['name']); ?>
                                            </h6>
                                            <?php if ($req_doc['fillable']): ?>
                                                <small class="text-info d-block mb-2">
                                                    <i class="bi bi-pencil-square me-1"></i>Fillable form available
                                                    <?php if ($form_completed): ?>
                                                        <span class="badge bg-info ms-1">Form Completed</span>
                                                    <?php
            endif; ?>
                                                </small>
                                            <?php
        endif; ?>
                                        </div>
                                        <div class="ms-2">
                                            <?php if ($is_uploaded): ?>
                                                <span class="badge bg-success rounded-pill px-2 py-1">
                                                    <i class="bi bi-check-circle me-1"></i>Uploaded
                                                </span>
                                            <?php
        elseif ($form_completed): ?>
                                                <span class="badge bg-primary rounded-pill px-2 py-1">
                                                    <i class="bi bi-file-earmark-check me-1"></i>Ready
                                                </span>
                                            <?php
        else: ?>
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1">
                                                    <i class="bi bi-clock me-1"></i>Pending
                                                </span>
                                            <?php
        endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
    endforeach; ?>
                    </div>
                </div>

                <!-- My Uploaded Documents -->
                <div>
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-upload text-info me-2 fs-5"></i>
                        <h5 class="mb-0 text-info fw-bold">My Uploaded Documents</h5>
                    </div>
                    <?php if ($documents->num_rows > 0): ?>
                        <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden;">
                            <div class="table-responsive" id="uploadedDocumentsTable">
                                <table class="table table-hover mb-0">
                                    <thead style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                        <tr>
                                            <th class="border-0 fw-bold text-muted py-3">Document Type</th>
                                            <th class="border-0 fw-bold text-muted py-3">File Name</th>
                                            <th class="border-0 fw-bold text-muted py-3">Upload Date</th>
                                            <th class="border-0 fw-bold text-muted py-3">Status</th>
                                            <th class="border-0 fw-bold text-muted py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
        $documents->data_seek(0);
        while ($doc = $documents->fetch_assoc()):
            $file_path = $doc['file_path'] ?? '';
            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $is_pdf = ($file_extension === 'pdf');
            $is_docx = ($file_extension === 'docx' || $file_extension === 'doc');
?>
                                        <tr style="transition: background-color 0.2s;">
                                            <td class="py-3">
                                                <span class="badge bg-light text-dark px-2 py-1">
                                                    <?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <?php if ($is_pdf): ?>
                                                    <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                                <?php
            elseif ($is_docx): ?>
                                                    <i class="bi bi-file-earmark-word text-primary me-2"></i>
                                                <?php
            else: ?>
                                                    <i class="bi bi-file-earmark text-secondary me-2"></i>
                                                <?php
            endif; ?>
                                                <strong><?php echo htmlspecialchars($doc['document_name'] ?? 'Unknown'); ?></strong>
                                            </td>
                                            <td class="py-3 text-muted">
                                                <?php echo date('M d, Y', strtotime($doc['upload_timestamp'])); ?>
                                            </td>
                                            <td class="py-3">
                                                <span class="badge px-3 py-2 <?php echo $doc['validation_status'] === 'approved' ? 'bg-success' : ($doc['validation_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                                    <i class="bi <?php echo $doc['validation_status'] === 'approved' ? 'bi-check-circle' : ($doc['validation_status'] === 'rejected' ? 'bi-x-circle' : 'bi-clock'); ?> me-1"></i>
                                                    <?php echo ucfirst($doc['validation_status']); ?>
                                                </span>
                                            </td>
                                            <td class="py-3">
                                                <button class="btn btn-outline-primary btn-sm" onclick="viewDocument('<?php echo htmlspecialchars($file_path); ?>', '<?php echo htmlspecialchars($doc['document_name'] ?? 'Unknown'); ?>')">
                                                    <i class="bi bi-eye me-1"></i>View
                                                </button>
                                            </td>
                                        </tr>
                                        <?php
        endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php
    else: ?>
                        <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                            <div class="card-body">
                                <i class="bi bi-file-earmark-x display-5 text-muted mb-3"></i>
                                <h5 class="text-muted mb-2">No documents uploaded yet</h5>
                                <p class="text-muted mb-3">Start by uploading your required documents</p>
                                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                                    <i class="bi bi-cloud-upload me-2"></i>Upload Your First Document
                                </button>
                            </div>
                        </div>
                    <?php
    endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================================== -->
    <!-- CATEGORY ASSETS & STAFF FEEDBACK DRAWER                    -->
    <!-- ========================================================== -->
    <?php
    // Show the drawer when a valid classification exists and the application
    // has progressed past the initial intake statuses.
    $intake_statuses = [
        'INTENT_RECEIVED', 'REQUIREMENTS_SENT', 'REQUIREMENTS_PENDING',
        'REQUIREMENTS_INCOMPLETE', 'REGISTERED', 'UNDER_AUTO_REVIEW',
    ];
    $show_category_drawer = $classification && !in_array($application['current_status'], $intake_statuses);

    $category_checklist_done = isset($fillable_forms_status['category_checklist']);

    // Guideline documents for this classification
    $cat_guideline_pdf = null;
    $cat_guideline_docx = null;
    if ($classification) {
        $cat_dir = __DIR__ . '/../assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/';
        if (is_dir($cat_dir)) {
            foreach (scandir($cat_dir) as $cgf) {
                $cgext = strtolower(pathinfo($cgf, PATHINFO_EXTENSION));
                if ($cgext === 'pdf')
                    $cat_guideline_pdf = 'assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/' . $cgf;
                if ($cgext === 'docx')
                    $cat_guideline_docx = 'assets/to_send/for_reply_to_categories/for_reply_to_' . $classification . '/' . $cgf;
            }
        }
    }

    // Check whether the classification-specific PHP filler exists
    $cat_filler_exists = $classification && file_exists(__DIR__ . '/fill-' . $classification . '-checklist.php');
?>

    <?php if ($show_category_drawer || !empty($staff_documents)): ?>
    <div class="card mb-4 border-0 shadow-lg" id="categoryAssetsSection" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #0d6efd; border-top: 5px solid #0d6efd; cursor: pointer;" onclick="toggleDrawer(this)">
            <div class="d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="mb-1" style="color: #0d6efd; font-weight: 700;">
                        <i class="bi bi-tags-fill me-2"></i>Category Assets &amp; Staff Feedback
                        <?php if ($classification): ?>
                        <span style="font-weight: 400; font-size: 0.85rem;">(<?php echo htmlspecialchars(ucfirst($classification)); ?>)</span>
                        <?php
        endif; ?>
                    </h5>
                    <small class="text-muted">Annotated QF02, classification guidelines, and your category-specific checklist</small>
                </div>
                <div class="text-end">
                    <?php if ($category_checklist_done): ?>
                    <span class="badge bg-success px-3 py-2 mb-1">
                        <i class="bi bi-check-circle me-1"></i>Checklist Submitted
                    </span><br>
                    <?php
        else: ?>
                    <span class="badge bg-warning text-dark px-3 py-2 mb-1">
                        <i class="bi bi-exclamation-circle me-1"></i>Action Required
                    </span><br>
                    <?php
        endif; ?>
                    <i class="bi bi-chevron-down drawer-icon text-muted fs-5"></i>
                </div>
            </div>
        </div>
        <div class="drawer-content">
            <div class="card-body p-4">

                <?php if ($show_category_drawer): ?>
                <div class="alert alert-primary border-0 shadow-sm mb-4" style="border-radius: 10px; background: rgba(13, 110, 253, 0.08); border-left: 5px solid #0d6efd;">
                    <div class="d-flex align-items-start">
                        <i class="bi bi-info-circle-fill fs-4 me-3 text-primary mt-1"></i>
                        <div>
                            <strong class="text-primary">Your application has been classified as: <?php echo htmlspecialchars(ucfirst($classification)); ?></strong><br>
                            Review the annotated QF02 and guideline documents, then complete the category-specific checklist below.
                        </div>
                    </div>
                </div>
                <?php
        endif; ?>

                <!-- Staff-Provided Assets -->
                <?php if (!empty($staff_documents)): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-file-earmark-check text-warning me-2 fs-5"></i>
                        <h6 class="mb-0 fw-bold text-warning">Staff-Provided Documents</h6>
                    </div>
                    <div class="row g-3">
                        <?php foreach ($staff_documents as $doc): ?>
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; border-left: 4px solid #ffc107;">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start">
                                        <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                                            <i class="bi <?php echo(strpos($doc['file_name'], 'qf02_') === 0) ? 'bi-chat-right-text' : 'bi-file-earmark-check'; ?> text-warning fs-5"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fw-bold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($doc['display_name']); ?></h6>
                                            <small class="text-muted d-block mb-2"><?php echo htmlspecialchars($doc['description']); ?></small>
                                            <small class="text-muted d-block" style="font-size:0.7rem;">Received: <?php echo date('M d, Y', strtotime($doc['created_at'])); ?></small>
                                            <div class="mt-2">
                                                <button class="btn btn-outline-warning btn-sm text-dark" onclick="viewDocument('<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['display_name']); ?>')">
                                                    <i class="bi bi-eye-fill me-1"></i>View
                                                </button>
                                                <a href="../<?php echo htmlspecialchars($doc['download_path']); ?>" download class="btn btn-dark btn-sm ms-1">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php
            endforeach; ?>
                    </div>
                </div>
                <?php
        endif; ?>

                <!-- Classification Guidelines -->
                <?php if ($cat_guideline_pdf || $cat_guideline_docx): ?>
                <div class="mb-4">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-book text-info me-2 fs-5"></i>
                        <h6 class="mb-0 fw-bold text-info">Classification Guidelines</h6>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php if ($cat_guideline_pdf): ?>
                        <a href="../<?php echo htmlspecialchars($cat_guideline_pdf); ?>" target="_blank" class="btn btn-outline-info btn-sm">
                            <i class="bi bi-file-earmark-pdf me-1"></i>View Guideline (PDF)
                        </a>
                        <?php
            endif; ?>
                        <?php if ($cat_guideline_docx): ?>
                        <a href="../<?php echo htmlspecialchars($cat_guideline_docx); ?>" download class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-download me-1"></i>Download Guideline (DOCX)
                        </a>
                        <?php
            endif; ?>
                    </div>
                </div>
                <?php
        endif; ?>

                <!-- Category Checklist Filler -->
                <?php if ($show_category_drawer): ?>
                <div class="mb-2">
                    <div class="d-flex align-items-center mb-3">
                        <i class="bi bi-clipboard-check <?php echo $category_checklist_done ? 'text-success' : 'text-primary'; ?> me-2 fs-5"></i>
                        <h6 class="mb-0 fw-bold <?php echo $category_checklist_done ? 'text-success' : 'text-primary'; ?>">
                            <?php echo htmlspecialchars(ucfirst($classification)); ?> Category Checklist
                            <?php if ($category_checklist_done): ?>
                                <span class="badge bg-success ms-2 fs-6"><i class="bi bi-check-circle me-1"></i>Completed</span>
                            <?php
            else: ?>
                                <span class="badge bg-primary ms-2 fs-6"><i class="bi bi-pencil-square me-1"></i>Fillable</span>
                            <?php
            endif; ?>
                        </h6>
                    </div>

                    <?php if (!$cat_filler_exists): ?>
                    <div class="alert alert-warning border-0 shadow-sm" style="border-radius: 10px;">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        The category checklist for <strong><?php echo htmlspecialchars(ucfirst($classification)); ?></strong> is not yet available.
                        Please contact UREO staff.
                    </div>
                    <?php
            else: ?>
                    <p class="text-muted small mb-3">
                        Complete the classification-specific checklist for the ethics review committee.
                        Your answers will be saved automatically.
                    </p>
                    <button class="btn btn-primary shadow-sm" onclick="openFillableForm('category_checklist')">
                        <i class="bi bi-<?php echo $category_checklist_done ? 'pencil' : 'pencil-square'; ?> me-2"></i>
                        <?php echo $category_checklist_done ? 'Update Checklist' : 'Fill Category Checklist'; ?>
                    </button>
                    <?php
            endif; ?>
                </div>
                <?php
        endif; ?>

            </div>
        </div>
    </div>
    <?php
    endif; ?>

    <?php
else: ?>
    <!-- ========================================================== -->
    <!-- REVISION PHASE                                              -->
    <!-- ========================================================== -->

    <!-- Staff-Generated Documents -->
    <?php if (!empty($staff_documents)): ?>
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #0d6efd;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-shield-check text-primary me-3 fs-5"></i>
                <div>
                    <h5 class="mb-0 text-primary fw-bold">Staff-Generated Documents</h5>
                    <small class="text-muted">Official documents and analysis from the review team</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-3">
                <?php foreach ($staff_documents as $doc): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-0 shadow-sm" style="border-radius: 10px; transition: transform 0.2s; border-left: 4px solid #0d6efd;">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-start">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                                    <?php if ($doc['document_type'] === 'QF02 Form' || $doc['document_type'] === 'TAU-REO-QF02'): ?>
                                        <i class="bi bi-file-earmark-pdf text-primary fs-5"></i>
                                    <?php
            elseif ($doc['document_type'] === 'Category Form'): ?>
                                        <i class="bi bi-tags text-primary fs-5"></i>
                                    <?php
            elseif ($doc['document_type'] === 'Approval Letter'): ?>
                                        <i class="bi bi-award text-primary fs-5"></i>
                                    <?php
            else: ?>
                                        <i class="bi bi-file-earmark-pdf text-primary fs-5"></i>
                                    <?php
            endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h6 class="mb-1 fw-bold text-truncate" style="font-size: 0.9rem; color: #0d6efd;">
                                        <?php echo htmlspecialchars($doc['document_type']); ?>
                                    </h6>
                                    <p class="mb-2 small text-muted"><?php echo htmlspecialchars($doc['description']); ?></p>
                                    <small class="text-muted d-block"><?php echo date('M d, Y H:i', strtotime($doc['created_at'])); ?></small>
                                    <div class="mt-2">
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewOrDownloadDocument('<?php echo htmlspecialchars($doc['file_path']); ?>', '<?php echo htmlspecialchars($doc['file_name']); ?>', '<?php echo strtolower(str_replace(' ', '', $doc['document_type'])); ?>')">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                        <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" download="<?php echo htmlspecialchars($doc['file_name']); ?>" class="btn btn-primary btn-sm ms-1">
                                            <i class="bi bi-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
        endforeach; ?>
            </div>
        </div>
    </div>
    <?php
    endif; ?>

    <!-- System Messages (Revision Phase) -->
    <?php if ($messages->num_rows > 0): ?>
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #006400;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-envelope-open text-primary me-3 fs-5"></i>
                <div>
                    <h5 class="mb-0 text-primary fw-bold">System Messages</h5>
                    <small class="text-muted">Review feedback and revision requirements</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <?php
        $messages->data_seek(0);
        while ($msg = $messages->fetch_assoc()):
?>
            <div class="message-item mb-4 p-4" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-left: 4px solid #006400; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h5 class="mb-0 text-primary fw-bold"><?php echo htmlspecialchars($msg['subject']); ?></h5>
                    <small class="text-muted bg-white px-2 py-1 rounded-pill">
                        <i class="bi bi-calendar me-1"></i><?php echo date('M d, Y h:i A', strtotime($msg['created_at'])); ?>
                    </small>
                </div>
                <div class="message-content" style="max-height: 250px; overflow-y: auto; white-space: pre-line; line-height: 1.6; font-size: 15px; background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">
                    <?php echo nl2br(htmlspecialchars(strip_tags($msg['message_body']))); ?>
                </div>
                <div class="mt-3">
                    <button class="btn btn-outline-primary btn-sm" onclick="viewMessageModal(<?php echo $msg['message_id']; ?>, '<?php echo htmlspecialchars(addslashes($msg['subject'])); ?>', `<?php echo addslashes(strip_tags($msg['message_body'])); ?>`)">
                        <i class="bi bi-eye me-1"></i>View Full Message
                    </button>
                </div>
            </div>
            <?php
        endwhile; ?>
        </div>
    </div>
    <?php
    endif; ?>

    <!-- Documents Requiring Revision -->
    <?php if (!empty($revision_documents)): ?>
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden; border-left: 4px solid #ffc107;">
        <div class="card-header" style="background: linear-gradient(135deg, #fff3cd, #ffeaa7); border-bottom: 3px solid #ffc107;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-exclamation-triangle-fill text-warning me-3 fs-5"></i>
                <div>
                    <h5 class="mb-0 text-warning fw-bold">Documents Requiring Revision</h5>
                    <small class="text-muted">Address the issues identified by staff</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius: 10px; background: linear-gradient(135deg, #fff3cd, #ffeaa7);">
                <div class="d-flex align-items-center">
                    <i class="bi bi-info-circle text-warning fs-5 me-3"></i>
                    <div>
                        <strong>Revision Required:</strong> The following documents have been flagged for revision. Please review the system messages above for specific issues and re-upload corrected versions.
                    </div>
                </div>
            </div>
            <div class="row g-4">
                <?php foreach ($revision_documents as $doc_type):
            $doc_name = '';
            $is_fillable = false;
            foreach ($required_documents as $req_doc) {
                if ($req_doc['type'] === $doc_type) {
                    $doc_name = $req_doc['name'];
                    $is_fillable = $req_doc['fillable'];
                    break;
                }
            }
            $already_uploaded = in_array($doc_type, $uploaded_types);
?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 border-warning shadow-sm" style="border-radius: 10px; transition: transform 0.2s;">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-start mb-3">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-2 me-3">
                                    <i class="bi bi-file-earmark-excel text-warning fs-5"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title text-warning mb-2 fw-bold" style="font-size: 1rem;">
                                        <?php echo htmlspecialchars($doc_name); ?>
                                    </h5>
                                </div>
                            </div>
                            <?php if ($is_fillable && in_array($doc_type, ['qf01', 'qf02'])): ?>
                                <div class="mb-3">
                                    <small class="text-muted d-block mb-2">This is a fillable form. You can:</small>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="openFillableForm('<?php echo $doc_type; ?>')">
                                            <i class="bi bi-pencil-square me-1"></i>Fill Form
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="openFormFiller('<?php echo $doc_type; ?>')">
                                            <i class="bi bi-file-pdf me-1"></i>View PDF
                                        </button>
                                    </div>
                                </div>
                            <?php
            endif; ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Re-upload Document:</label>
                                <input type="file" class="form-control form-control-sm border-warning"
                                       name="revision_file_<?php echo $doc_type; ?>"
                                       id="revision_file_<?php echo $doc_type; ?>"
                                       accept=".pdf"
                                       onchange="handleRevisionUpload('<?php echo $doc_type; ?>', this)"
                                       style="border-radius: 8px;">
                                <div class="form-text text-muted">Only PDF files are accepted (Max: 10MB)</div>
                            </div>
                            <div id="upload_status_<?php echo $doc_type; ?>" class="upload-status">
                                <?php if ($already_uploaded): ?>
                                    <span class="badge bg-info rounded-pill px-3 py-2">
                                        <i class="bi bi-check-circle me-1"></i>Previous version uploaded
                                    </span>
                                <?php
            else: ?>
                                    <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                        <i class="bi bi-clock me-1"></i>Not yet uploaded
                                    </span>
                                <?php
            endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
        endforeach; ?>
            </div>
            <div class="mt-4 p-4 bg-light rounded shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-lightbulb text-warning me-2 fs-5"></i>
                    <h6 class="mb-0 text-dark fw-bold">Revision Guidelines:</h6>
                </div>
                <ul class="text-muted mb-0 small ms-4">
                    <li>Review the system message above for specific issues with each document</li>
                    <li>For fillable forms (QF-01, QF-02), use the form filler to make corrections</li>
                    <li>Re-upload only the documents that need revision</li>
                    <li>Once all revisions are complete, your application will be reviewed again</li>
                </ul>
            </div>
        </div>
    </div>
    <?php
    endif; ?>

    <!-- Provided Documents (Revision Phase) -->
    <?php if ($system_documents->num_rows > 0): ?>
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #006400;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-file-text text-primary me-3 fs-5"></i>
                <div>
                    <h5 class="mb-0 text-primary fw-bold">Provided Documents</h5>
                    <small class="text-muted">Reference materials and guidelines</small>
                </div>
            </div>
        </div>
        <div class="list-group list-group-flush">
            <?php
        $system_documents->data_seek(0);
        while ($sys_doc = $system_documents->fetch_assoc()):
?>
            <div class="list-group-item border-0 px-4 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle bg-primary bg-opacity-10 p-2 me-3">
                            <i class="bi bi-file-earmark-pdf text-primary fs-5"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($sys_doc['document_name']); ?></h6>
                            <small class="text-muted">
                                <?php echo ucfirst($sys_doc['document_type']); ?> •
                                Provided on <?php echo date('M d, Y', strtotime($sys_doc['provided_at'])); ?>
                            </small>
                        </div>
                    </div>
                    <button class="btn btn-outline-primary btn-sm" onclick="viewOrDownloadDocument('<?php echo htmlspecialchars($sys_doc['document_path']); ?>', '<?php echo htmlspecialchars($sys_doc['document_name']); ?>')">
                        <i class="bi bi-eye me-1"></i>View
                    </button>
                </div>
            </div>
            <?php
        endwhile; ?>
        </div>
    </div>
    <?php
    endif; ?>

    <!-- Required Documents (Revision Phase) -->
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #006400;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-list-check text-success me-3 fs-5"></i>
                <div>
                    <h5 class="mb-0 text-success fw-bold">Required Documents</h5>
                    <small class="text-muted">Current status of all required documents</small>
                </div>
            </div>
        </div>
        <div class="list-group list-group-flush">
            <?php foreach ($required_documents as $req_doc):
        $is_uploaded = in_array($req_doc['type'], $uploaded_types);
        $form_completed = isset($fillable_forms_status[$req_doc['type']]) && $fillable_forms_status[$req_doc['type']]['completed'];
        $is_revision_required = in_array($req_doc['type'], $revision_documents);
?>
            <div class="list-group-item border-0 px-4 py-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="rounded-circle <?php echo $is_revision_required ? 'bg-warning' : ($is_uploaded ? 'bg-success' : 'bg-secondary'); ?> bg-opacity-10 p-2 me-3">
                            <i class="bi <?php echo $is_revision_required ? 'bi-exclamation-triangle' : ($is_uploaded ? 'bi-check-circle' : 'bi-lock'); ?> <?php echo $is_revision_required ? 'text-warning' : ($is_uploaded ? 'text-success' : 'text-secondary'); ?> fs-5"></i>
                        </div>
                        <div>
                            <h6 class="mb-1 fw-bold text-dark"><?php echo htmlspecialchars($req_doc['name']); ?></h6>
                            <?php if ($req_doc['fillable']): ?>
                                <small class="text-info">
                                    <i class="bi bi-pencil-square me-1"></i>Fillable form available
                                    <?php if ($form_completed): ?>
                                        <span class="badge bg-info ms-1">Form Completed</span>
                                    <?php
            endif; ?>
                                </small>
                            <?php
        endif; ?>
                        </div>
                    </div>
                    <div class="text-end">
                        <?php if ($is_revision_required): ?>
                            <span class="badge bg-warning text-dark rounded-pill px-3 py-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>Revision Required
                            </span>
                        <?php
        elseif ($is_uploaded): ?>
                            <span class="badge bg-secondary rounded-pill px-3 py-2">
                                <i class="bi bi-lock me-1"></i>Approved - Locked
                            </span>
                        <?php
        else: ?>
                            <span class="badge bg-secondary rounded-pill px-3 py-2">
                                <i class="bi bi-lock me-1"></i>Not Required
                            </span>
                        <?php
        endif; ?>
                    </div>
                </div>
            </div>
            <?php
    endforeach; ?>
        </div>
    </div>

    <!-- My Uploaded Documents (Revision Phase) -->
    <div class="card mb-4 border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
        <div class="card-header" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-bottom: 3px solid #006400;">
            <div class="d-flex align-items-center py-3">
                <i class="bi bi-upload text-info me-3 fs-5"></i>
                <div>
                    <h4 class="mb-0 text-info fw-bold">My Uploaded Documents</h4>
                    <small class="text-muted">All documents you've submitted</small>
                </div>
            </div>
        </div>
        <div class="card-body p-4">
            <?php if ($documents->num_rows > 0): ?>
                <div class="card border-0 shadow-sm" style="border-radius: 10px; overflow: hidden;">
                    <div class="table-responsive" id="uploadedDocumentsTable">
                        <table class="table table-hover mb-0">
                            <thead style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                                <tr>
                                    <th class="border-0 fw-bold text-muted py-3">Document Type</th>
                                    <th class="border-0 fw-bold text-muted py-3">File Name</th>
                                    <th class="border-0 fw-bold text-muted py-3">Upload Date</th>
                                    <th class="border-0 fw-bold text-muted py-3">Status</th>
                                    <th class="border-0 fw-bold text-muted py-3">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
        $documents->data_seek(0);
        while ($doc = $documents->fetch_assoc()):
            $file_path = $doc['file_path'] ?? '';
            $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
            $is_pdf = ($file_extension === 'pdf');
            $is_docx = ($file_extension === 'docx' || $file_extension === 'doc');
?>
                                <tr style="transition: background-color 0.2s;">
                                    <td class="py-3">
                                        <span class="badge bg-light text-dark px-2 py-1">
                                            <?php echo htmlspecialchars($doc['document_type'] ?? ''); ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <?php if ($is_pdf): ?>
                                            <i class="bi bi-file-earmark-pdf text-danger me-2"></i>
                                        <?php
            elseif ($is_docx): ?>
                                            <i class="bi bi-file-earmark-word text-primary me-2"></i>
                                        <?php
            else: ?>
                                            <i class="bi bi-file-earmark text-secondary me-2"></i>
                                        <?php
            endif; ?>
                                        <strong><?php echo htmlspecialchars($doc['document_name'] ?? 'Unknown'); ?></strong>
                                    </td>
                                    <td class="py-3 text-muted">
                                        <?php echo date('M d, Y', strtotime($doc['upload_timestamp'])); ?>
                                    </td>
                                    <td class="py-3">
                                        <span class="badge px-3 py-2 <?php echo $doc['validation_status'] === 'approved' ? 'bg-success' : ($doc['validation_status'] === 'rejected' ? 'bg-danger' : 'bg-warning text-dark'); ?>">
                                            <i class="bi <?php echo $doc['validation_status'] === 'approved' ? 'bi-check-circle' : ($doc['validation_status'] === 'rejected' ? 'bi-x-circle' : 'bi-clock'); ?> me-1"></i>
                                            <?php echo ucfirst($doc['validation_status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-3">
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewDocument('<?php echo htmlspecialchars($file_path); ?>', '<?php echo htmlspecialchars($doc['document_name'] ?? 'Unknown'); ?>')">
                                            <i class="bi bi-eye me-1"></i>View
                                        </button>
                                    </td>
                                </tr>
                                <?php
        endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php
    else: ?>
                <div class="card border-0 shadow-sm text-center py-5" style="border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                    <div class="card-body">
                        <i class="bi bi-file-earmark-x display-5 text-muted mb-3"></i>
                        <h5 class="text-muted mb-2">No documents uploaded yet</h5>
                        <p class="text-muted mb-0">Documents requiring revision will appear here</p>
                    </div>
                </div>
            <?php
    endif; ?>
        </div>
    </div>

    <?php
endif; ?>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); border-bottom: none;">
                <h5 class="modal-title text-white fw-bold">
                    <i class="bi bi-cloud-upload me-2"></i>Upload Document
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="upload-document.php" enctype="multipart/form-data" id="uploadForm" onsubmit="handleUpload(event)">
                <div class="modal-body p-4">
                    <?php if ($is_pending_revision && !empty($revision_documents)): ?>
                    <div class="alert alert-warning border-0 shadow-sm mb-4" style="border-radius: 10px; background: linear-gradient(135deg, #fff3cd, #ffeaa7);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle text-warning fs-5 me-3"></i>
                            <div>
                                <strong>Revision Mode:</strong> Only documents requiring revision can be uploaded at this time.
                            </div>
                        </div>
                    </div>
                    <?php
endif; ?>

                    <div class="mb-4">
                        <label for="document_type" class="form-label fw-bold text-dark fs-6">
                            <i class="bi bi-file-earmark-text text-primary me-2"></i>Document Type
                        </label>
                        <select class="form-select border-0 shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);" name="document_type" id="document_type" required onchange="handleDocumentTypeChange()">
                            <option value="">Select document type...</option>
                            <?php foreach ($required_documents as $req_doc):
    $is_uploaded = in_array($req_doc['type'], $uploaded_types);
    $is_revision_required = in_array($req_doc['type'], $revision_documents);
    if ($is_pending_revision && !$is_revision_required)
        continue;
    $is_disabled = $is_uploaded && !$is_revision_required && !$is_pending_revision;
?>
                                <option value="<?php echo htmlspecialchars($req_doc['type']); ?>"
                                        data-fillable="<?php echo $req_doc['fillable'] ? 'true' : 'false'; ?>"
                                        data-revision="<?php echo $is_revision_required ? 'true' : 'false'; ?>"
                                        <?php echo $is_disabled ? 'disabled' : ''; ?>>
                                    <?php echo htmlspecialchars($req_doc['name']); ?>
                                    <?php if ($is_uploaded && !$is_revision_required): ?>(Already Uploaded)
                                    <?php
    elseif ($is_revision_required): ?>(Revision Required)
                                    <?php
    endif; ?>
                                </option>
                            <?php
endforeach; ?>
                        </select>
                    </div>

                    <div id="fillableAlert" class="alert alert-info d-none border-0 shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #d1ecf1, #bee5eb);">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-info-circle text-info fs-5 me-3"></i>
                            <div id="fillableAlertContent">
                                <strong>This is a fillable form!</strong><br>
                                Please fill out the form first before uploading.
                                <div class="mt-3">
                                    <button type="button" id="fillableLink" class="btn btn-primary btn-sm">
                                        <i class="bi bi-pencil-square me-1"></i>Fill Form
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="uploadProgress" class="progress d-none mb-4" style="height: 8px; border-radius: 4px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar" style="width: 0%"></div>
                    </div>

                    <div class="mb-4">
                        <label for="document_file" class="form-label fw-bold text-dark fs-6">
                            <i class="bi bi-file-earmark-arrow-up text-success me-2"></i>Select File
                        </label>
                        <input type="file" class="form-control border-0 shadow-sm" style="border-radius: 10px; background: linear-gradient(135deg, #f8f9fa, #e9ecef);" name="document_file" id="document_file" accept=".pdf" required disabled>
                        <div class="form-text text-muted mt-2">
                            <i class="bi bi-info-circle me-1"></i>Only PDF files are accepted (Max: 10MB)
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 p-4" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <button type="submit" id="uploadBtn" class="btn btn-primary px-4 text-white" style="background: linear-gradient(135deg, #006400, #228B22); border: none;">
                        <i class="bi bi-upload me-1"></i>Upload Document
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Document Preview Modal -->
<div class="modal fade" id="previewModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); border-bottom: none;">
                <h5 class="modal-title text-white fw-bold" id="previewModalTitle">
                    <i class="bi bi-eye me-2"></i>Document Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh; overflow: hidden; background: #f8f9fa;">
                <div class="pdf-viewer-container">
                    <iframe id="documentFrame" style="width: 100%; height: 100%; border: none; border-radius: 0 0 15px 15px;"></iframe>
                    <svg id="remarks-svg-layer"></svg>
                    <div id="floating-remarks-layer"></div>
                </div>
                <div id="loadingIndicator" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 100;">
                    <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-muted mt-3">Loading document...</h5>
                    <p class="text-muted small">Please wait while we prepare your document</p>
                </div>
            </div>
            <div class="modal-footer border-0 p-3" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- DOCX Document Preview Modal -->
<div class="modal fade" id="docxModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); border-bottom: none;">
                <h5 class="modal-title text-white fw-bold" id="docxModalTitle">
                    <i class="bi bi-file-earmark-word me-2"></i>DOCX Document Preview
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh; overflow: hidden; background: #f8f9fa;">
                <iframe id="docxFrame" style="width: 100%; height: 100%; border: none; border-radius: 0 0 15px 15px;"></iframe>
                <div id="docxLoadingIndicator" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; z-index: 10;">
                    <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <h5 class="text-muted mt-3">Loading document...</h5>
                </div>
            </div>
            <div class="modal-footer border-0 p-3" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 0 0 15px 15px;">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Message View Modal -->
<div class="modal fade" id="messageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); border-bottom: none;">
                <h5 class="modal-title text-white fw-bold" id="messageModalTitle">
                    <i class="bi bi-envelope-open me-2"></i>System Message
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" id="messageModalBody" style="max-height: 70vh; overflow-y: auto; white-space: pre-line; line-height: 1.8; font-size: 15px; background: linear-gradient(135deg, #f8f9fa, #e9ecef); border-radius: 0 0 15px 15px;">
            </div>
            <div class="modal-footer border-0 p-3" style="background: linear-gradient(135deg, #f8f9fa, #e9ecef);">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle me-1"></i>Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Fillable Form Modal (fullscreen iframe) -->
<div class="modal fade" id="fillableFormModal" tabindex="-1">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 15px; overflow: hidden;">
            <div class="modal-header" style="background: linear-gradient(135deg, #006400, #228B22); border-bottom: none;">
                <h5 class="modal-title text-white fw-bold">
                    <i class="bi bi-pencil-square me-2"></i>Fill Required Form
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="overflow: hidden; background: #f8f9fa;">
                <iframe id="fillableFormFrame" style="width: 100%; height: calc(100vh - 56px); border: none;"></iframe>
            </div>
        </div>
    </div>
</div>

<style>
.collapsed-drawer .drawer-content { display: none; }
.drawer-icon { transition: transform 0.3s ease; }
.collapsed-drawer .drawer-icon { transform: rotate(-90deg); }
.message-content { scrollbar-width: thin; scrollbar-color: #006400 #f8f9fa; }
.message-content::-webkit-scrollbar { width: 6px; }
.message-content::-webkit-scrollbar-track { background: #f8f9fa; border-radius: 3px; }
.message-content::-webkit-scrollbar-thumb { background: #006400; border-radius: 3px; }
.upload-status .badge { font-size: 0.75rem; }
.spinning { animation: spin 1s linear infinite; }
@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
.card { transition: all 0.3s ease; }
.card:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important; }
.table-hover tbody tr:hover { background-color: rgba(0,100,0,0.05); }
.btn { transition: all 0.3s ease; }
.btn:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
.list-group-item { transition: all 0.3s ease; }
.list-group-item:hover { background-color: rgba(0,100,0,0.05) !important; transform: translateX(5px); }
.pdf-viewer-container { position: relative; width: 100%; height: 100%; background: #525659; overflow: hidden; }
#remarks-svg-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 10; }
#floating-remarks-layer { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 20; }
.float-remark-box { position: absolute; background: #1a1a1a; color: #fff; border-radius: 8px; padding: 0; width: min(260px,40vw); max-width: 90vw; box-shadow: 0 4px 15px rgba(0,0,0,0.5); border: 1px solid #333; overflow: visible; transition: top 0.3s ease,left 0.3s ease,opacity 0.3s ease; display: flex; flex-direction: column; pointer-events: auto; opacity: 1; }
.float-remark-anchor-dot { position: absolute; width: 10px; height: 10px; background: #ffcc00; border-radius: 50%; box-shadow: 0 0 8px rgba(255,204,0,0.9); border: 1.5px solid #000; z-index: 30; pointer-events: none; }
.float-remark-header { background: #ffcc00; color: #000; padding: 6px 12px; font-size: 11px; font-weight: 800; border-radius: 8px 8px 0 0; display: flex; align-items: center; gap: 8px; text-transform: uppercase; }
.float-remark-badge { background: #000; color: #ffcc00; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; }
.float-remark-content { padding: 10px 15px; font-size: 12px; line-height: 1.5; max-height: 120px; overflow-y: auto; }
.float-remark-box.active { border-color: #ffcc00; box-shadow: 0 0 20px rgba(255,204,0,0.6); opacity: 1; z-index: 100; }
@media (max-width: 768px) {
    .card:hover { transform: none; }
    .btn:hover { transform: none; }
    .list-group-item:hover { transform: none; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/mammoth@1.6.0/mammoth.browser.min.js"></script>
<script>
let currentFormType = '';
const fillableFormsStatus = <?php echo json_encode($fillable_forms_status); ?>;

// ── Fillable form routing ─────────────────────────────────────────────────────
// Maps form type keys to their PHP filler URLs.
// 'category_checklist' always routes through fill-category-form.php which
// internally resolves the correct classification from ai_classification.json.
const FORM_URLS = {
    'qf01':               'fill-qf01-form.php',
    'qf02':               'fill-qf02-form.php',
    'category_checklist': 'fill-category-form.php',
};

function openFillableForm(docType) {
    const formType = docType || currentFormType;
    const formUrl  = FORM_URLS[formType] || null;

    if (!formUrl) {
        alert('No form filler is available for: ' + formType);
        return;
    }

    document.getElementById('fillableFormFrame').src = formUrl;
    new bootstrap.Modal(document.getElementById('fillableFormModal')).show();
}

function handleDocumentTypeChange() {
    const select      = document.getElementById('document_type');
    const fileInput   = document.getElementById('document_file');
    const fillableAlert = document.getElementById('fillableAlert');
    const selectedOption = select.options[select.selectedIndex];

    if (selectedOption.disabled) {
        fillableAlert.innerHTML = '<i class="bi bi-check-circle text-success"></i> <strong>Document Already Uploaded!</strong><br>This document type has already been uploaded.';
        fillableAlert.classList.remove('d-none');
        fileInput.disabled = true;
        return;
    }

    const isFillable = selectedOption.getAttribute('data-fillable') === 'true';
    const isRevision = selectedOption.getAttribute('data-revision') === 'true';

    if (isRevision) {
        fillableAlert.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i> <strong>Revision Required!</strong><br>This document needs revision based on staff feedback. You can re-upload an updated version.';
        fillableAlert.classList.remove('d-none');
        fileInput.disabled = false;
        currentFormType = selectedOption.value;
        return;
    }

    if (isFillable) {
        currentFormType = selectedOption.value;

        // QF-01 requires QF-02 first
        if (currentFormType === 'qf01' && (!fillableFormsStatus['qf02'] || !fillableFormsStatus['qf02'].completed)) {
            fillableAlert.innerHTML = '<i class="bi bi-exclamation-triangle text-warning"></i> <strong>QF-02 Required!</strong><br>Complete QF-02 first.<div class="mt-2"><button type="button" class="btn btn-sm btn-warning" onclick="switchToQF02()"><i class="bi bi-arrow-right"></i> Go to QF-02</button></div>';
            fillableAlert.classList.remove('d-none');
            fileInput.disabled = true;
            return;
        }

        if (fillableFormsStatus[currentFormType] && fillableFormsStatus[currentFormType].completed) {
            fillableAlert.innerHTML = '<i class="bi bi-check-circle text-success"></i> <strong>Form already completed!</strong> You can now upload the generated PDF.';
            fillableAlert.classList.remove('d-none');
            fileInput.disabled = false;
        } else {
            fillableAlert.innerHTML = '<i class="bi bi-info-circle"></i> <strong>This is a fillable form!</strong><br>Please fill it out first.<div class="mt-2"><button type="button" class="btn btn-sm btn-primary" onclick="openFillableForm()"><i class="bi bi-pencil-square"></i> Fill Form</button></div>';
            fillableAlert.classList.remove('d-none');
            fileInput.disabled = true;
        }
    } else {
        fillableAlert.classList.add('d-none');
        fileInput.disabled = false;
        currentFormType = '';
    }
}

function switchToQF02() {
    document.getElementById('document_type').value = 'qf02';
    handleDocumentTypeChange();
}

// Listen for postMessage from any fillable-form iframe (qf01, qf02, category checklist)
window.addEventListener('message', function(event) {
    if (event.data && event.data.type === 'formCompleted') {
        fillableFormsStatus[event.data.formType] = { completed: true };
        document.getElementById('document_file').disabled = false;
        document.getElementById('fillableAlert').innerHTML = '<i class="bi bi-check-circle text-success"></i> <strong>Form completed!</strong> You can now select and upload the generated PDF.';
        const modal = bootstrap.Modal.getInstance(document.getElementById('fillableFormModal'));
        if (modal) modal.hide();
        alert(event.data.message || 'Form completed successfully!');
        setTimeout(() => location.reload(), 500);
    }
});

function previewDocument(path, name) {
    if (!path || path === '') { alert('Document path is not available.'); return; }
    const modal = new bootstrap.Modal(document.getElementById('previewModal'));
    document.getElementById('previewModalTitle').textContent = name || 'Document Preview';
    const iframe = document.getElementById('documentFrame');
    const loadingIndicator = document.getElementById('loadingIndicator');
    loadingIndicator.style.display = 'block';
    iframe.style.display = 'none';
    iframe.src = '';
    modal.show();
    setTimeout(() => {
        iframe.style.display = 'block';
        loadingIndicator.style.display = 'none';
        iframe.src = 'view-document.php?path=' + encodeURIComponent(path);
        const fileName = path.split('/').pop();
        if (fileName.startsWith('qf02_')) {
            iframe.onload = function() { loadFloatingRemarks(); };
        }
    }, 100);
}

function loadFloatingRemarks() {
    const queueNumber = '<?php echo htmlspecialchars($_SESSION['queue_number'] ?? ''); ?>';
    if (!queueNumber) return;
    fetch('get-qf02-remarks.php?queue=' + encodeURIComponent(queueNumber))
        .then(r => r.json())
        .then(data => { if (data.success && data.remarks) initializeFloatingRemarks(data.remarks); })
        .catch(err => console.error('Error loading remarks:', err));
}

function initializeFloatingRemarks(remarks) {
    const layer    = document.getElementById('floating-remarks-layer');
    const svgLayer = document.getElementById('remarks-svg-layer');
    if (!layer || !svgLayer) return;
    layer.innerHTML = ''; svgLayer.innerHTML = '';
    const tuning = { firstRowTop: 32.65, rowHeight: 3.05, colRight: 8.45, colWidth: 13.5 };
    for (let i = 1; i <= 20; i++) {
        const text = remarks[`crit_${i}_remarks`];
        if (text && text.trim() !== '') {
            const box = document.createElement('div');
            box.className = `float-remark-box float-box-${i}`;
            box.innerHTML = `<div class="float-remark-header"><div class="float-remark-badge">${i}</div><span>Criteria Point ${i}</span></div><div class="float-remark-content">${text}</div>`;
            layer.appendChild(box);
            const dot = document.createElement('div');
            dot.className = `float-remark-anchor-dot float-dot-${i}`;
            layer.appendChild(dot);
            const line = document.createElementNS("http://www.w3.org/2000/svg","line");
            line.setAttribute("class",`float-line-${i}`);
            line.setAttribute("stroke","#ffcc00");
            line.setAttribute("stroke-width","1.5");
            line.setAttribute("stroke-dasharray","4,2");
            line.setAttribute("opacity","0.6");
            svgLayer.appendChild(line);
        }
    }
    requestAnimationFrame(() => syncRemarks(tuning));
}

function syncRemarks(tuning) {
    const iframe = document.getElementById('documentFrame');
    const layer  = document.getElementById('floating-remarks-layer');
    const svgLayer = document.getElementById('remarks-svg-layer');
    if (!iframe || !layer) return;
    try {
        const iframeDoc = iframe.contentDocument || iframe.contentWindow.document;
        const canvas    = iframeDoc.querySelector('.pdf-canvas');
        if (canvas) {
            const rect       = canvas.getBoundingClientRect();
            const iframeRect = iframe.getBoundingClientRect();
            const parentRect = iframe.parentElement.getBoundingClientRect();
            const baseX = (iframeRect.left - parentRect.left) + rect.left;
            const baseY = (iframeRect.top  - parentRect.top)  + rect.top;
            const boxes = Array.from(layer.querySelectorAll('.float-remark-box'));
            boxes.sort((a,b) => parseInt(a.className.match(/float-box-(\d+)/)[1]) - parseInt(b.className.match(/float-box-(\d+)/)[1]));
            let lastBottom = baseY;
            const margin = 12;
            const viewportWidth = window.innerWidth;
            let horizontalOffset = 50;
            if (viewportWidth <= 768) horizontalOffset = 25;
            if (viewportWidth <= 576) horizontalOffset = 15;
            boxes.forEach(box => {
                const index   = parseInt(box.className.match(/float-box-(\d+)/)[1]);
                const dot     = layer.querySelector(`.float-dot-${index}`);
                const line    = svgLayer.querySelector(`.float-line-${index}`);
                const topPct  = tuning.firstRowTop + (index - 1) * tuning.rowHeight;
                const rowMidPx = (topPct + (tuning.rowHeight / 2)) / 100 * rect.height;
                const columnX  = baseX + (1 - (tuning.colRight + (tuning.colWidth / 2)) / 100) * rect.width;
                const dotY     = baseY + rowMidPx;
                if (dot) { dot.style.left = (columnX - 5) + 'px'; dot.style.top = (dotY - 5) + 'px'; }
                const idealTop = dotY - (box.offsetHeight / 2);
                const finalTop = Math.max(idealTop, lastBottom + margin);
                const boxLeft  = Math.min(baseX + rect.width + horizontalOffset, parentRect.width - box.offsetWidth - 20);
                box.style.top  = finalTop + 'px';
                box.style.left = boxLeft + 'px';
                if (line) { line.setAttribute("x1",columnX); line.setAttribute("y1",dotY); line.setAttribute("x2",boxLeft); line.setAttribute("y2",finalTop + (box.offsetHeight/2)); }
                lastBottom = finalTop + box.offsetHeight;
            });
        }
    } catch(e) {}
    if (iframe.offsetParent !== null) requestAnimationFrame(() => syncRemarks(tuning));
}

function viewDocument(path, name) {
    const ext = path.split('.').pop().toLowerCase();
    if (ext === 'pdf') { previewDocument(path, name); }
    else if (ext === 'docx' || ext === 'doc') { previewDocxDocument(path, name); }
    else { if (confirm('This file cannot be previewed. Download "' + name + '"?')) { const a = document.createElement('a'); a.href = '../' + path; a.download = name; document.body.appendChild(a); a.click(); document.body.removeChild(a); } }
}

function viewOrDownloadDocument(path, name, documentType) {
    const ext = path.split('.').pop().toLowerCase();
    if (ext === 'docx' || ext === 'doc') { previewDocxDocument(path, name); }
    else { previewDocument(path, name); }
}

function previewDocxDocument(path, name) {
    const modal = new bootstrap.Modal(document.getElementById('docxModal'));
    const iframe = document.getElementById('docxFrame');
    const loadingIndicator = document.getElementById('docxLoadingIndicator');
    document.getElementById('docxModalTitle').textContent = name || 'DOCX Document Preview';
    iframe.style.display = 'none'; loadingIndicator.style.display = 'block';
    modal.show();
    setTimeout(() => { iframe.style.display = 'block'; loadingIndicator.style.display = 'none'; iframe.src = 'view-docx.php?path=' + encodeURIComponent(path); }, 100);
}

function viewMessageModal(id, subject, body) {
    document.getElementById('messageModalTitle').textContent = subject;
    document.getElementById('messageModalBody').innerHTML = body.replace(/\n/g, '<br>');
    new bootstrap.Modal(document.getElementById('messageModal')).show();
}

function toggleDrawer(header) {
    header.closest('.card').classList.toggle('collapsed-drawer');
}

function handleUpload(event) {
    event.preventDefault();
    const form        = document.getElementById('uploadForm');
    const uploadBtn   = document.getElementById('uploadBtn');
    const progressBar = document.querySelector('#uploadProgress .progress-bar');
    const progressDiv = document.getElementById('uploadProgress');
    progressDiv.classList.remove('d-none');
    uploadBtn.disabled = true;
    fetch('upload-document.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: new FormData(form) })
        .then(r => { if (!r.ok) throw new Error('Server returned ' + r.status); return r.text(); })
        .then(text => { try { return JSON.parse(text); } catch(e) { throw new Error('Invalid server response.'); } })
        .then(data => {
            if (data.success) {
                bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
                alert('Document uploaded successfully!');
                form.reset();
                document.getElementById('document_file').disabled = true;
                document.getElementById('fillableAlert').classList.add('d-none');
                location.reload();
            } else { alert('Error: ' + data.message); }
        })
        .catch(err => { alert('Upload failed: ' + err.message); })
        .finally(() => { progressDiv.classList.add('d-none'); progressBar.style.width = '0%'; uploadBtn.disabled = false; });
}

function handleRevisionUpload(docType, fileInput) {
    const file = fileInput.files[0];
    if (!file) return;
    const statusDiv      = document.getElementById('upload_status_' + docType);
    const originalContent = statusDiv.innerHTML;
    statusDiv.innerHTML = '<span class="badge bg-primary"><i class="bi bi-arrow-repeat spinning"></i> Uploading...</span>';
    const formData = new FormData();
    formData.append('document_type', docType);
    formData.append('document_file', file);
    formData.append('is_revision', '1');
    fetch('upload-document.php', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: formData })
        .then(r => r.text())
        .then(text => { try { return JSON.parse(text); } catch(e) { throw new Error('Invalid server response'); } })
        .then(data => {
            if (data.success) {
                statusDiv.innerHTML = '<span class="badge bg-success"><i class="bi bi-check-circle"></i> Revision Uploaded</span>';
                fileInput.value = '';
                alert('Revision uploaded successfully!');
                setTimeout(() => location.reload(), 1000);
            } else { statusDiv.innerHTML = originalContent; alert('Error: ' + data.message); }
        })
        .catch(err => { statusDiv.innerHTML = originalContent; alert('Upload failed: ' + err.message); });
}

function openFormFiller(docType) {
    const fillerUrl = docType === 'qf01' ? 'generate-qf01-pdf.php' : 'generate-qf02-pdf.php';
    window.open(fillerUrl, '_blank');
}
</script>
<?php include '../includes/auth_footer.php'; ?>