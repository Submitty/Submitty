import json
from datetime import date

import os

from .base_testcase import BaseTestCase
from .base_testcase import LoginSession


class TestGraderAccess(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_grading_nologin(self):
        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                        ('gradeable_id', 'grading_homework')])
        # This is true if we're on the homepage
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                        ('action', 'details'), ('gradeable_id', 'grading_homework')])
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                        ('page', 'download_all_assigned'), ('dir', 'submissions'),
                        ('gradeable_id', 'grading_homework'), ('type', 'All')])
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                        ('page', 'download_all_assigned'), ('dir', 'submissions'),
                        ('gradeable_id', 'grading_homework')])
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                        ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                        ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
        self.assertEqual(1, len(self.driver.find_elements_by_name("stay_logged_in")))

        post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                ('page', 'electronic'), ('action', 'get_mark_data')],
                         data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker',
                               'gradeable_component_id': '30'})

        # This should give us the homepage, so json parse should fail
        self.assertRaises(Exception, json.loads, post.text)

        post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                ('page', 'electronic'), ('action', 'get_gradeable_comment')],
                         data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker'})
        self.assertRaises(Exception, json.loads, post.text)

        post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                ('page', 'electronic'), ('action', 'get_marked_users')],
                         data={'gradeable_id': 'grading_homework', 'gradeable_component_id': '30',
                               'order_num': '1'})
        self.assertRaises(Exception, json.loads, post.text)

    def test_grading_student(self):
        with LoginSession(self, "student", "student", "Joe"):
            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('gradeable_id', 'grading_homework')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'details'), ('gradeable_id', 'grading_homework')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework'), ('type', 'All')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
            self.expect_error()

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_mark_data')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker',
                                   'gradeable_component_id': '30'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_gradeable_comment')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_marked_users')],
                             data={'gradeable_id': 'grading_homework', 'gradeable_component_id': '30',
                                   'order_num': '1'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

    def test_grading_grader(self):
        with LoginSession(self, "grader", "grader", "Tim"):
            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'details'), ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework'), ('type', 'All')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework')])
            downloaded_file = os.path.join(self.download_dir,
                                           "grading_homework_section_students_{0:02}-{1:02}-{2:04}.zip".format(
                                               date.today().month, date.today().day, date.today().year))
            self.assertTrue(os.path.isfile(downloaded_file))
            os.unlink(downloaded_file)

            # Not in our section
            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
            self.expect_error()

            # Not in our section
            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_mark_data')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker',
                                   'gradeable_component_id': '30'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_gradeable_comment')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_marked_users')],
                             data={'gradeable_id': 'grading_homework', 'gradeable_component_id': '30',
                                   'order_num': '1'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "failure")

    def test_grading_ta(self):
        with LoginSession(self, "ta2", "ta2", "Jack"):
            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'details'), ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework'), ('type', 'All')])
            downloaded_file = os.path.join(self.download_dir,
                                           "grading_homework_all_students_{0:02}-{1:02}-{2:04}.zip".format(
                                               date.today().month, date.today().day, date.today().year))
            self.assertTrue(os.path.isfile(downloaded_file))
            os.unlink(downloaded_file)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework')])
            downloaded_file = os.path.join(self.download_dir,
                                           "grading_homework_section_students_{0:02}-{1:02}-{2:04}.zip".format(
                                               date.today().month, date.today().day, date.today().year))
            self.assertTrue(os.path.isfile(downloaded_file))
            os.unlink(downloaded_file)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
            self.expect_error(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
            self.expect_error(False)

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_mark_data')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker',
                                   'gradeable_component_id': '30'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_gradeable_comment')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_marked_users')],
                             data={'gradeable_id': 'grading_homework', 'gradeable_component_id': '30',
                                   'order_num': '1'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

    def test_grading_instructor(self):
        with LoginSession(self, "instructor", "instructor", "Quinn"):
            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'details'), ('gradeable_id', 'grading_homework')])
            self.expect_alert(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework'), ('type', 'All')])
            downloaded_file = os.path.join(self.download_dir,
                                           "grading_homework_all_students_{0:02}-{1:02}-{2:04}.zip".format(
                                               date.today().month, date.today().day, date.today().year))
            self.assertTrue(os.path.isfile(downloaded_file))
            os.unlink(downloaded_file)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'misc'),
                            ('page', 'download_all_assigned'), ('dir', 'submissions'),
                            ('gradeable_id', 'grading_homework')])
            downloaded_file = os.path.join(self.download_dir,
                                           "grading_homework_section_students_{0:02}-{1:02}-{2:04}.zip".format(
                                               date.today().month, date.today().day, date.today().year))
            self.assertTrue(os.path.isfile(downloaded_file))
            os.unlink(downloaded_file)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
            self.expect_error(False)

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
            self.expect_error(False)

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_mark_data')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker',
                                   'gradeable_component_id': '30'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_gradeable_comment')],
                             data={'gradeable_id': 'grading_homework', 'anon_id': 'aphacker'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

            post = self.post(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'),
                                    ('page', 'electronic'), ('action', 'get_marked_users')],
                             data={'gradeable_id': 'grading_homework', 'gradeable_component_id': '30',
                                   'order_num': '1'})

            response = json.loads(post.text)
            self.assertEqual(response['status'], "success")

    def expect_alert(self, expect=True, message=None, class_name="alert"):
        messages = self.driver.find_element_by_id("messages").find_elements_by_class_name(class_name)
        if expect:
            self.assertGreater(len(messages), 0)

            texts = [error.text.strip() for error in messages]
            if message is not None:
                self.assertIn(message, texts)
        else:
            self.assertEqual(len(messages), 0)

    def expect_error(self, expect=True, message=None):
        self.expect_alert(expect=expect, message=message, class_name="alert-error")

    def expect_notice(self, expect=True, message=None):
        self.expect_alert(expect=expect, message=message, class_name="alert-notice")

    def expect_success(self, expect=True, message=None):
        self.expect_alert(expect=expect, message=message, class_name="alert-success")


if __name__ == "__main__":
    import unittest

    unittest.main()
