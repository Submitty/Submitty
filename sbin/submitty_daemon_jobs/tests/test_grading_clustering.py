#!/usr/bin/env python3

import unittest
from unittest.mock import patch, MagicMock
import json
import subprocess
import tempfile
import sys
from pathlib import Path
sys.path.insert(0, str(Path(__file__).resolve().parents[2]))

from grading_clustering.algorithms.dummy_split import DummySplit
from grading_clustering.algorithms.single_cluster import SingleCluster
from submitty_jobs.jobs import GradingClustering

class TestGradingClustering(unittest.TestCase):
    def make_course_dir(self, data_dir, semester, course):
        """
        The job reads the group ownership off the course directory before it
        creates the status directory, so the course must already exist. That is
        always true on a real install; the temp DATA_DIR has to mirror it.
        """
        course_dir = Path(data_dir, 'courses', semester, course)
        course_dir.mkdir(parents=True)
        return course_dir

    def test_dummy_split_algorithm(self):
        submitters = [
            {'user_id': 'student1', 'team_id': None, 'active_version': 1},
            {'user_id': 'student2', 'team_id': None, 'active_version': 2},
            {'user_id': None, 'team_id': 'team1', 'active_version': 3}
        ]
        algo = DummySplit()
        clusters = algo.run(submitters)
        self.assertEqual(len(clusters), 2)
        self.assertEqual(len(clusters['Cluster A (A-M)']), 0)
        self.assertEqual(len(clusters['Cluster B (N-Z)']), 3)

    def test_single_cluster_algorithm(self):
        submitters = [
            {'user_id': 'student1', 'team_id': None, 'active_version': 1},
            {'user_id': 'student2', 'team_id': None, 'active_version': 2},
            {'user_id': None, 'team_id': 'team1', 'active_version': 3}
        ]
        algo = SingleCluster()
        clusters = algo.run(submitters)
        
        self.assertEqual(len(clusters), 1)
        self.assertIn('All Submitters', clusters)
        self.assertEqual(len(clusters['All Submitters']), 3)

    @patch('submitty_jobs.jobs.subprocess.run')
    def test_job_run_success(self, mock_subprocess):
        mock_result = MagicMock()
        mock_result.returncode = 0
        mock_subprocess.return_value = mock_result

        job_details = {
            'semester': 's23',
            'course': 'csci1100',
            'gradeable': 'hw1',
            'algorithm': 'dummy_split'
        }
        job = GradingClustering(job_details)
        with tempfile.TemporaryDirectory() as tmp_dir:
            self.make_course_dir(tmp_dir, 's23', 'csci1100')
            with patch('submitty_jobs.jobs.DATA_DIR', tmp_dir):
                job.run_job()

        # Verify it invoked subprocess correctly
        mock_subprocess.assert_called_once()
        args, kwargs = mock_subprocess.call_args
        self.assertEqual(args[0][0], 'python3')
        self.assertIn('main.py', args[0][1])
        self.assertIn('grading_clustering', args[0][1])
        self.assertEqual(args[0][2:], ['s23', 'csci1100', 'hw1', 'dummy_split'])
        self.assertTrue(kwargs.get('capture_output'))
        self.assertTrue(kwargs.get('text'))

    @patch('submitty_jobs.jobs.subprocess.run')
    def test_job_run_failure_writes_status_file(self, mock_subprocess):
        mock_result = MagicMock()
        mock_result.returncode = 1
        mock_result.stderr = 'Custom script did not produce output.json'
        mock_result.stdout = ''
        mock_subprocess.return_value = mock_result

        job_details = {
            'semester': 's23',
            'course': 'csci1100',
            'gradeable': 'hw1',
            'algorithm': 'custom_upload',
            'script_path': '/path/to/script.py',
            'docker_image': 'continuumio/anaconda3:latest'
        }

        # Write into a real temp directory rather than mocking open(), so the
        # test exercises the actual path construction.
        with tempfile.TemporaryDirectory() as tmp_dir:
            self.make_course_dir(tmp_dir, 's23', 'csci1100')
            with patch('submitty_jobs.jobs.DATA_DIR', tmp_dir):
                job = GradingClustering(job_details)
                with self.assertRaises(subprocess.CalledProcessError):
                    job.run_job()

                status_file = Path(
                    tmp_dir, 'courses', 's23', 'csci1100', 'clustering', 'hw1', 'status.json'
                )
                self.assertTrue(status_file.exists())
                with open(str(status_file)) as f:
                    parsed = json.load(f)

        self.assertIn('error', parsed)
        self.assertIn('output.json', parsed['error'])

        # The status file must not live in the daemon job queue, which would
        # cause the queue watcher to consume it as a job.
        self.assertNotIn('daemon_job_queue', str(status_file))

    @patch('submitty_jobs.jobs.subprocess.run')
    def test_job_run_success_writes_status_file(self, mock_subprocess):
        mock_result = MagicMock()
        mock_result.returncode = 0
        mock_subprocess.return_value = mock_result

        job_details = {
            'semester': 's23',
            'course': 'csci1100',
            'gradeable': 'hw1',
            'algorithm': 'dummy_split'
        }

        with tempfile.TemporaryDirectory() as tmp_dir:
            self.make_course_dir(tmp_dir, 's23', 'csci1100')
            with patch('submitty_jobs.jobs.DATA_DIR', tmp_dir):
                GradingClustering(job_details).run_job()

                status_file = Path(
                    tmp_dir, 'courses', 's23', 'csci1100', 'clustering', 'hw1', 'status.json'
                )
                self.assertTrue(status_file.exists())
                with open(str(status_file)) as f:
                    parsed = json.load(f)

        self.assertNotIn('error', parsed)


if __name__ == '__main__':
    unittest.main()
