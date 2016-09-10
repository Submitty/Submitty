from __future__ import print_function
import os
import unittest2
from selenium import webdriver


class BaseTestCase(unittest2.TestCase):
    def __init__(self, *args, **kwargs):
        super(BaseTestCase, self).__init__(*args, **kwargs)
        if "TEST_URL" in os.environ and os.environ['TEST_URL'] is not None:
            self.test_url = os.environ['TEST_URL']
        else:
            self.test_url = "http://192.168.56.101"

    def setUp(self):
        self.driver = webdriver.PhantomJS()
        self.log_in()

    def tearDown(self):
        self.log_out()
        self.driver.close()

    def get(self, url):
        if url[0] != "/":
            url = "/" + url
        self.driver.get(self.test_url + url)

    def log_in(self):
        self.get("/index.php?semester=f16&course=csci1000")
        assert "CSCI1000" in self.driver.title
        self.driver.find_element_by_name('user_id').send_keys("student")
        self.driver.find_element_by_name('password').send_keys("student")
        self.driver.find_element_by_name('login').click()
        assert "Joe" == self.driver.find_element_by_id("login-id").text

    def log_out(self):
        self.driver.find_element_by_id('logout').click()
        self.driver.find_element_by_id('login-guest')

if __name__ == "__main__":
    unittest2.main()
