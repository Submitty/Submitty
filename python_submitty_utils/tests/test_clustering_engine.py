import unittest
import os
import tempfile
import json
from pathlib import Path
from submitty_utils.clustering_engine import ClusteringEngine

try:
    import scipy
    SCIPY_AVAILABLE = True
except ImportError:
    SCIPY_AVAILABLE = False


class TestClusteringEngine(unittest.TestCase):
    
    def setUp(self):
        self.temp_dir = tempfile.TemporaryDirectory()
        self.data_dir = Path(self.temp_dir.name)
        
        self.semester = 'f24'
        self.course = 'csci1000'
        self.gradeable = 'hw1'
        
        # Create mock file structure
        self.gradeable_dir = self.data_dir / 'courses' / self.semester / self.course / 'submissions' / self.gradeable
        self.gradeable_dir.mkdir(parents=True)
        
        self.results_dir = self.data_dir / 'courses' / self.semester / self.course / 'results' / self.gradeable
        self.results_dir.mkdir(parents=True)
        
        self._create_mock_student('student1', 3, 'for i in range(10): print(i)')
        self._create_mock_student('student2', 1, 'for x in range(10): print(x)') # Similar to 1
        self._create_mock_student('student3', 2, 'def fact(n):\n if n<=1: return 1\n return n*fact(n-1)') # Different

    def tearDown(self):
        self.temp_dir.cleanup()
        
    def _create_mock_student(self, user_id, version, code):
        user_dir = self.gradeable_dir / user_id / str(version)
        user_dir.mkdir(parents=True)
        with open(user_dir / 'main.py', 'w') as f:
            f.write(code)
            
        res_dir = self.results_dir / user_id / str(version)
        res_dir.mkdir(parents=True)
        with open(res_dir / 'results.json', 'w') as f:
            json.dump({'testcases': [{'points': 1}, {'points': 0}]}, f)

    @unittest.skipIf(not SCIPY_AVAILABLE, "SciPy is not installed")
    def test_clustering(self):
        engine = ClusteringEngine(str(self.data_dir), self.semester, self.course, self.gradeable)
        result = engine.run_clustering()
        
        self.assertNotIn("error", result, f"Clustering failed: {result}")
        
        # Verify JSON tree structure
        tree = result.get('tree', {})
        self.assertIn("name", tree)
        self.assertIn("children", tree)
        
        # Should be a binary tree merge
        self.assertEqual(len(tree["children"]), 2)
        
if __name__ == '__main__':
    unittest.main()
