#!/usr/bin/env python3

import re
import argparse
from pathlib import Path


def get_args():
    parser = argparse.ArgumentParser()
    parser.add_argument('-r', '--repo', required=True)
    parser.add_argument('-v', '--version', required=True)

    return parser.parse_args()


def main():
    versions_path = Path(__file__).parent / 'versions.sh'
    with versions_path.open() as file:
        body = file.read()

    args = get_args()
    pattern = re.compile(r'^(export {}_Version=)(\S+)'.format(args.repo), re.MULTILINE)
    matches = re.findall(pattern, body)
    if len(matches) < 1:
        raise Exception('Error: Requested repository not found in versions file.')
    if len(matches) > 1:
        raise Exception('Error: Multiple instances of requested repository found in versions file.')

    with versions_path.open('w') as file:
        file.write(
            body.replace(
                matches[0][0] + matches[0][1],
                matches[0][0] + args.version
            )
        )

    print('old_version=' + matches[0][1])


if __name__ == '__main__':
    main()
