from .base_testcase import BaseTestCase
import os
import unittest
class TestGradeInquiry(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def set_grade_inquiries_for_course(self, allowed):
        # ensure that grade inquiries are enabled for the course
        self.driver.find_element_by_id("nav-sidebar-course-settings").click()
        regrade_enabled_checkbox = self.driver.find_element_by_id("regrade-enabled")

        if not regrade_enabled_checkbox.is_selected() and allowed:
            regrade_enabled_checkbox.click()
        elif regrade_enabled_checkbox.is_selected() and not allowed:
            regrade_enabled_checkbox.click()
        # navigate back to gradeable page
        self.driver.find_element_by_id('nav-sidebar-submitty').click()

    def set_grade_inquiries_for_gradeable(self, gradeable_id, date=None, allowed=True):
        # ensure that grade inquiries are enabled for grades_released_homework gradeable
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//*[@name='edit gradeable configuration_button']").click()
        if allowed:
            self.driver.find_element_by_id("yes_regrade_allowed").click()
        else:
            self.driver.find_element_by_id("no_regrade_allowed").click()

        # set deadline
        if date is not None:
            self.driver.find_element_by_xpath("//a[text()='Dates']").click()
            self.driver.find_element_by_name("regrade_request_date").send_keys(date)

        # navigate back to gradeable page
        self.driver.find_element_by_id("nav-sidebar-submitty").click()

    # TA GRADING INTERFACE TESTS
    # travis should not run this
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_normal_submission_grade_inquiry_panel(self):
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"

        # login as instructor
        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id, grade_inquiry_deadline_date, allowed=True)

        # navigate to TA grading interface of student with normal submission
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element_by_xpath("//a[contains(@href,'grading/grade?who_id=bauchg')]").click()

        # make sure submit button is present
        buttons = self.driver.find_elements_by_xpath("//*[contains(@class,'gi-submit')]")
        assert len(buttons) == 1
        assert buttons[0].text == "Submit Grade Inquiry"

    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_no_submission_grade_inquiry_panel(self):
        gradeable_id = 'grades_released_homework'
        # login as instructor
        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id,allowed=True)

        # navigate to TA grading interface of student with no submission
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element_by_xpath("//a[contains(@href,'grading/grade?who_id=lakinh')]").click()

        try:
           self.driver.find_element_by_xpath("//div[@id='regrade_info']//*[text()='No Submission']")
        except NoSuchElementException:
           assert False
        assert True
        buttons = self.driver.find_elements_by_xpath("//button[contains(@class,'gi-submit')]")
        assert len(buttons) == 0

    # STUDENT SUBMISSION TESTS
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_normal_submission_student_grade_inquiry_box(self):
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"

        self.log_in(user_id='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id,allowed=True)

        self.log_out()
        self.log_in(user_id='bauchg')
        self.click_class('sample')

        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[contains(text(),'VIEW GRADE')]").click()

        assert not self.driver.find_element_by_id("regradeBoxSection").is_displayed()
        open_grade_inquiry_button = self.driver.find_element_by_xpath("//button[contains(text(),'Open Grade Inquiry')]")
        open_grade_inquiry_button.click()
        assert not open_grade_inquiry_button.is_displayed()

        # make sure submit button is present
        buttons = self.driver.find_elements_by_xpath("//button[contains(@class,'gi-submit')]")
        assert len(buttons) == 1
        assert buttons[0].text == "Submit Grade Inquiry"
