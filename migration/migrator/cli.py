"""CLI interface for migrator."""

from argparse import ArgumentParser
from pathlib import Path
from . import VERSION, get_all_environments, get_environments, main
from .config import Config


def parse_args(argv, config_path=None):
    """
    Parse the arguments passed to the script using argparse.

    :param argv: arguments to parse
    :type argv: list
    :return: argparse.Namespace of the parsed args
    """
    desc = 'Migration script for upgrading/downgrading the database'
    parser = ArgumentParser(description=desc)
    parser.add_argument(
        '-v', '--version', action='version',
        version='%(prog)s {}'.format(VERSION)
    )

    default_config = None
    if config_path is not None and config_path.exists():
        default_config = config_path.resolve()

    parser.add_argument(
        '-c', '--config', dest='config_path', type=lambda p: Path(p).resolve(),
        required=default_config is None, default=default_config
    )
    parser.add_argument(
        '-e', '--environment', dest='environments',
        choices=get_all_environments(), action='append',
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

    sub = subparsers.add_parser(
        'rollback', help='Rollback the previously done migration'
    )
    sub.add_argument(
        '--fake', action='store_true', default=False, dest='set_fake',
        help='Mark migrations as run without actually running them'
    )

    sub = subparsers.add_parser('dump', help='Dump DB schema to file')
    args = parser.parse_args(argv)
    args.environments = get_environments(args.environments)
    return args


def run(argv, config_path=None):
    """
    Parse the CLI arguments, and then run the chosen command.

    :param argv: arguments to parse
    :type argv: list
    """
    args = parse_args(argv, config_path)
    args.config = Config(args.config_path)
    getattr(main, args.command)(args)
