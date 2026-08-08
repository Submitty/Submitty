#!/usr/bin/env python3

import unittest

from submitty_jobs.jobs import SyncCourseRepo


class TestCourseRepoSync(unittest.TestCase):
    def test_validate_branch_rejects_option_style_name(self):
        self.assertFalse(SyncCourseRepo._validate_branch('--upload-pack=/tmp/pwned'))

    def test_normalize_course_home_url_allows_empty_or_absolute_url(self):
        self.assertEqual(SyncCourseRepo._normalize_course_detail_setting('course_home_url', ''), '')
        self.assertEqual(
            SyncCourseRepo._normalize_course_detail_setting('course_home_url', 'https://example.com/course'),
            'https://example.com/course',
        )

    def test_normalize_course_home_url_rejects_relative_url(self):
        with self.assertRaisesRegex(RuntimeError, 'absolute URL'):
            SyncCourseRepo._normalize_course_detail_setting('course_home_url', 'blank')
