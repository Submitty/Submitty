import unittest2

from e2e.base_testcase import BaseTestCase


class TestLogin(BaseTestCase):
    def test_login(self):
        self.log_out()
        url = "/index.php?semester=f16&course=csci1000&component=student"
        self.get(url)
        self.driver.find_element_by_id("login-guest")
        self.driver.find_element_by_name("user_id").send_keys("student")
        self.driver.find_element_by_name("password").send_keys("student")
        self.driver.find_element_by_name("login").click()
        assert "Joe" == self.driver.find_element_by_id("login-id").text
        assert self.test_url + url == self.driver.current_url

if __name__ == "__main__":
    unittest2.main()