from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
import os
import unittest

class TestStats(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)
    def individual_grading_stats_test_helper(self, user_id, full_access):
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework/grading/status")]').click()
        numerical_data_text = self.driver.find_element_by_id("numerical-data").text
        if full_access:
            self.assertTrue("Students who have submitted: 72 / 101 (71.3%)" in numerical_data_text)
            self.assertTrue("Current percentage of grading done: 43.75 / 72 (60.8%)" in numerical_data_text)
            self.assertTrue("Section 1: 4 / 12 (33.3%)" in numerical_data_text)
        else:
            self.assertTrue("Students who have submitted: 15 / 20 (75%)" in numerical_data_text)
            self.assertTrue("Current percentage of grading done: 9 / 15 (60.0%)" in numerical_data_text)
            self.assertTrue("Section 4: 4 / 8 (50.0%)" in numerical_data_text)
        self.log_out()
    def individual_released_stats_test_helper(self, user_id, full_access):
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grades_released_homework/grading/status")]').click()
        numerical_data_text = self.driver.find_element_by_id("numerical-data").text
        if full_access:
            self.assertTrue("Students who have submitted: 71 / 101 (70.3%)" in numerical_data_text)
            self.assertTrue("Current percentage of grading done: 71 / 71 (100.0%)" in numerical_data_text)
            self.assertTrue("Section 1: 8 / 8 (100.0%)" in numerical_data_text)
            self.assertTrue("Number of students who have viewed their grade: 53 / 71 (74.6%)" in numerical_data_text)
        else:
            self.assertTrue("Students who have submitted: 13 / 20 (65%)" in numerical_data_text)
            self.assertTrue("Current percentage of grading done: 13 / 13 (100.0%)" in numerical_data_text)
            self.assertTrue("Section 4: 5 / 5 (100.0%)" in numerical_data_text)
            self.assertTrue("Number of students who have viewed their grade: 12 / 13 (92.3%)" in numerical_data_text)            
        self.log_out()
    def team_grading_stats_test_helper(self, user_id, full_access):
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_team_homework/grading/status")]').click()
        numerical_data_text = self.driver.find_element_by_id("numerical-data").text
        if full_access:
            self.assertTrue("Students on a team: 101/101 (100%)" in numerical_data_text)
            self.assertTrue("Number of teams: 36" in numerical_data_text)
            self.assertTrue("Teams who have submitted: 28 / 36 (77.8%)" in numerical_data_text)
            self.assertTrue("Section 1: 4 / 5 (80.0%)" in numerical_data_text)
        else:
            self.assertTrue("Students on a team: 20/20 (100%)" in numerical_data_text)
            self.assertTrue("Number of teams: 8" in numerical_data_text)
            self.assertTrue("Teams who have submitted: 5 / 8 (62.5%)" in numerical_data_text)
            self.assertTrue("Section 4: 2 / 3 (66.7%)" in numerical_data_text)
        self.log_out()
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_team_grading_stats(self):
        self.team_grading_stats_test_helper("instructor", True)
        self.team_grading_stats_test_helper("ta", True)
        self.team_grading_stats_test_helper("grader", False)
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_individual_grading_stats(self):
        self.individual_grading_stats_test_helper("instructor", True)
        self.individual_grading_stats_test_helper("ta", True)
        self.individual_grading_stats_test_helper("grader", False)
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_individual_released_stats(self):
        self.individual_released_stats_test_helper("instructor", True)
        self.individual_released_stats_test_helper("ta", True)
        self.individual_released_stats_test_helper("grader", False)
if __name__ == "__main__":
    import unittest
    unittest.main()
