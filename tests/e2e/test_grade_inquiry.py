class TestGradeInquiry(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname)

    def set_grade_inquiries_for_course(self, allowed):
        # ensure that grade inquiries are enabled for the course
        self.driver.find_element_by_xpath("//a[@id='nav-sidebar-course-settings']").click()
        regrade_enabled_checkbox = self.driver.find_elements_by_find_element_by_xpath("//input[@id='regrade-enabled']")
        if not regrade_enabled_checkbox.checked() and allowed:
            regrade_enabled_checkbox.click()
        elif regrade_enabled_checkbox.checked() and not allowed:
            regrade_enabled_checkbox.click()
        # navigate back to gradeable page
        self.driver.find_element_by_xpath("//a[@id='nav-sidebar-submitty']").click()

    def set_grade_inquiries_for_gradeable(self, gradeable_id, allowed):
        # ensure that grade inquiries are enabled for grades_released_homework gradeable
        self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//div[@class='course_main']//a").click()
        regrade_enabled_checkbox = self.driver.find_elements_by_find_element_by_xpath("//input[@id='yes-regrade-allowed']")
        if not regrade_enabled_checkbox.checked() and allowed:
            regrade_enabled_checkbox.click()
        elif regrade_enabled_checkbox.checked() and not allowed:
            regrade_enabled_checkbox.click()
        # navigate back to gradeable page
        self.driver.find_element_by_xpath("//a[@id='nav-sidebar-submitty']").click()


    def test_grade_inquiry_panel(self):
        gradeable_id = 'grades_released_homework'
        # login as instructor
        self.login(user_id='instructor', password='instructor')
        self.set_grade_inquiries_for_course(self, True)
        self.set_grade_inquiries_for_gradeable(self, gradeable_id, True)

       # navigate to TA grading interface
       self.driver.find_element_by_xpath("//div[@id='"+gradeable_id+"']//a[@class='btn-nav-grade']").click()
       self.driver.find_element_by_xpath("//a[text()='Grading Index']").click()




