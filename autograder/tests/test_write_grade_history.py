"""Tests for the write_grade_history module."""

from collections import OrderedDict
import json
from pathlib import Path
from tempfile import TemporaryDirectory
import unittest

from autograder import autograding_utils


class TestWriteGradeHistory(unittest.TestCase):
    """Unittest TestCase."""

    def test_write_history(self):
        """
        Test writing the information to a json file.

        If the file does not exist, create it to write to, else
        read from the file, append the information, and then
        write the full array out. The order of the elements of
        the json file is important.
        """
        with TemporaryDirectory() as tmpdirname:
            history_file = Path(tmpdirname, 'history.json')
            autograding_utils.just_write_grade_history(
                str(history_file),
                "2019-05-23 23:59:59-0400",
                "2019-05-23 20:39:12-0400",
                0,
                "2019-05-23 23:59:49-0400", # first access
                10, # access_duration
                "2019-05-23 20:39:12-0400",
                "",
                "2019-05-23 20:39:32-0400",
                20,
                "2019-05-23 20:39:55-0400",
                23,
                "Automatic grading total: 25 / 30",
                1
            )

            expected = []

            expected.append(OrderedDict({
                'assignment_deadline': '2019-05-23 23:59:59-0400',
                'submission_time': '2019-05-23 20:39:12-0400',
                'queue_time': '2019-05-23 20:39:12-0400',
                'batch_regrade': False,
                'first_access_time': '2019-05-23 23:59:49-0400',
                'access_duration': 10,
                'grading_began': '2019-05-23 20:39:32-0400',
                'wait_time': 20,
                'grading_finished': '2019-05-23 20:39:55-0400',
                'grade_time': 23,
                'autograde_result': 'Automatic grading total: 25 / 30',
                'autograde_total': 25,
                'autograde_max_possible': 30,
                'revision': 1
            }))

            with history_file.open() as open_file:
                actual = json.load(open_file, object_pairs_hook=OrderedDict)

            self.assertEqual(len(expected), len(actual))
            for i in range(len(expected)):
                self.assertEqual(expected[i], actual[i])

            autograding_utils.just_write_grade_history(
                str(history_file),
                "2019-05-23 23:59:59-0400",
                "2019-05-23 20:47:12-0400",
                0,
                "2019-05-23 23:59:49-0400", # first access
                10, # access_duration
                "2019-05-23 20:47:12-0400",
                "",
                "2019-05-23 20:47:32-0400",
                20,
                "2019-05-23 20:47:55-0400",
                23,
                "Automatic grading total: 30 / 30",
                2
            )

            expected.append(OrderedDict({
                'assignment_deadline': '2019-05-23 23:59:59-0400',
                'submission_time': '2019-05-23 20:47:12-0400',
                'queue_time': '2019-05-23 20:47:12-0400',
                'batch_regrade': False,
                'first_access_time': '2019-05-23 23:59:49-0400',
                'access_duration': 10,
                'grading_began': '2019-05-23 20:47:32-0400',
                'wait_time': 20,
                'grading_finished': '2019-05-23 20:47:55-0400',
                'grade_time': 23,
                'autograde_result': 'Automatic grading total: 30 / 30',
                'autograde_total': 30,
                'autograde_max_possible': 30,
                'revision': 2
            }))

            with history_file.open() as open_file:
                actual = json.load(open_file, object_pairs_hook=OrderedDict)

            self.assertEqual(len(expected), len(actual))
            for i in range(len(expected)):
                self.assertDictEqual(expected[i], actual[i])

    def test_late_submission(self):
        """
        Test non-zero seconds argument.

        This should write out an integer to the json file that is rounded up
        to the nearest day.
        """
        with TemporaryDirectory() as tmpdirname:
            history_file = Path(tmpdirname, 'history.json')
            autograding_utils.just_write_grade_history(
                str(history_file),
                "2019-05-23 23:59:59-0400",
                "2019-05-28 20:39:12-0400",
                301451,  # 3 days, 11 hours, 44 minutes, 11 seconds
                "2019-05-23 20:39:02-0400", #first access
                10, # access_duration
                "2019-05-28 20:39:12-0400",
                "",
                "2019-05-28 20:39:32-0400",
                20,
                "2019-05-28 20:39:55-0400",
                23,
                "Automatic grading total: 25 / 30",
                1
            )

            expected = []
            expected.append(OrderedDict({
                'assignment_deadline': '2019-05-23 23:59:59-0400',
                'submission_time': '2019-05-28 20:39:12-0400',
                'days_late_before_extensions': 4,
                'queue_time': '2019-05-28 20:39:12-0400',
                'batch_regrade': False,
                'first_access_time': '2019-05-23 20:39:02-0400',
                'access_duration': 10,
                'grading_began': '2019-05-28 20:39:32-0400',
                'wait_time': 20,
                'grading_finished': '2019-05-28 20:39:55-0400',
                'grade_time': 23,
                'autograde_result': 'Automatic grading total: 25 / 30',
                'autograde_total': 25,
                'autograde_max_possible': 30,
                'revision': 1
            }))

            with history_file.open() as open_file:
                actual = json.load(open_file, object_pairs_hook=OrderedDict)

            self.assertEqual(len(expected), len(actual))
            for i in range(len(expected)):
                self.assertDictEqual(expected[i], actual[i])
