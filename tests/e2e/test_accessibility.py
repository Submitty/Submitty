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
        '/home/courses/new',
        '/{}/{}',
        '/{}/{}/gradeable/future_no_tas_homework/update?nav_tab=0',
        '/{}/{}/autograding_config?g_id=future_no_tas_homework',
        '/{}/{}/gradeable/future_no_tas_lab/grading?view=all',
        '/{}/{}/gradeable/future_no_tas_test/grading?view=all',
        '/{}/{}/gradeable/open_homework/grading/status',
        '/{}/{}/gradeable/open_homework/bulk_stats',
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
        '/{}/{}/late_table',
        '/{}/{}/grades',
    ]

    baseline_path = ''

    def test_w3_validator(self):
        # Uncomment this to generate a new baseline for all pages on the website
        # Then run 'python3 -m unittest e2e.test_accessibility' from inside the tests folder
        # self.genBaseline()

        self.validatePages()


    # Any code that should be run before checking for accessibility
    def setUp(self):
        super().setUp()
        self.baseline_path = f'{os.path.dirname(os.path.realpath(__file__))}/accessibility_baseline.json'
        self.urls = [url.format(self.get_current_semester(), 'sample') for url in self.urls]


    def validatePages(self):
        self.log_out()
        self.log_in(user_id='instructor')
        self.click_class('sample')
        with open(self.baseline_path) as f:
            baseline = json.load(f)

        self.maxDiff = None
        for url in self.urls:
            with self.subTest(url=url):
                foundErrors = []
                foundErrorMessages = []
                self.get(url=url)

                payload = self.driver.page_source
                headers = {
                    'Content-Type': 'text/html; charset=utf-8'
                }
                response = requests.request(
                    "POST", "https://validator.w3.org/nu/?out=json", headers=headers, data=payload.encode('utf-8'))

                for error in response.json()['messages']:
                    # For some reason the test fails to detect this even though when you actually look at the rendered
                    # pages this error is not there. So therefore the test is set to just ignore this error.
                    if error['message'].startswith("Start tag seen without seeing a doctype first"):
                        continue
                    if error['message'].startswith("Possible misuse of “aria-label”"):
                        continue

                    if error['message'] not in baseline[url] and error['message'] not in foundErrorMessages:
                        # print(json.dumps(error, indent=4, sort_keys=True))
                        foundErrorMessages.append(error['message'])
                        clean_error = {
                            "error": error['message'].replace('\u201c',"'").replace('\u201d',"'").strip(),
                            "html extract": error['extract'].strip(),
                            "type": error['type'].strip()
                        }
                        foundErrors.append(clean_error)

                msg = f"\n{json.dumps(foundErrors, indent=4, sort_keys=True)}\nMore info can be found by using the w3 html validator. You can read more about it on submitty.org:\nhttps://validator.w3.org/#validate_by_input\nhttps://submitty.org/developer/interface_design_style_guide/web_accessibility#html-css-and-javascript"
                self.assertFalse(foundErrors != [], msg=msg)


    def genBaseline(self):
        self.log_out()
        self.log_in(user_id='instructor')
        self.click_class('sample')

        baseline = {}
        urls = self.urls

        for url in urls:
            self.get(url=url)
            payload = self.driver.page_source
            headers = {
                'Content-Type': 'text/html; charset=utf-8'
            }
            response = requests.request(
                "POST", "https://validator.w3.org/nu/?out=json", headers=headers, data=payload.encode('utf-8'))

            baseline[url] = []
            for error in response.json()['messages']:
                # For some reason the test fails to detect this even though when you actually look at the rendered
                # pages this error is not there. So therefore the test is set to just ignore this error.
                if error['message'].startswith("Start tag seen without seeing a doctype first"):
                    continue
                if error['message'].startswith("Possible misuse of “aria-label”"):
                    continue

                if error['message'] not in baseline[url]:
                    baseline[url].append(error['message'])
        with open(self.baseline_path, 'w') as file:
            json.dump(baseline, file, ensure_ascii=False, indent=4)
