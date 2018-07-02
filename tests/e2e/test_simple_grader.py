from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By

class TestSimpleGrader(BaseTestCase):
    
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

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
    def run_tests(self, lab_reg_func=None, test_reg_func=None, lab_rot_func=None, test_rot_func=None):
        def func_wrapper(func):
            def wrapped_func(gradeable_id, gradeable_name):
                self.click_nav_gradeable_button("items_being_graded", gradeable_id, "grade", (By.XPATH, "//div[@class='content']/h2[1][normalize-space(text())='{}']".format(gradeable_name)))
                func()
                self.click_header_link_text("sample", (By.XPATH, "//table[@class='gradeable_list']"))
            return wrapped_func if func is not None else lambda *args: None

        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class("sample", "SAMPLE")
        func_wrapper(lab_reg_func)("grading_lab", "Grading Lab")
        func_wrapper(test_reg_func)("grading_test", "Grading Test")
        func_wrapper(lab_rot_func)("grading_lab_rotating", "Grading Lab (Rotating Sections)")
        func_wrapper(test_rot_func)("grading_test_rotating", "Grading Test (Rotating Sections)")

    """
    TEST CASE FORMAT:

    def test_example:
        # func is called once selenium is on the grading page: preloading kwargs means it does not need any arguments
        def template_func():
            elem1 = self.driver.find_element_by_xpath("//div")
            self.assertEqual(elem.text, my_var1)
            elem2 = self.driver.find_element_by_xpath("//span")
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
            for tbody_elem in self.driver.find_elements_by_xpath("//div[@class='content']/table/tbody[not(starts-with(@id, 'section-'))]"):
                td_elem = tbody_elem.find_element_by_xpath("tr[@class='info persist-header']/td[1]")
                # check that the header text is correct
                self.assertEqual(expected_text, td_elem.text.strip()[:len(expected_text)])
                preceding_removed = td_elem.text.strip()[len(expected_text)+1:]
                if preceding_removed != "":
                    section_num = int(preceding_removed)
                    if prev_section_num is not None:
                        # check that the ordering is correct
                        self.assertTrue(prev_section_num < section_num)
                prev_section_num = section_num

        reg_func = self.insert_kwargs(template_func, expected_text="Students Enrolled in Registration Section")
        rot_func = self.insert_kwargs(template_func, expected_text="Students Assigned to Rotating Section")
        self.run_tests(reg_func, reg_func, rot_func, rot_func)



if __name__ == "__main__":
    import unittest
    unittest.main()
