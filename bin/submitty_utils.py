#!/usr/bin/env python3

import pytz
import time
import tzlocal
from datetime import datetime


# grab the system timezone (should only used when we don't have any info about the timezone)
def get_timezone():
    return tzlocal.get_localzone()


# grab the current time
def get_current_time():
    return datetime.now(get_timezone())


# convert a datetime object (with or without the timezone) to a string with a timezone
def write_submitty_date(d=get_current_time()):
    my_timezone = d.tzinfo
    if my_timezone is None:
        print ("NO TIMEZONE ",d,"assuming local timezone")
        my_timezone = get_timezone()
        d = my_timezone.localize(d)
    answer = d.strftime("%Y-%m-%d %H:%M:%S%z")
    return answer


# convert a string (with the timezone) to a date time object with a timezone
def read_submitty_date(s):
    words = s.split()
    if len(words) != 2:
        raise SystemExit("ERROR: unexpected date format %s" % s)
    thedatetime = str(words[0] + ' ' + words[1])
    print ("the date is: %s" % s)
    try:
        with_timezone = datetime.strptime(thedatetime, '%Y-%m-%d %H:%M:%S%z')
    except ValueError:
        raise SystemExit("ERROR:  invalid date format %s" % s)
    return with_timezone
