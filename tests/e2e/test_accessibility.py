from .base_testcase import BaseTestCase
import requests
import json
import os

class TestAccessibility(BaseTestCase):
    """
    Test cases revolving around the logging in functionality of the site
    """
    def __init__(self, testname):
        super().__init__(testname, log_in=False)


    # This should contain a url for every type of page on the webiste
    # please replace the semester and course with '/{}/{}'
    # So '/s20/sample/users' becomes '/{}/{}/users'
    urls = [
        '/home',
        '/{}/{}',
        '/{}/{}/gradeable/future_no_tas_homework/update?nav_tab=0',
        '/{}/{}/gradeable/future_no_tas_lab/grading?view=all',
        '/{}/{}/gradeable/future_no_tas_test/grading?view=all',
        '/{}/{}/gradeable/open_homework/grading/status',
        '/{}/{}/gradeable/open_homework/grading/details?view=all',
        '/{}/{}/gradeable/open_homework',
        '/{}/{}/gradeable/open_team_homework/team',
        '/{}/{}/gradeable/grades_released_homework_autota',
        '/{}/{}/notifications',
        '/{}/{}/notifications/settings',
        '/{}/{}/gradeable',
        '/{}/{}/config',
        '/{}/{}/theme',
        '/{}/{}/office_hours_queue',
        '/{}/{}/course_materials',
        '/{}/{}/forum',
        '/{}/{}/forum/threads/new',
        '/{}/{}/forum/categories',
        '/{}/{}/forum/stats',
        '/{}/{}/users',
        '/{}/{}/graders',
        '/{}/{}/sections',
        '/{}/{}/student_photos',
        '/{}/{}/late_days',
        '/{}/{}/extensions',
        '/{}/{}/grade_override',
        '/{}/{}/plagiarism',
        '/{}/{}/plagiarism/configuration/new',
        '/{}/{}/reports',
        '/{}/{}/late_table'
    ]

    baseline_path = ''

    def test_w3_validator(self):
        setup(self)

        # Uncomment this to generate a new baseline for all pages on the website
        # Then run 'python3 -m unittest e2e.test_accessibility' from inside the tests folder
        # genBaseline(self)

        # Uncomment this to generate a new baseline for a specific url
        # url = '' # your url here
        # genBaseline(self, url)

        validatePages(self)

# Any code that should be run before checking for accessibility
def setup(self):
    self.baseline_path = f'{os.path.dirname(os.path.realpath(__file__))}/accessibility_baseline.json'
    self.urls = [url.format(self.get_current_semester(), 'sample') for url in self.urls]


def validatePages(self):
    self.log_out()
    self.log_in(user_id='instructor')
    self.click_class('sample')
    with open(self.baseline_path) as f:
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
            # For some reason the test fails to detect this even though when you actually look at the rendered
            # pages this error is not there. So therefore the test is set to just ignore this error.
            if error['message'].startswith("Start tag seen without seeing a doctype first"):
                continue

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
        with open(self.baseline_path) as f:
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
            # For some reason the test fails to detect this even though when you actually look at the rendered
            # pages this error is not there. So therefore the test is set to just ignore this error.
            if error['message'].startswith("Start tag seen without seeing a doctype first"):
                continue

            if error['message'] not in baseline[url]:
                baseline[url][error['message']] = error
    with open(self.baseline_path, 'w') as file:
        json.dump(baseline, file, ensure_ascii=False, indent=4)
