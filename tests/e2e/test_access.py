from .base_testcase import BaseTestCase
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.alert import Alert
from selenium.common.exceptions import TimeoutException, ElementNotInteractableException
import os
import unittest
from urllib.parse import urlparse, parse_qs

# requests is not explicitly installed system wide in our requirements.txt
# (.setup/pip/system_requirements.txt), but exists currently installed
# in the vagrant vm as required by other packages
import requests

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))


class TestAccess(BaseTestCase):

    file_rel_path = 'submissions/closed_homework/aphacker/1/part1_buggy2.py'

    def __init__(self, testname):
        super().__init__(testname, log_in=True, user_id='ta', user_password='ta')

    @unittest.skipUnless(os.environ.get('CI') is None, "cannot run in CI")
    def get_credentials(self):
        cookies = {k: self.driver.get_cookie(k)['value']
                   for k in ['submitty_session', 'PHPSESSID', 'submitty_token']}
        csrf = self.driver.find_element(By.CSS_SELECTOR, 'body').get_attribute('data-csrf-token')
        return cookies, csrf

    def test_sample_file_exists(self):
        # this test ensures a specific sample course file we use in the subsequent test,
        # test_delete_file, is present on the Submitty machine

        self.log_out()
        self.log_in(user_id='aphacker', user_name='aphacker', user_password='aphacker')
        cookies, csrf = self.get_credentials()
        endpoint = f'/courses/{self.semester}/sample/download'
        r = requests.get(url=self.TEST_URL + endpoint, params={
            'csrf_token': csrf,
            'dir': 'submissions',
            'path': f'/var/local/submitty/courses/{self.semester}/sample/' + self.file_rel_path,
        }, cookies=cookies)

        # moderately fragile checks, but since we receive status 200 on failure,
        # this is sort of the best we can do.
        if ('test/html' in r.headers['Content-Type']) or \
           (b'You don\'t have access to this page.' in r.content):
            raise ValueError(f'no access to file {self.file_rel_path}, does it exist?')

        self.log_out()

    @unittest.skipUnless(os.environ.get('CI') is None, "cannot run in CI")
    def test_delete_file(self):
        # This test ensures two things:
        # - standard upload and deletion of files on the grading rubric page work
        # - it isn't possible to supply alternate paths to delete other files
        # This is kept as a single test since information from the first test
        # is needed for the second (specifically, the anon_id)

        cookies, csrf = self.get_credentials()

        # 1. Go to grading details page for "Grading Homework" gradeable
        # obtain valid anon_id of peer gradeable assignment
        self.get(f'/courses/{self.semester}/sample/gradeable/grading_homework/grading/details')
        # accept responsibilities as grader modal popup
        try:
            # accept all modal popups
            while True:
                agree_button = (By.ID, 'agree-button')
                # wait for modal to appear, if no more, then timeout or not interactable exception occurs
                WebDriverWait(self.driver, 1).until(EC.presence_of_element_located(agree_button))
                agree_button_elem = self.driver.find_element(*agree_button)
                agree_button_elem.click()
                # wait for modal to disappear
                WebDriverWait(self.driver, 1).until(EC.staleness_of(agree_button_elem))
        except (TimeoutException, ElementNotInteractableException):
            pass
        # click view all sections
        view_all_selector = 'a.btn[onclick="changeSections()"]'
        self.driver.find_element(By.CSS_SELECTOR, view_all_selector).click()
        # navigate to a specific students grading page
        sel = '#details-table > .details-content > tr > td > .btn-primary[href]'
        buttons = self.driver.find_elements(By.CSS_SELECTOR, sel)
        assert len(buttons) > 0, "no grade buttons found"
        buttons[0].click()
        # obtain anon_id of student,
        # I don't think there is a robust way to obtain this from the page
        WebDriverWait(self.driver, self.WAIT_TIME).until(EC.url_contains('who_id'))
        anon_id = parse_qs(urlparse(self.driver.current_url).query)['who_id']

        # upload attachment to the rubric page
        self.driver.find_element(By.CSS_SELECTOR,
                                 '#grading_rubric_btn > button:nth-child(1)').click()
        attach_upload = (By.ID, 'attachment-upload')
        WebDriverWait(self.driver, self.WAIT_TIME).until(EC.presence_of_element_located(attach_upload))
        upload_input = self.driver.find_element(*attach_upload)
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
        WebDriverWait(self.driver, self.WAIT_TIME).until(presence)
        self.driver.find_element(By.CSS_SELECTOR, del_btn).click()
        # confirm that we do want to delete the attachment
        Alert(self.driver).accept()

        # attempt delete another file
        endpoint = f'/courses/{self.semester}/sample' + \
                   '/gradeable/grading_homework/grading/attachments/delete'
        r = requests.post(url=self.TEST_URL + endpoint, data={
            'anon_id': anon_id,
            'csrf_token': csrf,
            'attachment': '../../../../' + self.file_rel_path,
        }, cookies=cookies)
        # checking for failure in this way isn't entirely robust,
        # however unit tests should be used on the specific access checking functions in Access.php
        json = r.json()
        self.assertTrue('status' in json)
        self.assertEqual(json['status'], 'fail')
