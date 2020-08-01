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
        time_zone_selector = Select(self.driver.find_element(By.ID, "theme_change_select"))
        self.assertEqual(self.student_id, student_id)
        self.assertEqual(self.student_first_name, student_first_name)
        self.assertEqual(self.student_last_name, student_last_name)
#         self.assertEqual(self.user_time_zone, user_time_zone)
        print(time_zone_selector.first_selected_option.get_attribute('value'))

    def test_time_zone_selection(self):
        self.setup_test_start()
        time_zone_selector = Select(self.driver.find_element(By.ID, "time_zone_drop_down"))
        time_zone_label = self.driver.find_element(By.ID, "time_zone_selector_label")
        student_time_zone = time_zone_label.get_attribute("data-user_time_zone")
        selected_time_zone = time_zone_selector.first_selected_option.get_attribute('value')
        self.assertEqual(student_time_zone, selected_time_zone)

        # Now check if updating the time_zone is correctly working
        # Choose a random option and select it

        try:
            success_popup_count = self.driver.find_element_by_css_selector("[id^='success-js-']").size
        except NoSuchElementException:
            success_popup_count = 0

        rand_option = random.randint(0, len(time_zone_selector.options))
        time_zone_selector.select_by_index(rand_option)
        # Look for success message
        WebDriverWait(self.driver, 10).until(EC.presence_of_element_located((By.ID, "success-js-{}".format(success_popup_count))))
        # Assert that offset and data attr updated
        updated_time_zone = time_zone_label.get_attribute("data-user_time_zone")
        self.assertEqual(updated_time_zone, time_zone_selector.options[rand_option].get_attribute('value'))


    def test_edit_preferred_names(self):
        pass

    def test_upload_profile_photo(self):
        pass

    def test_flagged_profile_photo(self):
        pass





if __name__ == "__main__":
    import unittest
    unittest.main()

