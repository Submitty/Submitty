from .base_testcase import BaseTestCase
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By
import os


CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))


class TestSubmission(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def setup_test_start(self):
        self.log_in()
        self.click_class("sample", "SAMPLE")
        self.click_nav_gradeable_button("open", "open_homework", "resubmit", (By.XPATH, "//div[@class='content']/div[1]/h2[1][normalize-space(text())='New submission for: Open Homework']"))

    def create_file_paths(self, multiple=False):
        examples_path = os.path.abspath(os.path.join(CURRENT_PATH, "..", "..", "more_autograding_examples"))
        file_paths = [os.path.join(examples_path,"python_simple_homework", "submissions", "infinite_loop_too_much_output.py")]
        if multiple:
            return file_paths + [os.path.join(examples_path,"python_simple_homework", "submissions", "part1.py")]
        else:
            return file_paths

    # drag and drop script inspired by https://stackoverflow.com/a/11203629
    def input_files(self, file_paths=[], drag_and_drop=False, target_id="upload1"):
        if drag_and_drop:
            # create an input element of type files
            self.driver.execute_script("seleniumUpload = window.$('<input/>').attr({id: 'seleniumUpload', type:'file', multiple:'', style: 'display: none'}).appendTo('body');")
            upload_element = self.driver.find_element_by_id("seleniumUpload")
        else:
            upload_element = self.driver.find_element_by_id(target_id).find_element_by_xpath("//input[@type='file']")
        # send all the files to the element as args
        upload_element.send_keys("\n".join(file_paths))
        if drag_and_drop:
            # simulate the drop event for the files
            self.driver.execute_script("e = document.createEvent('HTMLEvents'); e.initEvent('drop', true, true); e.dataTransfer = {{files: seleniumUpload.get(0).files }}; document.getElementById('{}').dispatchEvent(e);".format(target_id))

    # returns the number of submissions either
    def get_submission_count(self, include_zero=False):
        return len(self.driver.find_elements_by_xpath("//div[@class='content']/select/option"+(""if include_zero else"[not(@value='0')]")))

    def make_submission(self, file_paths=[], drag_and_drop=False, target_id="upload1"):
        # get the starting submission count
        submission_count = self.get_submission_count()
        # input and submit the files
        self.input_files(file_paths, drag_and_drop, target_id)
        self.driver.find_element_by_id("submit").click()
        # wait until there are no more files in the submission box before moving on to make sure files are submitted properly
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@id='{}' and count(label[@class='mylabel'])=0]".format(target_id))))
        # make sure the submission count has increased
        self.assertEqual(submission_count+1, self.get_submission_count())
        file_names = { os.path.basename(file_path) for file_path in file_paths }
        submitted_files_text = self.driver.find_element_by_xpath("//div[@id='container']/div[@class='content']/div[@class='sub'][2]/div[@class='box half'][1]").text
        for submitted_file_text in submitted_files_text.split("\n"):
            idx = submitted_file_text.rfind('(')
            file_name = submitted_file_text[:idx-1]
            file_names.remove(file_name)
        self.assertEqual(0, len(file_names))
    
    def change_submission_version(self):
        # find the version selection dropdown and click
        version_select_elem = self.driver.find_element_by_xpath("//div[@class='content']/select")
        version_select_elem.click()
        # find an unselected version and click
        new_version_elem = version_select_elem.find_element_by_xpath("//option[not(@selected) and not(@value='0')]")
        new_version = new_version_elem.get_attribute("value")
        new_version_elem.click()
        # wait until the page reloads to change the selected version, then click the "Grade This Version" button
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@class='content']/select/option[@value='{}' and @selected]".format(new_version))))
        self.driver.find_element_by_xpath("//div[@class='content']/form/input[@type='submit' and @id='version_change']").click()
        # wait until the page reloads to change the active version, completing the test
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@class='content']/select/option[@value='{}' and @selected and substring(text(), string-length(text())-17)='GRADE THIS VERSION']".format(new_version))))        
    
    # for test cases that require switching versions, make submissions to ensure they will
    def ensure_multiple_versions(self):
        if self.get_submission_count() < 1:
            self.make_submission(self.create_file_paths())
        if self.get_submission_count() < 2:
            self.make_submission(self.create_file_paths(True))


    def test_normal_upload(self):
        self.setup_test_start()
        self.make_submission(self.create_file_paths())

    def test_drag_and_drop_upload(self):
        self.setup_test_start()
        self.make_submission(self.create_file_paths(), True)

    def test_normal_upload_multiple(self):
        self.setup_test_start()
        self.make_submission(self.create_file_paths(True))

    def test_drag_and_drop_upload_multiple(self):
        self.setup_test_start()
        self.make_submission(self.create_file_paths(True), True)

    def test_change_submission_version(self):
        self.setup_test_start()
        # ensure that there are multiple versions so that switching is possible
        self.ensure_multiple_versions()
        # test changing the submission version
        self.change_submission_version()

    def test_cancel_submission_version(self):
        self.setup_test_start()
        # click button and wait until page reloads to cancel version
        self.driver.find_element_by_xpath("//div[@class='content']/form/input[@type='submit' and @id='do_not_grade']").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@class='content']/select/option[@value='0' and @selected]")))
        # change back to a valid submission version
        self.change_submission_version()


if __name__ == "__main__":
    import unittest
    unittest.main()
