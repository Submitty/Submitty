#!/usr/bin/env python3

"""
Basic migration script to handle the database
"""

from argparse import ArgumentParser
from collections import OrderedDict
import importlib.util
import json
import os

import psycopg2


def parse_args():
    parser = ArgumentParser(description='Migration script for upgrading/downgrading the database')
    parser.add_argument('direction', choices=['up', 'down'])
    return parser.parse_args()


def main():
    dir_path = os.path.dirname(os.path.realpath(__file__))
    migrations_path = os.path.abspath(os.path.join(dir_path, '..', 'migrations'))
    args = parse_args()

    with open(os.path.join(dir_path, '..', '.setup', 'submitty_conf.json')) as conf:
        config = json.load(conf)

    with psycopg2.connect(dbname='submitty', host=config['database_host'], user=config['database_user'],
                          password=config['database_password']) as connection:
        migrate_connection(connection, migrations_path, 'core', args.direction)

    for semester in os.listdir(os.path.join(config['submitty_data_dir'], 'courses')):
        for course in os.listdir(os.path.join(config['submitty_data_dir'], 'courses', semester)):
            with psycopg2.connect(dbname='submitty_{}_{}'.format(semester, course), host=config['database_host'],
                                  user=config['database_user'], password=config['database_password']) as connection:
                migrate_connection(connection, migrations_path, 'course', args.direction)


def migrate_connection(connection, migrations_path, mig_type, direction):
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
    );
    """)
        connection.commit()

    migrations = load_migrations(migrations_path, mig_type)

    with connection.cursor() as cursor:
        cursor.execute('SELECT id, migration, commit_time, status FROM database_migrations ORDER BY id')
        for migration in cursor.fetchall():
            if migration['id'] in migrations:
                migrations[migration['id']]['commit_time'] = migration['commit_time']
                migrations[migration['id']]['status'] = migration['status']

    if direction == 'up':
        up_migrations(connection, migrations)
    else:
        down_migrations(connection, migrations)


def noop(_):
    pass


def up_migrations(connection, migrations):
    for key in migrations:
        if migrations[key]['status'] == 0:
            getattr(migrations[key]['module'], 'up', noop)(connection)
            with connection.cursor() as cursor:
                cursor.execute('UPDATE database_migrations SET commit_time=CURRENT_TIMESTAMP, status=1 WHERE id=%s', (key,))
            connection.commit()


def down_migrations(connection, migrations):
    for key in reversed(migrations.keys()):
        if migrations[key]['status'] == 1:
            getattr(migrations[key]['module'], 'down', noop)(connection)
            with connection.cursor() as cursor:
                cursor.execute('UPDATE database_migrations SET commit_time=CURRENT_TIMESTAMP, status=0 WHERE id=%s', (key,))
            connection.commit()
            break


def load_migrations(path, mig_type):
    migrations = OrderedDict()
    path = os.path.join(path, mig_type)
    for migration in sorted(filter(lambda x: x.endswith('.py'), os.listdir(path))):
        (migration_id, name) = migration[:-3].split('_')
        spec = importlib.util.spec_from_file_location('module.{}'.format(name), os.path.join(path, migration))
        mod = importlib.util.module_from_spec(spec)
        spec.loader.exec_module(mod)
        migrations[migration_id] = {
            'migration': name,
            'commit_time': None,
            'status': 0,
            'module': mod
        }
    return migrations


if __name__ == '__main__':
    main()
