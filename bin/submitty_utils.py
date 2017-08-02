#!/usr/bin/env python3

import re
import pytz
import time
import tzlocal
from datetime import datetime, timedelta



# grab the system timezone (should only used when we don't have any info about the timezone)
def get_timezone():
    return tzlocal.get_localzone()


# grab the current time
def get_current_time():
    return datetime.now(get_timezone())


# convert a datetime object (with or without the timezone) to a string with a timezone
def write_submitty_date(d=get_current_time()):
    if not isinstance(d, datetime):
        print ("ERROR:  ",d," is not a datetime object, it is of type ",type(d))
        return d
    my_timezone = d.tzinfo
    if my_timezone is None:
        print ("ERROR: NO TIMEZONE ",d," assuming local timezone")
        my_timezone = get_timezone()
        d = my_timezone.localize(d)
    answer = d.strftime("%Y-%m-%d %H:%M:%S%z")
    return answer


# convert a string (with the timezone) to a date time object with a timezone
def read_submitty_date(s):
    words = s.split()
    if len(words) < 2 or len(words) > 3:
        raise SystemExit("ERROR: unexpected date format %s" % s)
    thedatetime = str(words[0] + ' ' + words[1])
    try:
        with_timezone = datetime.strptime(thedatetime, '%Y-%m-%d %H:%M:%S%z')
    except ValueError:
        raise SystemExit("ERROR:  invalid date format %s" % s)
    return with_timezone


def parse_datetime(date_string):
    """
    Given a string that should either represent an absolute date or an arbitrary date, parse this
    into a datetime object that is then used. Absolute dates should be in the format of
    YYYY-MM-DD HH:MM:SS while arbitrary dates are of the format "+/-# day(s) [at HH:MM:SS]" where
    the last part is optional. If the time is omitted, then it uses midnight of whatever day was
    specified. Datetimew without timezones assume local timezone

    Examples of allowed strings:
    2016-10-14
    2016-10-13 22:11:32
    -1 day
    +2 days at 00:01:01

    :param date_string:
    :type date_string: str
    :return:
    :rtype: datetime
    """

    if isinstance(date_string, datetime):
        my_timezone = date_string.tzinfo
        if my_timezone is None:
            # print ("NO TIMEZONE ",date_string,"assuming local timezone")
            my_timezone = get_timezone()
            date_string = my_timezone.localize(date_string)
        return date_string

    try:
        return datetime.strptime(date_string, '%Y-%m-%d %H:%M:%S%z')
    except ValueError:
        pass

    try:
        return datetime.strptime(date_string, '%Y-%m-%d %H:%M:%S%z').replace(hour=23, minute=59, second=59)
    except ValueError:
        pass

    m = re.search('([+|\-][0-9]+) (days|day) at [0-2][0-9]:[0-5][0-9]:[0-5][0-9]', date_string)
    if m is not None:
        hour = int(m.group(2))
        minu = int(m.group(3))
        sec = int(m.group(4))
        days = int(m.group(1))
        return get_current_time().replace(hour=hour, minute=minu, second=sec, microsecond=0) + timedelta(days=days)

    m = re.search('([+|\-][0-9]+) (days|day)', date_string)
    if m is not None:
        days = int(m.group(1))
        return get_current_time().replace(hour=23, minute=59, second=59, microsecond=0) + timedelta(days=days)

    raise ValueError("Invalid string for date parsing: " + str(date_string))
