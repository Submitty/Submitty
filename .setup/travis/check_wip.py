"""Utility script to more reliably check for PR title for Travis WIP check."""
from argparse import ArgumentParser
from os import environ
import sys
import time
import requests


def parse_args():
    """Parse command line arguments."""
    parser = ArgumentParser(description='Checks if a Github PR is a WIP')
    parser.add_argument('slug', help='Slug for repo to check (e.g. Submitty/Submitty')
    parser.add_argument('pr', help='PR number to check')
    return parser.parse_args()


def main():
    """Check if title of PR starts with [wip] or wip."""
    args = parse_args()
    url = "https://api.github.com/repos/{0}/pulls/{1}".format(args.slug, args.pr)
    print('URL => {}'.format(url))
    if 'GH_TOKEN' in environ:
        print('Using GH token')
    exc_errors = 0
    req_errors = 0
    while exc_errors < 5 and req_errors < 20:
        try:
            headers = {}
            if 'GH_TOKEN' in environ:
                headers['Authorization'] = 'token {}'.format(environ.get('GH_TOKEN'))
            res = requests.get(url, headers=headers, timeout=10)
            if res.status_code == 200:
                json = res.json()
                title = json['title'].lower()
                if title is None or title == 'null':
                    time.sleep(1)
                    continue
                print('Title => {}'.format(json['title']))
                check = title.startswith('[wip]') or title.startswith('wip')
                if check:
                    print("[WIP] tag detected, build failed. Remove [WIP] "
                          "tag and re-run build when ready for merging.",
                          file=sys.stderr)
                sys.exit(check)
            else:
                req_errors += 1
                time.sleep(1)
        except (requests.RequestException, requests.Timeout, KeyError):
            exc_errors += 1
            time.sleep(1)
    raise SystemExit('Could not get title of PR for repo')


if __name__ == '__main__':
    main()
