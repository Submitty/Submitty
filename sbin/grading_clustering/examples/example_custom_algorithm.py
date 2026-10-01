#!/usr/bin/env python3
"""
Example custom clustering algorithm for Submitty.
This script demonstrates the expected interface for custom clustering scripts.
It reads submitter data from 'input.json' and writes cluster assignments to 'output.json'.

Available libraries depend entirely on the Docker image you select in the "Create Clusters" modal.
If your script needs numpy, scipy, scikit-learn or pandas, pick an image that ships them.

The script runs with no network access, so it cannot install packages at runtime.
"""

import json
import numpy as np
from sklearn.cluster import KMeans


def main():
    #read input data
    with open('input.json', 'r') as f:
        data = json.load(f)

    submitters = data['submitters']
    # Each submitter is a dict with:
    #   - 'user_id': str or None (for individual assignments)
    #   - 'team_id': str or None (for team assignments)
    #   - 'active_version': int (the active submission version number)

    # your logic comes here
    # This example uses K-Means from scikit-learn to group students into
    # clusters based on dummy features. Replace this with your own ML logic!

    # A) Extract features for Machine Learning
    # Here we are just creating dummy features: a hash of the ID and their active_version
    features = []
    for s in submitters:
        uid = s.get('user_id') or s.get('team_id') or ''
        # We create a 2D feature vector just to satisfy KMeans
        features.append([hash(uid) % 1000, s['active_version']])

    # If there are no submitters, just return empty clusters to prevent errors
    if not features:
        with open('output.json', 'w') as f:
            json.dump({"clusters": {}}, f)
        return

    X = np.array(features)

    # B) Perform ML Clustering (K-Means)
    # Group the students into 3 clusters based on the dummy features
    n_clusters = min(3, len(submitters))
    kmeans = KMeans(n_clusters=n_clusters, random_state=42)
    labels = kmeans.fit_predict(X)

    # C) Group the submitters by their predicted label
    clusters = {}
    for i, label in enumerate(labels):
        name = f"ML Group {label + 1}"
        clusters.setdefault(name, []).append(submitters[i])

    #write the ouput
    output = {"clusters": clusters}

    with open('output.json', 'w') as f:
        json.dump(output, f, indent=2)

    print("Clustering complete!")


if __name__ == '__main__':
    main()
