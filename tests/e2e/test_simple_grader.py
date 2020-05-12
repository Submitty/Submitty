from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.common.keys import Keys
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC


class TestSimpleGrader(BaseTestCase):

    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    @staticmethod
    def get_next_lab_value(start_value):
        if start_value == "0":
            return "1"
        elif start_value == "1":
            return "0.5"
        else:
            return "0"


    # remove the need to pass pesky arguments from func
    # simplifies the writing of test cases, as variable names can be used instead of accessing kwargs
    def insert_kwargs(self, func, **kwargs):
        def func_with_args():
            for key in kwargs:
                globals()[key] = kwargs[key]
            func()
            for key in kwargs:
                del globals()[key]
        return func_with_args

    # template for creating simplegrader tests that test all
    def run_tests(self, lab_reg_func=None, test_reg_func=None, lab_rot_func=None, test_rot_func=None, users=[("instructor", "Quinn")]):
        def func_wrapper(func):
            def wrapped_func(gradeable_id, gradeable_name):
                self.click_nav_grade_button("items_being_graded", gradeable_id, "grade", (By.XPATH, "//div[@class='content']/h1[1][normalize-space(text())='{}']".format(gradeable_name)))
                func()
                self.click_header_link_text("sample", (By.XPATH, "//h1[text()='Gradeables']"))
            return wrapped_func if func is not None else lambda *args: None

        for user in users:
            self.log_in(user_id=user[0], user_name=user[1])
            self.click_class("sample", "SAMPLE")
            func_wrapper(lab_reg_func)("grading_lab", "Grading Lab")
            func_wrapper(test_reg_func)("grading_test", "Grading Test")
            func_wrapper(lab_rot_func)("grading_lab_rotating", "Grading Lab (Rotating Sections)")
            func_wrapper(test_rot_func)("grading_test_rotating", "Grading Test (Rotating Sections)")
            self.log_out()

    """
    TEST CASE FORMAT:

    def test_example:
        # func is called once selenium is on the grading page: preloading kwargs means it does not need any arguments
        def template_func():
            elem1 = self.driver.find_element(By.XPATH, "//div")
            self.assertEqual(elem.text, my_var1)
            elem2 = self.driver.find_element(By.XPATH, "//span")
            self.assertEqual(elem.text, my_var2)

        # insert the kwargs for each function
        lab_reg_func = self.insert_kwargs(template_func, my_var1="div text for lab w/ registration sections", my_var2="span text for lab w/ registration sections")
        test_reg_func = self.insert_kwargs(template_func, my_var1="div text for test w/ registration sections", my_var2="span text for test w/ registration sections")
        lab_rot_func = self.insert_kwargs(template_func, my_var1="div text for lab w/ rotating sections", my_var2="span text for lab w/ rotating sections")
        test_rot_func = self.insert_kwargs(template_func, my_var1="div text for test w/ rotating sections", my_var2="span text for test w/ rotating sections")

        # then call run_tests on the function
        self.run_tests(lab_reg_func, test_reg_func, lab_rot_func, test_rot_func)


    Writing tests this way means that all that need be done is to write templates for the different kinds of tests, and then all can be run from that
    """

    # tests that the headers are correct for rotating and registration gradeables, as well as the ordering
    def test_header_text(self):
        def template_func():
            prev_section_num = None
            for tbody_elem in self.driver.find_elements(By.XPATH, "//div[@class='content']/table/tbody[not(starts-with(@id, 'section-'))]"):
                td_elem = tbody_elem.find_element(By.XPATH, "tr[@class='info']/td[1]")
                # check that the header text is correct
                self.assertEqual(expected_text, td_elem.text.strip()[:len(expected_text)])
                preceding_removed = td_elem.text.strip()[len(expected_text)+1:]
                if preceding_removed != "NULL":
                    section_num = preceding_removed.split()[0]
                    if(section_num == "NULL"):
                        continue
                    section_num = int(section_num)
                    if prev_section_num is not None:
                        # check that the ordering is correct
                        self.assertTrue(prev_section_num < section_num)
                    prev_section_num = section_num

        reg_func = self.insert_kwargs(template_func, expected_text="Students Enrolled in Registration Section")
        rot_func = self.insert_kwargs(template_func, expected_text="Students Assigned to Rotating Section")
        self.run_tests(reg_func, reg_func, rot_func, rot_func)

    # tests that the different people can grade the same cell (this has broken multiple times in the past)
    def test_multiple_graders(self):
        def template_func():
            self.driver.refresh()
            # grade the first cell (as good as any other)
            grade_elem = self.driver.find_element(By.ID, "cell-0-0")
            # attribute where data is stored is different for lab/numeric
            attribute = "data-score" if is_lab else "value"
            score = grade_elem.get_attribute(attribute)
            # for lab, cycle the value
            if is_lab:
                next_score = TestSimpleGrader.get_next_lab_value(score)
                grade_elem.click()
            # for numeric, manually cycle using values that are not generated for sample courses
            else:
                next_score = "3.4" if score == "3.1" else "3.1"
                grade_elem.clear()
                grade_elem.send_keys(next_score)
                grade_elem.send_keys(Keys.ARROW_RIGHT)
            # wait until ajax is done, then refresh the page and wait until the element comes back with the updated data
            self.wait_after_ajax()
            self.driver.refresh()
            WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//*[@id='cell-0-0' and @{}='{}']".format(attribute, next_score))))
            # if numeric, reset value so that test will continue to work as expected
            if next_score == "3.4":
                grade_elem = self.driver.find_element(By.ID, "cell-0-0")
                grade_elem.clear()
                grade_elem.send_keys("3.3")
                grade_elem.send_keys(Keys.ARROW_RIGHT)

        lab_func = self.insert_kwargs(template_func, is_lab=True)
        test_func = self.insert_kwargs(template_func, is_lab=False)
        self.run_tests(lab_func, test_func, lab_func, test_func, users=[("instructor", "Quinn"), ("ta", "Jill")])

if __name__ == "__main__":
    import unittest
    unittest.main()
