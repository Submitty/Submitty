#!/usr/bin/env python3
"""
Script to trigger the generate grade summaries.

Usage:
./generate_grade_summaries.py <user_id> <password> <base_url>
./generate_grade_summaries.py instructor instructor "http://192.168.56.111/"
"""

import argparse
import re
from requests_html import HTMLSession


def main():
    """Run the generate function."""
    parser = argparse.ArgumentParser(
        description='Automatically click the Generate Grade Summaries button'
    )
    parser.add_argument('user_id')
    parser.add_argument('password')
    parser.add_argument('base_url')
    args = parser.parse_args()

    base_url = args.base_url.rstrip('/')
    session = HTMLSession()

    data = {
        "user_id": args.user_id,
        "password": args.password
    }
    res = session.post('{}/authentication/check_login'.format(base_url), data)
    res = session.get('{}'.format(base_url))
    pattern = re.compile(r'.*\/(.*)\/(.*)$')

    for child in res.html.find('.courses-table')[0].find('td'):
        links = child.absolute_links
        if len(links) == 0:
            continue
        match = pattern.match(list(links)[0])
        # print("Running grade summaries for {}.{}".format(match[1], match[2]))
        base = "semester={}&course={}".format(match[1], match[2])
        action = "component=admin&page=reports&action=summary"
        session.get('{}/index.php?{}&{}'.format(base_url, base, action))


if __name__ == "__main__":
    main()
