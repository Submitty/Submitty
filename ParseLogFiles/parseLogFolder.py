#!/usr/bin/env python2

# to add to robustness
# make sure the times do not roll over to the next day by checking wait times
# if the submission time - wait time is in the next hour then change it
# add class name argument
# multiple numper of arguments
# take in two arguments of two dates date range
# base level directory
# course name and homework

# Which hour of the day is busiest

# throughout Batch

# FIXES: when there is not a full week the is a division by 0 error
import os
from __future__ import division, print_function
# import numpy as np

print("lets parse some files!! ")


def weekday_to_num(str):
    return {
        "Sun": 0,
        "Mon": 1,
        "Tue": 2,
        "Wed": 3,
        "Thu": 4,
        "Fri": 5,
        "Sat": 6
    }[str]


def num_to_weekday(num):
    return {
        0: "Sun",
        1: "Mon",
        2: "Tue",
        3: "Wed",
        4: "Thu",
        5: "Fri",
        6: "Sat"
    }[num]

path = '/var/local/submitty/logs/autograding/'
submissions_per_hour = [0]*24
avg_hours_ot_week = [[0]*24 for _ in range(7)]
avg_hours_ot_week_datastructures = [[0]*24 for _ in range(7)]
avg_waittime_ot_week = [[0]*24 for _ in range(7)]  # average wait time per assignment per week
avg_gradingtime_ot_week = [[0]*24 for _ in range(7)]  # average grade time per assignment per week
number_of_weekdays = [0]*7

submissions_datastructures = []
submission_temp_cs1100 = 0
submission_temp_cs1200 = 0
submission_temp_cs2200 = 0
submission_temp_cs2600 = 0
submission_temp_cs4430 = 0
submission_temp_cs4380 = 0

hours_in_days = [[0]*24 for _ in range(7)]  # for waiting time calc

all_students = set([])
all_courses = set([])
num_of_sumbissions = 0
num_days = 0
full_week = 0
f3 = open('BARsubmissions_per_week.csv', 'w+')
f3.write(" , CS 1, Data Structures, FOCS, Principle of Software, Programming Languages, Operating Systems\n")
for filename in os.listdir(path):
    # hack to skip first (badly formatted log file)
    if (filename == "20150916.txt"):
        continue

    num_days += 1
    contents = open(path + '/' + filename)
    new_day = True
    full_week += 1

    for line in contents.readlines():
        if (line[40:45] != "BATCH"):
            num_of_sumbissions += 1

            info = ((line[48:99]).strip()).split("__")

            if (len(info) < 3):
                print(filename)
                print(info)
                continue

            all_students.add(info[3])

            all_courses.add(info[1])  # class

            if new_day:
                day_of_week = weekday_to_num(line[0:3])
                number_of_weekdays[day_of_week] += 1

            hour = int(line[11:13])

            if (line[101:105] == "wait"):
                waitsec = int(line[106:114])
                if (waitsec > 1000):
                    print("Anomoly %s %d  %d" % (filename, hour, waitsec))
                avg_waittime_ot_week[day_of_week][hour] += int(line[106:114])

            elif (line[101:106] == "grade"):
                avg_gradingtime_ot_week[day_of_week][hour] += int(line[107:114])

            if (info[1] == "datastructures"):  # Data Structures
                submission_temp_cs1200 += 1
                avg_hours_ot_week_datastructures[day_of_week][hour] += 1
            elif (info[1] == "csci2600"):  # Principles of Software
                submission_temp_cs2600 += 1
            elif (info[1] == "csci1100"):  # cs1
                submission_temp_cs1100 += 1
            elif (info[1] == "csci4430"):  # prog lang
                submission_temp_cs4430 += 1
            elif (info[1] == "csci2200"):  # focs
                submission_temp_cs2200 += 1
            elif (info[1] == "csci4380"):  # opsys
                submission_temp_cs4380 += 1

            avg_hours_ot_week[day_of_week][hour] += 1
            submissions_per_hour[hour] += 1
            new_day = False

    if (full_week == 7):
        print(filename[5:9])
        f3.write("%s %s, " % (line[4:10], filename[5:9]))
        submission_temp_cs1200 //= 2
        submission_temp_cs2600 //= 2
        submission_temp_cs1100 //= 2
        submission_temp_cs4430 //= 2
        submission_temp_cs4380 //= 2
        f3.write("%d, %d, %d, %d, %d, %d\n" % (submission_temp_cs1100, submission_temp_cs1200, submission_temp_cs2200, submission_temp_cs2600, submission_temp_cs4430, submission_temp_cs4380))
        full_week = 0
        submission_temp_cs1200 = 0
        submission_temp_cs1100 = 0
        submission_temp_cs2600 = 0
        submission_temp_cs4430 = 0
        submission_temp_cs2200 = 0
        submission_temp_cs4380 = 0

    # print(day_of_week)

num_of_sumbissions //= 2
submissions_per_hour[:] = [x // (2*num_days) for x in submissions_per_hour]
num_of_students = len(all_students)
# print(avg_hours_ot_week)
# print(", ".join(str(x) for x in submissions_per_hour))
print("The number of days %d" % num_days)
print("Number of submissions %d" % num_of_sumbissions)
print("Number of students %d" % num_of_students)
print("Number of courses %d" % len(all_courses))
print(all_courses)
print(number_of_weekdays)
# print(avg_waittime_ot_week)
# print(avg_hours_ot_week)
# i = 0
# f = open('submissions_per_hour.csv', 'w+')
# for x in submissions_per_hour:
#     f.write("%d, %d\n" % (i, x))
#     i += 1


f2 = open('FINALsubmissions_per_week.csv', 'w+')
for day in range(7):
    weekday = num_to_weekday(day)
    for hour in range(24):

        if (avg_hours_ot_week[day][hour] != 0):
            avg_waittime_ot_week[day][hour] /= avg_hours_ot_week[day][hour]
            avg_gradingtime_ot_week[day][hour] /= avg_hours_ot_week[day][hour]

        if (hour % 6 == 0):
            if (hour > 12):
                f2.write("%s %dpm," % (weekday, hour % 12))
            elif (hour == 0):
                f2.write("%s %dam," % (weekday, 12))
            else:
                f2.write("%s %dpm," % (weekday, hour))
        else:
            f2.write(" ,")


f2.write("\n")
for x in range(7):
    # lets make avg hours a week an avg lol
    avg_hours_ot_week[x][:] = [d // (2*number_of_weekdays[x]) for d in avg_hours_ot_week[x]]
    avg_hours_ot_week_datastructures[x][:] = [d // (2*number_of_weekdays[x]) for d in avg_hours_ot_week_datastructures[x]]

    # for h in range(24):
    #     if (avg_hours_ot_week[x][h] != 0):
    #         avg_waittime_ot_week[x][h] = avg_waittime_ot_week[x][h] // avg_hours_ot_week[x][h]

    # print("%s" % num_to_weekday(x))
    # print(", ".join(str(i) for i in avg_hours_ot_week[x]))

    # f2.write(num_to_weekday(x))
    f2.write(", ".join(str(i) for i in avg_hours_ot_week[x]))
    f2.write(", ")
    # f2.write("\n")
f2.write("\n")
for x in range(7):
    f2.write(", ".join(str(i) for i in avg_waittime_ot_week[x]))
    f2.write(", ")

f2.write("\n")
for x in range(7):
    f2.write(", ".join(str(i) for i in avg_gradingtime_ot_week[x]))
    f2.write(", ")
# print("AVERAGED")
# print(avg_waittime_ot_week)
# print(avg_hours_ot_week)
