#!/usr/bin/env python3

import pytz
import time
import dateutil
import dateutil.parser
import tzlocal
import datetime


def get_timezone():
    return tzlocal.get_localzone()


def write_date_with_full_timezone(d):
    
    print ("\nWD ",d)
    print ("tzinfo ",d.tzinfo)
    my_timezone = get_timezone()
    #my_timezone = d.tzinfo
    #print ("tz ",my_timezone)
    #print ("tz ",my_timezone())
    answer = d.strftime("%Y-%m-%d %H:%M:%S ") + str(my_timezone)#tz.tzname(my_timezone)
    print ("answer ",answer)
    return answer

def read_date_with_full_timezone(s):
    words = s.split()
    if not len(words) == 3:
        SystemExit("ERROR:  unexpected date format ",s)
    without_timezone = str(words[0] + ' ' + words[1])
    without_timezone = datetime.datetime.strptime(without_timezone,'%Y-%m-%d %H:%M:%S')
    my_timezone = pytz.timezone(words[2])
    #with_timezone = my_timezone.localize(without_timezone)
    tz = my_timezone.tzinfo
    #print ("RD TZ",tz)
    #with_timezone = without_timezone.replace(tzinfo=my_timezone.tzinfo)
    with_timezone = without_timezone.replace(tzinfo=tz)
    return with_timezone
