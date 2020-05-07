from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.common.by import By

from .base_testcase import BaseTestCase


class TestSidebar(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=False)

    def sidebar_test_helper(self, expected, user_id, user_name):
        self.log_in(user_id=user_id, user_name=user_name)
        self.click_class('sample')
        base_url = self.test_url + '/' + self.get_current_semester() + '/sample'

        title_map = {
            'Manage Sections': 'Manage Registration Sections',
            'Plagiarism Detection': 'Plagiarism Detection -- WORK IN PROGRESS',
            'My Late Days/Extensions': 'My Late Day Usage'
        }

        current_idx = 0

        while current_idx < len(expected):
            nav = self.driver.find_element(By.TAG_NAME, 'aside')
            links = nav.find_elements(By.TAG_NAME, 'li')

            for link in links:
                if not link.find_elements(By.TAG_NAME, 'a'):
                    links.remove(link)

            actual = [
                [link.find_element(By.TAG_NAME, 'a').get_attribute('href'), link.text]
                for link in links
            ]
            self.assertListEqual(expected, actual)

            a = links[current_idx].find_element(By.TAG_NAME, 'a')
            href = a.get_attribute('href')
            if not href.startswith(base_url):
                current_idx += 1
                continue
            elif a.text.startswith('Logout'):
                current_idx += 1
                continue

            links[current_idx].click()
            WebDriverWait(self.driver, BaseTestCase.WAIT_TIME).until(
                EC.presence_of_element_located((By.TAG_NAME, 'h1'))
            )

            expected_text = expected[current_idx][1]
            if expected_text in title_map:
                expected_text = title_map[expected_text]

            heading_text = []
            heading_text.append(self.driver.find_element(By.ID, 'main')
                                .find_elements(By.TAG_NAME, 'h1')[0].text)
            if(self.driver.find_element(By.ID, 'breadcrumbs')
               and len(self.driver.find_element(By.ID, 'breadcrumbs')
                       .find_elements(By.TAG_NAME, 'h1')) > 0):
                heading_text.append(self.driver.find_element(By.ID, 'breadcrumbs')
                                    .find_elements(By.TAG_NAME, 'h1')[0].text)
            self.assertIn(expected_text, heading_text)
            current_idx += 1

    def test_click_sidebar_links_instructor(self):
        base_url = self.test_url + '/' + self.get_current_semester() + '/sample'
        expected = [
            [base_url, 'Gradeables'],
            [base_url + '/notifications', 'Notifications'],
            [base_url + '/gradeable', 'New Gradeable'],
            [base_url + '/config', 'Course Settings'],
            [base_url + '/course_materials', 'Course Materials'],
            [base_url + '/forum', 'Discussion Forum'],
            [base_url + '/users', 'Manage Students'],
            [base_url + '/graders', 'Manage Graders'],
            [base_url + '/sections', 'Manage Sections'],
            [base_url + '/student_photos', 'Student Photos'],
            [base_url + '/late_days', 'Late Days Allowed'],
            [base_url + '/extensions', 'Excused Absence Extensions'],
            [base_url + '/grade_override', 'Grade Override'],
            [base_url + '/plagiarism', 'Plagiarism Detection'],
            [base_url + '/reports', 'Grade Reports'],
            [base_url + '/late_table', 'My Late Days/Extensions'],
            ['javascript: toggleSidebar();', 'Collapse Sidebar'],
            [self.test_url + '/authentication/logout', 'Logout Quinn']
        ]

        self.sidebar_test_helper(expected, 'instructor', 'Quinn')

    def test_click_sidebar_links_ta(self):
        base_url = self.test_url + '/' + self.get_current_semester() + '/sample'
        expected = [
            [base_url, 'Gradeables'],
            [base_url + '/notifications', 'Notifications'],
            # sample course has no course materials to start, so this link will not appear
            # [base_url + '/course_materials', 'Course Materials'],
            [base_url + '/forum', 'Discussion Forum'],
            [base_url + '/student_photos', 'Student Photos'],
            [base_url + '/late_table', 'My Late Days/Extensions'],
            ['javascript: toggleSidebar();', 'Collapse Sidebar'],
            [self.test_url + '/authentication/logout', 'Logout Jill']
        ]

        self.sidebar_test_helper(expected, 'ta', 'Jill')

    def test_click_sidebar_links_student(self):
        base_url = self.test_url + '/' + self.get_current_semester() + '/sample'
        expected = [
            [base_url, 'Gradeables'],
            [base_url + '/notifications', 'Notifications'],
            # sample course has no course materials in start, so this link will not appear
            # [base_url + '/course_materials', 'Course Materials'],
            [base_url + '/forum', 'Discussion Forum'],
            [base_url + '/late_table', 'My Late Days/Extensions'],
            ['javascript: toggleSidebar();', 'Collapse Sidebar'],
            [self.test_url + '/authentication/logout', 'Logout Joe']
        ]

        self.sidebar_test_helper(expected, 'student', 'Joe')
