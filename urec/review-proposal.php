<?php
declare(strict_types=1);

/**
 * Interactive PDF Proposal Reviewer (Pin Tool)
 * TAU-UREO Portal
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
$document_id = (int)($_GET['doc_id'] ?? 0);

if (empty($queue_number) || !$document_id) {
    die("Invalid request: Queue number and Document ID are required.");
}

$conn = getDBConnection();

// Get document details
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE document_id = ? AND queue_number = ?");
$doc_stmt->bind_param("is", $document_id, $queue_number);
$doc_stmt->execute();
$document = $doc_stmt->get_result()->fetch_assoc();

if (!$document) {
    die("Document not found.");
}

$file_path = '../' . $document['file_path'];
if (!file_exists($file_path)) {
    die("Document file not found on server.");
}

// Get application details for context
$app_stmt = $conn->prepare("SELECT applicant_name, research_title, urec_committee_id FROM applications WHERE queue_number = ?");
$app_stmt->bind_param("s", $queue_number);
$app_stmt->execute();
$application = $app_stmt->get_result()->fetch_assoc();

$committee_id = $application['urec_committee_id'];

$page_title = "Review Proposal - " . $queue_number;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <!-- Premium Font Stack -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <!-- Bootstrap & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --navy: #0f2942;
            --gold: #c9993a;
            --cream: #faf8f3;
            --shadow: 0 4px 24px rgba(15,41,66,0.12);
        }

        /* Security: Prevent screenshots, inspection, and copying */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }

        /* Prevent right-click context menu */
        body, .pdfContainer, canvas {
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -khtml-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

        /* Prevent print and save */
        @media print {
            body { display: none !important; }
        }

        /* Prevent developer tools access */
        body {
            -webkit-user-modify: read-only;
            -moz-user-modify: read-only;
            -ms-user-modify: read-only;
            user-modify: read-only;
        }

        /* Hide scrollbars to prevent full page screenshots */
        ::-webkit-scrollbar {
            display: none;
        }

        /* Prevent text selection and copying */
        * {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }

        /* Allow selection only for necessary inputs */
        input, textarea {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #2c3e50;
            color: #ecf0f1;
            margin: 0;
            overflow: hidden;
        }

        .tool-header {
            background: var(--navy);
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            border-bottom: 2px solid var(--gold);
            z-index: 1000;
        }

        .brand {
            font-family: 'Playfair Display', serif;
            color: var(--gold);
            font-weight: 700;
            text-decoration: none;
        }

        .main-container {
            display: grid;
            grid-template-columns: 280px 1fr 340px;
            height: calc(100vh - 60px);
        }

        /* Sidebar Panels */
        .panel {
            background: #fff;
            color: var(--navy);
            overflow-y: auto;
            border-right: 1px solid #ddd;
        }

        .panel-header {
            padding: 20px;
            background: var(--cream);
            border-bottom: 1px solid #eee;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.05em;
        }

        .panel-body { padding: 20px; }

        /* Viewer Area */
        .viewer-area {
            background: #525659;
            overflow-y: auto;
            position: relative;
            padding: 40px 0;
            scroll-behavior: smooth;
        }

        canvas {
            display: block;
            margin: 0 auto 40px auto;
            box-shadow: 0 0 20px rgba(0,0,0,0.5);
            background: white;
        }

        .page-container {
            position: relative;
            margin: 0 auto 40px auto;
            width: fit-content;
        }

        .annotation-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: auto;
            cursor: crosshair;
        }

        /* Pin/Annotation Styles */
        .pin {
            position: absolute;
            width: 24px;
            height: 24px;
            background: #e74c3c;
            border: 2px solid #fff;
            border-radius: 50% 50% 50% 0;
            transform: translate(-50%, -100%) rotate(-45deg);
            cursor: pointer;
            z-index: 50;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .pin:hover { transform: translate(-50%, -100%) rotate(-45deg) scale(1.2); background: #c0392b; }
        
        .pin-number {
            transform: rotate(45deg);
            display: block;
            text-align: center;
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            line-height: 20px;
        }

        .pin.active { background: #2ecc71; border-color: #fff; }

        /* Annotation Card Mini-View */
        .ann-card {
            background: var(--cream);
            border-left: 4px solid var(--navy);
            padding: 12px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 0.85rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .ann-card:hover { transform: translateX(5px); background: #fff; }
        .ann-card .meta { font-size: 0.7rem; color: #888; margin-top: 5px; }

        /* Popover Form */
        #pinForm {
            position: fixed;
            background: white;
            padding: 15px;
            border-radius: 12px;
            box-shadow: var(--shadow-lg);
            z-index: 2000;
            width: 300px;
            border: 1px solid var(--gold);
            display: none;
        }

        /* Loader */
        #loader {
            position: fixed;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 3000;
        }
    </style>
</head>
<body>

<div id="loader">
    <div class="spinner-border text-gold" style="width: 3rem; height: 3rem;" role="status">
        <span class="visually-hidden">Loading PDF...</span>
    </div>
</div>

<header class="tool-header">
    <a href="view-application.php?queue=<?php echo $queue_number; ?>" class="brand">TAU REO · PROPOSAL PINNER</a>
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-gold text-navy fw-bold"><?php echo $queue_number; ?></span>
        <button class="btn btn-sm btn-outline-light" onclick="window.close()">Close Tool</button>
    </div>
</header>

<div class="main-container">
    <!-- Left Sidebar: List of pages/thumbnails -->
    <aside class="panel">
        <div class="panel-header">Document Overview</div>
        <div class="panel-body">
            <p class="small text-muted mb-4">Click PDF area to drop a pin. Each pin saves a persistent remark linked to the location.</p>
            <div id="thumbnailView" class="d-grid gap-2"></div>
        </div>
    </aside>

    <!-- Center: PDF Viewer Area -->
    <main class="viewer-area" id="pdfViewer">
        <!-- Rendered PDF pages will appear here -->
    </main>

    <!-- Right Sidebar: Annotation List -->
    <aside class="panel" style="border-left: 1px solid #ddd; border-right: none;">
        <div class="panel-header">Annotation List</div>
        <div class="panel-body" id="annotationList">
            <div class="text-center py-5 text-muted small">No annotations yet. Click anywhere on the Proposal to add a pin.</div>
        </div>
    </aside>
</div>

<!-- Floating Pin Form -->
<div id="pinForm">
    <h6 class="fw-bold mb-2 text-navy small uppercase">Add Remark at <span id="pinCoords"></span></h6>
    <textarea id="pinContent" class="form-control form-control-sm mb-3" rows="4" placeholder="Enter your observation or required revision..."></textarea>
    <div class="d-flex justify-content-between">
        <button class="btn btn-sm btn-light" onclick="hidePinForm()">Cancel</button>
        <button class="btn btn-sm btn-premium" style="background:var(--navy); color:white;" onclick="saveAnnotation()">Save Pin</button>
    </div>
    <input type="hidden" id="tempX">
    <input type="hidden" id="tempY">
    <input type="hidden" id="tempPage">
</div>

<!-- PDF.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
const pdfjsLib = window['pdfjs-dist/build/pdf'];
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

let pdfDoc = null;
let currentAnnotations = [];
const queueNumber = '<?php echo $queue_number; ?>';
const documentType = 'proposal';
const committeeId = <?php echo json_encode($committee_id); ?>;

const pdfPath = '<?php echo $file_path; ?>';

// Initial Load
document.addEventListener('DOMContentLoaded', async () => {
    try {
        const loadingTask = pdfjsLib.getDocument(pdfPath);
        pdfDoc = await loadingTask.promise;
        document.getElementById('loader').style.display = 'none';
        
        await renderAllPages();
        loadAnnotations();
    } catch (e) {
        alert('Error loading PDF: ' + e.message);
        console.error(e);
    }
});

async function renderAllPages() {
    const viewer = document.getElementById('pdfViewer');
    
    for (let i = 1; i <= pdfDoc.numPages; i++) {
        const page = await pdfDoc.getPage(i);
        const viewport = page.getViewport({ scale: 1.5 });
        
        const container = document.createElement('div');
        container.className = 'page-container';
        container.id = `page-container-${i}`;
        container.dataset.page = i;
        container.style.width = viewport.width + 'px';
        container.style.height = viewport.height + 'px';
        
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');
        canvas.height = viewport.height;
        canvas.width = viewport.width;
        
        const overlay = document.createElement('div');
        overlay.className = 'annotation-overlay';
        overlay.id = `overlay-${i}`;
        overlay.onclick = (e) => handleOverlayClick(e, i);
        
        container.appendChild(canvas);
        container.appendChild(overlay);
        viewer.appendChild(container);
        
        await page.render({ canvasContext: context, viewport }).promise;
    }
}

function handleOverlayClick(e, pageNum) {
    const rect = e.target.getBoundingClientRect();
    const x = ((e.clientX - rect.left) / rect.width) * 100;
    const y = ((e.clientY - rect.top) / rect.height) * 100;
    
    showPinForm(x, y, pageNum, e.clientX, e.clientY);
}

function showPinForm(x, y, pageNum, screenX, screenY) {
    const form = document.getElementById('pinForm');
    form.style.display = 'block';
    
    // Adjust position to keep within screen
    let posX = screenX + 10;
    let posY = screenY + 10;
    if (posX + 300 > window.innerWidth) posX = screenX - 310;
    if (posY + 200 > window.innerHeight) posY = screenY - 210;
    
    form.style.left = posX + 'px';
    form.style.top = posY + 'px';
    
    document.getElementById('tempX').value = x;
    document.getElementById('tempY').value = y;
    document.getElementById('tempPage').value = pageNum;
    document.getElementById('pinCoords').innerText = `Pg ${pageNum} [${Math.round(x)}%, ${Math.round(y)}%]`;
    document.getElementById('pinContent').focus();
}

function hidePinForm() {
    document.getElementById('pinForm').style.display = 'none';
    document.getElementById('pinContent').value = '';
}

async function saveAnnotation() {
    const content = document.getElementById('pinContent').value;
    if (!content.trim()) return alert('Please enter remark content');
    
    const formData = new FormData();
    formData.append('action', 'save');
    formData.append('queue_number', queueNumber);
    formData.append('document_type', documentType);
    formData.append('page_number', document.getElementById('tempPage').value);
    formData.append('x_position', document.getElementById('tempX').value);
    formData.append('y_position', document.getElementById('tempY').value);
    formData.append('content', content);
    formData.append('committee_id', committeeId || '');

    try {
        const response = await fetch('ajax-annotations.php', { method: 'POST', body: formData });
        const result = await response.json();
        
        if (result.success) {
            hidePinForm();
            loadAnnotations();
        } else {
            alert(result.message);
        }
    } catch (e) {
        alert('Network error while saving annotation');
    }
}

async function loadAnnotations() {
    try {
        const response = await fetch(`ajax-annotations.php?action=load&queue_number=${queueNumber}&document_type=${documentType}`);
        const result = await response.json();
        
        if (result.success) {
            currentAnnotations = result.annotations;
            renderAnnotations();
        }
    } catch (e) { console.error('Failed to load annotations', e); }
}

function renderAnnotations() {
    // Clear existing pins
    document.querySelectorAll('.pin').forEach(p => p.remove());
    const list = document.getElementById('annotationList');
    list.innerHTML = '';
    
    console.log('Rendering annotations:', currentAnnotations.length, 'annotations found');
    
    if (currentAnnotations.length === 0) {
        list.innerHTML = '<div class="text-center py-5 text-muted small">No annotations yet.</div>';
        return;
    }

    currentAnnotations.forEach((ann, index) => {
        console.log(`Rendering annotation ${index + 1}:`, ann);
        
        // Place Pin on Overlay
        const overlay = document.getElementById(`overlay-${ann.page_number}`);
        console.log(`Looking for overlay-${ann.page_number}:`, overlay);
        
        if (overlay) {
            const pin = document.createElement('div');
            pin.className = 'pin';
            pin.style.left = ann.x_position + '%';
            pin.style.top = ann.y_position + '%';
            pin.style.zIndex = '10'; // Ensure pin is above overlay
            pin.innerHTML = `<span class="pin-number">${index + 1}</span>`;
            pin.onclick = (e) => {
                e.stopPropagation();
                showAnnotationDetail(ann);
            };
            overlay.appendChild(pin);
            console.log(`Pin ${index + 1} added to overlay ${ann.page_number}`);
        } else {
            console.warn(`Overlay ${ann.page_number} not found for annotation ${index + 1}`);
        }
        
        // Add to Sidebar List
        const card = document.createElement('div');
        card.className = 'ann-card shadow-sm';
        card.innerHTML = `
            <div class="d-flex justify-content-between mb-1">
                <span class="badge bg-navy text-white">#${index + 1} - Pg ${ann.page_number}</span>
                <button class="btn btn-sm p-0 border-0" onclick="deleteAnn(${ann.annotation_id}, event)">
                    <i class="bi bi-trash text-danger"></i>
                </button>
            </div>
            <div>${ann.content}</div>
            <div class="meta">By ${ann.author_name} · ${ann.created_at}</div>
        `;
        card.onclick = () => scrollToPage(ann.page_number);
        list.appendChild(card);
    });
    
    console.log('Annotation rendering completed');
}

function scrollToPage(pageNum) {
    const el = document.getElementById(`page-container-${pageNum}`);
    if (el) el.scrollIntoView({ behavior: 'smooth' });
}

async function deleteAnn(id, event) {
    event.stopPropagation();
    if (!confirm('Delete this annotation?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('annotation_id', id);
    
    const response = await fetch('ajax-annotations.php', { method: 'POST', body: formData });
    const result = await response.json();
    if (result.success) loadAnnotations();
}

function showAnnotationDetail(ann) {
    // Scroll to the annotation in list? 
    // For now just scroll viewer
    scrollToPage(ann.page_number);
}

// Security: Prevent screenshots and saving, but allow right-click
document.addEventListener('DOMContentLoaded', function() {
    // Allow right-click but prevent save-related actions
    document.addEventListener('contextmenu', function(e) {
        // Allow context menu but block specific save actions
        // This allows inspection but prevents saving
        return true;
    });

    // Prevent keyboard shortcuts for screenshots and saving
    document.addEventListener('keydown', function(e) {
        // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+Shift+C, Ctrl+U, Ctrl+S, Ctrl+P
        if (e.keyCode === 123 || // F12
            (e.ctrlKey && e.shiftKey && (e.keyCode === 73 || e.keyCode === 74)) || // Ctrl+Shift+I/J
            (e.ctrlKey && e.keyCode === 85) || // Ctrl+U
            (e.ctrlKey && e.keyCode === 83) || // Ctrl+S
            (e.ctrlKey && e.keyCode === 80) || // Ctrl+P
            (e.ctrlKey && e.shiftKey && e.keyCode === 67)) { // Ctrl+Shift+C
            e.preventDefault();
            e.stopPropagation();
            return false;
        }
    });

    // Prevent drag and drop
    document.addEventListener('dragstart', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent copy events
    document.addEventListener('copy', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent cut events
    document.addEventListener('cut', function(e) {
        e.preventDefault();
        return false;
    });

    // Prevent paste events
    document.addEventListener('paste', function(e) {
        e.preventDefault();
        return false;
    });

    });
</script>

</body>
</html>
