#!/usr/bin/env python3

"""Basic migration script to handle the database."""

from argparse import ArgumentParser
from collections import OrderedDict
from datetime import datetime
from importlib.machinery import SourceFileLoader
import json
import os
from pathlib import Path
import re
import sys

import psycopg2

VERSION = "1.3.0"
DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
ENVIRONMENTS = ['master', 'system', 'course']


class Config:
    """
    Class to hold the config details for Submitty to use within the migrator.

    It dynamically loads the JSON files in the config directory into a dictionary
    with the maching name accessible at Config.<name> (e.g. Config.database).
    """

    def __init__(self, config_path):
        """
        Initialize the Config class, loading files from the passed in config_path.

        :param config_path: Path or str to the config directory for Submitty
        """
        self.config_path = Path(config_path)

        self.database = self._get_data('database')
        self.submitty = self._get_data('submitty')
        self.submitty_users = self._get_data('submitty_users')

    def _get_data(self, filename):
        with Path(self.config_path, filename + '.json').open('r') as open_file:
            return json.load(open_file, object_pairs_hook=OrderedDict)


def parse_args():
    """
    Parse the arguments passed to the script using argparse.

    :return: argparse.Namespace of the parsed args
    """
    desc = 'Migration script for upgrading/downgrading the database'
    parser = ArgumentParser(description=desc)
    parser.add_argument(
        '-v', '--version', action='version',
        version='%(prog)s {}'.format(VERSION)
    )
    config_path = Path(DIR_PATH, '..', '..', '..', 'config')
    default_config = config_path.resolve() if config_path.exists() else None
    required = False if default_config is not None else True
    parser.add_argument(
        '-c', '--config', dest='config_path', type=str,
        required=required, default=default_config
    )
    parser.add_argument(
        '-e', '--environment', dest='environments',
        choices=ENVIRONMENTS, action='append'
    )
    parser.add_argument(
        '--course', dest='choose_course', nargs=2,
        metavar=('semester', 'course'), default=None
    )

    subparsers = parser.add_subparsers(metavar='command', dest='command')
    subparsers.required = True
    sub = subparsers.add_parser('create', help='Create migration')
    sub.add_argument('name', help='Name of new migration')
    sub = subparsers.add_parser('migrate', help='Run migrations')
    sub.add_argument(
        '--single', action='store_true', default=False,
        dest='single', help='Only run one migration'
    )
    sub.add_argument(
        '--fake', action='store_true', default=False, dest='set_fake',
        help='Mark migrations as run without actually running them'
    )
    sub.add_argument(
        '--initial', action='store_true', default=False,
        help='Only run the first migration and then mark '
             'the rest as done without running them.'
    )
    subparsers.add_parser('rollback', help='Rollback the previously done migration')
    args = parser.parse_args()
    if args.environments is None:
        args.environments = ENVIRONMENTS
    # make sure the order is of 'master', 'system', 'course' depending on what
    # environments have been selected system must be run after master initially
    # as system relies on master DB being setup for migration table
    environments = []
    for env in ENVIRONMENTS:
        if env in args.environments:
            environments.append(env)
    args.environments = environments
    args.config = Config(args.config_path)
    return args


def main():
    """Run the migrator tool, parsing the incoming arguments and a command."""
    args = parse_args()
    getattr(sys.modules[__name__], args.command)(args)


def create(args):
    """
    Create a new migration.

    This creates a new migration file for the specified environments. Each
    generated file will have an up() and down() function, but depending on
    the environment, different parameters to the function (e.g. system will
    just have a config variable, master will have config and master DB connection,
    etc.). The file's name is prefixed with a timestamp and then suffixed with
    a user given name, that can only contain alphanumerics or underscores.

    :param args:
    :type args: argparse.Namespace
    """
    now = datetime.now()
    date_args = [now.year, now.month, now.day, now.hour, now.minute, now.second]
    ver = "{:04}{:02}{:02}{:02}{:02}{:02}".format(*date_args)
    filename = "{}_{}".format(ver, args.name)
    check = re.search(r'[^A-Za-z0-9_\-]', filename)
    if check is not None:
        raise ValueError("Name '{}' contains invalid character '{}'".format(
            filename,
            check.group(0)
        ))
    filename += '.py'
    for environment in args.environments:
        parameters = ['config']
        if environment in ['course', 'master']:
            parameters.append('conn')
        if environment == 'course':
            parameters.append('semester')
            parameters.append('course')
        with Path(MIGRATIONS_PATH, environment, filename).open('w') as open_file:
            open_file.write("""def up({0}):
    pass


def down({0}):
    pass""".format(', '.join(parameters)))


def migrate(args):
    """
    Run the migrator in the up (migrate) direction.

    When migrating, the user can pass in arguments to specify to run
    just a single migration, fake the migrations, or only run the first
    migration and mark the rest as done. We can tell we're migrating
    up by checking args.direction.

    :param args: arguments parsed from argparse
    :type args: argparse.Namespace
    """
    args.direction = 'up'
    handle_migration(args)


def rollback(args):
    """
    Run the migrator in the down (rollback) direction.

    This will only rollback one migration at a time. We can tell
    we're rolling back by checking args.direction is down.

    :param args: arguments parsed from argparse
    :type args: argparse.Namespace
    """
    args.direction = 'down'
    handle_migration(args)


def handle_migration(args):
    """
    Run a helper function for handling the migration in both directions.

    Both up and down directions for the migrator contains a fair
    amount of boilerplate type code for creating DB connection for
    the migration, as well as if we're doing a migration for courses,
    iterate through the list of courses that we have

    :param args: arguments parsed from argparse
    :type args: argparse.Namespace
    """
    for environment in args.environments:
        args.course = None
        args.semester = None
        if environment in ['master', 'system']:
            params = {
                'dbname': 'submitty',
                'host': args.config.database['database_host'],
                'user': args.config.database['database_user'],
                'password': args.config.database['database_password']
            }

            print("Running {} migrations for {}...".format(
                args.direction, environment
            ), end="")
            with psycopg2.connect(**params) as connection:
                migrate_environment(connection, environment, args)

        if environment == 'course':
            params = {
                'host': args.config.database['database_host'],
                'user': args.config.database['database_user'],
                'password': args.config.database['database_password']
            }

            course_dir = Path(args.config.submitty['submitty_data_dir'], 'courses')
            if not course_dir.exists():
                print("Could not find courses directory: {}".format(course_dir))
            else:
                for semester in os.listdir(str(course_dir)):
                    for course in os.listdir(os.path.join(str(course_dir), semester)):
                        if args.choose_course is not None and \
                           [semester, course] != args.choose_course:
                            continue
                        args.semester = semester
                        args.course = course
                        print("Running {} migrations for {}.{}...".format(
                            args.direction, semester, course
                        ), end="")
                        params['dbname'] = 'submitty_{}_{}'.format(semester, course)
                        try:
                            with psycopg2.connect(**params) as connection:
                                migrate_environment(connection, environment, args)
                        except psycopg2.OperationalError:
                            print("Submitty Database Migration Warning:  "
                                  "Database does not exist for "
                                  "semester={} course={}".format(semester, course))


def migrate_environment(connection, environment, args):
    """
    Determine list of migrations/rollback steps that need to be run for environment.

    This function handles the actual business of migrating/rolling back based
    on a passed in environment and connection for that environment (system and master
    have DB connection to master DB and course has DB connection to their own DB).
    This function, given that information then determines the list of migrations
    that exist within the system, compare them against the list of migrations that
    are stored within the DB and if we find migrations that are in the DB, but not
    within the repo, we roll those back before continuing. After that, we now have
    the candidate migration set, which is then used for migrating up or rolling back.
    The candidate migration set is an OrderedDict wherein each value of the dict
    contains the loaded migration file as a module.

    :param connection: connection to the DB
    :param environment: environment we're using for migration step
    :param args: arguments parsed from argparse
    """
    missing_migrations = OrderedDict()
    migrations = load_migrations(MIGRATIONS_PATH / environment)

    # Check if the migration table exists, which it won't on the first time
    # we run the migrator. The initial migration is what creates this table for us.
    with connection.cursor() as cursor:
        cursor.execute(
            "SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE "
            "table_schema='public' AND "
            "table_name='migrations_{}')".format(environment)
        )
        exists = cursor.fetchone()[0]

    changes = False
    if exists:
        with connection.cursor() as cursor:
            cursor.execute('SELECT id, commit_time, status FROM migrations_{} '
                           'ORDER BY id'.format(environment))
            for migration in cursor.fetchall():
                # fetchall returns things as a tuple which is a bit unwieldy
                migration = {
                    'id': migration[0],
                    'commit_time': migration[1],
                    'status': migration[2]
                }
                if migration['id'] in migrations:
                    migrations[migration['id']].update({
                        'commit_time': migration['commit_time'],
                        'status': migration['status'],
                        'db': True
                    })
                else:
                    missing_migrations[migration['id']] = migration

    if len(missing_migrations) > 0:
        if not changes:
            print()
        print('Removing {} missing migrations:'.format(len(missing_migrations)))
        for key in missing_migrations:
            remove_migration(connection, missing_migrations[key], environment, args)
            changes = True
        print()

    args.fake = args.set_fake if 'set_fake' in args else False
    if args.direction == 'up':
        keys = list(migrations.keys())
        if args.initial is True:
            key = keys.pop(0)
            if not changes:
                print("")
                changes = True
            run_migration(connection, migrations[key], environment, args)
            args.fake = True
        for key in keys:
            if migrations[key]['status'] == 0:
                run_migration(connection, migrations[key], environment, args)
                if args.single:
                    break
    else:
        for key in reversed(list(migrations.keys())):
            if migrations[key]['status'] == 1:
                run_migration(connection, migrations[key], environment, args)
                break

    print("DONE")
    if changes:
        print()


def remove_migration(connection, migration, environment, args):
    """Remove migrations that exist on the system, but not within the migrator tool."""
    print("  {}".format(migration['id']))
    file_path = Path(
        args.config.submitty['submitty_install_dir'], 'migrations',
        environment, migration['id'] + '.py'
    )
    if file_path.exists():
        module = load_migration_module(migration['id'], file_path)
        call_func(getattr(module, 'down', noop), connection, environment, args)
        file_path.unlink()
    with connection.cursor() as cursor:
        cursor.execute(
            'DELETE FROM migrations_{} WHERE id=%s'.format(environment),
            (migration['id'],)
        )
    connection.commit()


def call_func(func, connection, environment, args):
    """
    Call a user supplied function with variable number of parameters.

    Depending on the environment that this function is being run under,
    it gets a different list of arguments.
    """
    parameters = [args.config]
    if environment in ['course', 'master']:
        parameters.append(connection)
    if environment == 'course':
        parameters.append(args.semester)
        parameters.append(args.course)
    func(*parameters)


def noop(_):
    """Run noop function that does nothing if migration is missing up or down func."""
    pass


def run_migration(connection, migration, environment, args):
    """Run the actual migration/rollback function for the migration module."""
    print("  {}{}".format(migration['id'], ' (FAKE)' if args.fake else ''))
    if not args.fake:
        call_func(
            getattr(migration['module'], args.direction, noop),
            connection,
            environment,
            args
        )
    status = 1 if args.direction == 'up' else 0
    with connection.cursor() as cursor:
        if migration['db']:
            cursor.execute(
                'UPDATE migrations_{} SET commit_time=CURRENT_TIMESTAMP, '
                'status=%s WHERE id=%s'.format(environment),
                (status, migration['id'],)
            )
        else:
            cursor.execute(
                'INSERT INTO migrations_{} (id, status) VALUES(%s, %s)'.format(
                    environment
                ),
                (migration['id'], status,)
            )
    connection.commit()


def load_migration_module(name, path):
    """Load the migration file as a python module."""
    # TODO: change this to not use deprecated loader.load_module
    # after dropping Python 3.4 support
    loader = SourceFileLoader(name, str(path))
    module = loader.load_module(name)
    return module


def load_migrations(path):
    """Given a path, load all migrations in that path."""
    migrations = OrderedDict()
    filtered = filter(
        lambda x: x.endswith('.py'),
        [x.name for x in path.iterdir()]
    )
    for migration in sorted(filtered):
        migration_id = migration[:-3]
        migrations[migration_id] = {
            'id': migration_id,
            'commit_time': None,
            'status': 0,
            'db': False,
            'module': load_migration_module(migration_id, path / migration)
        }
    return migrations


if __name__ == '__main__':
    main()
