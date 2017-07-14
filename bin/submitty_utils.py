#!/usr/bin/env python3

import pytz
import time
import tzlocal
import datetime


def get_timezone():
    return tzlocal.get_localzone()


def write_submitty_date(d):
    my_timezone = d.tzinfo
    if my_timezone is None:
        print ("NO TIMEZONE ",d,"assuming local timezone")
        my_timezone = get_timezone()
        d_with_timezone = my_timezone.localize(d)
    else:
        d_with_timezone = d;
    answer = d.strftime("%Y-%m-%d %H:%M:%S ") + str(d_with_timezone.tzinfo)
    return answer


def read_submitty_date(s):
    words = s.split()
    if len(words) < 2 or len(words) > 3:
        raise SystemExit("ERROR:  unexpected date format %s" % s)
    without_timezone = str(words[0] + ' ' + words[1])
    try:
        without_timezone = datetime.datetime.strptime(without_timezone,'%Y-%m-%d %H:%M:%S')
    except ValueError:
        raise SystemExit("ERROR:  invalid date format %s" % s)
    if len(words) == 2:
        print ("NO TIMEZONE ",s,"assuming local timezone")
        my_timezone = get_timezone()
    else:
        my_timezone = pytz.timezone(words[2])
    with_timezone = my_timezone.localize(without_timezone)
    return with_timezone
