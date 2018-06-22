from .base_testcase import BaseTestCase


class TestSimpleGrader(BaseTestCase):
    
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    # template for testing the header of a lab/numeric gradeable
    def header_testing(self, gradeable_id, gradeable_name, expected_text):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class("sample", "SAMPLE")
        self.click_nav_gradeable_button("items_being_graded", gradeable_id, "grade", gradeable_name)
        tbody_header_elems = self.driver.find_elements_by_xpath("//div[@class='content']/table/tbody[not(starts-with(@id, 'section-'))]")
        for tbody_elem in tbody_header_elems:
            td_elem = tbody_elem.find_element_by_xpath("tr[@class='info persist-header']/td[1]")
            self.assertEqual(expected_text, td_elem.text.strip()[:len(expected_text)])
        
    def test_lab_registration(self):
        self.header_testing("grading_lab", "Grading Lab", "Students Enrolled in Registration Section")
    
    def test_lab_rotating(self):
        self.header_testing("grading_lab_rotating", "Grading Lab (Rotating Sections)", "Students Assigned to Rotating Section")      
    
    def test_numeric_registration(self):
        self.header_testing("grading_test", "Grading Test", "Students Enrolled in Registration Section")
    
    def test_numeric_rotating(self):
        self.header_testing("grading_test_rotating", "Grading Test (Rotating Sections)", "Students Assigned to Rotating Section")
        

if __name__ == "__main__":
    import unittest
    unittest.main()
    
    