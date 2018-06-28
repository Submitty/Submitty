from datetime import date

import os

from .base_testcase import BaseTestCase
from .base_testcase import LoginSession


class TestGraderAccess(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_login_grading_student(self):
        with LoginSession(self, "student", "student", "Joe"):
            self.click_class("sample", "SAMPLE")
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

    def test_login_grading_grader(self):
        with LoginSession(self, "grader", "grader", "Tim"):
            self.click_class("sample", "SAMPLE")
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

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'aphacker')])
            self.expect_error()

            self.get(parts=[('semester', 's18'), ('course', 'sample'), ('component', 'grading'), ('page', 'electronic'),
                            ('action', 'grade'), ('gradeable_id', 'grading_homework'), ('who_id', 'instructor')])
            self.expect_error()

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
