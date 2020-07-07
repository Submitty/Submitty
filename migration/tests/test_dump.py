from argparse import Namespace
from io import StringIO
from pathlib import Path
import sys
from tempfile import TemporaryDirectory
from types import SimpleNamespace
from unittest import TestCase
from unittest.mock import patch

from migrator.main import dump

COURSE_DB_FRAGMENT = """
--
-- PostgreSQL database dump
--

-- Dumped from database version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)
-- Dumped by pg_dump version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: notifications_component; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.notifications_component AS ENUM (
    'forum',
    'student',
    'grading',
    'team'
);
"""

COURSE_DB_EXPECTED = """
--
-- PostgreSQL database dump
--


SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: notifications_component; Type: TYPE; Schema: public; Owner: -
--

CREATE TYPE public.notifications_component AS ENUM (
    'forum',
    'student',
    'grading',
    'team'
);
"""

MASTER_DB_FRAGMENT = """
--
-- PostgreSQL database dump
--

-- Dumped from database version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)
-- Dumped by pg_dump version 10.12 (Ubuntu 10.12-0ubuntu0.18.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: courses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    status smallint DEFAULT 1 NOT NULL
);
"""

MASTER_DB_EXPECTED = """
--
-- PostgreSQL database dump
--


SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: courses; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.courses (
    semester character varying(255) NOT NULL,
    course character varying(255) NOT NULL,
    status smallint DEFAULT 1 NOT NULL
);
"""


class TestDump(TestCase):
    def setUp(self):
        sys.stdout = StringIO()

    def tearDown(self):
        sys.stdout = sys.__stdout__

    @patch('migrator.main.subprocess.check_output', side_effect=[
        MASTER_DB_FRAGMENT,
        COURSE_DB_FRAGMENT
    ])
    def test_dump_all(self, subprocess):
        with TemporaryDirectory() as tmp_dirname:
            config = SimpleNamespace()
            config.database = {
                'database_driver': 'psql'
            }
            args = Namespace()
            args.config = config
            args.environments = ['master', 'course']
            args.path = tmp_dirname
            data_dir = Path(tmp_dirname, 'data')
            data_dir.mkdir()
            dump(args)
            submitty_db = data_dir / 'submitty_db.sql'
            self.assertTrue(submitty_db.exists())
            self.assertEqual(MASTER_DB_EXPECTED, submitty_db.read_text())
            course_db = data_dir / 'course_tables.sql'
            self.assertTrue(course_db.exists())
            self.assertEqual(COURSE_DB_EXPECTED, course_db.read_text())
            self.assertRegex(
                sys.stdout.getvalue(),
                r"Dumping master environment to .*/data/submitty_db.sql... DONE\n" +
                r"Dumping course environment to .*/data/course_tables.sql... DONE\n"
            )

    @patch('migrator.main.subprocess.check_output', side_effect=[
        MASTER_DB_FRAGMENT
    ])
    def test_dump_master(self, subprocess):
        with TemporaryDirectory() as tmp_dirname:
            config = SimpleNamespace()
            config.database = {
                'database_driver': 'psql'
            }
            args = Namespace()
            args.config = config
            args.environments = ['master']
            args.path = tmp_dirname
            data_dir = Path(tmp_dirname, 'data')
            data_dir.mkdir()
            dump(args)
            submitty_db = data_dir / 'submitty_db.sql'
            self.assertTrue(submitty_db.exists())
            self.assertEqual(MASTER_DB_EXPECTED, submitty_db.read_text())
            self.assertRegex(
                sys.stdout.getvalue(),
                r"Dumping master environment to .*/data/submitty_db.sql... DONE"
            )

    @patch('migrator.main.subprocess.check_output', side_effect=[
        COURSE_DB_EXPECTED
    ])
    def test_dump_course(self, subprocess):
        with TemporaryDirectory() as tmp_dirname:
            config = SimpleNamespace()
            config.database = {
                'database_driver': 'psql'
            }
            args = Namespace()
            args.config = config
            args.environments = ['course']
            args.path = tmp_dirname
            data_dir = Path(tmp_dirname, 'data')
            data_dir.mkdir()
            dump(args)
            course_db = data_dir / 'course_tables.sql'
            self.assertTrue(course_db.exists())
            self.assertEqual(COURSE_DB_EXPECTED, course_db.read_text())
            self.assertRegex(
                sys.stdout.getvalue(),
                r"Dumping course environment to .*/data/course_tables.sql... DONE"
            )

    def test_dump_non_psql_driver(self):
        with self.assertRaises(SystemExit) as cm:
            config = SimpleNamespace()
            config.database = {
                'database_driver': 'sqlite'
            }
            args = Namespace()
            args.config = config
            dump(args)
        self.assertEqual(str(cm.exception), 'Cannot dump schema for non-postgresql database')
