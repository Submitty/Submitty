from .base_testcase import BaseTestCase


class TestNavigationPageStudent(BaseTestCase):
    def test_navigation_page(self):
        self.click_class('sample')
        elements = self.driver.find_elements_by_class_name('course-section-heading')
        self.assertEqual(4, len(elements))
        self.assertEqual("open", elements[0].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("closed", elements[1].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('closed-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("items_being_graded", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('items_being_graded-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("graded", elements[3].get_attribute('id'))
        self.assertEqual(7, len(self.driver
                                .find_element_by_id('graded-section')
                                .find_elements_by_class_name("gradeable-row")))

        self.assertEqual(2, len(self.driver.find_element_by_class_name(
            'course-button-wrapper').find_elements_by_class_name('course-button')))


if __name__ == "__main__":
    import unittest
    unittest.main()
