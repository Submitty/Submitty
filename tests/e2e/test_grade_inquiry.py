from .base_testcase import BaseTestCase
class TestGradeInquiry(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def set_grade_inquiries_for_course(self, allowed):
        # ensure that grade inquiries are enabled for the course
        self.driver.find_element_by_id("nav-sidebar-course-settings").click()
        regrade_enabled_checkbox = self.driver.find_element_by_xpath("//input[@id='regrade-enabled']")

        if not regrade_enabled_checkbox.is_selected() and allowed:
            regrade_enabled_checkbox.click()
        elif regrade_enabled_checkbox.is_selected() and not allowed:
            regrade_enabled_checkbox.click()
        # navigate back to gradeable page
        self.driver.find_element_by_id('nav-sidebar-submitty').click()

    def set_grade_inquiries_for_gradeable(self, gradeable_id, date=None, allowed=True):
        # ensure that grade inquiries are enabled for grades_released_homework gradeable
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[@name='edit gradeable configuration_button']").click()
        if allowed:
            self.driver.find_element_by_id("yes_regrade_allowed").click()
        else:
            self.driver.find_element_by_id("no_regrade_allowed").click()

        # set deadline
        if date is not None:
            self.driver.find_element_by_xpath("//a[text()='Dates']").click()
            self.driver.find_element_by_xpath("//input[@name='regrade_request_date']").send_keys(date)

        # navigate back to gradeable page
        self.driver.find_element_by_xpath("//a[@id='nav-sidebar-submitty']").click()

    def test_normal_submission_grade_inquiry_panel(self):
        gradeable_id = 'grades_released_homework'
        grade_inquiry_deadline_date = "9998-01-01 00:00:00"

        # login as instructor
        self.log_in(user_id='instructor', user_password='instructor', user_name='instructor')
        self.click_class('sample')
        self.set_grade_inquiries_for_course(True)
        self.set_grade_inquiries_for_gradeable(gradeable_id, True, grade_inquiry_deadline_date)

        # navigate to TA grading interface
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[contains(@class,'btn-nav-grade')]").click()
        self.driver.find_element_by_xpath("//a[contains(text(),'Grading Index')]").click()
        self.driver.find_element_by_xpath("//a[contains(@href,'grading/grade?who_id=bauchg')]").click()

        # make sure submit button is present and disabled because no text
        buttons = self.driver.find_elements_by_xpath("//button[contains(@class,'gi-submit')]")
        assert len(buttons) == 1
        assert buttons[0].text == "Submit Grade Inquiry"
