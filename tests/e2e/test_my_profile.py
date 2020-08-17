import os
import random
from unittest import skipIf

from .base_testcase import BaseTestCase
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import NoSuchElementException
from selenium.webdriver.common.by import By

CURRENT_PATH = os.path.dirname(os.path.realpath(__file__))

class TestMyProfile(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)
        self.student_id = "student"
        self.student_first_name = "Joe"
        self.student_last_name = "Student"

    def setup_test_start(self):
        self.log_in(user_id=self.student_id, user_name=self.student_first_name)
        # click on my-profile icon
        self.driver.find_element(By.ID, "nav-sidebar-my-profile").click()


    def test_basic_info(self):
        self.setup_test_start()
        student_id = self.driver.find_element(By.XPATH, "//div[@id='username-row']/span[@class='value']").text
        student_first_name = self.driver.find_element(By.XPATH, "//div[@id='firstname-row']/span[@class='value']").text
        student_last_name = self.driver.find_element(By.XPATH, "//div[@id='lastname-row']/span[@class='value']").text
        user_time_zone = self.driver.find_element(By.ID, "time_zone_selector_label").get_attribute('data-user_time_zone')
        time_zone_selector = Select(self.driver.find_element(By.ID, "time_zone_drop_down"))
        self.assertEqual(self.student_id, student_id)
        self.assertEqual(self.student_first_name, student_first_name)
        self.assertEqual(self.student_last_name, student_last_name)
        self.assertEqual(user_time_zone, time_zone_selector.first_selected_option.get_attribute('value'))

    def test_time_zone_selection(self):
        self.setup_test_start()
        time_zone_selector = Select(self.driver.find_element(By.ID, "time_zone_drop_down"))
        time_zone_label = self.driver.find_element(By.ID, "time_zone_selector_label")
        student_time_zone = time_zone_label.get_attribute("data-user_time_zone")
        selected_time_zone = time_zone_selector.first_selected_option.get_attribute('value')
        self.assertEqual(student_time_zone, selected_time_zone)

        # Now check if updating the time_zone is correctly working
        # Choose a random option and select it
        rand_option = random.randint(0, len(time_zone_selector.options))
        time_zone_selector.select_by_index(rand_option)
        # Look for success message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "success-js-0")))
        # Assert that time-zone updated
        updated_time_zone = time_zone_label.get_attribute("data-user_time_zone")
        self.assertEqual(updated_time_zone, time_zone_selector.options[rand_option].get_attribute('value'))

    def test_edit_preferred_names(self):
        self.setup_test_start()
        new_first_name = "Rachel"
        new_last_name = "Green"
        # click on the edit-preferred-name link
        self.driver.find_element(By.XPATH, "//div[@id='basic_info']/span[2]/a").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "edit-username-form")))
        # Hit submit without any changes
        self.driver.find_element(By.XPATH, "//div[@id='edit-username-form']/form/div/div/div[2]/div[2]/div/input").click()
        # Look for Error message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "error-js-0")))

        # again click on the edit-preferred-name link
        self.driver.find_element(By.XPATH, "//div[@id='basic_info']/span[2]/a").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "edit-username-form")))
        # Clear the previous name and enter new names
        self.driver.find_element(By.ID, "user-firstname-change").clear()
        self.driver.find_element(By.ID, "user-lastname-change").clear()
        self.driver.find_element(By.ID, "user-firstname-change").send_keys(new_first_name)
        self.driver.find_element(By.ID, "user-lastname-change").send_keys(new_last_name)
        self.driver.find_element(By.XPATH, "//div[@id='edit-username-form']/form/div/div/div[2]/div[2]/div/input").click()

        # Look for success message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "success-js-1")))

        # edit form should be out-of-the screen
        self.assertFalse(self.driver.find_element(By.ID, "edit-username-form").is_displayed())
        # Assert that names are updated
        displayed_first_name = self.driver.find_element(By.XPATH, "//div[@id='firstname-row']/span[@class='value']").text
        displayed_last_name = self.driver.find_element(By.XPATH, "//div[@id='lastname-row']/span[@class='value']").text
        self.assertEqual(new_first_name, displayed_first_name)
        self.assertEqual(new_last_name, displayed_last_name)

        # Reset the names back to original
        self.driver.find_element(By.XPATH, "//div[@id='basic_info']/span[2]/a").click()
        self.driver.find_element(By.ID, "user-firstname-change").clear()
        self.driver.find_element(By.ID, "user-lastname-change").clear()
        self.driver.find_element(By.ID, "user-firstname-change").send_keys(self.student_first_name)
        self.driver.find_element(By.ID, "user-lastname-change").send_keys(self.student_last_name)
        self.driver.find_element(By.XPATH, "//div[@id='edit-username-form']/form/div/div/div[2]/div[2]/div/input").click()


    def test_upload_profile_photo(self):
        self.setup_test_start()

        # click on the upload-profile-photo link
        self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/span/a").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "edit-profile-photo-form")))
        # Hit submit without adding any file
        self.driver.find_element(By.XPATH, "//div[@id='edit-profile-photo-form']/form/div/div/div[2]/div[2]/div/input").click()
        # Look for Error message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "error-js-0")))

        # again click on the edit-preferred-name link
        self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/span/a").click()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "edit-profile-photo-form")))
        # Clear the previous name and enter new names
        image_path = os.path.abspath(os.path.join(CURRENT_PATH, "..", "..", "more_autograding_examples", "image_diff_mirror", "submissions", "student1.png"))

        self.driver.find_element(By.ID, "user-image-button").send_keys(image_path)
        self.driver.find_element(By.XPATH, "//div[@id='edit-profile-photo-form']/form/div/div/div[2]/div[2]/div/input").click()

        # Look for success message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "success-js-1")))

        # edit-profile-photo form should be out-of-the screen
        self.assertFalse(self.driver.find_element(By.ID, "edit-profile-photo-form").is_displayed())

        # Assert that image is added and alt-tag value is updated correctly
        alt_tag_val = self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/div/img").get_attribute('alt')
        self.assertTrue(self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/div/img").is_displayed())
        self.assertEqual("{} {}".format(self.student_first_name, self.student_last_name), alt_tag_val)

    def test_flagged_profile_photo(self):
        # Login as instructor and go to student photos page
        self.log_in(user_id='instructor', user_name='Quinn')
        self.click_class("sample", "SAMPLE")
        self.driver.find_element(By.ID, "nav-sidebar-photos").click()

        # find Joe(Student) image and flag it
        self.driver.find_element(By.XPATH, "//td[@class='{}-image-container']/div[@class='name']/a".format(self.student_id)).click()
        WebDriverWait(self.driver, 2).until(EC.alert_is_present(), "You are flagging {}'s preferred image as inappropriate.\nThis should be done if the image is not a recognizable passport style photo.\n\nDo you wish to proceed?".format(self.student_id))
        # accept the popup
        self.driver.switch_to.alert.accept()
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "success-js-0")))
        # logout from the submitty
        self.log_out()

        # login as student and go to my-profile page
        self.setup_test_start()

        # No image element and just an empty span element stating photo is 'N/A'
        try:
           self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/div/img")
           not_found = False
        except NoSuchElementException:
           not_found = True
        self.assertTrue(not_found)
        self.assertEqual('N/A', self.driver.find_element(By.XPATH, "//div[@id='user-card-img']/div/span[@class='center-img-tag']").text)

        # look for flagged image message
        self.assertEqual('Your preferred image was flagged as inappropriate.', self.driver.find_element(By.ID, "flagged-message").text)

if __name__ == "__main__":
    import unittest
    unittest.main()

