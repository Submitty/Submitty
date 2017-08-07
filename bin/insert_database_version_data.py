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
import json
import os
from submitty_utils import dateutils

from sqlalchemy import create_engine, Table, MetaData, bindparam, select, func

DB_HOST = "__INSTALL__FILLIN__DATABASE_HOST__"
DB_USER = "__INSTALL__FILLIN__DATABASE_USER__"
DB_PASSWORD = "__INSTALL__FILLIN__DATABASE_PASSWORD__"
DATA_DIR = "__INSTALL__FILLIN__SUBMITTY_DATA_DIR__"

def str2bool(v):
  return v.lower() in ("yes", "true", "t", "1")


def insert_to_database(semester,course,gradeable_id,user_id,team_id,who_id,is_team,version):

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

    db_name = "submitty_{}_{}".format(semester, course)

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
                            g_id=gradeable_id,  team_id=team_id, g_version=version)
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
                   g_id=gradeable_id, u_g_id=gradeable_id,
                   team_id=team_id, u_team_id=team_id,
                   g_version=version, u_g_version=version,
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
                            g_id=gradeable_id, user_id=user_id, g_version=version)
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
                   g_id=gradeable_id, u_g_id=gradeable_id,
                   user_id=user_id, u_user_id=user_id,
                   g_version=version, u_g_version=version,
                   autograding_non_hidden_non_extra_credit=non_hidden_non_ec,
                   autograding_non_hidden_extra_credit=non_hidden_ec,
                   autograding_hidden_non_extra_credit=hidden_non_ec,
                   autograding_hidden_extra_credit=hidden_ec,
                   submission_time=submission_time)


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
            #a = datetime.strptime(result_json[-1]['submission_time'], "%a %b  %d %H:%M:%S %Z %Y")
            a = dateutils.read_submitty_date(result_json[-1]['submission_time'])
            result_details['submission_time'] = '{}-{:02d}-{:02d} {:02d}:{:02d}:{:02d}' \
                .format(a.year, a.month, a.day, a.hour, a.minute, a.second)
    return result_details


