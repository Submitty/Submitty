import unittest2
from e2e.base_testcase import BaseTestCase


class TestAccess(BaseTestCase):
    def test_no_course_in_url(self):
        self.log_out()
        self.get("/index.php?semester=null")
        assert "Forbidden" == self.driver.find_element_by_tag_name("h1").text
        assert "Reason: Need to specify a course in the URL" in \
               self.driver.find_element_by_tag_name("div").text

    def test_no_semester_in_url(self):
        self.log_out()
        self.get("/index.php?course=null")
        assert "Forbidden" == self.driver.find_element_by_tag_name("h1").text
        assert "Reason: Need to specify a semester in the URL" in \
               self.driver.find_element_by_tag_name("div").text

    def test_invalid_semester(self):
        self.log_out()
        self.get("/index.php?semester=null&course=null")
        assert "Server Error" == self.driver.find_element_by_tag_name("h1").text
        assert "Invalid semester: null" in \
               self.driver.find_element_by_tag_name("div").text

    def test_invalid_course(self):
        self.log_out()
        self.get("/index.php?semester=" + self.semester + "&course=null")
        assert "Server Error" == self.driver.find_element_by_tag_name("h1").text
        assert "Invalid course: null" in \
               self.driver.find_element_by_tag_name("div").text

    def test_semester_with_directory_change(self):
        self.get("/index.php?semester=../../" + self.semester + "&course=csci1000")
        print(self.driver.current_url)
        assert self.driver.current_url == \
            self.test_url + "/index.php?semester=" + self.semester + "&course=csci1000"

    def test_course_with_directory_change(self):
        self.get("/index.php?semester=" + self.semester + "&course=../../csci1000")
        assert self.driver.current_url == \
            self.test_url + "/index.php?semester=" + self.semester + "&course=csci1000"

if __name__ == "__main__":
    unittest2.main()