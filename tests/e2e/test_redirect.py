from .base_testcase import BaseTestCase


class RedirectTest(BaseTestCase):

    def __init__(self, testname):
        super().__init__(testname, log_in=True)

    def test_trailing_slash_removal(self):
        """ Test for redirection that removes trailing slash in url. """
        url_template = [
            '/courses/{}/{}',
            '/courses/{}/{}/gradeable',
            '/courses/{}/{}/forum',
            '/courses/{}/{}/course_materials'
        ]
        url_formatted = [url.format(self.semester, 'sample') for url in url_template]

        for url in url_formatted:
            self.get(url + '/')
            # /courses/s22/sample/ -> /courses/s22/sample by redirect
            self.assertEqual(
                self.test_url + url,
                self.driver.current_url,
                "Should redirect to remove trailing slash"
            )

        pass
