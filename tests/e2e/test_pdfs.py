from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By


class TestPDFs(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_pdf_instructor_basic_access(self):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_peer/grading/status")]').click()
        self.driver.find_element_by_link_text("Grading Index").click()
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_peer/grading/grade?who_id=bechta&sort=id&direction=ASC")]').click()
        self.driver.find_element_by_id('submissions').click()
        self.driver.find_element_by_xpath('//a[contains(@file-url,"words_249.pdf")]').click()
        self.driver.implicitly_wait(10)
        self.driver.find_element_by_id('pageContainer1')

if __name__ == "__main__":
    import unittest
    unittest.main()
