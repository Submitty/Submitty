#!/usr/bin/env python3
"""
Validate the syntax of a JSON file.

As instructors are often times hand-rolling JSON files, this
is just a pre-processor run over these files to ensure that
the syntax is good and compliant.
"""
from argparse import ArgumentParser
from pathlib import Path
import json
import sys


__PROGRAM__ = 'json_syntax_checker.py'
__VERSION__ = '1.0.0'


class NotAFileError(OSError):
    """Brother of built-in NotADirectorError."""

    pass


def parse_args(argv):
    """Parse the CLI arguments."""
    parser = ArgumentParser(description="JSON validator to ensure that a JSON "
                                        "is of proper format")
    parser.add_argument(
        "file",
        metavar="file_name",
        type=str,
        help="File name of JSON file to validate"
    )
    parser.add_argument(
        '--version',
        help='Print out version',
        action='version',
        version='{} {}'.format(__PROGRAM__, __VERSION__)
    )
    return parser.parse_args(argv)


def validate_file(filename):
    """Validate that the file exists, is a file, and is valid JSON."""
    file_path = Path(filename)
    if not file_path.exists():
        raise FileNotFoundError(
            "Cannot find JSON file to validate: {}".format(filename)
        )
    elif not file_path.is_file():
        raise NotAFileError("Not a file: {}".format(filename))
    with file_path.open() as open_file:
        return json.load(open_file)


def main(argv):
    """Run the program."""
    args = parse_args(argv)
    try:
        validate_file(args.file)
    except Exception as e:
        raise SystemExit(e)
    return True


if __name__ == "__main__":  # pragma: no cover
    main(sys.argv[1:])
