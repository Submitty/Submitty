from pathlib import Path
from tempfile import TemporaryDirectory
from unittest import TestCase
from unittest.mock import patch

from migrator.dumper import dump_database

DB_FRAGMENT = """
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

CREATE TRIGGER user_sync_courses_users AFTER INSERT OR UPDATE ON public.courses_users FOR EACH ROW EXECUTE FUNCTION public.sync_courses_user();
"""

DB_EXPECTED = """
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

CREATE TRIGGER user_sync_courses_users AFTER INSERT OR UPDATE ON public.courses_users FOR EACH ROW EXECUTE PROCEDURE public.sync_courses_user();
"""


class TestDumper(TestCase):
    @patch('migrator.dumper.check_output', side_effect=[
        DB_FRAGMENT,
    ])
    def test_dump_database(self, subprocess):
        with TemporaryDirectory() as tmp_dirname:
            data_dir = Path(tmp_dirname, 'data')
            data_dir.mkdir()
            submitty_db = data_dir / 'submitty_db.sql'
            self.assertTrue(dump_database('submitty', submitty_db))
            self.assertTrue(submitty_db.exists())
            self.assertEqual(DB_EXPECTED, submitty_db.read_text())
