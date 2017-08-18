import unittest2
from e2e.base_testcase import BaseTestCase


class TestAccess(BaseTestCase):
    def setUp(self):
        self.driver = BaseTestCase.DRIVER

    def test_no_course_in_url(self):
        self.log_in("/index.php?semester=null", "Submitty")
        self.assertEquals(self.test_url + "/index.php?&success_login=true", self.driver.current_url)

    def test_no_semester_in_url(self):
        self.log_in("/index.php?course=null", "Submitty")
        self.assertEquals(self.test_url + "/index.php?&success_login=true", self.driver.current_url)

    def test_invalid_semester(self):
        self.log_in("/index.php?semester=null&course=null", "Submitty")
        self.assertEquals(self.test_url + "/index.php?&success_login=true", self.driver.current_url)

    def test_invalid_course(self):
        self.log_in("/index.php?semester=" + self.semester + "&course=null", "Submitty")
        self.assertEquals(self.test_url + "/index.php?&success_login=true", self.driver.current_url)

    def test_semester_with_directory_change(self):
        self.get("/index.php?semester=../../" + self.semester + "&course=sample")
        self.assertEqual(self.driver.current_url, self.test_url + "/index.php?semester=" +
                         self.semester + "&course=sample")

    def test_course_with_directory_change(self):
        self.get("/index.php?semester=" + self.semester + "&course=../../sample")
        self.assertEqual(self.driver.current_url, self.test_url + "/index.php?semester=" +
                         self.semester + "&course=sample")

if __name__ == "__main__":
    unittest2.main()
