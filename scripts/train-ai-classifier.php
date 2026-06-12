#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * CLI Training Script for Research Category Classifier
 * UREO Portal AI/ML Integration
 * 
 * Usage:
 *   php scripts/train-ai-classifier.php [--retrain] [--stats]
 * 
 * Options:
 *   --retrain    Force retraining even if model exists
 *   --stats      Show training statistics only
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('memory_limit', '1024M');
set_time_limit(600);

define('APP_ROOT', dirname(__DIR__));
require_once APP_ROOT . '/vendor/autoload.php';
require_once APP_ROOT . '/applicant/automation/ResearchCategoryClassifier.php';

$args = $argv;
array_shift($args);
$retrain = in_array('--retrain', $args);
$statsOnly = in_array('--stats', $args);

echo "=== UREO Portal AI Classifier Training ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $classifier = new ResearchCategoryClassifier();
    
    if ($statsOnly) {
        $stats = $classifier->getTrainingStats();
        echo "Training Statistics:\n";
        echo "  Model exists: " . ($stats['model_exists'] ? 'Yes' : 'No') . "\n";
        echo "  History entries: " . $stats['history_entries'] . "\n";
        echo "  Agreed: " . ($stats['agreed_count'] ?? 0) . "\n";
        echo "  Corrected: " . ($stats['corrected_count'] ?? 0) . "\n";
        if ($stats['metadata']) {
            echo "  Model version: " . ($stats['metadata']['version'] ?? 'N/A') . "\n";
            echo "  Accuracy: " . (($stats['metadata']['accuracy'] ?? 0) * 100) . "%\n";
            echo "  Training samples: " . ($stats['metadata']['dataset_size'] ?? 0) . "\n";
        }
        exit(0);
    }
    
    if ($retrain) {
        echo "Force retraining model...\n\n";
        $result = $classifier->retrain();
        echo $result['output'];
    } else {
        echo "Model training complete (loaded existing model).\n";
        $metadata = $classifier->getMetadata();
        if ($metadata) {
            echo "  Version: " . $metadata['version'] . "\n";
            echo "  Accuracy: " . ($metadata['accuracy'] * 100) . "%\n";
            echo "  Samples: " . $metadata['dataset_size'] . "\n";
        }
    }
    
    echo "\nDone.\n";
    
} catch (Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}