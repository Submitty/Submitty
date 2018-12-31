from argparse import ArgumentParser
import requests
import sys
import time


def parse_args():
    """Parse command line arguments."""
    parser = ArgumentParser(help='Checks if a Github PR is a WIP')
    parser.add_argument('slug')
    parser.add_argument('pr')
    return parser.parse_args()


def main():
    """Check if title of PR starts with [wip] or wip:"""
    args = parse_args()
    url = "https://api.github.com/repos/{0}/pulls/{1}".format(args.slug, args.pr)
    print('URL => {}'.format(url))
    tries = 0
    while tries < 5:
        try:
            res = requests.get(url)
            if res.status_code == 200:
                json = res.json()
                title = json['title'].lower()
                if title is None or title == 'null':
                    continue
                print('Title => {}'.format(title))
                check = title.startswith('[wip]') or title.startswith('wip:')
                if check:
                    print("[WIP] tag detected, build failed. Remove [WIP] "
                          "tag and re-run build when ready for merging.")
                sys.exit(check)
        finally:
            tries += 1
            time.sleep(1)
    print('Could not get title of PR for repo')
    sys.exit(1)


if __name__ == '__main__':
    main()
