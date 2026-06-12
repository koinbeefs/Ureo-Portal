<?php

declare(strict_types=1);

/**
 * Research Category Classifier using PHP-ML
 * 
 * Trainable AI/ML classifier for categorizing research applications
 * based on Section C text content. Uses TF-IDF vectorization and
 * Naive Bayes classification with proper model persistence.
 * 
 * Learning Sources:
 * 1. Reference data (reference.json) - base training data
 * 2. Historical corrections (history.jsonl) - staff feedback
 * 3. Training CSV (training_data.csv) - additional labeled data
 * 
 * Categories:
 * - Human Use
 * - Animal Welfare
 * - Plant Use
 * - Microbiological/Biotechnological Use
 * - Engineering
 * - Information Technology Use
 * - Food Technology Use
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Phpml\Classification\NaiveBayes;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\ModelManager;
use Phpml\Pipeline;
use Phpml\Tokenization\WordTokenizer;
use Phpml\CrossValidation\StratifiedRandomSplit;
use Phpml\Metric\Accuracy;

class ResearchCategoryClassifier
{
    private const MODEL_DIR = __DIR__ . '/models';
    private const MODEL_FILE = 'research_category_naivebayes_latest.phpml';
    private const METADATA_FILE = 'research_category_naivebayes_latest.json';
    private const REFERENCE_FILE = __DIR__ . '/reference.json';
    private const HISTORY_FILE = __DIR__ . '/history.jsonl';
    private const TRAINING_DATA_FILE = __DIR__ . '/training_data.csv';
    
    // Auto-retrain settings
    private const AUTO_RETRAIN_THRESHOLD = 10; // Retrain after this many new entries
    private const MAX_HISTORY_ENTRIES = 500; // Keep only last N entries to save space
    private const LAST_RETRAIN_FILE = __DIR__ . '/.last_retrain_count';

    private const CATEGORIES = [
        'Human Use',
        'Animal Welfare',
        'Plant Use',
        'Microbiological/Biotechnological Use',
        'Engineering',
        'Information Technology Use',
        'Food Technology Use'
    ];

    private ?Pipeline $pipeline = null;
    private ?array $metadata = null;
    private array $referenceData = [];
    private array $wordIndicators = [];
    private static ?ResearchCategoryClassifier $instance = null;

    public function __construct()
    {
        $this->loadReferenceData();
        $this->initializeModel();
    }

    /**
     * Get singleton instance for efficient model reuse
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Load reference.json for keyword-based features and training data generation
     */
    private function loadReferenceData(): void
    {
        if (!file_exists(self::REFERENCE_FILE)) {
            throw new RuntimeException('Reference data file not found: ' . self::REFERENCE_FILE);
        }

        $rawData = json_decode(file_get_contents(self::REFERENCE_FILE), true);
        if (!$rawData) {
            throw new RuntimeException('Invalid reference data format');
        }

        $this->referenceData = $rawData;
    }

    /**
     * Initialize or load the ML model
     */
    private function initializeModel(): void
    {
        // Ensure model directory exists
        if (!is_dir(self::MODEL_DIR)) {
            mkdir(self::MODEL_DIR, 0755, true);
        }

        // Add .htaccess to protect model files
        $htaccessFile = self::MODEL_DIR . '/.htaccess';
        if (!file_exists($htaccessFile)) {
            file_put_contents($htaccessFile, "Deny from all\n");
        }

        $modelPath = self::MODEL_DIR . '/' . self::MODEL_FILE;
        $metadataPath = self::MODEL_DIR . '/' . self::METADATA_FILE;

        if (file_exists($modelPath) && file_exists($metadataPath)) {
            $this->loadModel($modelPath, $metadataPath);
        } else {
            $this->trainAndSaveModel();
        }
    }

    /**
     * Load a trained model from disk
     */
    private function loadModel(string $modelPath, string $metadataPath): void
    {
        try {
            $modelManager = new ModelManager();
            $this->pipeline = $modelManager->restoreFromFile($modelPath);
            $this->metadata = json_decode(file_get_contents($metadataPath), true);
            $this->wordIndicators = $this->metadata['indicators'] ?? [];
        } catch (Throwable $e) {
            error_log('[ResearchCategoryClassifier] Failed to load model: ' . $e->getMessage());
            $this->trainAndSaveModel();
        }
    }

    /**
     * Generate comprehensive training samples from all sources
     * This is the key method that combines reference data + history + CSV
     */
    private function generateTrainingSamples(): array
    {
        $samples = [];
        $labels = [];
        $sourceCounts = ['reference' => 0, 'history' => 0, 'csv' => 0];

        // 1. Generate samples from reference data (descriptions and keywords)
        foreach ($this->referenceData as $category => $data) {
            // Add the full description as a training sample
            if (!empty($data['short_desc'])) {
                $samples[] = $data['short_desc'];
                $labels[] = $category;
                $sourceCounts['reference']++;
            }

            // Add must_keywords as individual samples (they represent the category)
            foreach ($data['must_keywords'] as $keyword) {
                $samples[] = $keyword;
                $labels[] = $category;
                $sourceCounts['reference']++;
            }

            // Generate limited combination samples from keyword groups
            $mustKeywords = $data['must_keywords'] ?? [];
            if (count($mustKeywords) >= 2) {
                // Only generate a few 2-keyword combinations to avoid memory issues
                $maxCombinations = min(15, count($mustKeywords));
                for ($i = 0; $i < $maxCombinations; $i += 3) {
                    $j = ($i + 1) % count($mustKeywords);
                    $combination = $mustKeywords[$i] . ' ' . $mustKeywords[$j];
                    $samples[] = $combination;
                    $labels[] = $category;
                    $sourceCounts['reference']++;
                }
            }
        }

        // 2. Load historical corrections from history.jsonl (staff feedback)
        if (file_exists(self::HISTORY_FILE)) {
            $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry && !empty($entry['section_c_text']) && !empty($entry['human_label'])) {
                    // Use the human label (staff's correction) as the training target
                    $samples[] = $entry['section_c_text'];
                    $labels[] = $entry['human_label'];
                    $sourceCounts['history']++;

                    // Also add variations if the text is long enough
                    $text = $entry['section_c_text'];
                    if (str_word_count($text) > 20) {
                        // Split into sentences and add as additional samples
                        $sentences = preg_split('/(?<=[.!?])\s+/', $text);
                        foreach (array_slice($sentences, 0, 5) as $sentence) {
                            $sentence = trim($sentence);
                            if (str_word_count($sentence) > 5) {
                                $samples[] = $sentence;
                                $labels[] = $entry['human_label'];
                                $sourceCounts['history']++;
                            }
                        }
                    }
                }
            }
        }

        // 3. Load training data from CSV if exists
        if (file_exists(self::TRAINING_DATA_FILE)) {
            $handle = fopen(self::TRAINING_DATA_FILE, 'r');
            if ($handle !== false) {
                $header = fgetcsv($handle); // Skip header
                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) >= 2 && !empty($row[0]) && !empty($row[1])) {
                        $samples[] = $row[0];
                        $labels[] = $row[1];
                        $sourceCounts['csv']++;
                    }
                }
                fclose($handle);
            }
        }

        echo sprintf("Training data sources: reference=%d, history=%d, csv=%d\n", 
            $sourceCounts['reference'], $sourceCounts['history'], $sourceCounts['csv']);

        return ['samples' => $samples, 'labels' => $labels];
    }

    /**
     * Compute word indicators for explainability
     * Shows which words are most characteristic of each category
     */
    private function computeWordIndicators(array $samples, array $labels): array
    {
        $tokenizer = new WordTokenizer();
        $wordFreqs = [];
        $totalFreqs = [];

        // Initialize frequency counters
        foreach (self::CATEGORIES as $cat) {
            $wordFreqs[$cat] = [];
        }

        // Count word frequencies per category
        for ($i = 0; $i < count($samples); $i++) {
            $text = $samples[$i];
            $label = $labels[$i];

            if (!in_array($label, self::CATEGORIES, true)) {
                continue;
            }

            $tokens = $tokenizer->tokenize(mb_strtolower($text));
            foreach ($tokens as $token) {
                // Skip very short words
                if (mb_strlen($token) < 3) {
                    continue;
                }

                // Skip common stop words
                $stopWords = ['the', 'and', 'for', 'with', 'from', 'this', 'that', 'have', 'has', 'was', 'were', 'are', 'been', 'being', 'does', 'did', 'but', 'not', 'all', 'their', 'its', 'who', 'whom', 'which', 'where', 'when', 'what', 'how', 'been', 'were', 'will', 'would', 'shall', 'should', 'may', 'might', 'must', 'can', 'could'];
                if (in_array($token, $stopWords)) {
                    continue;
                }

                if (!isset($wordFreqs[$label][$token])) {
                    $wordFreqs[$label][$token] = 0;
                }
                $wordFreqs[$label][$token]++;

                if (!isset($totalFreqs[$token])) {
                    $totalFreqs[$token] = 0;
                }
                $totalFreqs[$token]++;
            }
        }

        // Score words by specificity: (freq_in_category / total_freq) * log(freq_in_category + 1)
        $indicators = [];
        foreach (self::CATEGORIES as $cat) {
            $scores = [];
            foreach ($wordFreqs[$cat] as $word => $freq) {
                $total = $totalFreqs[$word] ?? 1;
                $specificity = $freq / $total;
                $scores[$word] = $specificity * log($freq + 1);
            }

            // Sort by score descending and keep top 50
            arsort($scores);
            $indicators[$cat] = array_slice($scores, 0, 50, true);
        }

        return $indicators;
    }

    /**
     * Train and save the model with proper evaluation
     */
    public function trainAndSaveModel(): void
    {
        // Increase memory limit for training
        ini_set('memory_limit', '1024M');
        set_time_limit(600);
        
        echo "=== Research Category Classifier Training ===\n";
        echo "Generating training samples from all sources...\n";
        $trainingData = $this->generateTrainingSamples();
        $samples = $trainingData['samples'];
        $labels = $trainingData['labels'];

        echo sprintf("Total training samples: %d\n", count($samples));

        if (count($samples) < 10) {
            throw new RuntimeException('Insufficient training data. Need at least 10 samples.');
        }

        // Remove duplicate samples
        $uniqueData = $this->removeDuplicates($samples, $labels);
        $samples = $uniqueData['samples'];
        $labels = $uniqueData['labels'];
        echo sprintf("After deduplication: %d samples\n", count($samples));

        // Compute word indicators before splitting
        echo "Computing word indicators...\n";
        $this->wordIndicators = $this->computeWordIndicators($samples, $labels);

        // Check category distribution
        echo "Category distribution:\n";
        $categoryCounts = array_count_values($labels);
        foreach (self::CATEGORIES as $cat) {
            $count = $categoryCounts[$cat] ?? 0;
            echo "  - {$cat}: {$count} samples\n";
        }

        // Split for evaluation (80/20)
        echo "Splitting dataset for evaluation (80/20)...\n";
        
        // Create a simple dataset class for php-ml
        $dataset = new class($samples, $labels) implements \Phpml\Dataset\Dataset {
            private array $samples;
            private array $targets;

            public function __construct(array $samples, array $targets)
            {
                $this->samples = array_map(function($s) { return [$s]; }, $samples);
                $this->targets = $targets;
            }

            public function getSamples(): array { return $this->samples; }
            public function getTargets(): array { return $this->targets; }
            public function getSampleCount(): int { return count($this->samples); }
        };

        $split = new StratifiedRandomSplit($dataset, 0.2, 42);

        $trainSamples = array_map(fn($s) => (string)$s[0], $split->getTrainSamples());
        $trainLabels = $split->getTrainLabels();
        $testSamples = array_map(fn($s) => (string)$s[0], $split->getTestSamples());
        $testLabels = $split->getTestLabels();

        echo sprintf("Training set: %d samples, Test set: %d samples\n", 
            count($trainSamples), count($testSamples));

        // Build and train the pipeline
        echo "Training model pipeline (TF-IDF + NaiveBayes)...\n";
        $pipeline = new Pipeline([
            new TokenCountVectorizer(new WordTokenizer()),
            new TfIdfTransformer(),
        ], new NaiveBayes());

        $pipeline->train($trainSamples, $trainLabels);

        // Evaluate accuracy
        echo "Evaluating model accuracy...\n";
        $predictions = $pipeline->predict($testSamples);
        $accuracy = Accuracy::score($testLabels, $predictions);
        echo sprintf("Model accuracy on test set: %.2f%%\n", $accuracy * 100);

        // Show per-category accuracy
        $categoryCorrect = [];
        $categoryTotal = [];
        for ($i = 0; $i < count($testLabels); $i++) {
            $cat = $testLabels[$i];
            if (!isset($categoryTotal[$cat])) {
                $categoryTotal[$cat] = 0;
                $categoryCorrect[$cat] = 0;
            }
            $categoryTotal[$cat]++;
            if ($predictions[$i] === $cat) {
                $categoryCorrect[$cat]++;
            }
        }
        echo "Per-category accuracy:\n";
        foreach (self::CATEGORIES as $cat) {
            $correct = $categoryCorrect[$cat] ?? 0;
            $total = $categoryTotal[$cat] ?? 0;
            $catAccuracy = $total > 0 ? ($correct / $total * 100) : 0;
            echo "  - " . $cat . ": " . number_format($catAccuracy, 1) . "% (" . $correct . "/" . $total . ")\n";
        }

        // Retrain on full dataset for deployment
        echo "Retraining on full dataset for deployment...\n";
        $this->pipeline = new Pipeline([
            new TokenCountVectorizer(new WordTokenizer()),
            new TfIdfTransformer(),
        ], new NaiveBayes());
        $this->pipeline->train($samples, $labels);

        // Save model
        $version = date('Ymd_His');
        $modelPath = self::MODEL_DIR . '/research_category_naivebayes_' . $version . '.phpml';
        $metadataPath = self::MODEL_DIR . '/research_category_naivebayes_' . $version . '.json';

        $modelManager = new ModelManager();
        $modelManager->saveToFile($this->pipeline, $modelPath);
        $modelManager->saveToFile($this->pipeline, self::MODEL_DIR . '/' . self::MODEL_FILE);

        // Format indicators for JSON
        $formattedIndicators = [];
        foreach ($this->wordIndicators as $cat => $words) {
            foreach ($words as $word => $weight) {
                $formattedIndicators[$cat][] = [
                    'word' => $word,
                    'weight' => round($weight, 4)
                ];
            }
        }

        $this->metadata = [
            'trained_at' => date('Y-m-d H:i:s'),
            'dataset_size' => count($samples),
            'accuracy' => round($accuracy, 4),
            'algorithm' => 'NaiveBayes_Pipeline',
            'version' => $version,
            'categories' => self::CATEGORIES,
            'indicators' => $formattedIndicators,
            'category_distribution' => $categoryCounts
        ];

        $jsonContent = json_encode($this->metadata, JSON_PRETTY_PRINT);
        file_put_contents($metadataPath, $jsonContent);
        file_put_contents(self::MODEL_DIR . '/' . self::METADATA_FILE, $jsonContent);

        echo sprintf("\n=== Training Complete ===\n");
        echo sprintf("Model saved: %s\n", $modelPath);
        echo sprintf("Metadata saved: %s\n", $metadataPath);
        echo sprintf("Final accuracy: %.2f%%\n", $accuracy * 100);
    }

    /**
     * Remove duplicate samples while preserving labels
     */
    private function removeDuplicates(array $samples, array $labels): array
    {
        $seen = [];
        $uniqueSamples = [];
        $uniqueLabels = [];

        for ($i = 0; $i < count($samples); $i++) {
            $key = md5(strtolower(trim($samples[$i])) . '|' . $labels[$i]);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $uniqueSamples[] = $samples[$i];
                $uniqueLabels[] = $labels[$i];
            }
        }

        return ['samples' => $uniqueSamples, 'labels' => $uniqueLabels];
    }

    /**
     * Classify text and return detailed prediction results
     */
    public function classify(string $sectionCText, array $originalTypes = []): array
    {
        if (!$this->pipeline) {
            throw new RuntimeException('Model not trained or loaded');
        }

        // Perform prediction
        $predictions = $this->pipeline->predict([$sectionCText]);
        $predicted = (string)$predictions[0];

        // Calculate scores for all categories using keyword analysis + model confidence
        $scores = $this->calculateCategoryScores($sectionCText, $predicted);
        $maxScore = max($scores);

        // Adjust based on historical corrections
        $historicalAdjustment = $this->getHistoricalAdjustment($sectionCText, $predicted);
        if ($historicalAdjustment) {
            $predicted = $historicalAdjustment['category'];
            $maxScore = max($maxScore, $historicalAdjustment['confidence']);
        }

        // Determine confidence level
        $confidence = $this->calculateConfidence($maxScore);

        // Generate reasoning
        $reason = $this->generateReasoning($sectionCText, $predicted, $maxScore, $originalTypes);

        // Find similar past cases
        $similarCases = $this->findSimilarCases($sectionCText);

        return [
            'predicted' => $predicted,
            'max_score' => $maxScore,
            'scores' => $scores,
            'confidence' => $confidence,
            'reason' => $reason,
            'learning_stats' => [
                'confidence' => $confidence,
                'similar_cases_found' => count($similarCases),
                'model_version' => $this->metadata['version'] ?? 'unknown',
                'model_accuracy' => $this->metadata['accuracy'] ?? 0,
                'training_samples' => $this->metadata['dataset_size'] ?? 0
            ],
            'similar_past_cases' => $similarCases,
            'indicators' => $this->getMatchedIndicators($sectionCText)
        ];
    }

    /**
     * Calculate scores for all categories
     */
    private function calculateCategoryScores(string $text, string $predicted): array
    {
        $scores = [];
        $processed = $this->preprocessText($text);

        foreach (self::CATEGORIES as $category) {
            $score = 0;
            $categoryData = $this->referenceData[$category] ?? null;

            if (!$categoryData) {
                $scores[$category] = 0;
                continue;
            }

            $mustKeywords = $categoryData['must_keywords'] ?? [];
            $totalKeywords = count($mustKeywords);

            foreach ($mustKeywords as $keyword) {
                if (stripos($processed, $keyword) !== false) {
                    $score++;
                }
            }

            // Penalize for avoid keywords
            $avoidKeywords = $categoryData['avoid_keywords'] ?? [];
            foreach ($avoidKeywords as $avoidKeyword) {
                if (stripos($processed, $avoidKeyword) !== false) {
                    $score -= 0.3;
                }
            }

            // Normalize score
            $scores[$category] = max(0, min(1, $totalKeywords > 0 ? $score / $totalKeywords : 0));
        }

        // Boost the predicted category slightly
        if (isset($scores[$predicted])) {
            $scores[$predicted] = min(1, $scores[$predicted] + 0.1);
        }

        return $scores;
    }

    /**
     * Get historical adjustment based on similar past corrections
     */
    private function getHistoricalAdjustment(string $text, string $predicted): ?array
    {
        if (!file_exists(self::HISTORY_FILE)) {
            return null;
        }

        $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $bestMatch = null;
        $bestSimilarity = 0;

        // Check last 100 cases for efficiency
        foreach (array_slice($lines, -100) as $line) {
            $case = json_decode($line, true);
            if (!$case) continue;

            // Only look at corrections (cases where staff disagreed with AI)
            if ($case['agreed']) continue;

            $similarity = $this->calculateTextSimilarity($text, $case['section_c_text']);
            if ($similarity > 0.3 && $similarity > $bestSimilarity) {
                $bestMatch = $case;
                $bestSimilarity = $similarity;
            }
        }

        if ($bestMatch) {
            return [
                'category' => $bestMatch['human_label'],
                'confidence' => min(0.9, 0.5 + $bestSimilarity)
            ];
        }

        return null;
    }

    /**
     * Calculate confidence level from score
     */
    private function calculateConfidence(float $score): string
    {
        if ($score >= 0.7) return 'high';
        if ($score >= 0.5) return 'moderate';
        return 'low';
    }

    /**
     * Generate human-readable reasoning for the prediction
     */
    private function generateReasoning(string $text, string $predicted, float $score, array $originalTypes): string
    {
        $confidencePercent = round($score * 100);
        $keywordAnalysis = $this->analyzeKeywordsInText($text, $predicted);
        $matchedIndicators = $this->getMatchedIndicators($text);

        $reason = "### AI Classification Analysis\n\n";
        $reason .= "**Predicted Category:** {$predicted}\n";
        $reason .= "**Confidence Score:** {$confidencePercent}%\n\n";

        // Match strength description
        if ($score >= 0.7) {
            $reason .= "**Match Strength:** Strong alignment detected\n";
            $reason .= "**Analysis:** The content shows excellent correspondence with {$predicted} category requirements. ";
        } elseif ($score >= 0.5) {
            $reason .= "**Match Strength:** Moderate alignment detected\n";
            $reason .= "**Analysis:** The content demonstrates reasonable alignment with {$predicted} characteristics. ";
        } else {
            $reason .= "**Match Strength:** Weak alignment detected\n";
            $reason .= "**Analysis:** The content shows limited alignment with {$predicted} category themes. ";
        }

        // Add top matched indicators
        if (!empty($matchedIndicators)) {
            $topIndicators = array_slice($matchedIndicators, 0, 5);
            $indicatorWords = array_map(fn($i) => $i['word'], $topIndicators);
            $reason .= "Key matching terms identified: " . implode(', ', $indicatorWords) . ".\n";
        }

        // Add keyword analysis
        if (!empty($keywordAnalysis['found_keywords'])) {
            $reason .= "Matched category keywords: " . implode(', ', array_slice($keywordAnalysis['found_keywords'], 0, 5)) . ".\n";
        }

        $wordCount = str_word_count($text);
        $reason .= "\n**Content Overview:** {$wordCount} words analyzed.\n";

        // Add applicant's original selection if available
        if (!empty($originalTypes)) {
            $reason .= "\n**Applicant Selection:** " . implode(', ', $originalTypes);
            if (!in_array($predicted, $originalTypes)) {
                $reason .= " (AI suggests alternative classification)";
            } else {
                $reason .= " (Matches applicant's selection)";
            }
        }

        $reason .= "\n\n**Methodology:** Classification based on TF-IDF vectorization and Naive Bayes machine learning, enhanced by keyword matching and historical learning data.";

        return $reason;
    }

    /**
     * Analyze which keywords are present in the text
     */
    private function analyzeKeywordsInText(string $text, string $predictedCategory): array
    {
        $processed = $this->preprocessText($text);
        $foundKeywords = [];
        $avoidedKeywords = [];

        $categoryData = $this->referenceData[$predictedCategory] ?? [];
        $mustKeywords = $categoryData['must_keywords'] ?? [];
        $avoidKeywords = $categoryData['avoid_keywords'] ?? [];

        foreach ($mustKeywords as $keyword) {
            if (stripos($processed, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }

        foreach ($avoidKeywords as $avoidKeyword) {
            if (stripos($processed, $avoidKeyword) !== false) {
                $avoidedKeywords[] = $avoidKeyword;
            }
        }

        return [
            'found_keywords' => $foundKeywords,
            'avoided_keywords' => $avoidedKeywords
        ];
    }

    /**
     * Get matched word indicators for explainability
     */
    private function getMatchedIndicators(string $text): array
    {
        $tokenizer = new WordTokenizer();
        $tokens = $tokenizer->tokenize(mb_strtolower($text));
        $matched = [];

        foreach ($this->wordIndicators as $category => $indicators) {
            foreach ($indicators as $indicator) {
                $word = $indicator['word'];
                $weight = $indicator['weight'];
                foreach ($tokens as $token) {
                    if ($token === $word) {
                        $matched[] = [
                            'word' => $word,
                            'category' => $category,
                            'weight' => $weight
                        ];
                        break;
                    }
                }
            }
        }

        // Sort by weight descending
        usort($matched, fn($a, $b) => $b['weight'] <=> $a['weight']);

        return array_slice($matched, 0, 20);
    }

    /**
     * Find similar past cases from history
     */
    private function findSimilarCases(string $text): array
    {
        if (!file_exists(self::HISTORY_FILE)) {
            return [];
        }

        $similarCases = [];
        $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach (array_slice($lines, -50) as $line) {
            $case = json_decode($line, true);
            if (!$case) continue;

            $similarity = $this->calculateTextSimilarity($text, $case['section_c_text']);

            if ($similarity > 0.2) {
                $similarCases[] = [
                    'system_predicted' => $case['system_predicted'] ?? 'Unknown',
                    'label' => $case['human_label'] ?? 'Unknown',
                    'score' => round($similarity, 3),
                    'status' => $case['agreed'] ? 'Agreed' : 'Corrected',
                    'agreed' => $case['agreed']
                ];
            }
        }

        // Sort by similarity descending and return top 5
        usort($similarCases, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($similarCases, 0, 5);
    }

    /**
     * Calculate Jaccard similarity between two texts
     */
    private function calculateTextSimilarity(string $text1, string $text2): float
    {
        $words1 = array_unique(str_word_count(strtolower($text1), 1));
        $words2 = array_unique(str_word_count(strtolower($text2), 1));

        if (empty($words1) || empty($words2)) {
            return 0;
        }

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($union) > 0 ? count($intersection) / count($union) : 0;
    }

    /**
     * Preprocess text for analysis
     */
    private function preprocessText(string $text): string
    {
        $text = strtolower($text);
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Learn from a staff correction - adds to history for future training
     * Also checks if auto-retrain is needed and trims history for space saving
     */
    public function learnFromCorrection(string $sectionCText, string $systemPredicted, string $humanLabel, string $staffNote = ''): bool
    {
        // The correction is logged to history.jsonl by the calling code
        // This method handles auto-retrain and space saving
        
        $shouldRetrain = $this->checkAndPrepareAutoRetrain();
        
        if ($shouldRetrain) {
            error_log('[ResearchCategoryClassifier] Auto-retrain triggered after new correction');
            $this->trimHistoryForSpace();
            $this->trainAndSaveModel();
            $this->updateLastRetrainCount();
        }
        
        return true;
    }
    
    /**
     * Check if auto-retrain is needed based on new entries since last retrain
     */
    private function checkAndPrepareAutoRetrain(): bool
    {
        if (!file_exists(self::HISTORY_FILE)) {
            return false;
        }
        
        $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $currentCount = count($lines);
        $lastCount = (int) @file_get_contents(self::LAST_RETRAIN_FILE);
        
        $newEntries = $currentCount - $lastCount;
        
        return $newEntries >= self::AUTO_RETRAIN_THRESHOLD;
    }
    
    /**
     * Update the last retrain count marker
     */
    private function updateLastRetrainCount(): void
    {
        if (file_exists(self::HISTORY_FILE)) {
            $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            file_put_contents(self::LAST_RETRAIN_FILE, (string) count($lines));
        }
    }
    
    /**
     * Trim history file to keep only recent entries for space saving
     * Keeps the last MAX_HISTORY_ENTRIES entries
     */
    private function trimHistoryForSpace(): void
    {
        if (!file_exists(self::HISTORY_FILE)) {
            return;
        }
        
        $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if (count($lines) <= self::MAX_HISTORY_ENTRIES) {
            return; // No need to trim
        }
        
        // Keep only the last MAX_HISTORY_ENTRIES
        $trimmedLines = array_slice($lines, -self::MAX_HISTORY_ENTRIES);
        
        // Write back with exclusive lock
        $content = implode("\n", $trimmedLines) . "\n";
        file_put_contents(self::HISTORY_FILE, $content, LOCK_EX);
        
        error_log(sprintf('[ResearchCategoryClassifier] History trimmed from %d to %d entries', 
            count($lines), count($trimmedLines)));
    }
    
    /**
     * Initialize the last retrain count if not exists
     */
    public function initializeRetrainMarker(): void
    {
        if (!file_exists(self::LAST_RETRAIN_FILE) && file_exists(self::HISTORY_FILE)) {
            $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            file_put_contents(self::LAST_RETRAIN_FILE, (string) count($lines));
        }
    }

    /**
     * Get model metadata
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Get all categories
     */
    public static function getCategories(): array
    {
        return self::CATEGORIES;
    }

    /**
     * Retrain the model (can be called from CLI or admin interface)
     */
    public function retrain(): array
    {
        // Reset singleton to force reload
        self::$instance = null;
        
        ob_start();
        $this->trainAndSaveModel();
        $output = ob_get_clean();

        return [
            'success' => true,
            'output' => $output,
            'metadata' => $this->metadata
        ];
    }

    /**
     * Get training statistics
     */
    public function getTrainingStats(): array
    {
        $stats = [
            'model_exists' => file_exists(self::MODEL_DIR . '/' . self::MODEL_FILE),
            'history_entries' => 0,
            'metadata' => $this->metadata
        ];

        if (file_exists(self::HISTORY_FILE)) {
            $lines = file(self::HISTORY_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $stats['history_entries'] = count($lines);
            
            // Count agreed vs corrected
            $agreed = 0;
            $corrected = 0;
            foreach ($lines as $line) {
                $entry = json_decode($line, true);
                if ($entry) {
                    if ($entry['agreed']) {
                        $agreed++;
                    } else {
                        $corrected++;
                    }
                }
            }
            $stats['agreed_count'] = $agreed;
            $stats['corrected_count'] = $corrected;
        }

        return $stats;
    }
}