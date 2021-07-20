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
            self.assertTrue("Students who have submitted: 68 / 101 (67.3%)" in numerical_data_text)
            self.assertTrue("Current percentage of TA grading done: 31.25 / 68 (46.0%)" in numerical_data_text)
            self.assertTrue("Section 1: 3 / 9 (33.3%)" in numerical_data_text)
        else:
            self.assertTrue("Students who have submitted: 13 / 20 (65%)" in numerical_data_text)
            self.assertTrue("Current percentage of TA grading done: 6 / 13 (46.2%)" in numerical_data_text)
            self.assertTrue("Section 4: 4 / 6 (66.7%)" in numerical_data_text)
        self.log_out()
    def individual_released_stats_test_helper(self, user_id, full_access):
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grades_released_homework/grading/status")]').click()
        numerical_data_text = self.driver.find_element_by_id("numerical-data").text
        if full_access:
            self.assertTrue("Students who have submitted: 73 / 101 (72.3%)" in numerical_data_text)
            self.assertTrue("Current percentage of TA grading done: 72 / 73 (98.6%)" in numerical_data_text)
            self.assertTrue("Section 1: 12 / 12 (100.0%)" in numerical_data_text)
            self.assertTrue("Number of students who have viewed their grade: 50 / 73 (68.5%)" in numerical_data_text)
        else:
            self.assertTrue("Students who have submitted: 13 / 20 (65%)" in numerical_data_text)
            self.assertTrue("Current percentage of TA grading done: 13 / 13 (100.0%)" in numerical_data_text)
            self.assertTrue("Section 4: 8 / 8 (100.0%)" in numerical_data_text)
            self.assertTrue("Number of students who have viewed their grade: 8 / 13 (61.5%)" in numerical_data_text)            
        self.log_out()
    def team_grading_stats_test_helper(self, user_id, full_access):
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_team_homework/grading/status")]').click()
        numerical_data_text = self.driver.find_element_by_id("numerical-data").text
        if full_access:
            self.assertTrue("Students on a team: 101/101 (100%)" in numerical_data_text)
            self.assertTrue("Number of teams: 36" in numerical_data_text)
            self.assertTrue("Teams who have submitted: 32 / 36 (88.9%)" in numerical_data_text)
            self.assertTrue("Section 1: 3 / 5 (60.0%)" in numerical_data_text)
        else:
            self.assertTrue("Students on a team: 20/20 (100%)" in numerical_data_text)
            self.assertTrue("Number of teams: 8" in numerical_data_text)
            self.assertTrue("Teams who have submitted: 7 / 8 (87.5%)" in numerical_data_text)
            self.assertTrue("Section 4: 3 / 4 (75.0%)" in numerical_data_text)
        self.log_out()
    @unittest.skipUnless(os.environ.get('CI') is None, "cannot run in CI")
    def test_team_grading_stats(self):
        self.team_grading_stats_test_helper("instructor", True)
        self.team_grading_stats_test_helper("ta", True)
        self.team_grading_stats_test_helper("grader", False)
    @unittest.skipUnless(os.environ.get('CI') is None, "cannot run in CI")
    def test_individual_grading_stats(self):
        self.individual_grading_stats_test_helper("instructor", True)
        self.individual_grading_stats_test_helper("ta", True)
        self.individual_grading_stats_test_helper("grader", False)
    @unittest.skipUnless(os.environ.get('CI') is None, "cannot run in CI")
    def test_individual_released_stats(self):
        self.individual_released_stats_test_helper("instructor", True)
        self.individual_released_stats_test_helper("ta", True)
        self.individual_released_stats_test_helper("grader", False)
if __name__ == "__main__":
    import unittest
    unittest.main()
