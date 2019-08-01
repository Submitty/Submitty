from .base_testcase import BaseTestCase


class TestNavigationPageNonStudent(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_instructor(self):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample')
        elements = self.driver.find_elements_by_class_name('course-section-heading')
        self.assertEqual(6, len(elements))
        self.assertEqual("future", elements[0].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                         .find_element_by_id('future-section')
                         .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("beta", elements[1].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                                 .find_element_by_id('beta-section')
                                 .find_elements_by_class_name('gradeable-row')))
        self.assertEqual("open", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("closed", elements[3].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                         .find_element_by_id('closed-section')
                         .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("items_being_graded", elements[4].get_attribute('id'))
        self.assertEqual(6, len(self.driver
                         .find_element_by_id('items_being_graded-section')
                         .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("graded", elements[5].get_attribute('id'))
        self.assertEqual(9, len(self.driver
                         .find_element_by_id('graded-section')
                         .find_elements_by_class_name("gradeable-row")))
        self.assertEqual(4, len(self.driver.find_element_by_class_name(
            'gradeable-row').find_elements_by_class_name('course-button')))

    def test_ta(self):
        self.log_in(user_id="ta", user_name="Jill")
        self.click_class('sample')
        elements = self.driver.find_elements_by_class_name('course-section-heading')
        self.assertEqual(5, len(elements))
        self.assertEqual("beta", elements[0].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                                .find_element_by_id('beta-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("open", elements[1].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("closed", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('closed-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("items_being_graded", elements[3].get_attribute('id'))
        self.assertEqual(6, len(self.driver
                                .find_element_by_id('items_being_graded-section')
                                .find_elements_by_class_name("gradeable-row")))
        self.assertEqual("graded", elements[4].get_attribute('id'))
        self.assertEqual(9, len(self.driver
                                .find_element_by_id('graded-section')
                                .find_elements_by_class_name("gradeable-row")))

        self.assertEqual(3, len(self.driver.find_element_by_class_name(
            'gradeable-row').find_elements_by_class_name('course-button')))


if __name__ == "__main__":
    import unittest
    unittest.main()
