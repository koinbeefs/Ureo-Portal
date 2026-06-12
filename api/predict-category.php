<?php

declare(strict_types=1);

/**
 * API Endpoint: Research Category Prediction
 * UREO Portal AI/ML Integration
 * 
 * Usage:
 *   POST /api/predict-category.php
 *   {
 *     "title": "Research title",
 *     "section_c_text": "Full text from Section C",
 *     "original_types": ["Human Use"] (optional)
 *   }
 * 
 * Returns:
 *   {
 *     "status": "success",
 *     "prediction": "Human Use",
 *     "confidence": "high",
 *     "score": 0.85,
 *     "scores": { "Human Use": 0.85, "Animal Welfare": 0.12, ... },
 *     "reason": "AI analysis text...",
 *     "indicators": [...],
 *     "learning_stats": { ... },
 *     "similar_past_cases": [...]
 *   }
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../applicant/automation/ResearchCategoryClassifier.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$title = $input['title'] ?? '';
$sectionCText = $input['section_c_text'] ?? '';
$originalTypes = $input['original_types'] ?? [];

// Validation
if (empty($sectionCText) && empty($title)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Either title or section_c_text is required']);
    exit;
}

// Use title if no section_c_text provided
$textToClassify = $sectionCText ?: $title;

try {
    // Get singleton instance for efficiency
    $classifier = ResearchCategoryClassifier::getInstance();
    
    // Perform classification
    $result = $classifier->classify($textToClassify, $originalTypes);
    
    // Get metadata
    $metadata = $classifier->getMetadata();
    
    echo json_encode([
        'status' => 'success',
        'prediction' => $result['predicted'],
        'confidence' => $result['confidence'],
        'score' => round($result['max_score'], 4),
        'scores' => array_map(fn($s) => round($s, 4), $result['scores']),
        'reason' => $result['reason'],
        'indicators' => $result['indicators'],
        'learning_stats' => $result['learning_stats'],
        'similar_past_cases' => $result['similar_past_cases'],
        'model_info' => [
            'version' => $metadata['version'] ?? 'unknown',
            'accuracy' => $metadata['accuracy'] ?? 0,
            'trained_at' => $metadata['trained_at'] ?? 'unknown',
            'dataset_size' => $metadata['dataset_size'] ?? 0
        ]
    ]);
    
} catch (Throwable $e) {
    error_log('[API predict-category] Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Classification failed: ' . $e->getMessage()
    ]);
}