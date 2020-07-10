from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By


class TestPDFs(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_pdf_instructor(self):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[@href="http://localhost:1501/courses/s20/sample/gradeable/grading_homework/grading/status"]').click()
        self.driver.find_element_by_link_text("Grading Index").click()
        student_url = "http://localhost:1501/courses/s20/sample/gradeable/grading_homework/grading/grade?who_id=aufded&sort=id&direction=ASC"
        self.driver.find_element_by_xpath('//a[@href="'+student_url+'"]').click()
        self.driver.find_element_by_id('submissions').click()
        file_url = "/var/local/submitty/courses/s20/sample/submissions/grading_homework/dbFCC0LPYC3bAjc/1/part1_buggy2.py"
        self.driver.find_element_by_xpath('//a[@file-url="'+file_url+'"]').click()
        self.driver.find_element_by_id('pageContainer1')


if __name__ == "__main__":
    import unittest
    unittest.main()
