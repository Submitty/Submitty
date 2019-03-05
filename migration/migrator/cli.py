"""CLI interface for migrator."""

from argparse import ArgumentParser
from pathlib import Path
from . import VERSION, get_dir_path, get_environments
from .config import Config
import migrator.main


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
    config_path = Path(get_dir_path(), '..', '..', '..', '..', 'config')
    default_config = config_path.resolve() if config_path.exists() else None
    required = False if default_config is not None else True
    parser.add_argument(
        '-c', '--config', dest='config_path', type=str,
        required=required, default=default_config
    )
    parser.add_argument(
        '-e', '--environment', dest='environments',
        choices=get_environments(), action='append',
        required=True
    )
    parser.add_argument(
        '--course', dest='choose_course', nargs=2,
        metavar=('semester', 'course'), default=None
    )

    subparsers = parser.add_subparsers(metavar='command', dest='command')
    subparsers.required = True
    sub = subparsers.add_parser('create', help='Create migration')
    sub.add_argument('name', help='Name of new migration')

    sub = subparsers.add_parser(
        'status',
        help='Get status of migrations for environment'
    )

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
        args.environments = get_environments()
    # make sure the order is of 'master', 'system', 'course' depending on what
    # environments have been selected system must be run after master initially
    # as system relies on master DB being setup for migration table
    environments = []
    for env in get_environments():
        if env in args.environments:
            environments.append(env)
    args.environments = environments
    args.config = Config(args.config_path)
    return args


def run():
    """Parse the CLI arguments, and then run the chosen command."""
    args = parse_args()
    getattr(migrator.main, args.command)(args)
