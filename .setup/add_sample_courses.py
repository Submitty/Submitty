#!/usr/bin/env python

from collections import OrderedDict
from datetime import date, timedelta
import grp
import json
import os
import pwd

submitty_repository = "/usr/local/submitty/GIT_CHECKOUT_Submitty"
submitty_install_dir = "/usr/local/submitty"
submitty_data_dir = "/var/local/submitty"
sample_dir = submitty_install_dir + "/sample_files/sample_assignment_config"
hwphp = (pwd.getpwnam("hwphp").pw_uid, grp.getgrnam("hwphp").gr_gid)


def create_course(course, semester, course_group, assignments):
    """
    Creates a course for use within the system. This deals with running the appropriate create
    scripts, as well as populate the course with some specified assignments for use within the
    system.

    :param course: course name for the course to build
    :param semester: what semester is the course we're creating in
    :param course_group: what is the primary group that should own the directories for the course
    :param assignments: what assignments should be built and added to this course
    """
    course_group_gid = grp.getgrnam(course_group).gr_gid

    # ---------------------------------------------------------------
    # CREATE THE COURSE
    os.system("%s/bin/create_course.sh %s %s %s %s" % (
        submitty_install_dir, semester, course, "instructor", course_group))

    # ---------------------------------------------------------------
    # CREATE THE COURSE DATABASE AND POPULATE IT

    os.environ['PGPASSWORD'] = 'hsdbu'
    database = "submitty_" + semester + "_" + course
    os.system('psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE ' + database + '"')
    os.system("psql -d %s -h localhost -U hsdbu -f %s/site/data/tables.sql" %
              (database, submitty_repository))
    os.system("psql -d %s -h localhost -U hsdbu -f %s/.setup/vagrant/db_inserts.sql" %
              (database, submitty_repository))

    # ---------------------------------------------------------------
    # RUN THE BUILD COURSE SCRIPT
    os.system("%s/courses/%s/%s/BUILD_%s.sh" % (submitty_data_dir, semester, course, course))

    # ---------------------------------------------------------------
    # DELETE THE PGPASSWORD FILE
    os.system("psql -d {} -h localhost -U hsdbu -c \"SELECT pg_catalog.setval("
              "'gradeable_component_gc_id_seq', {:d}, true)\"".format(database, len(assignments)))
    del os.environ['PGPASSWORD']

if __name__ == "__main__":
    today = date.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]

    os.system("mkdir -p " + submitty_data_dir + "/courses")

    python_homeworks = ["python_simple_homework", "python_linehighlight",
                        "python_simple_homework_multipart", "python_static_analysis"]
    cpp_homeworks = ["cpp_simple_lab", "cpp_cats", "cpp_memory_debugging", "cpp_custom", "cpp_hidden_tests"]
    java_homeworks = ["java_factorial", "java_coverage_factorial"]
    all = python_homeworks + cpp_homeworks + java_homeworks

    create_course("csci1000", semester, "csci1000_tas_www", all)
    create_course("csci1100", semester, "csci1100_tas_www", python_homeworks)
    create_course("csci1200", semester, "csci1200_tas_www", cpp_homeworks)
    create_course("csci2600", semester, "csci2600_tas_www", java_homeworks)
