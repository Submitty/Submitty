import os
import json
import math
from pathlib import Path
from collections import defaultdict
from typing import List, Dict, Any
from submitty_utils.feature_extractor import CodeFeatureExtractor

try:
    from scipy.cluster.hierarchy import linkage
    SCIPY_AVAILABLE = True
except ImportError:
    SCIPY_AVAILABLE = False

class ClusteringEngine:
    def __init__(self, data_dir: str, semester: str, course: str, gradeable: str):
        self.data_dir = data_dir
        self.semester = semester
        self.course = course
        self.gradeable = gradeable
        self.extractor = CodeFeatureExtractor()

    def run_clustering(self) -> Dict[str, Any]:
        """
        Main entry point. Discovers submissions, extracts features, 
        clusters them, and returns a JSON-serializable dictionary.
        """
        submissions = self._discover_submissions()
        if not submissions:
            return {"error": "No submissions found for clustering."}

        feature_matrix = []
        labels = []

        for sub in submissions:
            user_id = sub['user']
            version = sub['version']
            code_files = self._get_code_files(sub['path'])
            
            if not code_files:
                continue

            test_results = self._get_test_results(user_id, version)
            
            # For POC: just extract features from the first python file found
            # Or concatenate them, but let's just use the first for simplicity
            with open(code_files[0], 'r', encoding='utf-8') as f:
                code = f.read()
                
            features_dict = self.extractor.extract_features(code, test_results)
            
            # Convert dict to a list of values in consistent order
            feature_vector = [features_dict[k] for k in sorted(features_dict.keys())]
            feature_matrix.append(feature_vector)
            labels.append(f"{user_id}_v{version}")

        if not feature_matrix:
             return {"error": "No features could be extracted."}

        # Normalize features
        normalized_matrix = self._normalize_features(feature_matrix)

        if SCIPY_AVAILABLE:
            # Perform HAC
            Z = linkage(normalized_matrix, method='ward')
            # Convert Linkage matrix Z to dendrogram JSON
            tree_json = self._linkage_to_json(Z, labels)
            return {"status": "success", "tree": tree_json}
        else:
            return {"error": "Scipy is not available. Clustering failed.", "features": len(feature_matrix)}

    def _discover_submissions(self) -> List[Dict[str, Any]]:
        """Finds the highest version submission for each user."""
        submissions_dir = Path(self.data_dir) / 'courses' / self.semester / self.course / 'submissions' / self.gradeable
        if not submissions_dir.exists():
            return []

        active_submissions = []
        for user_dir in submissions_dir.iterdir():
            if user_dir.is_dir():
                versions = []
                for v_dir in user_dir.iterdir():
                    if v_dir.is_dir() and v_dir.name.isdigit():
                        versions.append(int(v_dir.name))
                
                if versions:
                    highest_version = max(versions)
                    active_submissions.append({
                        'user': user_dir.name,
                        'version': highest_version,
                        'path': user_dir / str(highest_version)
                    })
        return active_submissions

    def _get_code_files(self, version_path: Path) -> List[Path]:
        """Finds python files in the submission."""
        code_files = []
        for root, _, files in os.walk(version_path):
            for file in files:
                if file.endswith('.py'):
                    code_files.append(Path(root) / file)
        return code_files

    def _get_test_results(self, user_id: str, version: int) -> Dict[str, Any]:
        """Loads autograder results.json if it exists."""
        results_file = Path(self.data_dir) / 'courses' / self.semester / self.course / 'results' / self.gradeable / user_id / str(version) / 'results.json'
        if results_file.exists():
            try:
                with open(results_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                
                # Mocking parsing logic of Submitty results.json for POC
                passed = 0
                failed = 0
                testcases = data.get('testcases', [])
                results_array = []
                for tc in testcases:
                    points = tc.get('points_awarded', 0)
                    if points > 0:
                        passed += 1
                        results_array.append(True)
                    else:
                        failed += 1
                        results_array.append(False)
                        
                return {
                    'total_tests': passed + failed,
                    'passed': passed,
                    'failed': failed,
                    'results': results_array
                }
            except Exception:
                pass
        
        return {}

    def _normalize_features(self, matrix: List[List[float]]) -> List[List[float]]:
        """Z-score normalization manually to avoid numpy dependency if possible."""
        if not matrix or not matrix[0]: return matrix
        
        num_rows = len(matrix)
        num_cols = len(matrix[0])
        
        means = [0.0] * num_cols
        stdevs = [0.0] * num_cols
        
        for row in matrix:
            for j in range(num_cols):
                means[j] += row[j]
        
        means = [m / num_rows for m in means]
        
        for row in matrix:
            for j in range(num_cols):
                stdevs[j] += (row[j] - means[j]) ** 2
                
        stdevs = [math.sqrt(s / num_rows) for s in stdevs]
        
        normalized = []
        for row in matrix:
            new_row = []
            for j in range(num_cols):
                if stdevs[j] == 0:
                    new_row.append(0.0)
                else:
                    new_row.append((row[j] - means[j]) / stdevs[j])
            normalized.append(new_row)
            
        return normalized

    def _linkage_to_json(self, Z, labels: List[str]) -> Dict[str, Any]:
        """Converts scipy linkage matrix to nested JSON for D3.js"""
        # Z is a (n-1) x 4 matrix where each row represents a cluster merge.
        # n is the number of original samples.
        n = len(labels)
        
        # Keep track of the nodes we create
        # Initially, the nodes are the original samples
        nodes = [{"name": labels[i], "distance": 0.0} for i in range(n)]
        
        # Iterate over the merges
        for i, row in enumerate(Z):
            idx1 = int(row[0])
            idx2 = int(row[1])
            dist = float(row[2])
            
            node1 = nodes[idx1]
            node2 = nodes[idx2]
            
            new_node = {
                "name": f"Cluster_{n + i}",
                "distance": dist,
                "children": [node1, node2]
            }
            nodes.append(new_node)
            
        return nodes[-1] # The root of the tree
