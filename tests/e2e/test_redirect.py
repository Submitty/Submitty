from .base_testcase import BaseTestCase


class RedirectTest(BaseTestCase):
    def __init__(self, testname):
        super().__init__(testname, log_in=True)

    def test_redirect(self):
        url_template = [
            '/courses/{}/{}',
            '/courses/{}/{}/gradeable',
            '/courses/{}/{}/forum',
            '/courses/{}/{}/course_materials'
        ]

        url_formatted = [url.format(self.semester, 'sample') for url in url_template]        

        for url in url_formatted:
            redirect_from = url + '/'
            self.get(url)
            redirected_to = self.driver.current_url
            msg = "Redirection to remove trailing slash not accomplished"
            self.assertEqual(redirect_from, redirected_to, msg)

        pass

