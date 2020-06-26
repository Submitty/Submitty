from datetime import datetime, timedelta, timezone
from unittest import TestCase
from unittest.mock import patch

from pytz import timezone as pytz_timezone

from submitty_utils import dateutils


class TestDateUtils(TestCase):
    @patch(
        "submitty_utils.dateutils.get_current_time",
        return_value=pytz_timezone('America/New_York').localize(datetime(
            2016, 10, 14, 22, 11, 32, 0
        ))
    )
    @patch(
        "submitty_utils.dateutils.get_timezone",
        return_value=pytz_timezone('America/New_York')
    )
    def test_parse_datetime(self, current_time, get_timezone):
        testcases = (
            (
                '2016-10-14 22:11:32+0200',
                datetime(
                    2016, 10, 14, 22, 11, 32, 0, timezone(timedelta(hours=2))
                )
            ),
            (
                '2016-10-14 22:11:32',
                datetime(
                    2016, 10, 14, 22, 11, 32, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '2016-10-14',
                datetime(
                    2016, 10, 14, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '+1 days',
                datetime(
                    2016, 10, 15, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '+3 day',
                datetime(
                    2016, 10, 17, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '+0 days',
                datetime(
                    2016, 10, 14, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '-1 days',
                datetime(
                    2016, 10, 13, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '-10 day',
                datetime(
                    2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                '+1 day at 10:30:00',
                datetime(
                    2016, 10, 15, 10, 30, 0, 0, timezone(timedelta(hours=-4))
                )
            ),
            (
                datetime(
                    2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=+1))
                ),
                datetime(
                    2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=+1))
                )
            ),
            (
                datetime(
                    2016, 10, 4, 23, 59, 59, 0
                ),
                datetime(
                    2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=-4))
                )
            )
        )

        for testcase in testcases:
            with self.subTest(str(testcase[0])):
                self.assertEqual(
                    testcase[1],
                    dateutils.parse_datetime(testcase[0])
                )

    def test_parse_datetime_invalid_type(self):
        with self.assertRaises(TypeError) as cm:
            dateutils.parse_datetime(10)
        self.assertEqual(
            "Invalid type, expected str, got <class 'int'>",
            str(cm.exception)
        )

    def test_parse_datetime_invalid_format(self):
        with self.assertRaises(ValueError) as cm:
            dateutils.parse_datetime('invalid datetime')

        self.assertEqual(
            'Invalid string for date parsing: invalid datetime',
            str(cm.exception)
        )

    @patch('submitty_utils.dateutils.datetime')
    def test_get_semester(self, mock):
        testcases = (
            (datetime(year=2021, month=1, day=1), 's21'),
            (datetime(year=2020, month=6, day=22), 's20'),
            (datetime(year=2019, month=7, day=1), 'f19'),
            (datetime(year=2020, month=12, day=22), 'f20'),
        )
        for testcase in testcases:
            with self.subTest(testcase[1]):
                mock.today.return_value = testcase[0]
                self.assertEqual(testcase[1], dateutils.get_current_semester())
