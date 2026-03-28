"""
Submission Similarity Clustering
Clusters student submissions by code similarity using TF-IDF + KMeans.
Used by the TA Grading interface to enable bulk grading of similar work.
"""

import os
import json
import argparse
import numpy as np
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.cluster import KMeans
from sklearn.preprocessing import normalize


def load_submissions(submissions_dir: str) -> dict:
    """Read all student code files from submissions directory."""
    submissions = {}
    if not os.path.isdir(submissions_dir):
        return submissions

    for student_id in os.listdir(submissions_dir):
        student_path = os.path.join(submissions_dir, student_id)
        if not os.path.isdir(student_path):
            continue

        code_text = ""
        for fname in os.listdir(student_path):
            if fname.endswith(('.py', '.cpp', '.java', '.c', '.h', '.txt')):
                fpath = os.path.join(student_path, fname)
                try:
                    with open(fpath, 'r', errors='ignore') as f:
                        code_text += f.read() + "\n"
                except Exception:
                    continue

        if code_text.strip():
            submissions[student_id] = code_text

    return submissions


def cluster_submissions(submissions: dict, n_clusters: int = 5) -> dict:
    """
    Vectorize code using TF-IDF and cluster using KMeans.
    Returns dict: {cluster_id: [student_id, ...]}
    """
    if len(submissions) < 2:
        return {"0": list(submissions.keys())}

    n_clusters = min(n_clusters, len(submissions))

    student_ids = list(submissions.keys())
    texts = [submissions[sid] for sid in student_ids]

    # TF-IDF on code tokens (variable names, keywords)
    vectorizer = TfidfVectorizer(
        analyzer='word',
        token_pattern=r'[a-zA-Z_][a-zA-Z0-9_]*',
        max_features=500,
        sublinear_tf=True
    )
    X = vectorizer.fit_transform(texts)
    X = normalize(X)

    kmeans = KMeans(n_clusters=n_clusters, random_state=42, n_init=10)
    labels = kmeans.fit_predict(X)

    clusters = {}
    for idx, label in enumerate(labels):
        key = str(label)
        clusters.setdefault(key, []).append(student_ids[idx])

    return clusters


def main():
    parser = argparse.ArgumentParser(
        description='Cluster student submissions by code similarity'
    )
    parser.add_argument('--submissions_dir', required=True,
                        help='Path to gradeable submissions folder')
    parser.add_argument('--output_file', required=True,
                        help='Path to write clusters JSON output')
    parser.add_argument('--n_clusters', type=int, default=5,
                        help='Number of clusters (default: 5)')
    args = parser.parse_args()

    submissions = load_submissions(args.submissions_dir)
    print(f"Loaded {len(submissions)} submissions")

    if len(submissions) < 2:
        print("Not enough submissions to cluster.")
        return

    clusters = cluster_submissions(submissions, args.n_clusters)

    os.makedirs(os.path.dirname(args.output_file), exist_ok=True)
    result = {
        "total_submissions": len(submissions),
        "n_clusters": len(clusters),
        "clusters": clusters
    }

    with open(args.output_file, 'w') as f:
        json.dump(result, f, indent=2)

    print(f"Saved clusters to {args.output_file}")
    for cid, members in clusters.items():
        print(f"  Cluster {cid}: {len(members)} students → {members[:3]}")


if __name__ == '__main__':
    main()