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
    # please replace the semester and course with '/courses/{}/{}'
    # So '/courses/s20/sample/users' becomes '/courses/{}/{}/users'
    urls = [
        '/home',
        '/home/courses/new',
        '/courses/{}/{}',
        '/courses/{}/{}/gradeable/future_no_tas_homework/update?nav_tab=0',
        '/courses/{}/{}/autograding_config?g_id=future_no_tas_homework',
        '/courses/{}/{}/gradeable/future_no_tas_lab/grading?view=all',
        '/courses/{}/{}/gradeable/future_no_tas_test/grading?view=all',
        '/courses/{}/{}/gradeable/open_homework/grading/status',
        '/courses/{}/{}/gradeable/open_homework/bulk_stats',
        '/courses/{}/{}/gradeable/open_homework/grading/details?view=all',
        '/courses/{}/{}/gradeable/open_homework',
        '/courses/{}/{}/gradeable/open_team_homework/team',
        '/courses/{}/{}/gradeable/grades_released_homework_autota',
        '/courses/{}/{}/notifications',
        '/courses/{}/{}/notifications/settings',
        '/courses/{}/{}/gradeable',
        '/courses/{}/{}/config',
        '/courses/{}/{}/theme',
        '/courses/{}/{}/office_hours_queue',
        '/courses/{}/{}/course_materials',
        '/courses/{}/{}/forum',
        '/courses/{}/{}/forum/threads/new',
        '/courses/{}/{}/forum/categories',
        '/courses/{}/{}/forum/stats',
        '/courses/{}/{}/users',
        '/courses/{}/{}/graders',
        '/courses/{}/{}/sections',
        '/courses/{}/{}/student_photos',
        '/courses/{}/{}/late_days',
        '/courses/{}/{}/extensions',
        '/courses/{}/{}/grade_override',
        '/courses/{}/{}/plagiarism',
        '/courses/{}/{}/plagiarism/configuration/new',
        '/courses/{}/{}/reports',
        '/courses/{}/{}/late_table',
        '/courses/{}/{}/grades',
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
        self.urls = [url.format(self.semester, 'sample') for url in self.urls]


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
