from .base_testcase import BaseTestCase
import os
import unittest

from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By


class TestGradeInquiry(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def set_grade_inquiries_for_course(self, allowed):
        # ensure that grade inquiries are enabled for the course
        self.driver.find_element(By.ID, "nav-sidebar-course-settings").click()
        regrade_enabled_checkbox = self.driver.find_element(By.ID, "regrade-enabled")

        if not regrade_enabled_checkbox.is_selected() and allowed:
            regrade_enabled_checkbox.click()
        elif regrade_enabled_checkbox.is_selected() and not allowed:
            regrade_enabled_checkbox.click()
        # navigate back to gradeable page
        self.driver.find_element(By.ID, 'nav-sidebar-submitty').click()

    def set_grade_inquiries_for_gradeable(self, gradeable_id, date=None, allowed=True):
        # ensure that grade inquiries are enabled for grades_released_homework gradeable
        self.driver.find_element(By.XPATH, "//div[@id='"+gradeable_id+"']//*[contains(@class, 'fa-pencil-alt')]").click()

        if allowed:
            self.driver.find_element(By.ID, "yes_regrade_allowed").click()
        else:
            self.driver.find_element(By.ID, "no_regrade_allowed").click()

        # set deadline
        if date is not None:
            self.driver.find_element(By.XPATH, "//a[text()='Dates']").click()

            grade_inquiry_date_input = self.driver.find_element(By.NAME, "regrade_request_date")
            # wait for flatpickr to appear
            grade_inquiry_date_input.click()
            wait = WebDriverWait(self.driver, self.WAIT_TIME)
            wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "#date_regrade_request.active")))
            # then input new date
            grade_inquiry_date_input.clear()
            grade_inquiry_date_input.send_keys(date,Keys.ENTER)

        # navigate back to gradeable page
        self.driver.find_element(By.ID, "nav-sidebar-submitty").click()

    # TA GRADING INTERFACE TESTS
    # travis should not run this
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_normal_submission_grade_inquiry_panel(self):
        # makes sure that default ta grading panel is correct
        # user bauchg should have a submission and no grade inquiries
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"

        # login as instructor
        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id, date=grade_inquiry_deadline_date, allowed=True)

        # navigate to TA grading interface of student with normal submission
        self.driver.find_element(By.XPATH, "//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(@href,'grading/grade?who_id=bauchg')]").click()

        # make sure submit button is present
        buttons = self.driver.find_elements(By.XPATH, "//*[contains(@class,'gi-submit')]")
        assert len(buttons) == 1
        assert buttons[0].text == "Submit Grade Inquiry"

    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_no_submission_grade_inquiry_panel(self):
        # makes sure that it is made clear to the use that there is no submission
        # and no grade inquiry can be made
        # user likinh should have no submission
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"
        # login as instructor
        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id,date=grade_inquiry_deadline_date,allowed=True)

        # navigate to TA grading interface of student with no submission
        self.driver.find_element(By.XPATH, "//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(@href,'grading/grade?who_id=lakinh')]").click()

        try:
           self.driver.find_element(By.XPATH, "//div[@id='regrade_info']//*[text()='No Submission']")
        except NoSuchElementException:
           assert False
        assert True
        buttons = self.driver.find_elements(By.XPATH, "//button[contains(@class,'gi-submit')]")
        assert len(buttons) == 0

    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_after_grade_inquiry_deadline_no_previous_inquiry(self):
        # This test makes sure that a gradeable with no grade inquiry has no
        # buttons or forms when it is currently past the grade inquiry deadline
        # user bauchg should have a submission and no grade inquiries
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "1974-01-01 23:59:59"
        # login as instructor
        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id,date=grade_inquiry_deadline_date,allowed=True)

        # navigate to TA grading interface of student with no inquiry
        self.driver.find_element(By.XPATH, "//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element(By.XPATH, "//a[contains(@href,'grading/grade?who_id=bauchg')]").click()

        # There should be no forms or buttons
        forms = self.driver.find_elements(By.XPATH, "//form[contains(@class,'reply-text-form')]")
        assert len(forms) == 0
        buttons = self.driver.find_elements(By.XPATH, "//form[contains(@class,'gi-submit')]")
        assert len(buttons) == 0


    # STUDENT SUBMISSION TESTS
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_normal_submission_student_grade_inquiry_box(self):
        # makes sure default student view is correct
        # user bauchg should have a submission and no grade inquiries
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"

        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id,date=grade_inquiry_deadline_date,allowed=True)

        self.log_out()
        self.log_in(user_id='bauchg')
        self.click_class('sample')

        self.driver.find_element(By.XPATH, "//div[@id='"+gradeable_id+"']//a[contains(text(),'VIEW GRADE')]").click()

        assert not self.driver.find_element(By.ID, "regradeBoxSection").is_displayed()
        open_grade_inquiry_button = self.driver.find_element(By.XPATH, "//button[contains(text(),'Open Grade Inquiry')]")
        open_grade_inquiry_button.click()
        assert not open_grade_inquiry_button.is_displayed()

        # make sure submit button is present
        buttons = self.driver.find_elements(By.XPATH, "//button[contains(@class,'gi-submit')]")
        assert len(buttons) == 1
        assert buttons[0].text == "Submit Grade Inquiry"
