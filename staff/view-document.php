<?php
/**
 * Document Viewer - Staff version (serves documents for inline viewing)
 */

require_once '../config/config.php';
require_once '../includes/functions.php';

requireLogin();

if (!isset($_GET['path']) || empty($_GET['path'])) {
    die('No document path provided');
}

$path = $_GET['path'];
$fullPath = '../' . $path;

// Check if file exists
if (!file_exists($fullPath)) {
    die('Document not found');
}

$extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

if ($extension === 'docx' || $extension === 'doc') {
    // Serve DOCX directly with proper headers - browser will handle it
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($fullPath));
    
    // Output file
    readfile($fullPath);
    exit();
    
} else {
    // For PDFs and other files, serve directly
    $mimeTypes = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif'
    ];

    // If PDF, render an HTML wrapper that embeds the PDF with viewer fragment parameters
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
        <style>html,body{height:100%;margin:0;overflow:hidden;background:#222} #pdfContainer{width:100%;height:100%;overflow:auto;box-sizing:border-box;padding:18px;background:#333} .pdf-canvas{display:block;margin:0 auto 18px;box-shadow:0 6px 18px rgba(0,0,0,0.6);background:white}</style>
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
                    canvas.width = Math.floor(viewport.width);
                    canvas.height = Math.floor(viewport.height);
                    var ctx = canvas.getContext('2d');
                    return page.render({canvasContext: ctx, viewport: viewport}).promise;
                });
            }

            function renderAllPages(){
                if (!pdfDoc) return Promise.resolve();
                if (!container.querySelector('.pdf-canvas')) container.innerHTML = '';
                var promises = [];
                for (var i = 1; i <= pdfDoc.numPages; i++){
                    promises.push(renderPage(i));
                }
                return Promise.all(promises);
            }

            fetch(url).then(function(response){ return response.arrayBuffer(); }).then(function(data){
                return pdfjsLib.getDocument({data: data}).promise;
            }).then(function(pdf){
                pdfDoc = pdf;
                return renderAllPages();
            }).catch(function(err){
                container.innerHTML = '<div style="color:#fff;padding:20px;">Error loading PDF: '+(err.message||err)+'</div>';
            });

            // Zoom with Ctrl/Cmd + mouse wheel
            container.addEventListener('wheel', function(e){
                if (e.ctrlKey || e.metaKey){
                    e.preventDefault();
                    var delta = e.deltaY;
                    if (delta < 0) currentScale *= 1.1; else currentScale /= 1.1;
                    currentScale = Math.max(0.2, Math.min(4, currentScale));
                    renderAllPages();
                }
            }, {passive: false});

        })();
        </script>
        </body></html>
        HTML;
        exit();
    }

    $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $mimeType);
    header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
    header('Content-Transfer-Encoding: binary');
    header('Accept-Ranges: bytes');
    header('Content-Length: ' . filesize($fullPath));

    // Output file
    readfile($fullPath);
    exit();
}
?>