#!/usr/bin/env python3

"""
Basic migration script to handle the database
"""

from argparse import ArgumentParser
from collections import OrderedDict
from datetime import datetime
import importlib.util
import json
import os
from pathlib import Path
import sys

import psycopg2


DIR_PATH = Path(__file__).parent.resolve()
MIGRATIONS_PATH = DIR_PATH / 'migrations'
ENVIRONMENTS = [path.name for path in MIGRATIONS_PATH.iterdir()]


def parse_args():
    parser = ArgumentParser(description='Migration script for upgrading/downgrading the database')
    parser.add_argument('--environment', '-e', dest='environments', choices=ENVIRONMENTS, action='append')
    parser.add_argument('--course', nargs=2, metavar=('semester', 'course'), default=None)
    config_path = Path(DIR_PATH, '..', '..', 'config')
    default_config = config_path.resolve() if config_path.exists() else None
    required = False if default_config is not None else True
    parser.add_argument('--config', '-c', dest='config_path', type=str, required=required, default=default_config)
    subparsers = parser.add_subparsers(metavar='command', dest='command')
    subparsers.required = True
    sub = subparsers.add_parser('create', help='Create migration')
    sub.add_argument('name', help='Name of argument')
    sub = subparsers.add_parser('migrate', help='Run migrations')
    sub.add_argument('--fake', action='store_true', default=False, dest='set_fake',
                     help='Mark migrations as run without actually running them')
    sub.add_argument('--initial', action='store_true', default=False,
                     help='Only run the first migration and then mark the rest as done without running them.')
    subparsers.add_parser('rollback', help='Rollback the previously done migration')
    args = parser.parse_args()
    if args.environments is None:
        args.environments = ENVIRONMENTS
    return args


def main():
    args = parse_args()
    getattr(sys.modules[__name__], args.command)(args)


def create(args):
    now = datetime.now()
    ver = "{:04}{:02}{:02}{:02}{:02}{:02}".format(now.year, now.month, now.day, now.hour, now.min, now.second)
    filename = "{}_{}.py".format(ver, args.name)
    for environment in args.environments:
        with Path(MIGRATIONS_PATH, environment, filename).open('w') as open_file:
            open_file.write("""def up({0}):
    pass


def down({0}):
    pass""".format("" if environment == 'system' else 'conn'))


def migrate(args):
    args.direction = 'up'
    handle_migration(args)


def rollback(args):
    args.direction = 'down'
    handle_migration(args)


def handle_migration(args):
    with Path(args.config_path, 'submitty.json').open() as open_file:
        config = json.load(open_file)
    args.install_dir = config['submitty_install_dir']

    with Path(args.config_path,  'database.json').open() as open_file:
        database = json.load(open_file)

    for environment in args.environments:
        if environment in ['system', 'master']:
            params = {
                'dbname': 'submitty',
                'host': database['database_host'],
                'user': database['database_user'],
                'password': database['database_password']
            }

            with psycopg2.connect(**params) as connection:
                migrate_environment(connection, environment, args)

        if environment == 'course':
            params = {
                'host': database['database_host'],
                'user': database['database_user'],
                'password': database['database_password']
            }

            for semester in os.listdir(os.path.join(config['submitty_data_dir'], 'courses')):
                for course in os.listdir(os.path.join(config['submitty_data_dir'], 'courses', semester)):
                    if args.course is None or [semester, course] == args.course:
                        params['dbname'] = 'submitty_{}_{}'.format(semester, course)
                        with psycopg2.connect(**params) as connection:
                            migrate_environment(connection, environment, args)


def migrate_environment(connection, environment, args):
    # Get the migration table and if it doesn't exist, then we have to create it
    with connection.cursor() as cursor:
        cursor.execute("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND "
                       "table_name='migrations_{}')".format(environment))
        exists = cursor.fetchone()[0]

    if not exists:
        with connection.cursor() as cursor:
            cursor.execute("""CREATE TABLE migrations_{} (
      id VARCHAR(100) PRIMARY KEY NOT NULL,
      commit_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      status NUMERIC(1) DEFAULT 0 NOT NULL
    );""".format(environment))
        connection.commit()

    missing_migrations = OrderedDict()
    migrations = load_migrations(MIGRATIONS_PATH / environment)

    with connection.cursor() as cursor:
        cursor.execute('SELECT id, commit_time, status FROM migrations_{} ORDER BY id'.format(environment))
        for migration in cursor.fetchall():
            # fetchall returns things as a tuple which is a bit unwieldy
            migration = {'id': migration[0], 'commit_time': migration[1], 'status': migration[2]}
            if migration['id'] in migrations:
                migrations[migration['id']].update({
                    'commit_time': migration['commit_time'],
                    'status': migration['status'],
                    'db': True
                })
            else:
                missing_migrations[migration['id']] = migration

    if len(missing_migrations) > 0:
        print('Removing {} missing migrations:'.format(len(missing_migrations)))
        for migration in missing_migrations:
            remove_migration(connection, migration, environment, args)

    print("Running {} migrations for {}:".format(args.direction, environment))
    args.fake = args.set_fake
    if args.direction == 'up':
        keys = list(migrations.keys())
        if args.initial is True:
            key = keys.pop(0)
            run_migration(connection, migrations[key], environment, args)
            args.fake = True
        for key in keys:
            if migrations[key]['status'] == 0:
                run_migration(connection, migrations[key], environment, args)
    else:
        for key in reversed(list(migrations.keys())):
            if migrations[key]['status'] == 1:
                run_migration(connection, migrations[key], environment, args)
                break


def remove_migration(connection, migration, environment, args):
    print("  {}".format(migration['id']))
    file_name = migration['id'] + '_' + migration['name'] + '.py'
    file_path = Path(args.install_dir, 'migrations', environment, file_name)
    if file_path.exists():
        module = load_migration_module(migration['name'], file_path)
        call_func(getattr(module, 'down', noop), connection, environment)
    with connection.cursor() as cursor:
        cursor.execute('DELETE FROM migrations_{} WHERE id=%s'.format(environment), (migration['id'],))
    connection.commit()


def call_func(func, connection, environment):
    if environment == 'system':
        func()
    else:
        func(connection)


def noop(_):
    pass


def run_migration(connection, migration, environment, args):
    print("  {}".format(migration['id']))
    if not args.fake:
        call_func(getattr(migration['module'], args.direction, noop), connection, environment)
    status = 1 if args.direction == 'up' else 0
    with connection.cursor() as cursor:
        if migration['db']:
            cursor.execute('UPDATE migrations_{} SET commit_time=CURRENT_TIMESTAMP, status=%s WHERE id=%s'.format(environment), (status, migration['id'],))
        else:
            cursor.execute('INSERT INTO migrations_{} (id, status) VALUES(%s, %s)'.format(environment), (migration['id'], status,))
    connection.commit()


def load_migration_module(name, path):
    spec = importlib.util.spec_from_file_location('module.{}'.format(name), str(path))
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module


def load_migrations(path):
    migrations = OrderedDict()
    for migration in sorted(filter(lambda x: x.endswith('.py'), [x.name for x in path.iterdir()])):
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
