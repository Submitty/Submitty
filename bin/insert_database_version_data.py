#!/usr/bin/env python3

"""
This file is used to put submission details about a version into the database so that it can be
easily and efficiently used by the PHP script. This needs (in the general case) read access to the
DATA_DIR files for a course (both in config/ and results/ directory). The script should then also
only be readable/executable by the hwcron user as it does contain the database information and we
do notwant some crafty student to try and get access to it. Thus the file should be owned by
hwcron and the permissions set to 500.

Main usage of the script is:
./insert_database_version_data.py <semester> <course> <gradeable_id> <user_id> <version>

which will fetch the data from the config/build and results/ directories to contruct the details
about the student's submissions, inserting it into the database.

However, you can directly pass in additional optional arguments to either override the student's
point total in a category or if you're not using the files (via -n flag). Doing:
./insert_database_version_data.py --help
will explain how to do that.
"""
import argparse
from datetime import datetime
import json
import os

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, func

DB_HOST = "__INSTALL__FILLIN__DATABASE_HOST__"
DB_USER = "__INSTALL__FILLIN__DATABASE_USER__"
DB_PASSWORD = "__INSTALL__FILLIN__DATABASE_PASSWORD__"
DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

def str2bool(v):
  return v.lower() in ("yes", "true", "t", "1")

def parse_arguments():
    """
    Parse the arguments coming from argparse
    :return: parsed arguments from argparse
    """
    parser = argparse.ArgumentParser(
        description="Insert details about a version of a submission into the database. This can "
                    "be used either in conjunction with loading the submission information from "
                    "files or can pass the details directly into the script.")
    parser.add_argument("semester", type=str, help="")
    parser.add_argument("course", type=str, help="")
    parser.add_argument("gradeable_id", type=str, help="")
    parser.add_argument("user_id", type=str, help="")
    parser.add_argument("team_id", type=str, help="")
    parser.add_argument("who_id", type=str, help="")
    parser.add_argument("is_team", type=str, help="")  # converted to boolean below
    parser.add_argument("version", type=int, help="")

    parser.add_argument("-n", "--no-file", action="store_true", dest="no_file", default=False,
                        help="do not load any data from files")

    parser.add_argument("-a", "--autograding_non_hidden_non_extra_credit", type=float, default=None,
                        dest="autograding_non_hidden_non_extra_credit",
                        help="score from autograder on non-hidden non-extra credit autochecks. "
                             "setting this will overwrite any data gotten from the files. "
                             "default to 0, only if -n is set")
    parser.add_argument("-b", "--autograding_non_hidden_extra_credit", type=float, default=None,
                        dest="autograding_non_hidden_extra_credit",
                        help="score from autograder on non-hidden extra credit autochecks. "
                             "setting this will overwrite any data gotten from the files. "
                             "default to 0, only if -n is set")
    parser.add_argument("-c", "--autograding_hidden_non_extra_credit", type=float, default=None,
                        dest="autograding_hidden_non_extra_credit",
                        help="score from autograder on hidden, non-extra credit autochecks. "
                             "setting this will overwrite any data gotten from the files. "
                             "default to 0, only if -n is set")
    parser.add_argument("-d", "--autograding_hidden_extra_credit", type=float, default=None,
                        dest="autograding_hidden_extra_credit",
                        help="score from autograder on hidden, extra credit autochecks. "
                             "setting this will overwrite any data gotten from the files. "
                             "default to 0, only if -n is set")
    parser.add_argument("-e", "--submission_time", type=str, default=None,
                        dest="submission_time",
                        help="submission timestamp for the assignment in the format YYYY-MM-DD "
                             "HH:MM:SS. setting this will overwrite any data gotten from the "
                             "files. default to current timestamp, only if -n is set")
    return parser.parse_args()


def main():
    """
    Program execution
    """

    args = parse_arguments()

    semester = args.semester
    course = args.course
    gradeable_id = args.gradeable_id
    user_id = args.user_id
    team_id = args.team_id
    who_id = args.who_id
    is_team = str2bool(args.is_team)
    version = args.version

    if not args.no_file:
        non_hidden_non_ec = 0
        non_hidden_ec = 0
        hidden_non_ec = 0
        hidden_ec = 0
        testcases = get_testcases(semester, course, gradeable_id)
        results = get_result_details(semester, course, gradeable_id, who_id, version)
        if not len(testcases) == len(results['testcases']):
            print ("ERROR!  mismatched # of testcases ",len(testcases)," != ",len(results['testcases']))
        for i in range(len(testcases)):
            if testcases[i]['hidden'] and testcases[i]['extra_credit']:
                hidden_ec += results['testcases'][i]['points']
            elif testcases[i]['hidden']:
                hidden_non_ec += results['testcases'][i]['points']
            elif testcases[i]['extra_credit']:
                non_hidden_ec += results['testcases'][i]['points']
            else:
                non_hidden_non_ec += results['testcases'][i]['points']
        submission_time = results['submission_time']
    else:
        non_hidden_non_ec = parse_default_int(args.autograding_non_hidden_non_extra_credit)
        non_hidden_ec = parse_default_int(args.autograding_non_hidden_extra_credit)
        hidden_non_ec = parse_default_int(args.autograding_hidden_non_extra_credit)
        hidden_ec = parse_default_int(args.autograding_hidden_extra_credit)
        submission_time = parse_default_time(args.submission_time)

    db_name = "submitty_{}_{}".format(args.semester, args.course)

    # If using a UNIX socket, have to specify a slightly different connection string
    if os.path.isdir(DB_HOST):
        conn_string = "postgresql://{}:{}@/{}?host={}".format(DB_USER, DB_PASSWORD, db_name, DB_HOST)
    else:
        conn_string = "postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASSWORD, DB_HOST, db_name)
    db = create_engine(conn_string)
    metadata = MetaData(bind=db)
    data_table = Table('electronic_gradeable_data', metadata, autoload=True)

    """
    The data row should have been inserted by PHP when the student uploads the submission, requiring
    us to do an update here (as the PHP also deals with the active version for us), but in case
    we're using some other method of grading, we'll insert the row and whoever called the script
    will need to handle the active version afterwards.
    """
    if is_team is True:
        result = db.execute(select([func.count()]).select_from(data_table)
                            .where(data_table.c.g_id == bindparam('g_id'))
                            .where(data_table.c.team_id == bindparam('team_id'))
                            .where(data_table.c.g_version == bindparam('g_version')),
                            g_id=args.gradeable_id,  team_id=args.team_id, g_version=args.version)
        row = result.fetchone()
        query_type = data_table.insert()
        if row[0] > 0:
            query_type = data_table\
                .update(
                    values=
                    {
                        data_table.c.autograding_non_hidden_non_extra_credit:
                            bindparam("autograding_non_hidden_non_extra_credit"),
                        data_table.c.autograding_non_hidden_extra_credit:
                            bindparam("autograding_non_hidden_extra_credit"),
                        data_table.c.autograding_hidden_non_extra_credit:
                            bindparam("autograding_hidden_non_extra_credit"),
                        data_table.c.autograding_hidden_extra_credit:
                            bindparam("autograding_hidden_extra_credit")
                    })\
                .where(data_table.c.g_id == bindparam('u_g_id'))\
                .where(data_table.c.team_id == bindparam('u_team_id'))\
                .where(data_table.c.g_version == bindparam('u_g_version'))
            # we bind "u_g_id" (and others) as we cannot use "g_id" in the where clause for an
            # update. Passing this as an argument to db.execute doesn't cause any issue when we
            # use the insert query (that doesn't have u_g_id)
        db.execute(query_type,
                   g_id=args.gradeable_id, u_g_id=args.gradeable_id,
                   team_id=args.team_id, u_team_id=args.team_id,
                   g_version=args.version, u_g_version=args.version,
                   autograding_non_hidden_non_extra_credit=non_hidden_non_ec,
                   autograding_non_hidden_extra_credit=non_hidden_ec,
                   autograding_hidden_non_extra_credit=hidden_non_ec,
                   autograding_hidden_extra_credit=hidden_ec,
                   submission_time=submission_time)

    else:
        result = db.execute(select([func.count()]).select_from(data_table)
                            .where(data_table.c.g_id == bindparam('g_id'))
                            .where(data_table.c.user_id == bindparam('user_id'))
                            .where(data_table.c.g_version == bindparam('g_version')),
                            g_id=args.gradeable_id, user_id=args.user_id, g_version=args.version)
        row = result.fetchone()
        query_type = data_table.insert()
        if row[0] > 0:
            query_type = data_table\
                .update(
                    values=
                    {
                        data_table.c.autograding_non_hidden_non_extra_credit:
                            bindparam("autograding_non_hidden_non_extra_credit"),
                        data_table.c.autograding_non_hidden_extra_credit:
                            bindparam("autograding_non_hidden_extra_credit"),
                        data_table.c.autograding_hidden_non_extra_credit:
                            bindparam("autograding_hidden_non_extra_credit"),
                        data_table.c.autograding_hidden_extra_credit:
                            bindparam("autograding_hidden_extra_credit")
                    })\
                .where(data_table.c.g_id == bindparam('u_g_id'))\
                .where(data_table.c.user_id == bindparam('u_user_id'))\
                .where(data_table.c.g_version == bindparam('u_g_version'))
            # we bind "u_g_id" (and others) as we cannot use "g_id" in the where clause for an
            # update. Passing this as an argument to db.execute doesn't cause any issue when we
            # use the insert query (that doesn't have u_g_id)
        db.execute(query_type,
                   g_id=args.gradeable_id, u_g_id=args.gradeable_id,
                   user_id=args.user_id, u_user_id=args.user_id,
                   g_version=args.version, u_g_version=args.version,
                   autograding_non_hidden_non_extra_credit=non_hidden_non_ec,
                   autograding_non_hidden_extra_credit=non_hidden_ec,
                   autograding_hidden_non_extra_credit=hidden_non_ec,
                   autograding_hidden_extra_credit=hidden_ec,
                   submission_time=submission_time)


def parse_default_int(arg):
    """
    Defaults value to 0 for numeric argument if the argument is not set (None)
    :param arg:
    :return: 0 if arg isn't None, but otherwise return arg
    """
    return 0 if arg is None else arg


def parse_default_time(arg):
    """
    Defaults value to the current date and time if argument is not set (None), otherwise return arg
    :param arg:
    :return:
    """
    if arg is None:
        a = datetime.now()
        arg = '{}-{:02d}-{:02d} {:02d}:{:02d}:{:02d}' .format(a.year, a.month, a.day, a.hour,
                                                          a.minute, a.second)
    return arg


def get_testcases(semester, course, g_id):
    """
    Get all the testcases for a homework from its build json file. This should have a 1-to-1
    correspondance with the testcases that come from the results.json file.

    :param semester:
    :param course:
    :param g_id:
    :return:
    """
    testcases = []
    build_file = os.path.join(DATA_DIR, "courses", semester, course, "config", "build",
                              "build_" + g_id + ".json")
    if os.path.isfile(build_file):
        with open(build_file) as build_file:
            build_json = json.load(build_file)
            if 'testcases' in build_json and build_json['testcases'] is not None:
                for testcase in build_json['testcases']:
                    testcases.append({'hidden': testcase['hidden'],
                                      'extra_credit': testcase['extra_credit'],
                                      'points': testcase['points']})
    return testcases


def get_result_details(semester, course, g_id, who_id, version):
    """
    Gets the result details for a particular version of a gradeable for the who (user or team). It returns a
    dictionary that contains a list of the testcases (that should have a 1-to-1 correspondance
    with the testcases gotten through get_testcases() method) and the submission time for the
    particular version.

    :param semester:
    :param course:
    :param g_id:
    :param who_id:
    :param version:
    :return:
    """
    result_details = {'testcases': [], 'submission_time': None}
    result_dir = os.path.join(DATA_DIR, "courses", semester, course, "results", g_id, who_id,
                              str(version))
    if os.path.isfile(os.path.join(result_dir, "results.json")):
        with open(os.path.join(result_dir, "results.json")) as result_file:
            result_json = json.load(result_file)
            if 'testcases' in result_json and result_json['testcases'] is not None:
                for testcase in result_json['testcases']:
                    result_details['testcases'].append({'points': testcase['points_awarded']})

    if os.path.isfile(os.path.join(result_dir, "history.json")):
        with open(os.path.join(result_dir, "history.json")) as result_file:
            result_json = json.load(result_file)
            a = datetime.strptime(result_json[-1]['submission_time'], "%a %b  %d %H:%M:%S %Z %Y")
            result_details['submission_time'] = '{}-{:02d}-{:02d} {:02d}:{:02d}:{:02d}' \
                .format(a.year, a.month, a.day, a.hour, a.minute, a.second)
    return result_details


if __name__ == "__main__":
    main()
