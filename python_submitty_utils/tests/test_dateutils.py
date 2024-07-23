from datetime import datetime, timedelta, timezone
from zoneinfo import ZoneInfo
from unittest import TestCase
from unittest.mock import patch
import unittest
import tzlocal

from submitty_utils import dateutils


class TestDateUtils(TestCase):
    @patch(
        "submitty_utils.dateutils.datetime",
    )
    def test_get_current_time(self, mock_datetime):
        fixed_time = datetime(2016, 10, 14, 22, 11, 32, 0)
        mock_datetime.now.return_value = fixed_time
        curr_time = dateutils.get_current_time()

        self.assertEqual(curr_time, fixed_time)

    @patch(
        "submitty_utils.dateutils.get_current_time",
        return_value=datetime(
            2016, 10, 14, 22, 11, 32, 0, tzinfo=ZoneInfo("America/New_York")
        ),
    )
    def test_write_submitty_date_default(self, current_time):
        date = dateutils.write_submitty_date()
        self.assertTrue(current_time.called)
        self.assertEqual("2016-10-14 22:11:32-0400", date)

    def test_write_submitty_date(self):
        testcases = (
            (
                datetime(2020, 6, 12, 3, 21, 30, tzinfo=ZoneInfo("UTC")),
                "2020-06-12 03:21:30+0000",
            ),
            (
                datetime(2020, 12, 25, 3, 21, 30, tzinfo=ZoneInfo("UTC")),
                "2020-12-25 03:21:30+0000",
            ),
            (
                datetime(2020, 6, 12, 3, 21, 30, 123, tzinfo=ZoneInfo("UTC")),
                "2020-06-12 03:21:30+0000",
            ),
            (
                datetime(2020, 6, 12, 3, 21, 30, tzinfo=ZoneInfo("America/New_York")),
                "2020-06-12 03:21:30-0400",
            ),
            (
                datetime(2020, 12, 12, 3, 21, 30, tzinfo=ZoneInfo("America/New_York")),
                "2020-12-12 03:21:30-0500",
            ),
        )
        for testcase in testcases:
            with self.subTest(i=testcase[0]):
                self.assertEqual(
                    testcase[1], dateutils.write_submitty_date(testcase[0])
                )

    def test_write_submitty_date_microseconds(self):
        testcases = (
            (
                datetime(2020, 6, 12, 3, 21, 30, tzinfo=ZoneInfo("UTC")),
                "2020-06-12 03:21:30.000+0000",
            ),
            (
                datetime(2020, 6, 12, 3, 21, 30, 123500, tzinfo=ZoneInfo("UTC")),
                "2020-06-12 03:21:30.123+0000",
            ),
            (
                datetime(
                    2020, 6, 12, 3, 21, 30, 211500, tzinfo=ZoneInfo("America/New_York")
                ),
                "2020-06-12 03:21:30.211-0400",
            ),
        )
        for testcase in testcases:
            with self.subTest(i=testcase[0]):
                self.assertEqual(
                    testcase[1], dateutils.write_submitty_date(testcase[0], True)
                )

    def test_invalid_type_write_submitty_date(self):
        testcases = ("2020-06-12 03:21:30.123+0000", 10)
        for testcase in testcases:
            with self.subTest(testcase):
                with self.assertRaises(TypeError) as cm:
                    dateutils.write_submitty_date(10)
                self.assertEqual(
                    "Invalid type. Expected datetime or datetime string,"
                    " got <class 'int'>.",
                    str(cm.exception),
                )

    def test_read_submitty_date(self):
        test_cases = (
            (
                "2020-06-12 03:21:30+0000",
                datetime(2020, 6, 12, 3, 21, 30, tzinfo=ZoneInfo("UTC")),
            ),
            (
                "2020-12-25 03:21:30+0000",
                datetime(2020, 12, 25, 3, 21, 30, tzinfo=ZoneInfo("UTC")),
            ),
            (
                "2020-06-12 03:21:30-0400",
                datetime(
                    2020,
                    6,
                    12,
                    3,
                    21,
                    30,
                    tzinfo=timezone(timedelta(days=-1, seconds=72000)),
                ),
            ),
            (
                "2020-12-12 03:21:30-0500",
                datetime(
                    2020,
                    12,
                    12,
                    3,
                    21,
                    30,
                    tzinfo=timezone(timedelta(days=-1, seconds=68400)),
                ),
            ),
        )

        for test_case in test_cases:
            with self.subTest(i=test_case[0]):
                self.assertEqual(
                    test_case[1], dateutils.read_submitty_date(test_case[0])
                )

    def test_read_submitty_date_unexpected_format(self):
        date = "2024-03-29"

        with self.assertRaises(SystemExit) as cm:
            dateutils.read_submitty_date(date)

        self.assertEqual(f"ERROR: unexpected date format {date}", str(cm.exception))

    def test_read_submitty_date_no_timezone(self):
        parsed_date = dateutils.read_submitty_date("2020-06-12 03:21:30")
        expected_date = datetime(2020, 6, 12, 3, 21, 30, tzinfo=tzlocal.get_localzone())

        self.assertEqual(expected_date, parsed_date)

    def test_read_submitty_date_short_timezone(self):
        parsed_date = dateutils.read_submitty_date("2020-06-12 03:21:30-04")
        expected_date = datetime(
            2020,
            6,
            12,
            3,
            21,
            30,
            tzinfo=timezone(timedelta(days=-1, seconds=72000)),
        )

        self.assertEqual(parsed_date, expected_date)

    def test_read_submitty_date_bad_format(self):
        date = "2020-06-12 03:21:30-4"

        with self.assertRaises(SystemExit) as cm:
            dateutils.read_submitty_date(date)

        self.assertEqual(f"ERROR:  invalid date format {date}", str(cm.exception))

    @patch(
        "submitty_utils.dateutils.get_current_time",
        return_value=datetime(
            2016, 10, 14, 22, 11, 32, 0, tzinfo=tzlocal.get_localzone()
        ),
    )
    def test_parse_datetime(self, _current_time):
        local_zone = tzlocal.get_localzone()
        testcases = (
            (
                "2016-10-14 22:11:32+0200",
                datetime(2016, 10, 14, 22, 11, 32, 0, timezone(timedelta(hours=2))),
            ),
            (
                "2016-10-14 22:11:32",
                datetime(2016, 10, 14, 22, 11, 32, 0, tzinfo=local_zone),
            ),
            (
                "2016-10-14",
                datetime(2016, 10, 14, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "+1 days",
                datetime(2016, 10, 15, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "+3 day",
                datetime(2016, 10, 17, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "+0 days",
                datetime(2016, 10, 14, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "-1 days",
                datetime(2016, 10, 13, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "-10 day",
                datetime(2016, 10, 4, 23, 59, 59, 0, tzinfo=local_zone),
            ),
            (
                "+1 day at 10:30:00",
                datetime(2016, 10, 15, 10, 30, 0, 0, tzinfo=local_zone),
            ),
            (
                datetime(2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=+1))),
                datetime(2016, 10, 4, 23, 59, 59, 0, timezone(timedelta(hours=+1))),
            ),
            (
                datetime(2016, 10, 4, 23, 59, 59, 0),
                datetime(2016, 10, 4, 23, 59, 59, 0, tzinfo=local_zone),
            ),
        )

        for testcase in testcases:
            with self.subTest(str(testcase[0])):
                self.assertEqual(testcase[1], dateutils.parse_datetime(testcase[0]))

    def test_parse_datetime_none(self):
        parsed_date = dateutils.parse_datetime(None)
        self.assertIsNone(parsed_date)

    def test_parse_datetime_none(self):
        parsed_date = dateutils.parse_datetime(None)
        self.assertIsNone(parsed_date)

    def test_parse_datetime_invalid_type(self):
        with self.assertRaises(TypeError) as cm:
            dateutils.parse_datetime(10)
        self.assertEqual(
            "Invalid type, expected str, got <class 'int'>", str(cm.exception)
        )

    def test_parse_datetime_invalid_format(self):
        with self.assertRaises(ValueError) as cm:
            dateutils.parse_datetime("invalid datetime")

        self.assertEqual(
            "Invalid string for date parsing: invalid datetime", str(cm.exception)
        )

    @patch("submitty_utils.dateutils.datetime")
    def test_get_semester(self, mock):
        testcases = (
            (datetime(year=2021, month=1, day=1), "s21"),
            (datetime(year=2020, month=6, day=22), "s20"),
            (datetime(year=2019, month=7, day=1), "f19"),
            (datetime(year=2020, month=12, day=22), "f20"),
        )
        for testcase in testcases:
            with self.subTest(testcase[1]):
                mock.today.return_value = testcase[0]
                self.assertEqual(testcase[1], dateutils.get_current_semester())


if __name__ == "__main__":
    unittest.main()
