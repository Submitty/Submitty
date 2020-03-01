from .base_testcase import BaseTestCase
import requests
import json

class TestAccessibility(BaseTestCase):
    """
    Test cases revolving around the logging in functionality of the site
    """
    def __init__(self, testname):
        super().__init__(testname, log_in=False)


    # This should contain a url for every type of page on the webiste
    urls = [
        '/home',
        '/s20/sample',
        '/s20/sample/gradeable/future_no_tas_homework/update?nav_tab=0',
        '/s20/sample/gradeable/future_no_tas_lab/grading?view=all',
        '/s20/sample/gradeable/future_no_tas_test/grading?view=all',
        '/s20/sample/gradeable/open_homework/grading/status',
        '/s20/sample/gradeable/open_homework/grading/details?view=all',
        '/s20/sample/gradeable/open_homework',
        '/s20/sample/gradeable/open_team_homework/team',
        '/s20/sample/gradeable/grades_released_homework_autota',
        '/s20/sample/notifications',
        '/s20/sample/notifications/settings',
        '/s20/sample/gradeable',
        '/s20/sample/config',
        '/s20/sample/theme',
        '/s20/sample/office_hours_queue',
        '/s20/sample/course_materials',
        '/s20/sample/forum',
        '/s20/sample/forum/threads/new',
        '/s20/sample/forum/categories',
        '/s20/sample/users',
        '/s20/sample/graders',
        '/s20/sample/sections',
        '/s20/sample/student_photos',
        '/s20/sample/late_days',
        '/s20/sample/extensions',
        '/s20/sample/grade_override',
        '/s20/sample/plagiarism',
        '/s20/sample/plagiarism/configuration/new',
        '/s20/sample/reports',
        '/s20/sample/reports/rainbow_grades_customization',
        '/s20/sample/late_table'
    ]

    def test_w3_validator(self):
        # Uncomment this to generate a new baseline for all pages on the website
        # genBaseline(self)

        # Uncomment this to generate a new baseline for a specific url
        # url = '' # your url here
        # genBaseline(self, url)


        validatePages(self)



def validatePages(self):
    self.log_out()
    self.log_in(user_id='instructor')
    self.click_class('sample')

    with open('e2e/accessibility_baseline.json') as f:
        baseline = json.load(f)

    foundError = False
    for url in self.urls:
        self.get(url=url)

        payload = self.driver.page_source
        headers = {
          'Content-Type': 'text/html; charset=utf-8'
        }
        response = requests.request("POST", "https://validator.w3.org/nu/?out=json", headers=headers, data = payload.encode('utf-8'))



        for error in response.json()['messages']:
            if error['message'] not in baseline[url]:
                error['url'] = url
                print(json.dumps(error, indent=4, sort_keys=True))
                foundError = True

    self.assertEqual(foundError, False)



def genBaseline(self, new_url=None):
    self.log_out()
    self.log_in(user_id='instructor')
    self.click_class('sample')

    baseline = {}
    urls = self.urls
    if new_url:
        with open('e2e/accessibility_baseline.json') as f:
            baseline = json.load(f)
        urls = [new_url]

    for url in urls:
        self.get(url=url)
        payload = self.driver.page_source
        headers = {
          'Content-Type': 'text/html; charset=utf-8'
        }
        response = requests.request("POST", "https://validator.w3.org/nu/?out=json", headers=headers, data = payload.encode('utf-8'))

        if new_url == None or url == new_url:
            baseline[url] = {}
        for error in response.json()['messages']:
            if error['message'] not in baseline[url]:
                baseline[url][error['message']] = error
    with open('e2e/accessibility_baseline.json', 'w') as f:
        json.dump(baseline, f, ensure_ascii=False, indent=4)
