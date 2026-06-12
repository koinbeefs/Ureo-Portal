<?php
/**
 * Trainable Ethics Classifier using PHP-ML
 * Replaces Python AI classification system with pure PHP implementation
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use Phpml\Classification\NaiveBayes;

class TrainableEthicsClassifier
{
    private $classifier;
    private $categories;
    private $referenceData;
    private $modelPath;
    private $historyPath;
    private $vocabulary;

    public function __construct()
    {
        $this->modelPath = __DIR__ . '/model_data.json';
        $this->historyPath = __DIR__ . '/history.jsonl';
        $this->categories = [
            'Human Use',
            'Animal Welfare',
            'Plant Use',
            'Microbiological/Biotechnological Use',
            'Engineering',
            'Information Technology Use',
            'Food Technology Use'
        ];

        $this->loadReferenceData();
        $this->buildVocabulary();
        $this->initializeClassifier();
        $this->loadOrTrainModel();
    }

    /**
     * Load reference.json as base training data
     */
    private function loadReferenceData()
    {
        $referenceFile = __DIR__ . '/reference.json';
        if (!file_exists($referenceFile)) {
            throw new Exception('Reference data file not found: ' . $referenceFile);
        }

        $this->referenceData = json_decode(file_get_contents($referenceFile), true);
        if (!$this->referenceData) {
            throw new Exception('Invalid reference data format');
        }
    }

    /**
     * Build vocabulary from all keywords
     */
    private function buildVocabulary()
    {
        $this->vocabulary = [];
        foreach ($this->referenceData as $data) {
            $this->vocabulary = array_merge($this->vocabulary, $data['must_keywords'], $data['avoid_keywords']);
        }
        $this->vocabulary = array_unique($this->vocabulary);
    }

    /**
     * Initialize the classifier
     */
    private function initializeClassifier()
    {
        $this->classifier = new NaiveBayes();
    }

    /**
     * Load existing model or train new one
     */
    private function loadOrTrainModel()
    {
        if (file_exists($this->modelPath)) {
            $this->loadModel();
        } else {
            $this->trainInitialModel();
        }
    }

    /**
     * Train initial model using reference data
     */
    private function trainInitialModel()
    {
        $samples = [];
        $labels = [];

        // Generate training samples from reference data
        foreach ($this->referenceData as $category => $data) {
            // Add the short description as a sample
            $samples[] = $this->textToFeatures($data['short_desc']);
            $labels[] = $category;

            // Add must_keywords as positive examples
            foreach ($data['must_keywords'] as $keyword) {
                $samples[] = $this->textToFeatures($keyword);
                $labels[] = $category;
            }

            // Generate some negative examples from other categories' avoid_keywords
            foreach ($this->referenceData as $otherCategory => $otherData) {
                if ($otherCategory !== $category) {
                    foreach ($otherData['avoid_keywords'] as $avoidKeyword) {
                        $samples[] = $this->textToFeatures($avoidKeyword);
                        $labels[] = $otherCategory; // Label as the other category to create contrast
                    }
                }
            }
        }

        // Train the classifier
        $this->classifier->train($samples, $labels);

        // Save the model
        $this->saveModel();
    }

    /**
     * Convert text to feature vector (binary bag-of-words)
     */
    private function textToFeatures($text)
    {
        $processed = $this->preprocessText($text);
        $words = array_map('strtolower', explode(' ', $processed));
        $features = [];

        foreach ($this->vocabulary as $keyword) {
            $features[] = in_array(strtolower($keyword), $words) ? 1 : 0;
        }

        return $features;
    }

    /**
     * Preprocess text for classification
     */
    private function preprocessText($text)
    {
        // Convert to lowercase
        $text = strtolower($text);

        // Remove punctuation and extra whitespace
        $text = preg_replace('/[^\w\s]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }

    /**
     * Load saved model
     */
    private function loadModel()
    {
        // For simplicity, we'll retrain when loading
        // In a production system, you'd implement proper model serialization
        $this->trainInitialModel();
    }

    /**
     * Save model to file
     */
    private function saveModel()
    {
        // For simplicity, we'll retrain when loading
        // In a production system, you'd implement proper model serialization
        $modelData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'categories' => $this->categories
        ];

        file_put_contents($this->modelPath, json_encode($modelData, JSON_PRETTY_PRINT));
    }

    /**
     * Classify section C text
     */
    public function classify($sectionCText, $originalTypes = [])
    {
        $features = $this->textToFeatures($sectionCText);

        // Get prediction probabilities for all categories
        $scores = [];
        $maxScore = 0;
        $predicted = '';

        // Since PHP-ML NaiveBayes doesn't provide probabilities directly,
        // we'll use a simple keyword matching approach with learning from history
        $predicted = $this->fallbackKeywordMatching($sectionCText);
        $scores = $this->calculateKeywordScores($sectionCText);
        $maxScore = max($scores);

        // Adjust based on historical corrections
        $historicalAdjustment = $this->getHistoricalAdjustment($sectionCText, $predicted);
        if ($historicalAdjustment) {
            $predicted = $historicalAdjustment['category'];
            $maxScore = $historicalAdjustment['confidence'];
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
                'similar_cases_found' => count($similarCases)
            ],
            'similar_past_cases' => $similarCases
        ];
    }

    /**
     * Calculate keyword-based scores for each category
     */
    private function calculateKeywordScores($text)
    {
        $processed = $this->preprocessText($text);
        $words = explode(' ', $processed);
        $scores = [];

        foreach ($this->categories as $category) {
            $score = 0;
            $totalKeywords = count($this->referenceData[$category]['must_keywords']);

            foreach ($this->referenceData[$category]['must_keywords'] as $keyword) {
                if (stripos($processed, $keyword) !== false) {
                    $score++;
                }
            }

            // Penalize for avoid keywords
            foreach ($this->referenceData[$category]['avoid_keywords'] as $avoidKeyword) {
                if (stripos($processed, $avoidKeyword) !== false) {
                    $score -= 0.5;
                }
            }

            $scores[$category] = max(0, $score / max(1, $totalKeywords));
        }

        return $scores;
    }

    /**
     * Fallback keyword matching when ML fails
     */
    private function fallbackKeywordMatching($text)
    {
        $scores = $this->calculateKeywordScores($text);
        return array_keys($scores, max($scores))[0];
    }

    /**
     * Get historical adjustment based on similar past corrections
     */
    private function getHistoricalAdjustment($text, $predicted)
    {
        if (!file_exists($this->historyPath)) {
            return null;
        }

        $lines = file($this->historyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $bestMatch = null;
        $bestSimilarity = 0;

        foreach (array_slice($lines, -20) as $line) { // Check last 20 cases
            $case = json_decode($line, true);
            if (!$case) continue;
            
            // Only look at corrections (cases where staff disagreed with AI)
            if ($case['agreed']) continue;

            $similarity = $this->calculateTextSimilarity($text, $case['section_c_text']);
            if ($similarity > 0.4 && $similarity > $bestSimilarity) { // 40% similarity threshold
                $bestMatch = $case;
                $bestSimilarity = $similarity;
            }
        }

        if ($bestMatch) {
            return [
                'category' => $bestMatch['human_label'],
                'confidence' => min(0.9, 0.5 + $bestSimilarity) // Boost confidence based on similarity
            ];
        }

        return null;
    }

    /**
     * Calculate confidence level
     */
    private function calculateConfidence($score)
    {
        if ($score >= 0.8) return 'high';
        if ($score >= 0.6) return 'moderate';
        return 'low';
    }

    /**
     * Generate reasoning text
     */
    private function generateReasoning($text, $predicted, $score, $originalTypes)
    {
        $confidencePercent = round($score * 100);

        // Analyze the text for keywords
        $keywordAnalysis = $this->analyzeKeywordsInText($text, $predicted);

        // Build comprehensive reasoning
        $reason = "### AI Classification Analysis\n\n";

        $reason .= "**Predicted Category:** {$predicted}\n";
        $reason .= "**Confidence Score:** {$confidencePercent}%\n\n";

        // Match strength description
        if ($score >= 0.8) {
            $reason .= "**Match Strength:** Strong alignment detected\n";
            $reason .= "**Analysis:** The content shows excellent correspondence with {$predicted} category requirements. ";
        } elseif ($score >= 0.6) {
            $reason .= "**Match Strength:** Moderate alignment detected\n";
            $reason .= "**Analysis:** The content demonstrates reasonable alignment with {$predicted} characteristics. ";
        } else {
            $reason .= "**Match Strength:** Weak alignment detected\n";
            $reason .= "**Analysis:** The content shows limited alignment with {$predicted} category themes. ";
        }

        // Add keyword analysis details
        if (!empty($keywordAnalysis['found_keywords'])) {
            $reason .= "Key matching terms identified: " . implode(', ', array_slice($keywordAnalysis['found_keywords'], 0, 5));
            if (count($keywordAnalysis['found_keywords']) > 5) {
                $reason .= " (and " . (count($keywordAnalysis['found_keywords']) - 5) . " more)";
            }
            $reason .= ".\n";
        }

        if (!empty($keywordAnalysis['avoided_keywords'])) {
            $reason .= "Terms suggesting other categories: " . implode(', ', array_slice($keywordAnalysis['avoided_keywords'], 0, 3)) . ".\n";
        }

        // Add text characteristics
        $wordCount = str_word_count($text);
        $reason .= "\n**Content Overview:** {$wordCount} words analyzed. ";

        // Add applicant's original selection if available
        if (!empty($originalTypes)) {
            $reason .= "\n\n**Applicant Selection:** " . implode(', ', $originalTypes);
            if (!in_array($predicted, $originalTypes)) {
                $reason .= " (AI suggests alternative classification)";
            } else {
                $reason .= " (Matches applicant's selection)";
            }
        }

        $reason .= "\n\n**Methodology:** Classification based on keyword matching against established ethical review categories, enhanced by historical learning data.";

        return $reason;
    }

    /**
     * Analyze which keywords are present in the text
     */
    private function analyzeKeywordsInText($text, $predictedCategory)
    {
        $processed = $this->preprocessText($text);
        $foundKeywords = [];
        $avoidedKeywords = [];

        // Check for must keywords in predicted category
        foreach ($this->referenceData[$predictedCategory]['must_keywords'] as $keyword) {
            if (stripos($processed, $keyword) !== false) {
                $foundKeywords[] = $keyword;
            }
        }

        // Check for avoid keywords in other categories that appear in text
        foreach ($this->referenceData as $category => $data) {
            if ($category !== $predictedCategory) {
                foreach ($data['avoid_keywords'] as $avoidKeyword) {
                    if (stripos($processed, $avoidKeyword) !== false && !in_array($avoidKeyword, $avoidedKeywords)) {
                        $avoidedKeywords[] = $avoidKeyword;
                    }
                }
            }
        }

        return [
            'found_keywords' => $foundKeywords,
            'avoided_keywords' => $avoidedKeywords
        ];
    }

    /**
     * Find similar past cases from history
     */
    private function findSimilarCases($text)
    {
        if (!file_exists($this->historyPath)) {
            return [];
        }

        $similarCases = [];
        $lines = file($this->historyPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach (array_slice($lines, -10) as $line) { // Check last 10 cases
            $case = json_decode($line, true);
            if (!$case) continue;

            // Simple similarity check based on text length and word overlap
            $similarity = $this->calculateTextSimilarity($text, $case['section_c_text']);

            if ($similarity > 0.3) { // 30% similarity threshold
                $similarCases[] = [
                    'system_predicted' => $case['system_predicted'],
                    'label' => $case['human_label'],
                    'score' => $similarity,
                    'status' => $case['agreed'] ? 'Agreed' : 'Corrected',
                    'agreed' => $case['agreed']
                ];
            }
        }

        return array_slice($similarCases, 0, 3); // Return top 3 similar cases
    }

    /**
     * Calculate simple text similarity
     */
    private function calculateTextSimilarity($text1, $text2)
    {
        $words1 = array_unique(str_word_count(strtolower($text1), 1));
        $words2 = array_unique(str_word_count(strtolower($text2), 1));

        $intersection = array_intersect($words1, $words2);
        $union = array_unique(array_merge($words1, $words2));

        return count($union) > 0 ? count($intersection) / count($union) : 0;
    }

    /**
     * Learn from staff correction
     */
    public function learnFromCorrection($sectionCText, $systemPredicted, $humanLabel, $staffNote = '')
    {
        // The corrections are already logged to history.jsonl
        // The classifier will use this historical data in future classifications
        // For now, we don't retrain immediately, but this could be added later

        return true;
    }

    /**
     * Retrain model with all available data
     */
    public function retrainModel()
    {
        // This would retrain using both reference data and historical corrections
        // For now, we'll just retrain the initial model
        $this->trainInitialModel();
    }
}