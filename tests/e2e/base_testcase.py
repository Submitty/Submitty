from __future__ import print_function

import shutil
import tempfile
from datetime import date
import os
import unittest

from urllib.parse import urlencode

from selenium import webdriver

from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By


# noinspection PyPep8Naming
class BaseTestCase(unittest.TestCase):
    """
    Base class that all e2e tests should extend. It provides several useful
    helper functions, sets up the selenium webdriver, and provides a common
    interface for logging in/out a user. Each test then only really needs to
    override user_id, user_name, and user_password as necessary for a
    particular testcase and this class will handle the rest to setup the test.
    """
    TEST_URL = "http://192.168.56.111"
    USER_ID = "student"
    USER_NAME = "Joe"
    USER_PASSWORD = "student"

    WAIT_TIME = 20

    def __init__(self, testname, user_id=None, user_password=None, user_name=None, log_in=True):
        super().__init__(testname)
        if "TEST_URL" in os.environ and os.environ['TEST_URL'] is not None:
            self.test_url = os.environ['TEST_URL']
        else:
            self.test_url = BaseTestCase.TEST_URL
        self.driver = None
        """ :type driver: webdriver.Chrome """
        self.options = webdriver.ChromeOptions()
        self.options.add_argument('--headless')
        self.options.add_argument("--disable-extensions")
        self.options.add_argument('--hide-scrollbars')
        self.options.add_argument('--disable-gpu')
        self.options.add_argument('--no-proxy-server')

        self.download_dir = tempfile.mkdtemp(prefix="vagrant-submitty")
        # https://stackoverflow.com/a/26916386/214063
        profile = {
            'download.prompt_for_download': False,
            'download.default_directory': self.download_dir,
            'download.directory_upgrade': True,
            'plugins.plugins_disabled': ['Chrome PDF Viewer']
        }
        self.options.add_experimental_option('prefs', profile)
        self.user_id = user_id if user_id is not None else BaseTestCase.USER_ID
        self.user_name = user_name if user_name is not None else BaseTestCase.USER_NAME
        if user_password is None and user_id is not None:
            user_password = user_id
        self.user_password = user_password if user_password is not None else BaseTestCase.USER_PASSWORD
        self.semester = BaseTestCase.get_current_semester()
        self.full_semester = BaseTestCase.get_display_semester(self.semester)
        self.logged_in = False
        self.use_log_in = log_in

    def setUp(self):
        self.driver = webdriver.Chrome(options=self.options)
        self.enable_download_in_headless_chrome(self.download_dir)
        if self.use_log_in:
            self.log_in()

    def tearDown(self):
        self.driver.quit()
        shutil.rmtree(self.download_dir)

    def get(self, url=None, parts=None):
        if url is None:
            # Can specify parts = [('semester', 's18'), ...]
            self.assertIsNotNone(parts)
            url = "/index.php?" + urlencode(parts)

        if url[0] != "/":
            url = "/" + url
        self.driver.get(self.test_url + url)

        # Frog robot
        self.assertNotEqual(self.driver.title, "Submitty - Error", "Got Error Page")

    def log_in(self, url=None, title="Submitty", user_id=None, user_password=None, user_name=None):
        """
        Provides a common function for logging into the site (and ensuring
        that we're logged in)
        :return:
        """
        if url is None:
            url = "/index.php"

        if user_password is None:
            user_password = user_id if user_id is not None else self.user_password
        if user_id is None:
            user_id = self.user_id
        if user_name is None:
            user_name = self.user_name

        self.get(url)
        # print(self.driver.page_source)
        self.assertIn(title, self.driver.title)
        self.driver.find_element_by_name('user_id').send_keys(user_id)
        self.driver.find_element_by_name('password').send_keys(user_password)
        self.driver.find_element_by_name('login').click()
        
        #OLD self.assertEqual(user_name, self.driver.find_element_by_id("login-id").get_attribute('innerText').strip(' \t\r\n'))

        #FIXME: WANT SOMETHING LIKE THIS...  WHEN WE HAVE JUST ONE ELEMENT WITH THIS ID
        #self.assertEqual("Logout "+user_name, self.driver.find_element_by_id("logout").get_attribute('innerText').strip(' \t\r\n'))

        #instead, just make sure this element exists
        self.driver.find_element_by_id("logout")
                    
        self.logged_in = True

    def log_out(self):
        if self.logged_in:
            self.logged_in = False
            self.driver.find_element_by_id('logout').click()
            self.driver.find_element_by_id('login-guest')

    def click_class(self, course, course_name=None):
        if course_name is None:
            course_name = course.upper()
        self.driver.find_element_by_id(self.get_current_semester() + '_' + course).click()
        WebDriverWait(self.driver, BaseTestCase.WAIT_TIME).until(EC.title_is(course_name))

    # see Navigation.twig for html attributes to use as arguments
    # loaded_selector must recognize an element on the page being loaded (test_simple_grader.py has xpath example)
    def click_nav_grade_button(self, gradeable_category, gradeable_id, button_name, loaded_selector):
        self.driver.find_element_by_xpath("//tbody[@id='{}_tbody']/tr[@id='{}']/td/a[contains(@class, 'btn-nav-grade')]".format(gradeable_category, gradeable_id, button_name)).click()
        WebDriverWait(self.driver, BaseTestCase.WAIT_TIME).until(EC.presence_of_element_located(loaded_selector))

    def click_nav_submit_button(self, gradeable_category, gradeable_id, button_name, loaded_selector):
        self.driver.find_element_by_xpath("//tbody[@id='{}_tbody']/tr[@id='{}']/td/a[contains(@class, 'btn-nav-submit')]".format(gradeable_category, gradeable_id, button_name)).click()
        WebDriverWait(self.driver, BaseTestCase.WAIT_TIME).until(EC.presence_of_element_located(loaded_selector))

    # clicks the navigation header text to 'go back' pages
    # for homepage, selector can be gradeable list
    def click_header_link_text(self, text, loaded_selector):
        self.driver.find_element_by_xpath("//div[@id='header-text']/div/h2[1]/a[text()='{}']".format(text)).click()
        WebDriverWait(self.driver, BaseTestCase.WAIT_TIME).until(EC.presence_of_element_located(loaded_selector))



    def wait_after_ajax(self):
        WebDriverWait(self.driver, 10).until(lambda driver: driver.execute_script("return jQuery.active == 0"))    

    @staticmethod
    def wait_user_input():
        """
        Causes the running selenium test to pause until the user has hit the enter key in the
        terminal that is running python. This is useful for using in the middle of building tests
        as then you cna use the javascript console to inspect the page, get the name/id of elements
        or other such actions and then use that to continue building the test
        """
        input("Hit enter to continue...")

    @staticmethod
    def get_current_semester():
        """
        Returns the "current" academic semester which is in use on the Vagrant/Travis machine (as we
        want to keep referring to a url that is "up-to-date"). The semester will either be spring
        (prefix "s") if we're in the first half of the year otherwise fall (prefix "f") followed
        by the last two digits of the current year. Unless you know you're using a course that
        was specifically set-up for a certain semester, you should always be using the value
        generated by this function in the code.

        :return:
        """
        today = date.today()
        semester = "f" + str(today.year)[-2:]
        if today.month < 7:
            semester = "s" + str(today.year)[-2:]
        return semester

    @staticmethod
    def get_display_semester(current_semester):
        s = 'Fall' if current_semester[0] == 'f' else 'Summer' if current_semester[0] == 'u' else 'Spring'
        s += ' 20' + current_semester[1:]
        return s

    # https://stackoverflow.com/a/47366981/214063
    def enable_download_in_headless_chrome(self, download_dir):
        # add missing support for chrome "send_command"  to selenium webdriver
        self.driver.command_executor._commands["send_command"] = ("POST", '/session/$sessionId/chromium/send_command')

        params = {'cmd': 'Page.setDownloadBehavior', 'params': {'behavior': 'allow', 'downloadPath': download_dir}}
        command_result = self.driver.execute("send_command", params)
