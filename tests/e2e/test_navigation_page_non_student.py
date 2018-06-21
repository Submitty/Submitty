from .base_testcase import BaseTestCase


class TestNavigationPageNonStudent(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def test_instructor(self):
        self.log_in(user_id="instructor", user_name="Quinn")
        self.click_class('sample', 'SAMPLE')
        elements = self.driver.find_elements_by_class_name('nav-title-row')
        self.assertEqual(6, len(elements))
        self.assertEqual("future", elements[0].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                         .find_element_by_id('future_tbody')
                         .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("beta", elements[1].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                                 .find_element_by_id('beta_tbody')
                                 .find_elements_by_class_name('gradeable_row')))
        self.assertEqual("open", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("closed", elements[3].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                         .find_element_by_id('closed_tbody')
                         .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("items_being_graded", elements[4].get_attribute('id'))
        self.assertEqual(6, len(self.driver
                         .find_element_by_id('items_being_graded_tbody')
                         .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("graded", elements[5].get_attribute('id'))
        self.assertEqual(9, len(self.driver
                         .find_element_by_id('graded_tbody')
                         .find_elements_by_class_name("gradeable_row")))
        self.assertEqual(7, len(self.driver.find_element_by_class_name(
            'gradeable_row').find_elements_by_tag_name('td')))

    def test_ta(self):
        self.log_in(user_id="ta", user_name="Jill")
        self.click_class('sample', 'SAMPLE')
        elements = self.driver.find_elements_by_class_name('nav-title-row')
        self.assertEqual(5, len(elements))
        self.assertEqual("beta", elements[0].get_attribute('id'))
        self.assertEqual(3, len(self.driver
                                .find_element_by_id('beta_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("open", elements[1].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('open_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("closed", elements[2].get_attribute('id'))
        self.assertEqual(2, len(self.driver
                                .find_element_by_id('closed_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("items_being_graded", elements[3].get_attribute('id'))
        self.assertEqual(6, len(self.driver
                                .find_element_by_id('items_being_graded_tbody')
                                .find_elements_by_class_name("gradeable_row")))
        self.assertEqual("graded", elements[4].get_attribute('id'))
        self.assertEqual(9, len(self.driver
                                .find_element_by_id('graded_tbody')
                                .find_elements_by_class_name("gradeable_row")))

        self.assertEqual(4, len(self.driver.find_element_by_class_name(
            'gradeable_row').find_elements_by_tag_name('td')))


if __name__ == "__main__":
    import unittest
    unittest.main()
