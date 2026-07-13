import unittest
from unittest.mock import patch, MagicMock
import sys
import os
from pathlib import Path

# Add sbin to path to import clustering algorithms
sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from clustering_algorithms import dummy_split
from submitty_jobs.jobs import GradingClustering

class TestGradingClustering(unittest.TestCase):
    def test_dummy_split_algorithm(self):
        submitters = [
            {'user_id': 'student1', 'team_id': None, 'active_version': 1},
            {'user_id': 'student2', 'team_id': None, 'active_version': 2},
            {'user_id': None, 'team_id': 'team1', 'active_version': 3}
        ]
        clusters = dummy_split.run(submitters)
        
        self.assertEqual(len(clusters), 2)
        self.assertEqual(len(clusters['Cluster A (A-M)']), 0)
        self.assertEqual(len(clusters['Cluster B (N-Z)']), 3)

    @patch('submitty_jobs.jobs.subprocess.run')
    def test_job_run(self, mock_subprocess):
        job_details = {
            'semester': 's23',
            'course': 'csci1100',
            'gradeable': 'hw1',
            'algorithm': 'dummy_split'
        }
        job = GradingClustering(job_details)
        job.run_job()
        
        # Verify it invoked subprocess correctly
        mock_subprocess.assert_called_once()
        args, kwargs = mock_subprocess.call_args
        self.assertEqual(args[0][0], 'python3')
        self.assertIn('run_grading_clustering.py', args[0][1])
        self.assertEqual(args[0][2:], ['s23', 'csci1100', 'hw1', 'dummy_split'])

if __name__ == '__main__':
    unittest.main()
