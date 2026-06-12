# UREO Portal AI/ML Classifier Implementation

## Overview

This document describes the improved AI/ML classifier system for the UREO Portal, based on analysis of the tesi-portal implementation.

## What Was Implemented

### 1. New ResearchCategoryClassifier Class
**File:** `applicant/automation/ResearchCategoryClassifier.php`

This is a complete rewrite of the AI classifier with the following improvements:

- **Proper PHP-ML Pipeline**: Uses `TokenCountVectorizer` + `TfIdfTransformer` + `NaiveBayes`
- **Multiple Training Sources**:
  1. Reference data (`reference.json`) - base training data with descriptions and keywords
  2. Historical corrections (`history.jsonl`) - staff feedback from previous classifications
  3. Training CSV (`training_data.csv`) - additional labeled data (optional)
- **Model Persistence**: Proper model saving/loading using `ModelManager`
- **Word Indicators**: Generates explainable word weights for each category
- **Singleton Pattern**: Efficient model reuse across requests
- **Historical Learning**: Adjusts predictions based on similar past corrections

### 2. CLI Training Script
**File:** `scripts/train-ai-classifier.php`

Usage:
```bash
# Show training statistics
php scripts/train-ai-classifier.php --stats

# Force retrain the model
php scripts/train-ai-classifier.php --retrain
```

### 3. API Endpoint
**File:** `api/predict-category.php`

REST API endpoint for classification:
```bash
POST /api/predict-category.php
{
  "title": "Research title",
  "section_c_text": "Full text from Section C",
  "original_types": ["Human Use"]
}
```

Returns:
```json
{
  "status": "success",
  "prediction": "Human Use",
  "confidence": "high",
  "score": 0.85,
  "scores": { "Human Use": 0.85, ... },
  "reason": "AI analysis text...",
  "indicators": [...],
  "learning_stats": { ... },
  "similar_past_cases": [...]
}
```

### 4. Updated Staff Feedback Handler
**File:** `staff/handle-ai-feedback.php`

Updated to use the new `ResearchCategoryClassifier` for learning from staff corrections.

## Key Differences from tesi-portal

| Feature | tesi-portal | ureo-portal (new) |
|---------|-------------|-------------------|
| Categories | 3 (technical, social, social_technical) | 7 (Human Use, Animal Welfare, etc.) |
| Training Data | CSV file | JSON reference + history + CSV |
| Model | NaiveBayes Pipeline | NaiveBayes Pipeline |
| Learning | Static model | Trainable from history |
| Explainability | Word indicators | Word indicators + keyword analysis |

## Training Data Generation

The classifier generates training samples from:

1. **Reference descriptions**: Full short_desc from each category
2. **Individual keywords**: Each must_keyword as a sample
3. **Keyword combinations**: Pairs of related keywords
4. **Historical corrections**: Full text from staff-reviewed cases
5. **Sentence variations**: Individual sentences from long texts

## Memory Considerations

The training process can be memory-intensive. The script sets:
- `memory_limit = 256M`
- `time_limit = 300 seconds`

If you encounter memory issues, you can:
1. Increase memory limit in the training script
2. Reduce the number of keyword combinations in `generateTrainingSamples()`
3. Train during off-peak hours

## How Learning Works

1. **Initial Training**: Model is trained on reference data (keywords + descriptions)
2. **Staff Feedback**: When staff accept or correct AI predictions, the data is logged to `history.jsonl`
3. **Auto-Retrain**: After every 10 new corrections, the model automatically retrains itself
4. **Historical Adjustment**: During classification, similar past corrections can adjust predictions

## Auto-Retrain & Space Saving

The classifier has built-in auto-retrain and space-saving features:

### Auto-Retrain (`AUTO_RETRAIN_THRESHOLD = 10`)
- After every 10 new corrections added to `history.jsonl`, the model automatically retrains
- Uses a marker file (`.last_retrain_count`) to track how many entries existed at last retrain
- Retrains happen inline when `learnFromCorrection()` is called (typically during staff feedback)
- Can also be triggered manually via: `php scripts/train-ai-classifier.php --retrain`

### Space Saving (`MAX_HISTORY_ENTRIES = 500`)
- Automatically trims `history.jsonl` to keep only the most recent 500 entries
- Older entries are removed during auto-retrain to prevent storage bloat
- The model still remembers patterns from old data since it was trained on them
- Old model version files in `models/` can be manually deleted to save space

### Configuration Constants (in ResearchCategoryClassifier.php):
```php
private const AUTO_RETRAIN_THRESHOLD = 10;  // Retrain after 10 new entries
private const MAX_HISTORY_ENTRIES = 500;     // Keep last 500 entries
private const LAST_RETRAIN_FILE = __DIR__ . '/.last_retrain_count';  // Marker file
```

## File Structure

```
applicant/automation/
├── ResearchCategoryClassifier.php  # Main classifier class
├── TrainableEthicsClassifier.php   # Old classifier (kept for reference)
├── reference.json                   # Category definitions with keywords
├── history.jsonl                    # Staff feedback history
├── training_data.csv               # Optional additional training data
└── models/
    ├── .htaccess                   # Security: deny access
    ├── research_category_naivebayes_latest.phpml  # Trained model
    └── research_category_naivebayes_latest.json   # Model metadata

scripts/
└── train-ai-classifier.php         # CLI training script

api/
└── predict-category.php            # REST API endpoint
```

## Next Steps

1. **Run Initial Training**: Execute `php scripts/train-ai-classifier.php --retrain` to train the model
2. **Test Classification**: Use the API endpoint to test predictions
3. **Monitor Accuracy**: Review staff corrections to assess model performance
4. **Regular Retraining**: Schedule periodic retraining (e.g., weekly) to incorporate new feedback

## Cron Job Example

To automatically retrain the model weekly:
```cron
# Retrain AI classifier every Sunday at 2:00 AM
0 2 * * 0 /usr/bin/php /path/to/ureo-portal/scripts/train-ai-classifier.php --retrain >> /var/log/ureo/ml_train.log 2>&1
```

## Troubleshooting

### Memory Errors
If you see "Allowed memory size exhausted":
1. Increase `memory_limit` in the training script
2. Reduce keyword combinations in `generateTrainingSamples()`

### Slow Training
Training time depends on:
- Number of training samples
- Vocabulary size
- Available CPU/memory

### Poor Accuracy
If accuracy is low:
1. Add more training data to `training_data.csv`
2. Review and improve keyword definitions in `reference.json`
3. Allow more staff feedback to accumulate for learning