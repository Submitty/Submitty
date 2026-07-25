# Complete code
"""
Module for testing course utilities.
"""
import unittest
from course_utils import get_autograding_status_data

class TestCourseUtils(unittest.TestCase):
    def test_get_autograding_status_data(self):
        # Get the course data
        course_data = get_course_data(1)

        # Get the autograding status data
        autograding_status_data = get_autograding_status_data(course_data)

        # Check that the autograding status data is correct
        self.assertEqual(autograding_status_data['course'], 'Course Name')
        self.assertEqual(len(autograding_status_data['gradeables']), 2)

if __name__ == '__main__':
    unittest.main()