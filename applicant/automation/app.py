# python/app.py
from flask import Flask, request, jsonify
from flask_cors import CORS
import json
import os
from datetime import datetime
from sentence_transformers import SentenceTransformer, util
import numpy as np

app = Flask(__name__)
CORS(app)  # allow calls from localhost PHP

REFERENCE_FILE = "reference.json"
HISTORY_FILE   = "history.jsonl"   # adjust path if needed

# Load model once at startup
model = SentenceTransformer('all-MiniLM-L6-v2')

# Load reference once
with open(REFERENCE_FILE, encoding="utf-8") as f:
    REFERENCES = json.load(f)

def read_history():
    if not os.path.exists(HISTORY_FILE):
        return []
    records = []
    with open(HISTORY_FILE, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if line:
                try:
                    records.append(json.loads(line))
                except json.JSONDecodeError:
                    pass  # skip invalid lines
    return records

@app.route("/classify", methods=["POST"])
def classify():
    data = request.get_json()
    section_c_text = data.get("section_c", "").strip()
    original_types = data.get("original_types", [])   # what applicant selected
    used_fallback = data.get("used_fallback", False)
    focus_section = data.get("focus_section", "C")    # which section we're focusing on

    if not section_c_text:
        return jsonify({"error": "No section C text"}), 400

    # ────── Load history for continuous learning ──────
    history = read_history()
    
    # ────── Semantic embedding for query ──────
    query_emb = model.encode(section_c_text, convert_to_tensor=True)

    # ────── FIRST: Check history for direct matches (prioritize learned cases) ──────
    similar_history = []
    history_prediction = None
    history_confidence = 0.0
    
    if history:
        for record in history[-100:]:  # Check last 100 cases
            past_text = record.get("section_c_text", "")[:2000]
            if past_text:
                past_emb = model.encode(past_text, convert_to_tensor=True)
                similarity = float(util.cos_sim(query_emb, past_emb).item())
                
                # Store all cases with any similarity for learning
                if similarity >= 0.20:  # Lower threshold for learning consideration
                    similar_history.append({
                        "similarity": similarity,
                        "human_label": record.get("human_label"),
                        "system_predicted": record.get("system_predicted"),
                        "agreed": record.get("agreed", False),
                        "snippet": past_text[:200],
                        "record": record
                    })
                
                # Use history as primary prediction if very similar (50%+)
                if similarity >= 0.50 and record.get("human_label"):
                    if similarity > history_confidence:
                        history_prediction = record.get("human_label")
                        history_confidence = similarity

    # ────── SECOND: Reference-based classification (fallback) ──────
    scores = {}
    for cat, info in REFERENCES.items():
        ref_text = info["short_desc"]
        ref_emb = model.encode(ref_text, convert_to_tensor=True)
        # Cosine similarity (-1 to 1) → we normalize to 0–1
        sim = float(util.cos_sim(query_emb, ref_emb).item())
        scores[cat] = max(0.0, sim)  # clip negative values

    # ────── Keyword boost (reduced influence) ──────
    text_lower = section_c_text.lower()
    for cat, info in REFERENCES.items():
        boost = 0.0
        for kw in info.get("must_keywords", []):
            if kw.lower() in text_lower:
                boost += 0.10  # reduced from 0.20
        penalty = 0.0
        for kw in info.get("avoid_keywords", []):
            if kw.lower() in text_lower:
                penalty += 0.15  # reduced from 0.25
        scores[cat] = max(0.0, min(1.0, scores[cat] + boost - penalty))
    
    # ────── Apply history-based prediction if available ──────
    if history_prediction and history_confidence >= 0.50:
        # Override with history prediction
        for cat in scores:
            if cat == history_prediction:
                scores[cat] = max(scores[cat], history_confidence)
            else:
                scores[cat] = scores[cat] * 0.7  # Reduce others when history match is strong
    
    # ────── Apply additional learning from similar past cases ──────
    if similar_history:
        # Sort by similarity
        similar_history.sort(key=lambda x: x["similarity"], reverse=True)
        
        # Weight adjustment based on past corrections (only for cases with 30%+ similarity)
        learning_applied = False
        for past_case in similar_history[:5]:  # Top 5 most similar
            if past_case["similarity"] >= 0.30:  # Only apply learning for moderately similar cases
                sim_weight = past_case["similarity"] * 0.15  # Reduced influence since history is primary
                correct_label = past_case["human_label"]
                
                # Boost the correct label from past cases
                if correct_label in scores:
                    scores[correct_label] = min(1.0, scores[correct_label] + sim_weight)
                    learning_applied = True
                
                # If system was wrong before, slightly penalize that category
                if not past_case["agreed"]:
                    wrong_label = past_case["system_predicted"]
                    if wrong_label in scores and wrong_label != correct_label:
                        scores[wrong_label] = max(0.0, scores[wrong_label] - (sim_weight * 0.2))
                        learning_applied = True

    # Sort by final score
    ranked = sorted(scores.items(), key=lambda x: x[1], reverse=True)
    top_cat, top_score = ranked[0]

    # Build comprehensive reason with learning insights
    reason_parts = []
    
    # Primary prediction source
    if history_prediction and history_confidence >= 0.50:
        reason_parts.append(f"Primary prediction from history: Found highly similar past case ({history_confidence*100:.0f}% match) classified as '{history_prediction}'.")
        reason_parts.append(f"Reference analysis confirmed with semantic similarity {top_score:.3f} ({top_score*100:.0f}%) for {top_cat}.")
    else:
        reason_parts.append(f"Analysis of section {focus_section}: Semantic similarity {top_score:.3f} ({top_score*100:.0f}%)")
    
    if top_score >= 0.55:
        reason_parts.append(f"**strong match** for {top_cat}. Content aligns well with category themes.")
    elif top_score >= 0.35:
        reason_parts.append(f"**moderate match** for {top_cat}. Some relevant concepts detected.")
    else:
        reason_parts.append(f"**low confidence**. Possible mismatch or content is very short/unclear.")
    
    # Add learning insights
    if similar_history:
        # Filter for cases that actually influenced learning (30%+)
        influential_cases = [h for h in similar_history if h["similarity"] >= 0.30]
        if influential_cases:
            top_similar = influential_cases[0]
            if top_similar["similarity"] >= 0.60:
                reason_parts.append(f"Learning applied: Highly similar past case ({top_similar['similarity']*100:.0f}% match) reinforced '{top_similar['human_label']}' classification.")
            elif top_similar["similarity"] >= 0.40:
                reason_parts.append(f"Learning applied: Similar past case ({top_similar['similarity']*100:.0f}% match) influenced toward '{top_similar['human_label']}'.")
            
            # Count agreements and disagreements
            agreed_count = sum(1 for h in influential_cases if h["agreed"])
            if agreed_count > 0:
                reason_parts.append(f"System accuracy on {agreed_count} similar past case(s): prediction matched staff review.")
    
    if used_fallback:
        reason_parts.append(f"(Fallback mode: full document text was used because section {focus_section} could not be isolated.)")
    
    reason = " ".join(reason_parts)

    # Format similar past cases for display (20% threshold for visibility)
    similar_past = []
    for past_case in similar_history[:5]:  # Top 5
        if past_case["similarity"] >= 0.20:
            status = "✓ Agreed" if past_case["agreed"] else "✗ Corrected"
            if past_case["similarity"] >= 0.50:
                status += " (Primary)"
            elif past_case["similarity"] >= 0.30:
                status += " (Learning)"
            similar_past.append({
                "score": past_case["similarity"],
                "label": past_case["human_label"],
                "system_predicted": past_case["system_predicted"],
                "status": status,
                "snippet": past_case["snippet"][:180] + "..." if len(past_case["snippet"]) > 180 else past_case["snippet"]
            })
    
    # Provide confidence assessment
    confidence_level = "high" if top_score >= 0.55 else "moderate" if top_score >= 0.35 else "low"
    if history_prediction and history_confidence >= 0.50:
        confidence_level = "high"  # History-based predictions are highly confident
    
    # Calculate learning statistics
    influential_cases = len([h for h in similar_history if h["similarity"] >= 0.30])
    learning_stats = {
        "total_history_count": len(history),
        "similar_cases_found": len(similar_history),
        "learning_applied": influential_cases > 0,
        "history_primary_prediction": history_prediction is not None,
        "confidence_level": confidence_level
    }
    
    return jsonify({
        "predicted": top_cat,
        "scores": {k: float(v) for k,v in ranked},
        "max_score": float(top_score),
        "reason": reason,
        "similar_past_cases": similar_past,
        "learning_stats": learning_stats,
        "timestamp": datetime.now().isoformat()
    })

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=True)