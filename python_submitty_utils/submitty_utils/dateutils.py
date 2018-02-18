import re
import tzlocal
from datetime import datetime, timedelta


def get_timezone():
    """
    Grab the system timezone, should generally only be used when we don't have any further
    information on what timezone we should be using

    :return:
    """
    return tzlocal.get_localzone()


def get_current_time():
    """
    Get the current time, in the timezone set on the server
    :return:
    """
    return datetime.now(get_timezone())


def write_submitty_date(d=get_current_time(),microseconds=False):
    """
    Converts a datetime object to a string with a timezone. If the datetime object
    does not have a timezone, it'll use the server's timezone.

    :param d: datetime object you want to convert to string
    :return:

    FIXME: We should not be printing anything out here
    """
    if not isinstance(d, datetime):
        print("ERROR:  ", d, " is not a datetime object, it is of type ", type(d))
        return d
    my_timezone = d.tzinfo
    if my_timezone is None:
        print("ERROR: NO TIMEZONE ", d, " assuming local timezone")
        my_timezone = get_timezone()
        d = my_timezone.localize(d)
    answer = d.strftime("%Y-%m-%d %H:%M:%S%z")
    if microseconds:
        mlsec = d.strftime("%f")
        mlsec = mlsec[0:3]
        answer = d.strftime("%Y-%m-%d %H:%M:%S.{} %z".format(mlsec))
    return answer


def read_submitty_date(s):
    """
    Convert a string (with a timezone) to a date time object with a timezone
    :param s:
    :return:

    FIXME: We should not be raising a SystemExit within a utility function
    """
    words = s.split()
    if len(words) < 2 or len(words) > 3:
        raise SystemExit("ERROR: unexpected date format %s" % s)
    thedatetime = str(words[0] + ' ' + words[1])
    try:
        # hoping to find timezone -0400
        with_timezone = datetime.strptime(thedatetime, '%Y-%m-%d %H:%M:%S%z')
    except ValueError:
        try:
            # hoping to find no timezone
            without_timezone = datetime.strptime(thedatetime, '%Y-%m-%d %H:%M:%S')
            my_timezone = get_timezone()
            with_timezone = my_timezone.localize(without_timezone)
        except ValueError:
            try:
                # hoping to find timezone -04
                thedatetime = thedatetime+"00"
                print ("dateutils read_submitty_date -- added '00' to ",thedatetime)
                with_timezone = datetime.strptime(thedatetime, '%Y-%m-%d %H:%M:%S%z')
            except ValueError:
                print ("DATE PROBLEM",s)
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
