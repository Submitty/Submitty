import random
import shutil
import sys
import tempfile
import unittest
from argparse import Namespace
from io import StringIO
from pathlib import Path
from types import SimpleNamespace
from unittest.mock import Mock, patch

import migrator
import migrator.db
import migrator.main
from sqlalchemy.exc import OperationalError


class TestLoadTriggers(unittest.TestCase):
    def setUp(self):
        self.stdout = sys.stdout
        sys.stdout = StringIO()
        self.dir = tempfile.mkdtemp()
        self.old_triggers_path = migrator.TRIGGERS_PATH
        migrator.TRIGGERS_PATH = Path(self.dir)

        (migrator.TRIGGERS_PATH / 'master').mkdir()
        (migrator.TRIGGERS_PATH / 'course').mkdir()

        self.data_master = [random.randint(0, 100000) for _ in range(0, 5)]
        for i in range(0, len(self.data_master)):
            with (migrator.TRIGGERS_PATH / 'master' / 'test_fn_master_{}.sql'.format(i))\
                    .open('w') as file:
                file.write(str(self.data_master[i]))

        self.data_course = [random.randint(0, 100000) for _ in range(0, 8)]
        for i in range(0, len(self.data_course)):
            with (migrator.TRIGGERS_PATH / 'course' / 'test_fn_course_{}.sql'.format(i))\
                    .open('w') as file:
                file.write(str(self.data_course[i]))

    def tearDown(self):
        sys.stdout = self.stdout
        shutil.rmtree(self.dir)
        migrator.TRIGGERS_PATH = self.old_triggers_path

    def test_master(self):
        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        for show_output in (True, False):
            with patch.object(migrator.db, 'Database') as mock_class:
                if show_output:
                    migrator.main.load_triggers(args)
                else:
                    migrator.main.load_triggers(args, False)

            self.assertEqual(1, mock_class.call_count)
            masterdb = mock_class.return_value
            self.assertEqual(len(self.data_master), masterdb.execute.call_count)
            self.assertEqual(1, masterdb.commit.call_count)
            self.assertEqual(1, masterdb.close.call_count)

            data = [int(args[0][0]) for args in masterdb.execute.call_args_list]
            self.assertEqual(set(self.data_master), set(data))

            self.assertEqual(
                'Loading trigger functions to master...\n  {}\nDONE\n'
                .format('\n  '.join(['test_fn_master_{}'
                                    .format(self.data_master.index(n)) for n in data])),
                sys.stdout.getvalue()
            )

    def test_course(self):
        args = Namespace()
        args.environments = ['course']
        args.config = SimpleNamespace()
        args.config.database = dict()

        course_logs = ''
        for show_output in (True, False):
            num_courses = random.randint(3, 9)
            mock_instances = [Mock() for _ in range(0, num_courses+2)]
            mock_instances[0].execute.return_value.mappings.return_value.all.return_value = [
                {'term': 's{}'.format(i), 'course': 'c{}'.format(i)}
                for i in range(0, num_courses)
            ]

            with patch.object(migrator.db, 'Database') as mock_class:
                mock_class.side_effect = mock_instances
                if show_output:
                    migrator.main.load_triggers(args)
                else:
                    migrator.main.load_triggers(args, False)

            self.assertEqual(len(mock_instances)-1, mock_class.call_count)
            self.assertEqual('submitty', mock_class.call_args_list[0][0][0]['dbname'])
            self.assertEqual('master', mock_class.call_args_list[0][0][1])
            for i in range(0, num_courses):
                self.assertEqual('submitty_s{0}_c{0}'.format(i),
                                mock_class.call_args_list[i+1][0][0]['dbname'])
                self.assertEqual('course', mock_class.call_args_list[i+1][0][1])

            masterdb = mock_instances[0]
            self.assertEqual(1, masterdb.execute.call_count)
            self.assertEqual(0, masterdb.commit.call_count)
            self.assertEqual(1, masterdb.close.call_count)

            self.assertFalse(mock_instances[-1].called)

            coursedbs = mock_instances[1:-1]
            i = 0
            if show_output:
                for coursedb in coursedbs:
                    self.assertEqual(len(self.data_course), coursedb.execute.call_count)
                    self.assertEqual(1, coursedb.commit.call_count)
                    self.assertEqual(1, coursedb.close.call_count)

                    data = [int(args[0][0]) for args in coursedb.execute.call_args_list]
                    self.assertEqual(set(self.data_course), set(data))

                    course_logs += ('Loading trigger functions to s{0}.c{0}...\n  {1}\nDONE\n'
                                    .format(i, '\n  '.join(
                                        ['test_fn_course_{}'
                                        .format(self.data_course.index(n)) for n in data]
                                    )))
                    i += 1

            self.assertEqual(course_logs, sys.stdout.getvalue())

    def test_system(self):
        args = Namespace()
        args.environments = ['system']
        args.config = SimpleNamespace()
        args.config.database = dict()

        migrator.main.load_triggers(args)

        self.assertEqual('', sys.stdout.getvalue())

    def test_no_trigger_files(self):
        trigger_path = migrator.TRIGGERS_PATH
        migrator.TRIGGERS_PATH = Path(tempfile.mkdtemp())
        (migrator.TRIGGERS_PATH / 'master').mkdir()
        (migrator.TRIGGERS_PATH / 'course').mkdir()

        args = Namespace()
        args.environments = ['master', 'course']
        args.config = SimpleNamespace()
        args.config.database = dict()

        for show_output in (True, False):
            with patch.object(migrator.db, 'Database') as mock_class:
                if show_output:
                    migrator.main.load_triggers(args)
                else:
                    migrator.main.load_triggers(args, False)

            self.assertFalse(mock_class.called)

            self.assertEqual('Loading trigger functions to master...DONE\n'
                             'Loading trigger functions to course...DONE\n', sys.stdout.getvalue())

        shutil.rmtree(migrator.TRIGGERS_PATH.absolute())
        migrator.TRIGGERS_PATH = trigger_path

    def test_master_db_fail(self):
        args = Namespace()
        args.environments = ['master']
        args.config = SimpleNamespace()
        args.config.database = dict()

        for show_output in (True, False):
            with patch.object(migrator.db, 'Database') as mock_class:
                mock_class.side_effect = OperationalError(None, None,
                                                          'first '
                                                          + ('a' if show_output else 'b')
                                                          + '\nsecond')
                with self.assertRaises(SystemExit) as cm:
                    if show_output:
                        migrator.main.load_triggers(args)
                    else:
                        migrator.main.load_triggers(args, False)

            self.assertTrue(mock_class.called)
            if show_output:
                self.assertEqual('Error connecting to master database:\n  first a', cm.exception.args[0])
            else:
                self.assertEqual('\nError connecting to master database:\n  first b', cm.exception.args[0])

    def test_course_db_fail(self):
        args = Namespace()
        args.environments = ['course']
        args.config = SimpleNamespace()
        args.config.database = dict()

        for show_output in (True, False):
            masterdb = Mock()
            masterdb.execute.return_value.mappings.return_value.all.return_value = [
                {
                    'term': 'my_semester_1',
                    'course': 'my_course_1'
                },
                {
                    'term': 'my_semester_2',
                    'course': 'my_course_2'
                }
            ]

            with patch.object(migrator.db, 'Database') as mock_class:
                mock_class.side_effect = [
                    masterdb,
                    OperationalError(None, None, 'first ' + ('a' if show_output else 'b') + '\nsecond'),
                    OperationalError(None, None, 'third ' + ('a' if show_output else 'b') + '\nfourth')
                ]
                if show_output:
                    migrator.main.load_triggers(args)
                else:
                    migrator.main.load_triggers(args, False)

            self.assertEqual(3, mock_class.call_count)
            self.assertEqual(0, mock_class.return_value.execute.call_count)

        self.assertEqual(
            'Failed to connect to course db \'submitty_my_semester_1_my_course_1\'\n'
            '  Error: first a\n'
            'Failed to connect to course db \'submitty_my_semester_2_my_course_2\'\n'
            '  Error: third a\n'
            '\n'
            'Failed to connect to course db \'submitty_my_semester_1_my_course_1\'\n'
            '  Error: first b\n'
            'Failed to connect to course db \'submitty_my_semester_2_my_course_2\'\n'
            '  Error: third b\n',
            sys.stdout.getvalue()
        )
