from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import Select
import os
import unittest

class TestPDFs(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)
    @unittest.skipUnless(os.environ.get('TRAVIS_BUILD_DIR') is None, "cannot run in Travis-CI")
    def test_pdf_basic_access(self):
        self.switch_settings("Limited Access Grader")
        self.pdf_access("instructor", "Quinn", "aphacker", "grading_homework_pdf", "words_1463.pdf")
        self.pdf_access("ta", "Jill", "aphacker", "grading_homework_pdf", "words_1463.pdf")
        self.pdf_access("grader", "Tim", "jastm", "grading_homework_pdf", "words_1463.pdf")
    
    def test_pdf_basic_no_access(self):
        self.switch_settings("Instructor")
        self.pdf_no_access("ta", "Jill", "aphacker", "grading_homework_pdf", "words_1463.pdf")
        self.pdf_no_access("grader", "Tim", "jastm", "grading_homework_pdf", "words_1463.pdf")
        
    def test_peer_basic_access(self):
        self.log_in(user_id="student", user_name="Student")

    def test_pdf_instructor_basic_access(self):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_pdf/grading/status")]').click()
        self.driver.find_element_by_link_text("Grading Index").click()
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_pdf/grading/grade?who_id=bechta&sort=id&direction=ASC")]').click()
        self.driver.find_element_by_id('submissions').click()
        self.driver.find_element_by_xpath('//a[contains(@file-url,"words_249.pdf")]').click()
        self.driver.implicitly_wait(10)
        self.driver.find_element_by_id('pageContainer1')
    
    def switch_settings(self, setting):
        self.log_out()
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/grading_homework_pdf/update")]').click()
        self.driver.find_element_by_id('page_3_nav').click()
        select_element = Select(self.driver.find_element_by_id("minimum_grading_group"))
        select_element.select_by_visible_text(setting);
        self.log_out()
        self.driver.implicitly_wait(10)
        
    def pdf_access(self, user_id, user_name, student_id, gradeable_id, pdf_name):
        self.driver.get(self.test_url + "/authentication/login")
        self.log_in(user_id=user_id, user_name=user_name)
        self.click_class('sample')
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/'+gradeable_id+'/grading/status")]').click()
        self.driver.find_element_by_link_text("Grading Index").click()
        self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/'+gradeable_id+'/grading/grade?who_id='+student_id+'&sort=id&direction=ASC")]').click()
        self.driver.implicitly_wait(10)
        self.driver.find_element_by_id('submissions').click()
        self.driver.implicitly_wait(10)
        self.driver.find_element_by_xpath('//a[contains(@file-url,"'+pdf_name+'")]').click()
        self.driver.implicitly_wait(10)
        self.driver.find_element_by_id('pageContainer1')
        #pdf_url = '/courses/' + "f20" + "/sample/display_file?dir=submissions&file="+pdf_name+"&path=/var/local/submitty/courses/f20/sample/submissions/"+gradeable_id+"/"+student_id+"/1/"+pdf_name+"&ta_grading=true"
        #self.get(pdf_url)
        #self.driver.implicitly_wait(10)
        #print(self.driver.current_url)
        #text = self.driver.find_element_by_id("content").text
        #self.assertFalse("You don't have access to this page." in text)
        
        
    def pdf_no_access(self, user_id, user_name, student_id, gradeable_id, pdf_name):
        self.driver.get(self.test_url + "/authentication/login")
        self.log_in(user_id=user_id, user_name=user_name)
        self.click_class('sample')
        self.assertTrue(self.driver.find_element_by_xpath('//a[contains(@href,"/sample/gradeable/'+gradeable_id+'/grading/status")]') is None)  
        #pdf_url = self.test_url + '/courses/' + "f20" + "/sample/display_file?dir=submissions&file="+pdf_name+"&path=%2Fvar%2Flocal%2Fsubmitty%2Fcourses%2Ff20%2Fsample%2Fsubmissions%2F"+gradeable_id+"%2F"+student_id+"%2F1%2F"+pdf_name+"&ta_grading=true"
        #self.driver.get(pdf_url)
        #text = self.driver.find_element_by_id("content").text
        #self.assertTrue("You don't have access to this page." in text)
        self.driver.find_element_by_id('pageContainer1')

if __name__ == "__main__":
    import unittest
    unittest.main()
