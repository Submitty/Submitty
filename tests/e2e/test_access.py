from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.alert import Alert
from selenium.common.exceptions import TimeoutException
import os

# requests is not explicitly installed system wide in our requirements.txt
# (.setup/pip/system_requirements.txt), but exists currently installed
# in the vagrant vm as required by other packages
import requests

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))


class TestAccess(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=True, user_id='ta', user_password='ta')

    def test_delete_file(self):
        # This test ensures two things:
        # - standard upload and deletion of files on the grading rubric page work
        # - it isn't possible to supply alternate paths to delete other files
        # This is kept as a single test since information from the first test
        # is needed for the second (specifically, the anon_id)

        cookies = {k: self.driver.get_cookie(k)['value']
                   for k in ['submitty_session', 'PHPSESSID', 'submitty_token']}
        csrf = self.driver.find_element(By.CSS_SELECTOR, 'body').get_attribute('data-csrf-token')

        # 1. Go to grading details page for "Grading Homework" gradeable
        # obtain valid anon_id of peer gradeable assignment
        self.get('/courses/f22/sample/gradeable/grading_homework/grading/details')
        # accept responsibilities as grader modal popup
        self.driver.find_element(By.ID, 'agree-button').click()
        # click view all sections
        self.driver.find_element(By.CSS_SELECTOR, 'a.btn:nth-child(1)').click()
        # navigate to a specific students grading page
        sel = 'tbody.details-content:nth-child(4) > tr:nth-child(1)' + \
              '> td:nth-child(8) > a:nth-child(1)'
        self.driver.find_element(By.CSS_SELECTOR, sel).click()
        # obtain anon_id of student
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, 'anon-id')))
        anon_id = self.driver.find_element(By.ID, 'anon-id').get_attribute('data-anon_id')

        # upload attachment to the rubric page
        self.driver.find_element(By.CSS_SELECTOR,
                                 '#grading_rubric_btn > button:nth-child(1)').click()
        upload_input = self.driver.find_element(By.ID, 'attachment-upload')
        parts = [CURRENT_PATH, "..", "..", "more_autograding_examples", "image_diff_mirror",
                 "submissions", "student1.png"]
        image_path = os.path.abspath(os.path.join(*parts))

        # Note when testing locally;
        # Selenium may complain about "File not found" even for existing file,
        # despite the file existing with appropriate permissions.
        # This also occurred in test_my_profile.py during photo upload.
        # This occurred for me using chromium-browser and chromium-chromedriver.
        # I suspect this is due to snap applying some form of sandboxing,
        # preventing chrome from accessing files in /usr/local/submitty
        # try installing regular google-chrome instead.

        assert os.path.exists(image_path), f'test upload file {image_path} does not exist'
        upload_input.send_keys(image_path)

        try:
            # confirm upload if file already present,
            # this isn't strictly necessary but useful for debugging
            WebDriverWait(self.driver, 1).until(EC.alert_is_present())
            Alert(self.driver).accept()
        except TimeoutException:
            pass
        # click delete button once it appears
        del_btn = '#attachments-list-ta > div:nth-child(1) > div:nth-child(1) > a:nth-child(4)'
        presence = EC.presence_of_element_located((By.CSS_SELECTOR, del_btn))
        WebDriverWait(self.driver, 10).until(presence)
        self.driver.find_element(By.CSS_SELECTOR, del_btn).click()
        # confirm that we do want to delete the attachment
        Alert(self.driver).accept()

        # attempt delete another file
        file_rel_path = 'submissions/closed_homework/aphacker/1/part1_buggy2.py'
        assert os.path.exists('/var/local/submitty/courses/f22/sample/' + file_rel_path)
        endpoint = '/courses/f22/sample/gradeable/grading_homework/grading/attachments/delete'
        r = requests.post(url=self.TEST_URL + endpoint, data={
            'anon_id': anon_id,
            'csrf_token': csrf,
            'attachment': '../../../../' + file_rel_path,
        }, cookies=cookies)
        # checking for failure in this way isn't entirely robust,
        # however unit tests should be used on the specific access checking functions in Access.php
        json = r.json()
        self.assertTrue('status' in json)
        self.assertEqual(json['status'], 'fail')
