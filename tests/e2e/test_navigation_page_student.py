from .base_testcase import BaseTestCase


class TestNavigationPageStudent(BaseTestCase):
    def test_navigation_page(self):
        self.driver.find_element_by_id(self.get_current_semester() + '_sample').click()
        elements = self.driver.find_elements_by_class_name('nav-title-row')
        self.assertEqual(4, len(elements))
        self.assertEqual("open", elements[0].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("closed", elements[1].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('closed_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("items_being_graded", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('items_being_graded_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("graded", elements[3].get_attribute('id'))
        self.assertEqual(7, len(self.driver
                                .find_element_by_id('graded_tbody')
                                .find_elements_by_class_name("gradeable_row")))

        self.assertEqual(3, len(self.driver.find_element_by_class_name(
            'gradeable_row').find_elements_by_tag_name('td')))


if __name__ == "__main__":
    import unittest
    unittest.main()
