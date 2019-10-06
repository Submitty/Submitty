"""
API test runner.

This script loads all of the JSON files under the tests/ directory, and then
runs them against the Submitty API.
"""

from argparse import ArgumentParser
from datetime import datetime
from json import load, loads, dumps
from os import walk
from os.path import dirname, join, realpath
import requests
import unittest
from uuid import uuid1


DIR_PATH = dirname(realpath(__file__))


class ApiTestCase(unittest.TestCase):
    """Base API testcase."""

    def __init__(self, url: str, token: str, test: dict):
        """
        Initialize the testcase.

        :param url: base url that Submitty is running at (with /api)
        :param token: access token to use for Authorization header
        :param test: test instance to be run
        """
        super().__init__()
        self.maxDiff = None
        self.url = url
        self.token = token
        self.test = test

    def runTest(self):
        """Test runner."""
        if self.test['method'] == 'get':
            actual = self.get(self.test['endpoint'])
        else:
            actual = self.post(
                self.test['endpoint'],
                self.test['data'] if 'data' in self.test else dict()
            )
        self.assertEqual(self.test['response'], actual)

    def get(self, endpoint):
        """Get request helper."""
        return requests.get(
            self.url + endpoint,
            headers={
                'Authorization': self.token
            }
        ).json()

    def post(self, endpoint, data):
        """Post request helper."""
        return requests.post(
            self.url + endpoint,
            json=data,
            headers={
                'Authorization': self.token
            }
        ).json()


def suite(api_url: str, token: str):
    """Load the suite of testcases."""
    suite = unittest.TestSuite()

    # suite.addTest(WidgetTestCase('test_default_widget_size'))
    for root, _, files in walk(join(DIR_PATH, 'tests')):
        for name in files:
            with open(join(root, name)) as test_file:
                test_details = load(test_file)
            for test in test_details:
                initialize_values(test)
                test_class = ApiTestCase(api_url, token, test)
                suite.addTest(test_class)
    return suite


def get_current_semester() -> str:
    """Get the current semester for today's date."""
    today = datetime.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]
    return semester


def get_current_year() -> int:
    """Get the year for today's date."""
    return datetime.today().year


def initialize_values(test: dict):
    """Initialize values for API tests."""
    if 'method' not in test:
        test['method'] = 'get'
    test['method'] = test['method'].lower()
    for val in ['endpoint', 'data', 'response']:
        if val in test:
            tmp = dumps(test[val])
            replacements = {
                'current_semester': get_current_semester,
                'current_year': get_current_year,
                'random_uuid': uuid1
            }
            for key, func in replacements.items():
                tmp = tmp.replace(f'<{key}>', f'{func()}')
            test[val] = loads(tmp)


def get_token(api_url: str, user_id: str) -> str:
    """Get a token to use for our tests for a user from Submitty."""
    payload = {'user_id': user_id, 'password': user_id}
    res = requests.post(api_url + '/token', json=payload).json()
    if res['status'] != 'success':
        raise RuntimeError(f'Could not get token for {user_id}')
    return res['data']['token']


def main():
    """Run the test suite."""
    parser = ArgumentParser(description='Run the API test suite')
    parser.add_argument(
        'url',
        type=str,
        nargs='?',
        default='http://192.168.56.111',
        help='URL Submitty is running at'
    )
    args = parser.parse_args()
    api_url = args.url.rstrip('/').rstrip('/api') + '/api'

    token = get_token(api_url, 'instructor')
    runner = unittest.TextTestRunner()
    runner.run(suite(api_url, token))


if __name__ == '__main__':
    main()
