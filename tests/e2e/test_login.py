import unittest2
from e2e.base_testcase import BaseTestCase


class TestLogin(BaseTestCase):
    """
    Test cases revolving around the logging in functionality of the site
    """
    def test_login(self):
        """
        Test that if you attempt to go to a url when not logged in,
        you'll be taken to the login screen, and then once logged in,
        taken to that original page you had requested.
        :return:
        """
        self.log_out()
        url = "/index.php?semester=" + self.semester + "&course=csci1000&component=cpp_cats"
        self.get(url)
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys(self.user_id)
        self.driver.find_element_by_name("password").send_keys(self.user_password)
        self.driver.find_element_by_name("login").click()
        assert self.user_name == self.driver.find_element_by_id("login-id").text
        assert self.test_url + url == self.driver.current_url
        self.logged_in = True

    def test_bad_login_password(self):
        self.log_out()
        self.get("/index.php?semester=" + self.semester + "&course=csci1000")
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys(self.user_id)
        self.driver.find_element_by_name("password").send_keys("bad_password")
        self.driver.find_element_by_name("login").click()
        error = self.driver.find_element_by_id("error-0")
        assert "Could not login using that user id or password" == error.text

    def test_bad_login_username(self):
        self.log_out()
        self.get("/index.php?semester=" + self.semester + "&course=csci1000")
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys("bad_username")
        self.driver.find_element_by_name("password").send_keys(self.user_password)
        self.driver.find_element_by_name("login").click()
        error = self.driver.find_element_by_id("error-0")
        assert "Could not login using that user id or password" == error.text

if __name__ == "__main__":
    unittest2.main()
