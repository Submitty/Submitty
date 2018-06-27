from .base_testcase import BaseTestCase
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By


class TestSubmission(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def make_submission(self, file_paths=[], drag_and_drop=False):
        def get_submission_count():
            return len(self.driver.find_elements_by_xpath("//div[@class='content']/select/option"))
        target_id="upload1"
        # TODO: check server files before submission
        self.log_in()
        self.click_class("sample", "SAMPLE")
        self.click_nav_gradeable_button("open", "open_homework", "resubmit", (By.XPATH, "//div[@class='content']/div[1]/h2[1][normalize-space(text())='New submission for: Open Homework']"))
        submission_count = get_submission_count()
        self.input_files(file_paths, target_id, drag_and_drop)
        self.driver.find_element_by_id("submit").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.XPATH, "//div[@id='{}' and count(label[@class='mylabel'])=0]".format(target_id))))
        self.assertEqual(submission_count+1, get_submission_count())
        # TODO: check server files after submission
    
    def test_normal_upload(self):
        file_paths = ["/usr/local/submitty/GIT_CHECKOUT/Submitty/more_autograding_examples/python_simple_homework/submissions/infinite_loop_too_much_output.py"]
        self.make_submission(file_paths, False)

    def test_drag_and_drop_upload(self):
        file_paths = ["/usr/local/submitty/GIT_CHECKOUT/Submitty/more_autograding_examples/python_simple_homework/submissions/infinite_loop_too_much_output.py"]
        self.make_submission(file_paths, True)


if __name__ == "__main__":
    import unittest
    unittest.main()
