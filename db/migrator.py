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


DIR_PATH = Path(__file__).resolve()
MIGRATIONS_PATH = DIR_PATH.parent / 'migrations'


def parse_args():
    parser = ArgumentParser(description='Migration script for upgrading/downgrading the database')
    parser.add_argument('--environment', '-e', choices=['master', 'course'], required=True)
    parser.add_argument('--config', '-c', dest='config_path', type=str, required=True)
    subparsers = parser.add_subparsers(metavar='command', dest='command')
    sub = subparsers.add_parser('create', help='Create migration')
    sub.add_argument('name', help='Name of argument')
    sub = subparsers.add_parser('migrate', help='Run migrations')
    sub.add_argument('--fake', action='store_true', default=False,
                     help='Mark migrations as run without actually running them')
    sub.add_argument('--initial', action='store_true', default=False,
                     help='Only run the first migration and then mark the rest as done without running them.')
    subparsers.add_parser('rollback', help='Rollback the previously done migration')
    return parser.parse_args()


def main():
    args = parse_args()
    args.migrations_path = MIGRATIONS_PATH / args.environment
    if args.command == 'migrate':
        args.direction = 'up'
    elif args.command == 'rollback':
        args.command = 'migrate'
        args.direction = 'down'
    getattr(sys.modules[__name__], args.command)(args)


def create(args):
    now = datetime.now()
    ver = "{:04}{:02}{:02}{:02}{:02}{:02}".format(now.year, now.month, now.day, now.hour, now.min, now.second)
    filename = "{}_{}.py".format(ver, args.name)
    Path(args.migrations_path, filename).write_text("""def up(conn):
    pass


def down(conn):
    pass""")


def migrate(args):
    with Path(args.config_path, 'submitty.json').open() as open_file:
        config = json.load(open_file)

    with Path(args.config_path,  'database.json').open() as open_file:
        database = json.load(open_file)

    with psycopg2.connect(dbname='submitty', host=database['database_host'], user=database['database_user'],
                          password=database['database_password']) as connection:
        migrate_connection(connection, MIGRATIONS_PATH, 'core', args)

    for semester in os.listdir(os.path.join(config['submitty_data_dir'], 'courses')):
        for course in os.listdir(os.path.join(config['submitty_data_dir'], 'courses', semester)):
            with psycopg2.connect(dbname='submitty_{}_{}'.format(semester, course), host=database['database_host'],
                                  user=database['database_user'], password=database['database_password']) as connection:
                migrate_connection(connection, MIGRATIONS_PATH, 'course', args)


def migrate_connection(connection, migrations_path, mig_type, args):
    # Get the migration table and if it doesn't exist, then we have to create it
    with connection.cursor() as cursor:
        cursor.execute("SELECT EXISTS(SELECT 1 FROM information_schema.tables WHERE table_schema='public' AND "
                       "table_name='database_migrations')")
        exists = cursor.fetchone()[0]

    if not exists:
        with connection.cursor() as cursor:
            cursor.execute("""
    CREATE TABLE database_migrations (
      id NUMERIC(20) PRIMARY KEY NOT NULL,
      migration VARCHAR(100) NOT NULL,
      commit_time TIMESTAMP NOT NULL,
      status NUMERIC(1) DEFAULT 0 NOT NULL
    );""")
        connection.commit()

    migrations = load_migrations(migrations_path, mig_type)

    with connection.cursor() as cursor:
        cursor.execute('SELECT id, migration, commit_time, status FROM database_migrations ORDER BY id')
        for migration in cursor.fetchall():
            if migration['id'] in migrations:
                migrations[migration['id']]['commit_time'] = migration['commit_time']
                migrations[migration['id']]['status'] = migration['status']

    if args.direction == 'up':
        keys = list(migrations.keys())
        if args.initial is True:
            key = keys.pop(0)
            run_migration(connection, args.direction, migrations[key]['module'], migrations[key]['id'], 1)
            args.fake = True
        for key in keys:
            if migrations[key]['status'] == 0:
                run_migration(connection, migrations[key], args)
    else:
        for key in reversed(list(migrations.keys())):
            if migrations[key]['status'] == 1:
                run_migration(connection, migrations[key], args)
                break


def noop(_):
    pass


def run_migration(connection, migration, args):
    if not args.fake:
        getattr(migration['module'], args.direction, noop)(connection)
    status = 1 if migration['status'] == 0 else 0
    with connection.cursor() as cursor:
        cursor.execute('UPDATE database_migrations SET commit_time=CURRENT_TIMESTAMP, status=%d WHERE id=%s', (status, migration['id'],))
    connection.commit()


def load_migrations(path, mig_type):
    migrations = OrderedDict()
    path = os.path.join(path, mig_type)
    for migration in sorted(filter(lambda x: x.endswith('.py'), os.listdir(path))):
        (migration_id, name) = migration[:-3].split('_')
        spec = importlib.util.spec_from_file_location('module.{}'.format(name), os.path.join(path, migration))
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        migrations[migration_id] = {
            'id': migration_id,
            'migration': name,
            'commit_time': None,
            'status': 0,
            'module': mod
        }
    return migrations


if __name__ == '__main__':
    main()
