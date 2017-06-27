import unittest2
from e2e.base_testcase import BaseTestCase


class TestAccess(BaseTestCase):
    def setUp(self):
        self.driver = BaseTestCase.DRIVER

    def test_no_course_in_url(self):
        self.get("/index.php?semester=null")
        self.assertEqual("Forbidden", self.driver.find_element_by_tag_name("h1").text)
        self.assertIn("Reason: Need to specify a course in the URL",
                      self.driver.find_element_by_tag_name("div").text)

    def test_no_semester_in_url(self):
        self.get("/index.php?course=null")
        self.assertEqual("Forbidden", self.driver.find_element_by_tag_name("h1").text)
        self.assertIn("Reason: Need to specify a semester in the URL",
                      self.driver.find_element_by_tag_name("div").text)

    def test_invalid_semester(self):
        self.get("/index.php?semester=null&course=null")
        self.assertEqual("Server Error", self.driver.find_element_by_tag_name("h1").text)
        self.assertIn("Invalid semester: null", self.driver.find_element_by_tag_name("div").text)

    def test_invalid_course(self):
        self.get("/index.php?semester=" + self.semester + "&course=null")
        self.assertEqual("Server Error", self.driver.find_element_by_tag_name("h1").text)
        self.assertIn("Invalid course: null", self.driver.find_element_by_tag_name("div").text)

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
