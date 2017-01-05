#!/usr/bin/env python

from __future__ import division

from collections import OrderedDict
from datetime import date, timedelta
import grp
import json
import os
import pwd
import sys

SUBMITTY_REPOSITORY = "/usr/local/submitty/GIT_CHECKOUT_Submitty"
SUBMITTY_INSTALL_DIR = "/usr/local/submitty"
SUBMITTY_DATA_DIR = "/var/local/submitty"
SAMPLE_DIR = SUBMITTY_INSTALL_DIR + "/sample_files/sample_assignment_config"
HWPHP = (pwd.getpwnam("hwphp").pw_uid, grp.getgrnam("hwphp").gr_gid)

PYTHON_HOMEWORKS = ["python_simple_homework", "python_linehighlight",
                    "python_simple_homework_multipart", "python_static_analysis"]
CPP_HOMEWORKS = ["cpp_simple_lab", "cpp_cats", "cpp_memory_debugging", "cpp_custom",
                 "cpp_hidden_tests", "c_fork", "c_failure_messages"]
JAVA_HOMEWORKS = ["java_factorial", "java_coverage_factorial"]
ALL_HOMEWORKS = PYTHON_HOMEWORKS + CPP_HOMEWORKS + JAVA_HOMEWORKS


def create_course(course, semester, course_group, assignments=None):
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
        SUBMITTY_INSTALL_DIR, semester, course, "instructor", course_group))

    # ---------------------------------------------------------------
    # CREATE THE COURSE DATABASE AND POPULATE IT
    os.environ['PGPASSWORD'] = 'hsdbu'
    database = "submitty_" + semester + "_" + course
    os.system('psql -d postgres -h localhost -U hsdbu -c "CREATE DATABASE {}"'.format(database))
    os.system("psql -d {} -h localhost -U hsdbu -f {}/site/data/tables.sql"
              .format(database, SUBMITTY_REPOSITORY))
    os.system("psql -d {} -h localhost -U hsdbu -f {}/.setup/vagrant/db_inserts.sql"
              .format(database, SUBMITTY_REPOSITORY))

    # ---------------------------------------------------------------
    # ADD DATES TO THE PER ASSIGNMENT FORM JSONS AND INSERT PER ASSIGNMENT DATA TO DATABASE
    count = 0
    if assignments is not None:
        for i in range(len(assignments)):
            assignment = assignments[i]
            with open("%s/sample_files/sample_form_config/form_%s.json" %
                      (SUBMITTY_REPOSITORY, assignment)) as read_file:
                form_json = json.load(read_file, object_pairs_hook=OrderedDict)

            tmp = date.today() + timedelta(days=((2 * i) - 2))
            otmp = tmp - timedelta(days=8)
            otmp2 = otmp - timedelta(days=1)
            form_json["ta_view_date"] = "{:d}-{:d}-{:d} 23:59:59".format(otmp2.year,
                                                                         otmp2.month,
                                                                         otmp2.day)
            form_json["date_submit"] = "{:d}-{:d}-{:d} 00:00:01".format(otmp.year, otmp.month,
                                                                        otmp.day)
            form_json["date_due"] = "{:d}-{:d}-{:d} 23:59:59".format(tmp.year, tmp.month, tmp.day)
            tmp2 = tmp + timedelta(days=4)
            form_json["date_grade"] = "{:d}-{:d}-{:d} 23:59:59".format(tmp2.year, tmp2.month,
                                                                       tmp2.day)
            tmp2 = tmp2 + timedelta(days=1)
            form_json["date_released"] = "{:d}-{:d}-{:d} 23:59:59".format(tmp2.year, tmp2.month,
                                                                          tmp2.day)

            form_file = "{}/courses/{}/{}/config/form/form_{}.json".format(SUBMITTY_DATA_DIR,
                                                                           semester, course,
                                                                           assignment)
            with open(form_file, "w") as form_write:
                json.dump(form_json, form_write, indent=2)
            os.chown(form_file, HWPHP[0], course_group_gid)

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO gradeable VALUES ('{}', "
                      "'{}', '{}', '', false, 0, true, '{}', '{}', 'homework', 1, NULL, '{}')\""
                      .format(database, form_json['gradeable_id'], form_json['gradeable_title'],
                              form_json['instructions_url'], form_json['date_grade'],
                              form_json['date_released'], form_json['ta_view_date']))

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO electronic_gradeable "
                      "VALUES ('{}', '{}', '{}', false, '', true, '{}', 2, {})\""
                      .format(database, form_json['gradeable_id'],
                              form_json['date_submit'], form_json['date_due'],
                              form_json['config_path'], form_json['point_precision']))

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO gradeable_component "
                      "VALUES ({:d}, '{}', 'Test', '', '', 5, false, false, 1)\""
                      .format(database, i, form_json['gradeable_id']))

        os.system("psql -d {} -h localhost -U hsdbu -c \"SELECT pg_catalog.setval("
                  "'gradeable_component_gc_id_seq', {:d}, true)\"".format(database,
                                                                          len(assignments)))
    else:
        type_dict = {
            0: {"id": "homework", "name": "Homework", "type": "Electronic File", "bucket":
                "homework"},
            1: {"id": "lab", "name": "Lab", "type": "Checkpoints", "bucket": "lab"},
            2: {"id": "test", "name": "Test", "type": "Numeric", "bucket": "test"}
        }
        for i in range(0, 8):
            type = (i % 2) + 1
            category = int(i / 2)
            tmp = type_dict[type]

            form_json = {
                "gradeable_id": tmp["id"] + "_" + str(category),
                "gradeable_title": tmp["name"] + " " + str(category),
                "gradeable_type": tmp["type"],
                "instructions_url": "",
                "minimum_grading_group": 3,
                "section_type": "reg_section",
                "ta_view_date": None,
                "date_grade": None,
                "date_released": None,
                "gradeable_buckets": tmp["bucket"]
            }

            if category == 0:
                # Future
                form_json["ta_view_date"] = "12/31/9997 23:59:59"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif category == 1:
                # Future (TA Can View)
                form_json["ta_view_date"] = "01/01/1970 00:00:01"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif category == 2:
                # Grading
                form_json["ta_view_date"] = "01/01/1970 00:00:01"
                form_json["date_grade"] = "01/02/1971 00:00:01"
                form_json["date_released"] = "12/31/9999 23:59:59"
            else:
                # Grades Released
                form_json["ta_view_date"] = "01/01/1970 00:00:01"
                form_json["date_grade"] = "01/02/1971 00:00:01"
                form_json["date_released"] = "01/03/1972 00:00:01"

            if type == 1:
                # Checkpoints
                form_json['instructions_url'] = "https://github.com/Submitty/Submitty/wiki/" \
                                                "Create-or-Edit-a-Gradeable#numerictext"
                form_json["checkpoint_label"] = ["Checkpoint 1", "Checkpoint 2"]
                form_json["checkpoint_extra"] = [2]

            elif type == 2:
                # Numeric
                form_json["num_numeric_items"] = 2
                form_json["num_text_items"] = 1
                form_json["numeric_labels"] = ["1", "2"]
                form_json["max_score"] = [5, 5]
                form_json["numeric_extra"] = [2]
                form_json["text_label"] = ["Seating"]
                form_json["minimum_grading_group"] = 2

            assignment = form_json['gradeable_id']
            form_file = "{}/courses/{}/{}/config/form/form_{}.json".format(SUBMITTY_DATA_DIR,
                                                                           semester, course,
                                                                           assignment)
            with open(form_file, "w") as form_write:
                json.dump(form_json, form_write, indent=2)
            os.chown(form_file, HWPHP[0], course_group_gid)

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO gradeable (g_id, "
                      "g_title, g_instructions_url, g_overall_ta_instructions, g_team_assignment, "
                      "g_gradeable_type, g_grade_by_registration, g_grade_start_date, "
                      "g_grade_released_date, g_syllabus_bucket, g_min_grading_group, "
                      "g_closed_date, g_ta_view_start_date) VALUES ('{}', '{}', '{}', '', "
                      "false, {}, true, '{}', '{}', '{}', {}, NULL, '{}')\""
                      .format(database, form_json['gradeable_id'], form_json['gradeable_title'],
                              form_json['instructions_url'], type, form_json['date_grade'],
                              form_json['date_released'], tmp['bucket'],
                              form_json['minimum_grading_group'], form_json['ta_view_date']))

            if type == 1:
                insert_gradeable_component(database, form_json['gradeable_id'], "Checkpoint 1",
                                           5, 1)
                insert_gradeable_component(database, form_json['gradeable_id'], "Checkpoint 1",
                                           5, 2, ec=1)
            else:
                insert_gradeable_component(database, form_json['gradeable_id'], "1", 5, 1)
                insert_gradeable_component(database, form_json['gradeable_id'], "2", 5, 2, ec=1)
                insert_gradeable_component(database, form_json['gradeable_id'], "Seating", 5, 3, 1)

        for i in range(0, 6):
            tmp = type_dict[0]
            form_json = {
                "gradeable_id": tmp["id"] + "_" + str(i),
                "gradeable_title": tmp["name"] + " " + str(i),
                "gradeable_type": tmp["type"],
                "instructions_url": "https://github.com/Submitty/Submitty/wiki/"
                                    "Create-or-Edit-a-Gradeable#electronic-submission",
                "minimum_grading_group": 3,
                "section_type": "reg_section",
                "eg_late_days": 2,
                "upload_type": "Upload File",
                "config_path": "/usr/local/submitty/sample_files/sample_assignment_config/"
                               "python_simple_homework/",
                "point_precision": 0.5,
                "ta_grading": True,
                "comment_title": ["Test 1", "Test 2"],
                "ta_comment": ["", ""],
                "student_comment": ["", ""],
                "points": [5, 5],
                "eg_extra": [2],
                "ta_view_date": None,
                "date_submit": None,
                "date_due": None,
                "date_grade": None,
                "date_released": None,
                "gradeable_buckets": tmp["bucket"]
            }
            if i == 0:
                # FUTURE
                form_json["ta_view_date"] = "12/31/9995 23:59:59"
                form_json["date_submit"] = "12/31/9996 23:59:59"
                form_json["date_due"] = "12/31/9997 23:59:59"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif i == 1:
                # FUTURE (TA Viewing)
                form_json["ta_view_date"] = "01/01/1970 23:59:59"
                form_json["date_submit"] = "12/31/9996 23:59:59"
                form_json["date_due"] = "12/31/9997 23:59:59"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif i == 2:
                # OPEN
                form_json["ta_view_date"] = "01/01/1970 23:59:59"
                form_json["date_submit"] = "01/01/1971 23:59:59"
                form_json["date_due"] = "12/31/9997 23:59:59"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif i == 3:
                # CLOSED
                form_json["ta_view_date"] = "01/01/1970 23:59:59"
                form_json["date_submit"] = "01/01/1971 23:59:59"
                form_json["date_due"] = "01/01/1972 23:59:59"
                form_json["date_grade"] = "12/31/9998 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif i == 4:
                # GRADING
                form_json["ta_view_date"] = "01/01/1970 23:59:59"
                form_json["date_submit"] = "01/01/1971 23:59:59"
                form_json["date_due"] = "01/01/1972 23:59:59"
                form_json["date_grade"] = "01/01/1973 23:59:59"
                form_json["date_released"] = "12/31/9999 23:59:59"
            elif i == 5:
                # GRADING RELEASED
                form_json["ta_view_date"] = "01/01/1970 23:59:59"
                form_json["date_submit"] = "01/01/1971 23:59:59"
                form_json["date_due"] = "01/01/1972 23:59:59"
                form_json["date_grade"] = "01/01/1973 23:59:59"
                form_json["date_released"] = "01/01/1974 23:59:59"

            assignment = form_json['gradeable_id']
            form_file = "{}/courses/{}/{}/config/form/form_{}.json".format(SUBMITTY_DATA_DIR,
                                                                           semester, course,
                                                                           assignment)
            with open(form_file, "w") as form_write:
                json.dump(form_json, form_write, indent=2)
            os.chown(form_file, HWPHP[0], course_group_gid)

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO gradeable (g_id, "
                      "g_title, g_instructions_url, g_overall_ta_instructions, g_team_assignment, "
                      "g_gradeable_type, g_grade_by_registration, g_grade_start_date, "
                      "g_grade_released_date, g_syllabus_bucket, g_min_grading_group, "
                      "g_closed_date, g_ta_view_start_date) VALUES ('{}', '{}', '{}', '', "
                      "false, 0, true, '{}', '{}', 'homework', 3, NULL, '{}')\""
                      .format(database, form_json['gradeable_id'], form_json['gradeable_title'],
                              form_json['instructions_url'], form_json['date_grade'],
                              form_json['date_released'], form_json['ta_view_date']))

            os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO electronic_gradeable "
                      "VALUES ('{}', '{}', false, '', true, '{}', '{}', 2, {})\""
                      .format(database, form_json['gradeable_id'], form_json['config_path'],
                              form_json['date_submit'], form_json['date_due'],
                              form_json['point_precision']))

            insert_gradeable_component(database, assignment, "Test 1", 5, 1)
            insert_gradeable_component(database, assignment, "Test 2", 5, 2, ec=1)

    # ---------------------------------------------------------------
    # RUN THE BUILD COURSE SCRIPT
    os.system("%s/courses/%s/%s/BUILD_%s.sh" % (SUBMITTY_DATA_DIR, semester, course, course))

    # ---------------------------------------------------------------
    # DELETE THE PGPASSWORD FILE
    del os.environ['PGPASSWORD']


def insert_gradeable_component(database, gradeable_id, title, score, order, text=0, ec=0):
    """
    Runs an insertion query to the database for gradeable components. Should be run only after
    setting the database password OS variable (os.environ['PGPASSWORD']

    :param database:
    :param gradeable_id:
    :param title:
    :param score:
    :param order:
    :param text:
    :param ec:
    :return:
    """
    text = "true" if text == 1 else "false"
    ec = "true" if ec == 1 else "false"

    os.system("psql -d {} -h localhost -U hsdbu -c \"INSERT INTO gradeable_component "
              "(g_id, gc_title, gc_ta_comment, gc_student_comment, gc_max_value, "
              "gc_is_text, gc_is_extra_credit, gc_order) VALUES ('{}', '{}', '', '', "
              "{:d}, {}, {}, {})\""
              .format(database, gradeable_id, title, score, text, ec, order))


def main():
    """
    Main program execution
    """
    today = date.today()
    semester = "f" + str(today.year)[-2:]
    if today.month < 7:
        semester = "s" + str(today.year)[-2:]

    os.system("mkdir -p " + SUBMITTY_DATA_DIR + "/courses")

    if len(sys.argv) > 1:
        for i in range(1, len(sys.argv)):
            create_course(sys.argv[i], semester, sys.argv[i] + "_tas_www")
    else:
        create_course("csci1000", semester, "csci1000_tas_www")
        create_course("csci1100", semester, "csci1100_tas_www", PYTHON_HOMEWORKS)
        create_course("csci1200", semester, "csci1200_tas_www", CPP_HOMEWORKS)
        create_course("csci2600", semester, "csci2600_tas_www", JAVA_HOMEWORKS)

if __name__ == "__main__":
    main()
