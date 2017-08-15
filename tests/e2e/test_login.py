import unittest2
from e2e.base_testcase import BaseTestCase


class TestLogin(BaseTestCase):
    """
    Test cases revolving around the logging in functionality of the site
    """

    def setUp(self):
        self.driver = BaseTestCase.DRIVER

    def test_login(self):
        """
        Test that if you attempt to go to a url when not logged in,
        you'll be taken to the login screen, and then once logged in,
        taken to that original page you had requested.
        """
        url = "/index.php?semester=" + self.semester + \
              "&course=sample&component=student&gradeable_id=open_homework&success_login=true"
        self.log_in(url)
        self.assertEqual(self.test_url + url, self.driver.current_url)

    def test_bad_login_password(self):
        self.get("/index.php?semester=" + self.semester + "&course=sample")
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys(self.user_id)
        self.driver.find_element_by_name("password").send_keys("bad_password")
        self.driver.find_element_by_name("login").click()
        error = self.driver.find_element_by_id("error-0")
        self.assertEqual("Could not login using that user id or password", error.text)

    def test_bad_login_username(self):
        self.get("/index.php?semester=" + self.semester + "&course=sample")
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys("bad_username")
        self.driver.find_element_by_name("password").send_keys(self.user_password)
        self.driver.find_element_by_name("login").click()
        error = self.driver.find_element_by_id("error-0")
        self.assertEqual("Could not login using that user id or password", error.text)

    def test_login_non_course_user(self):
        self.get("/index.php?semester=" + self.semester + "&course=sample")
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys("pearsr")
        self.driver.find_element_by_name("password").send_keys("pearsr")
        self.driver.find_element_by_name("login").click()
        element = self.driver.find_element_by_class_name("content")
        self.assertEqual("You don't have access to Course Name. If you think this is mistake, please contact your instructor to gain access.", element.text)

if __name__ == "__main__":
    unittest2.main()
