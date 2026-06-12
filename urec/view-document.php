<?php
declare(strict_types=1);
/**
 * Document Viewer - UREC version
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Simple auth check
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'urec') {
    die('Unauthorized access');
}

$conn = getDBConnection();
$doc_id = $_GET['id'] ?? '';
$is_system = isset($_GET['type']) && $_GET['type'] === 'system';

$path = '';
$filename = '';

if ($is_system) {
    $stmt = $conn->prepare("SELECT file_path, document_name FROM system_documents WHERE system_doc_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $path = $result['file_path'];
        $filename = $result['document_name'];
    }
} else {
    $stmt = $conn->prepare("SELECT file_path, document_name FROM documents WHERE document_id = ?");
    $stmt->bind_param("i", $doc_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $path = $result['file_path'];
        $filename = $result['document_name'];
    }
}

closeDBConnection($conn);

if (empty($path)) {
    die('Document not found');
}

$fullPath = '../' . $path;

if (!file_exists($fullPath)) {
    die('File not found on server');
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

// Use the same PDF viewer logic as staff/view-document.php
if ($extension === 'pdf') {
    // Build a web-accessible URL for the PDF file (relative path from this script)
    $pdfUrl = htmlspecialchars('../' . $path, ENT_QUOTES, 'UTF-8');
    // Some PDF viewers respect these fragment params (Adobe/older viewers). Modern browsers may ignore.
    $fragment = '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';

    echo "<!doctype html><html><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">";
    echo "<title>" . htmlspecialchars(basename($fullPath)) . "</title>";
    // Emit the HTML/JS and enable Ctrl+wheel zoom (no fixed-size canvases)
    $safePdfUrl = json_encode($pdfUrl);
    echo <<<HTML
        <style>
            html,body{height:100%;margin:0;overflow:hidden;background:#222} 
            #pdfContainer{width:100%;height:100%;overflow:auto;box-sizing:border-box;padding:18px;background:#333} 
            .pdf-canvas{display:block;margin:0 auto 18px;box-shadow:0 6px 18px rgba(0,0,0,0.6);background:white}
            
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
            body, #pdfContainer, canvas {
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
        </style>
        </head><body>
        <div id="pdfContainer">Loading document...</div>

        <!-- PDF.js from CDN -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js"></script>
        <script>
        (function(){
            var url = {$safePdfUrl};
            function blockPrint(e){
                var key = e.key || e.keyCode;
                if ((e.ctrlKey || e.metaKey) && (key === 'p' || key === 'P' || key === 80 || key === 112)){
                    e.preventDefault();
                    e.stopPropagation();
                    try{ alert('This document is protected and cannot be printed.'); }catch(err){}
                    return false;
                }
            }
            document.addEventListener('keydown', blockPrint, true);
            try{ window.print = function(){}; window.onbeforeprint = function(){ return false; }; }catch(e){}

            var pdfjsLib = window.pdfjsLib || window['pdfjs-dist/build/pdf'];
            if (!pdfjsLib) { document.getElementById('pdfContainer').innerText = 'PDF viewer unavailable.'; return; }
            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';

            var container = document.getElementById('pdfContainer');
            container.innerHTML = '';

            var pdfDoc = null;
            var currentScale = 1.2;

            function renderPage(pageNum){
                return pdfDoc.getPage(pageNum).then(function(page){
                    var viewport = page.getViewport({scale: currentScale});
                    var canvas = document.getElementById('pdf-canvas-'+pageNum);
                    if (!canvas){
                        canvas = document.createElement('canvas');
                        canvas.id = 'pdf-canvas-'+pageNum;
                        canvas.className = 'pdf-canvas';
                        container.appendChild(canvas);
                    }
                    var context = canvas.getContext('2d');
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    var renderContext = {
                        canvasContext: context,
                        viewport: viewport
                    };
                    return page.render(renderContext).promise;
                });
            }

            function renderAllPages(){
                var promises = [];
                for(var i = 1; i <= pdfDoc.numPages; i++){
                    promises.push(renderPage(i));
                }
                return Promise.all(promises);
            }

            pdfjsLib.getDocument(url).promise.then(function(pdf){
                pdfDoc = pdf;
                return renderAllPages();
            }).then(function(){
                console.log('PDF rendered successfully');
            }).catch(function(error){
                console.error('Error loading PDF:', error);
                container.innerText = 'Failed to load PDF document.';
            });

            // Optional: Add zoom with Ctrl+wheel
            document.addEventListener('wheel', function(e){
                if(e.ctrlKey){
                    e.preventDefault();
                    var delta = e.deltaY < 0 ? 0.1 : -0.1;
                    currentScale = Math.max(0.5, Math.min(3, currentScale + delta));
                    if(pdfDoc){
                        container.innerHTML = '';
                        renderAllPages();
                    }
                }
            }, {passive: false});

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

                // Detect and block developer tools
                setInterval(function() {
                    if (window.outerHeight - window.innerHeight > 200 || 
                        window.outerWidth - window.innerWidth > 200) {
                        document.body.innerHTML = '<div style="text-align:center; margin-top:100px; color:red; font-size:20px;">Developer tools are not allowed on this page.</div>';
                    }
                }, 1000);
            });
        })();
        </script>
        </body></html>
HTML;
    exit();
} else {
    // Other files
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'doc' => 'application/msword'
    ];
    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    readfile($fullPath);
    exit();
}
