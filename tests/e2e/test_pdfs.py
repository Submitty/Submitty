from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
import os
import unittest
import time
class TestPDFs(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_pdf_basic_access(self):
        self.switch_settings("Limited Access Grader")
        self.pdf_access("instructor", "bechta", "grading_homework_pdf", "words_249.pdf", "2")
        self.driver.implicitly_wait(20)
        self.pdf_access("ta", "bechta", "grading_homework_pdf", "words_249.pdf", "2")
        self.driver.implicitly_wait(20)
        self.pdf_access("grader", "jastm", "grading_homework_pdf", "words_881.pdf", "1")
        self.driver.implicitly_wait(20)
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_pdf_team_access(self):
        self.switch_settings("Limited Access Grader")
        self.pdf_access("instructor", "00002_aphacker", "grading_homework_team_pdf", "words_881.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("ta", "00002_aphacker", "grading_homework_team_pdf", "words_881.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("grader", "00020_kovaco", "grading_homework_team_pdf", "words_881.pdf", "1")
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")    
    def test_pdf_peer_access(self):
        self.switch_settings("Limited Access Grader")
        self.pdf_access("instructor", "bechta", "grading_pdf_peer_homework", "words_249.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("ta", "bechta", "grading_pdf_peer_homework", "words_249.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("grader", "jastm", "grading_pdf_peer_homework", "words_1463.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("student", "aphacker", "grading_pdf_peer_homework", "words_1463.pdf", "1")
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")    
    def test_pdf_peer_team_access(self):
        self.switch_settings("Limited Access Grader")
        self.pdf_access("instructor", "00006_bechta", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("ta", "00006_bechta", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("grader", "00022_kovaco", "grading_pdf_peer_team_homework", "words_1463.pdf", "1")
        self.driver.implicitly_wait(20)
        self.pdf_access("bauchg", "9R5ArOhSmQMlHHc", "grading_pdf_peer_team_homework", "words_881.pdf", "1")
    def pdf_access(self, user_id, student_id, gradeable_id, pdf_name, version):
        self.log_out()
        self.log_in(user_id=user_id)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/'+gradeable_id+'/grading/status")]').click()
        self.driver.find_element_by_link_text("Grading Index").click()
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/'+gradeable_id+'/grading/grade?who_id='+student_id+'&sort=id&direction=ASC")]').click()
        self.driver.find_element_by_id('submissions').click()
        current_window = self.driver.window_handles[0]
        print(current_window)
        self.driver.find_element_by_id('open_file_'+pdf_name).click()
        time.sleep(5)
        new_window = self.driver.window_handles[1]
        self.driver.switch_to.window(new_window)
        #text = self.driver.find_element_by_id("content")
        #self.assertFalse("You don't have access to this page." in text)
        self.driver.close()
        self.driver.switch_to.window(current_window)
        self.driver.find_element_by_xpath('//a[contains(@file-url,"'+pdf_name+'")]').click()
        self.driver.implicitly_wait(20)
        self.driver.find_element_by_id('pageContainer1')
    def switch_settings(self, setting):
        self.log_out()
        self.log_in(user_id="instructor")
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_pdf/update")]').click()
        self.driver.find_element_by_id('page_3_nav').click()
        select_element = Select(self.driver.find_element_by_id("minimum_grading_group"))
        select_element.select_by_visible_text(setting);
        self.log_out()
        self.driver.implicitly_wait(20)

if __name__ == "__main__":
    import unittest
    unittest.main()
