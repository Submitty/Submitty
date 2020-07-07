import os
from unittest import skipIf

from .base_testcase import BaseTestCase
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.action_chains import ActionChains
from selenium.webdriver.common.by import By
from selenium.common.exceptions import TimeoutException

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))


class TestSubmission(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def setup_test_start(self, gradeable_category="open", gradeable_id="open_homework", button_name="submit", loaded_selector=(By.XPATH, "//h1[1][normalize-space(text())='New submission for: Open Homework']")):
        self.log_in()
        self.click_class("sample", "SAMPLE")
        self.click_nav_submit_button(gradeable_category, gradeable_id, button_name, loaded_selector)

    def create_file_paths(self, multiple=False, autograding=False):
        examples_path = os.path.abspath(os.path.join(CURRENT_PATH, "..", "..", "more_autograding_examples"))
        if autograding:
            file_paths = [os.path.join(examples_path, "cpp_hidden_tests", "submissions", "frame.cpp")]
            if multiple:
                return file_paths + [os.path.join(examples_path, "cpp_hidden_tests", "submissions", "frame_buggy.cpp")]
            else:
                return file_paths
        else:
            file_paths = [os.path.join(examples_path, "python_simple_homework", "submissions", "infinite_loop_too_much_output.py")]
            if multiple:
                return file_paths + [os.path.join(examples_path, "python_simple_homework", "submissions", "part1.py")]
            else:
                return file_paths

    # drag and drop script inspired by https://stackoverflow.com/a/11203629
    def input_files(self, file_paths=[], drag_and_drop=False, target_id="upload1"):
        if drag_and_drop:
            # create an input element of type files
            self.driver.execute_script("seleniumUpload = window.$('<input/>').attr({id: 'seleniumUpload', type:'file', multiple:'', style: 'display: none'}).appendTo('body');")
            upload_element = self.driver.find_element(By.ID, "seleniumUpload")
        else:
            upload_element = self.driver.find_element(By.ID, target_id).find_element(By.XPATH, "//input[@type='file']")
        # send all the files to the element as args
        upload_element.send_keys("\n".join(file_paths))
        if drag_and_drop:
            # simulate the drop event for the files
            self.driver.execute_script("e = document.createEvent('HTMLEvents'); e.initEvent('drop', true, true); e.dataTransfer = {{files: seleniumUpload.get(0).files }}; document.getElementById('{}').dispatchEvent(e);".format(target_id))

    # returns the number of submissions
    def get_submission_count(self, include_zero=False):
        return len(self.driver.find_elements(By.XPATH, "//div[@class='content']/div[@id='version-cont']/select/option"+(""if include_zero else"[not(@value='0')]")))

    def accept_alerts(self, num_alerts):
        try:
            for i in range(num_alerts):
                WebDriverWait(self.driver, 2).until(EC.alert_is_present())
                self.driver.switch_to.alert.accept()
        except TimeoutException as ex:
            pass

    def make_submission(self, file_paths=[], drag_and_drop=False, target_id="upload1", autograding=False):
        # get the starting submission count
        submission_count = self.get_submission_count()

        # clear the previous submission files (if any)
        clear_btn = self.driver.find_element(By.ID, "startnew")
        if clear_btn.is_enabled():
            clear_btn.click()

        # input and submit the files
        self.input_files(file_paths, drag_and_drop, target_id)
        self.driver.find_element(By.ID, "submit").click()

        # accept submission and late day limit alerts
        self.accept_alerts(2)

        # Making sure that the files are submitted properly by waiting for the submission success popup (inner message)
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@id='success-0']")))

        # make sure the submission count has increased
        self.assertEqual(submission_count+1, self.get_submission_count())

        # create a set of file names and compare them to the displayed submitted files
        file_names = {os.path.basename(file_path) for file_path in file_paths}
        submitted_files_text = self.driver.find_element(By.ID, 'submitted-files').text
        for submitted_file_text in submitted_files_text.strip().split("\n"):
            idx = submitted_file_text.rfind('(')
            file_name = submitted_file_text[:idx-1].strip()
            file_names.discard(file_name)
        self.assertEqual(0, len(file_names))

        # wait for the autograding results to finish. Refresh the page intermittently to (hopefully) load autograding results
        if autograding:
            autograding_done = False
            for i in range(6):
                try:
                    WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[div/@id='tc_0']")))
                    autograding_done = True
                except TimeoutException as ex:
                    self.driver.refresh()
            self.assertTrue(autograding_done)

    def change_submission_version(self):
        # find the version selection dropdown and click
        version_select_elem = self.driver.find_element(By.XPATH, "//div[@class='content']/div[@id='version-cont']/select")
        version_select_elem.click()

        # find an unselected version and click
        new_version_elem = version_select_elem.find_element(By.XPATH, "//option[not(@selected) and not(@value='0')]")
        new_version = new_version_elem.get_attribute("value")
        new_version_elem.click()

        # wait until the page reloads to change the selected version, then click the "Grade This Version" button
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@class='content']/div[@id='version-cont']/select/option[@value='{}' and @selected]".format(new_version))))
        self.driver.find_element(By.XPATH, "//div[@class='content']/div[@id='version-cont']/form/input[@type='submit' and @id='version_change']").click()

        # accept late day alert
        self.accept_alerts(1)

        # wait until the page reloads to change the active version, completing the test
        select = Select(self.driver.find_element(By.ID, 'submission-version-select'))
        select_idx = -1
        for i in range(len(select.options)):
            if select.options[i].text.endswith('GRADE THIS VERSION'):
                select_idx = i
        self.assertGreater(select_idx, -1)
        select.select_by_visible_text(select.options[select_idx].text)

        version_xpath = "//div[@class='content']/div[@id='version-cont']/select/option[@value='{}' and @selected and substring(text(), string-length(text())-17)='GRADE THIS VERSION']".format(new_version)
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, version_xpath)))

    # for test cases that require switching versions, make submissions to ensure they will
    def ensure_multiple_versions(self):
        if self.get_submission_count() < 1:
            self.make_submission(self.create_file_paths())
        if self.get_submission_count() < 2:
            self.make_submission(self.create_file_paths(True))

    # ========================================== #
    # ============== TEST CASES ================ #
    # ========================================== #

    # test a normal upload of a single file
    def test_normal_upload(self):
        self.setup_test_start(gradeable_category="graded",
                              gradeable_id="grades_released_homework_autohiddenEC",
                              button_name="submit",
                              loaded_selector=(By.XPATH, "//h1[1][normalize-space(text())='New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)']")
                              )
        self.make_submission(self.create_file_paths(autograding=True), autograding=True)

    # test a drag and drop upload of a single file
    def test_drag_and_drop_upload(self):
        self.setup_test_start(gradeable_category="graded",
                              gradeable_id="grades_released_homework_autohiddenEC",
                              button_name="submit",
                              loaded_selector=(By.XPATH, "//h1[1][normalize-space(text())='New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)']")
                              )
        self.make_submission(self.create_file_paths(autograding=True), drag_and_drop=True, autograding=True)

    # test a normal upload of multiple files
    def test_normal_upload_multiple(self):
        self.setup_test_start(gradeable_category="graded",
                              gradeable_id="grades_released_homework_autohiddenEC",
                              button_name="submit",
                              loaded_selector=(By.XPATH, "//h1[1][normalize-space(text())='New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)']")
                              )
        self.make_submission(self.create_file_paths(multiple=True, autograding=True), autograding=True)

    # test a drag and drop upload of multiple files
    def test_drag_and_drop_upload_multiple(self):
        self.setup_test_start(gradeable_category="graded",
                              gradeable_id="grades_released_homework_autohiddenEC",
                              button_name="submit",
                              loaded_selector=(By.XPATH, "//h1[1][normalize-space(text())='New submission for: Autograder Hidden and Extra Credit (C++ Hidden Tests)']")
                              )
        self.make_submission(self.create_file_paths(multiple=True, autograding=True), drag_and_drop=True, autograding=True)

    # test changing the submission version
    def test_change_submission_version(self):
        self.setup_test_start()

        # ensure that there are multiple versions so that switching is possible
        self.ensure_multiple_versions()

        # test changing the submission version
        self.change_submission_version()

    # test cancelling the submission version
    def test_cancel_submission_version(self):
        self.setup_test_start()

        # ensure that there are multiple versions so that switching is possible
        self.ensure_multiple_versions()

        # click button and wait until page reloads to cancel version
        self.driver.find_element(By.XPATH, "//div[@class='content']/div[@id='version-cont']/form/input[@type='submit' and @id='do_not_grade']").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@class='content']/div[@id='version-cont']/select/option[@value='0' and @selected]")))

        # change back to a valid submission version
        self.change_submission_version()


if __name__ == "__main__":
    import unittest
    unittest.main()
