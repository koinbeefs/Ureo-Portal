<?php
declare(strict_types=1);
/**
 * View Research Proposal Annotations
 * TAU-UREO Portal - Applicant View
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is an applicant
requireLogin();
checkSessionTimeout();

if ($_SESSION['user_type'] !== 'applicant') {
    header("Location: ../index.php");
    exit();
}

$queue_number = $_GET['queue'] ?? '';
if (empty($queue_number)) {
    header("Location: dashboard.php");
    exit();
}

$conn = getDBConnection();

// Verify this application belongs to the current user
$stmt = $conn->prepare("SELECT * FROM applications WHERE queue_number = ? AND applicant_email = ?");
$stmt->bind_param("ss", $queue_number, $_SESSION['email']);
$stmt->execute();
$application = $stmt->get_result()->get_assoc();

if (!$application) {
    die("Application not found or access denied.");
}

// Check if there's a research proposal document
$doc_stmt = $conn->prepare("SELECT * FROM documents WHERE queue_number = ? AND document_name LIKE '%research%' AND document_type = 'pdf'");
$doc_stmt->bind_param("s", $queue_number);
$doc_stmt->execute();
$document = $doc_stmt->get_result()->get_assoc();

if (!$document) {
    die("Research proposal document not found.");
}

$file_path = '../uploads/' . $document['file_path'];

if (!file_exists($file_path)) {
    die("Research proposal file not found.");
}

closeDBConnection($conn);

$page_title = 'Research Proposal Annotations';
$active_menu = 'applications';
require_once __DIR__ . '/../includes/applicant_header.php';
?>

<style>
    :root {
        --navy: #0f2942;
        --gold: #c9993a;
        --cream: #faf8f3;
        --shadow: 0 4px 24px rgba(15,41,66,0.12);
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
        color: white;
        padding: 15px 30px;
        display: flex;
        align-items: center;
        justify-content: space-between;
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
        grid-template-columns: 1fr 340px;
        height: calc(100vh - 60px);
    }

    /* PDF Viewer */
    .pdf-viewer {
        background: #34495e;
        overflow-y: auto;
        padding: 20px;
        position: relative;
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
        pointer-events: none;
        z-index: 5;
    }

    /* Annotation Panel */
    .annotation-panel {
        background: #fff;
        color: var(--navy);
        overflow-y: auto;
        border-left: 1px solid #ddd;
    }

    .panel-header {
        background: var(--navy);
        color: white;
        padding: 15px 20px;
        border-bottom: 2px solid var(--gold);
        position: sticky;
        top: 0;
        z-index: 10;
    }

    .panel-content {
        padding: 20px;
    }

    /* Pin Styles (View Only) */
    .pin {
        position: absolute;
        width: 24px;
        height: 24px;
        background: #e74c3c;
        border: 2px solid #fff;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        cursor: pointer;
        transition: all 0.2s;
        box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        pointer-events: auto;
        z-index: 10;
    }

    .pin:hover { transform: translate(-50%, -50%) scale(1.2); background: #c0392b; }
    
    .pin-number {
        transform: rotate(45deg);
        display: block;
        text-align: center;
        color: #fff;
        font-weight: bold;
        font-size: 10px;
        line-height: 20px;
    }

    /* Annotation Card */
    .ann-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        cursor: pointer;
        transition: all 0.2s;
    }

    .ann-card:hover {
        box-shadow: var(--shadow);
        transform: translateY(-2px);
    }

    .ann-card .badge {
        font-size: 0.75rem;
        padding: 4px 8px;
    }

    .ann-card .meta {
        font-size: 0.8rem;
        color: #666;
        margin-top: 8px;
    }

    .ann-card .content {
        margin: 10px 0;
        line-height: 1.5;
    }

    /* Loading */
    .loader {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        text-align: center;
        color: white;
        z-index: 1000;
    }

    .spinner {
        border: 3px solid rgba(255,255,255,0.3);
        border-top: 3px solid var(--gold);
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin: 0 auto 15px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* No annotations message */
    .no-annotations {
        text-align: center;
        padding: 40px 20px;
        color: #666;
    }

    .no-annotations i {
        font-size: 3rem;
        color: #ddd;
        margin-bottom: 15px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .main-container {
            grid-template-columns: 1fr;
        }
        
        .annotation-panel {
            display: none;
        }
    }
</style>

<div class="tool-header">
    <a href="dashboard.php" class="brand">
        <i class="bi bi-mortarboard me-2"></i>TAU-UREO Portal
    </a>
    <div>
        <span class="me-3">Research Proposal Annotations</span>
        <small class="text-muted">Queue: <?php echo htmlspecialchars($queue_number); ?></small>
    </div>
</div>

<div class="main-container">
    <!-- PDF Viewer -->
    <div class="pdf-viewer" id="pdfViewer">
        <div class="loader" id="loader">
            <div class="spinner"></div>
            <div>Loading Research Proposal...</div>
        </div>
    </div>

    <!-- Annotation Panel -->
    <div class="annotation-panel">
        <div class="panel-header">
            <h5 class="mb-0"><i class="bi bi-chat-square-text me-2"></i>UREC Review Comments</h5>
        </div>
        <div class="panel-content" id="annotationList">
            <div class="loader">
                <div class="spinner" style="width: 20px; height: 20px; border-width: 2px;"></div>
                <div>Loading annotations...</div>
            </div>
        </div>
    </div>
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
        
        container.appendChild(canvas);
        container.appendChild(overlay);
        viewer.appendChild(container);
        
        await page.render({ canvasContext: context, viewport }).promise;
    }
}

async function loadAnnotations() {
    try {
        const response = await fetch(`../urec/ajax-annotations.php?action=load&queue_number=${queueNumber}&document_type=${documentType}`);
        const result = await response.json();
        
        if (result.success) {
            currentAnnotations = result.annotations;
            renderAnnotations();
        }
    } catch (e) { console.error('Failed to load annotations', e); }
}

function renderAnnotations() {
    const list = document.getElementById('annotationList');
    list.innerHTML = '';
    
    console.log('Rendering annotations:', currentAnnotations.length, 'annotations found');
    
    if (currentAnnotations.length === 0) {
        list.innerHTML = `
            <div class="no-annotations">
                <i class="bi bi-chat-square-text"></i>
                <h6>No Review Comments</h6>
                <p class="small text-muted">The UREC committee has not added any review comments yet.</p>
            </div>
        `;
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
            pin.style.zIndex = '10';
            pin.innerHTML = `<span class="pin-number">${index + 1}</span>`;
            pin.title = `Click to view comment #${index + 1}`;
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
                <span class="badge bg-danger text-white">#${index + 1} - Pg ${ann.page_number}</span>
            </div>
            <div class="content">${ann.content}</div>
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

function showAnnotationDetail(ann) {
    // Scroll to the annotation page
    scrollToPage(ann.page_number);
    
    // Briefly highlight the pin
    const overlay = document.getElementById(`overlay-${ann.page_number}`);
    if (overlay) {
        const pins = overlay.querySelectorAll('.pin');
        pins.forEach(pin => {
            if (pin.textContent.includes(ann.annotation_id || '')) {
                pin.style.transform = 'translate(-50%, -50%) scale(1.5)';
                setTimeout(() => {
                    pin.style.transform = 'translate(-50%, -50%) scale(1)';
                }, 500);
            }
        });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/applicant_footer.php'; ?>
