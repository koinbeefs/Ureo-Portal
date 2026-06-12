<?php
/**
 * DOCX Document Viewer - Converts DOCX files to HTML for inline viewing
 */

require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../vendor/autoload.php';

use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Settings;

requireApplicantLogin();

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

if ($extension !== 'docx' && $extension !== 'doc') {
    die('This endpoint only handles DOCX and DOC files');
}

try {
    // Load the DOCX file
    $phpWord = IOFactory::load($fullPath);

    // Convert to HTML
    $htmlWriter = IOFactory::createWriter($phpWord, 'HTML');
    ob_start();
    $htmlWriter->save('php://output');
    $htmlContent = ob_get_clean();

    // Clean up and format the HTML
    $htmlContent = str_replace('<!DOCTYPE html>', '', $htmlContent);
    $htmlContent = str_replace('<html>', '', $htmlContent);
    $htmlContent = str_replace('</html>', '', $htmlContent);
    $htmlContent = str_replace('<head>', '', $htmlContent);
    $htmlContent = str_replace('</head>', '', $htmlContent);
    $htmlContent = str_replace('<body>', '', $htmlContent);
    $htmlContent = str_replace('</body>', '', $htmlContent);

    // Remove meta tags and title
    $htmlContent = preg_replace('/<meta[^>]*>/', '', $htmlContent);
    $htmlContent = preg_replace('/<title[^>]*>.*?<\/title>/', '', $htmlContent);

    // Create a clean HTML document
    $cleanHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars(basename($fullPath)) . '</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            padding: 20px;
            max-width: none;
            margin: 0 auto;
            background: white;
        }
        h1, h2, h3, h4, h5, h6 {
            color: #2c5530;
            margin-top: 1.5em;
            margin-bottom: 0.5em;
        }
        p {
            margin: 0.5em 0;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 1em 0;
            border: 1px solid #ddd;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
        }
        ul, ol {
            margin: 1em 0;
            padding-left: 2em;
        }
        li {
            margin: 0.25em 0;
        }
        .page-break {
            page-break-before: always;
        }
        img {
            max-width: 100%;
            height: auto;
        }
        .center {
            text-align: center;
        }
        .right {
            text-align: right;
        }
    </style>
</head>
<body>
    ' . $htmlContent . '
</body>
</html>';

    // Output the HTML
    header('Content-Type: text/html; charset=utf-8');
    echo $cleanHtml;

} catch (Exception $e) {
    // If conversion fails, show error page
    $errorHtml = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Error</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background: #f8f9fa;
        }
        .error-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        h1 {
            color: #dc3545;
            margin-bottom: 20px;
        }
        p {
            color: #6c757d;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1><i class="bi bi-exclamation-triangle"></i> Document Error</h1>
        <p>Unable to load the document. The file may be corrupted or in an unsupported format.</p>
        <p><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>
    </div>
</body>
</html>';

    header('Content-Type: text/html; charset=utf-8');
    echo $errorHtml;
}
?>