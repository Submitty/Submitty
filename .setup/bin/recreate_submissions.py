#!/usr/bin/env python3

import argparse
import json
import os
from pathlib import Path
import shutil
from sqlalchemy import create_engine, Table, MetaData, select, insert, update
import sys
import typing

from submitty_utils import dateutils as submitty_dateutils

'''
DOCUMENTATION

To use this script, first do the following:
1. Create a new course, for example "test_course"
2. Add students to the course, possibly with CSV upload. This script will not
create a submission for a student who is not in the course.
3. Create and configure a gradeable, for example "test_gradeable"
4. Make one submission to this gradeable. This is to create the submissions
directory because the script copies permissions from it.
5. Run the command described below and answer "y" when given the prompt to add
submissions to the grading queue.
6. Wait for regrading to complete.

Specify command line arguments for semester, course, and gradeable, followed by
a directory containing the submisison data. For example:
recreate_submissions.py s23 test_course test_gradeable /root/submissions
The --active-only or --verbose options can be included
--active-only submits only the active version (determined from the
user_assignment_settings.json file if it exists, otherwise the highest version
number in the student's directory)
--verbose shows extra output displayed for debugging purposes, such as the SQL
queries run on the database
'''

'''
DATABASE

COURSE.electronic_gradeable - gradeable parameters - DO NOT NEED TO MODIFY
- columns: g_id,eg_config_path,eg_is_repository,
    eg_using_subdirectory,eg_vcs_subdirectory,eg_vcs_partial_path,
    eg_vcs_host_type,eg_team_assignment,eg_max_team_size,eg_team_lock_date,
    eg_use_ta_grading,eg_student_download,eg_student_view,
    eg_student_view_after_grades,eg_student_submit,eg_submission_open_date,
    eg_submission_due_date,eg_has_due_date,eg_late_days,
    eg_allow_late_submission,eg_precision,eg_grade_inquiry_allowed,
    eg_grade_inquiry_per_component_allowed,eg_grade_inquiry_due_date,
    eg_thread_ids,eg_has_discussion,eg_limited_access_blind,eg_peer_blind,
    eg_grade_inquiry_start_date,eg_hidden_files,eg_depends_on,
    eg_depends_on_points,eg_has_release_date

COURSE.electronic_gradeable_data
- columns: g_id,user_id,team_id,g_version,
    autograding_non_hidden_non_extra_credit,autograding_non_hidden_extra_credit,
    autograding_hidden_non_extra_credit,autograding_hidden_extra_credit,
    submission_time,autograding_complete
- INSERT INTO electronic_gradeable_data (g_id,user_id,g_version,
    submission_time,autograding_complete) VALUES (?,?,?,?,false)

COURSE.electronic_gradeable_version
- columns: g_id,user_id,team_id,active_version,anonymous_leaderboard
- UPDATE electronic_gradeable_version SET active_version=?
    WHERE g_id=? AND user_id=?
'''

# load configuration files
with open('/usr/local/submitty/config/submitty.json', 'r') as conf:
    SUBMITTY_CONFIG_JSON = json.load(conf)
with open('/usr/local/submitty/config/database.json', 'r') as conf:
    SUBMITTY_DBCONFIG_JSON = json.load(conf)

ARG_INSTALL_DIR = SUBMITTY_CONFIG_JSON['submitty_install_dir']
ARG_DATA_DIR = SUBMITTY_CONFIG_JSON['submitty_data_dir']
ARG_DB_HOST = SUBMITTY_DBCONFIG_JSON['database_host']
ARG_DB_PORT = SUBMITTY_DBCONFIG_JSON['database_port']
ARG_DB_USER = SUBMITTY_DBCONFIG_JSON['database_user']
ARG_DB_PASS = SUBMITTY_DBCONFIG_JSON['database_password']

# variables specifying gradeable to submit to
ARG_SEMESTER = ''
ARG_COURSE = ''
ARG_GRADEABLE = ''

# place with files to submit
ARG_SUBMISSIONS = ''

# only use active versions?
ARG_ACTIVE_ONLY = False

# display extra output
ARG_VERBOSE = False

# some file names to look for
UASF_NAME = 'user_assignment_settings.json'
UAAF_NAME = '.user_assignment_access.json'
UST_NAME = '.submit.timestamp'


def submit(semester: str, course: str, gradeable: str, user: str, data: Path,
           func_add_submission: typing.Callable[[str, str, int, str], None],
           func_update_active: typing.Callable[[str, str, int], None]):
    '''
    Create a new submission as a student
    semester,course,gradeable = specify gradeable to make submission to
    user = user to submit as
    data = path to directory containing the files for submission
    '''
    gradeable_path = Path(ARG_DATA_DIR, 'courses', semester,
                          course, 'submissions', gradeable)
    # make sure we are submitting to a real gradeable
    assert gradeable_path.is_dir(), gradeable_path
    user_path = gradeable_path / user
    if not user_path.exists():
        user_path.mkdir(parents=True)
    # load data from the user_assignment_settings.json file if it exists
    if (user_path / UASF_NAME).exists():
        with open(user_path / UASF_NAME, 'r') as uasf:
            UAS = json.load(uasf)
    else:  # initialize empty
        UAS = {
            'active_version': 0,
            'history': []
        }
    if len(UAS['history']) == 0:
        highest_version = 0
    else:
        highest_version = max(obj['version'] for obj in UAS['history'])
    submission_path = user_path / str(highest_version+1)
    # copy submission files, the submission_path should be created here
    shutil.copytree(data, submission_path)
    current_time_str = submitty_dateutils.write_submitty_date()
    # use current time for submission timestamp
    with open(submission_path / UST_NAME, 'w') as stf:
        stf.write(current_time_str+'\n')
    # leave the user assignment access list empty
    with open(submission_path / UAAF_NAME, 'w') as uaaf:
        uaaf.write('[]')
    UAS['history'].append({
        'version': highest_version+1,
        'time': current_time_str,
        'who': user,
        'type': 'upload'
    })
    UAS['active_version'] = highest_version+1
    # store updated user_assignment_settings.json file
    with open(user_path / UASF_NAME, 'w') as uasf:
        json.dump(UAS, uasf, indent=4)
    # set file permissions, copy owner+group from gradeable directory
    # files should be set to -rw-r--r--
    stat = os.stat(gradeable_path)
    os.chown(user_path, stat.st_uid, stat.st_gid)
    os.chmod(user_path, stat.st_mode)
    for root, dirs, files in os.walk(user_path):
        for dir in dirs:
            os.chown(Path(root, dir), stat.st_uid, stat.st_gid)
            os.chmod(Path(root, dir), stat.st_mode)
        for file in files:
            os.chown(Path(root, file), stat.st_uid, stat.st_gid)
            os.chmod(Path(root, file), 0o640)
    # update database
    func_add_submission(gradeable, user, highest_version+1, current_time_str)
    func_update_active(gradeable, user, highest_version+1)


def parseArgs():
    global ARG_SEMESTER, ARG_COURSE, ARG_GRADEABLE, ARG_SUBMISSIONS
    global ARG_ACTIVE_ONLY, ARG_VERBOSE
    parser = argparse.ArgumentParser(
        prog='recreate_submissions.py',
        description='upload student submissions to an existing gradeable'
    )
    parser.add_argument('semester',
                        help='the semester containing the gradeable to submit to')
    parser.add_argument('course',
                        help='the course containing the gradeable to submit to')
    parser.add_argument('gradeable',
                        help='the gradeable ID to submit to')
    parser.add_argument('submissions',
                        help='directory containing the file structure of submissions to use')
    parser.add_argument('--active-only', action='store_true')
    parser.add_argument('--verbose', action='store_true')
    print(f'sys.argv = {sys.argv}')
    args = parser.parse_args(sys.argv[1:])
    ARG_SEMESTER = args.semester
    ARG_COURSE = args.course
    ARG_GRADEABLE = args.gradeable
    ARG_SUBMISSIONS = args.submissions
    ARG_ACTIVE_ONLY = args.active_only
    ARG_VERBOSE = args.verbose


def main():
    parseArgs()
    dbengine_course = create_engine(
        f'postgresql:///submitty_{ARG_SEMESTER}_{ARG_COURSE}?host={ARG_DB_HOST}'
        f'&port={ARG_DB_PORT}&user={ARG_DB_USER}&password={ARG_DB_PASS}')
    dbconn_course = dbengine_course.connect()
    metadata = MetaData()
    table_users = Table('users', metadata, autoload_with=dbconn_course)
    table_egdata = Table('electronic_gradeable_data', metadata, autoload_with=dbconn_course)
    table_egver = Table('electronic_gradeable_version', metadata, autoload_with=dbconn_course)
    ret = dbconn_course.execute(select(table_users))
    user_list = set(row['user_id'] for row in ret.mappings().all())
    ret.close()

    # function for query to electronic_gradeable_data table
    def insert_egdata(g_id: str, user_id: str, g_version: int,
                      submission_time: str, autograding_complete=False):
        if ARG_VERBOSE:
            print('    insert_egdata()')
            print(f'    g_id                 = {g_id}')
            print(f'    user_id              = {user_id}')
            print(f'    g_version            = {g_version}')
            print(f'    submission_time      = {submission_time}')
            print(f'    autograding_complete = {autograding_complete}')
        query = insert(table_egdata).values(
            g_id=g_id, user_id=user_id, g_version=g_version,
            submission_time=submission_time,
            autograding_complete=autograding_complete
        )
        if ARG_VERBOSE:
            print(f'    SQL: {query}')
        dbconn_course.execute(query)
        dbconn_course.commit()

    # function for query to electronic_gradeable_version table
    def update_egver(g_id: str, user_id: str, active_version: int):
        if ARG_VERBOSE:
            print('    update_egver()')
            print(f'    g_id           = {g_id}')
            print(f'    user_id        = {user_id}')
            print(f'    active_version = {active_version}')
        query = select(table_egver).where(
            (table_egver.c.g_id == g_id) &
            (table_egver.c.user_id == user_id)
        )
        ret = dbconn_course.execute(query)
        rows = ret.all()
        if ARG_VERBOSE:
            print(f'    SQL: {query}')
            print(f'    egver_data = {rows}')
        if len(rows) == 0:  # INSERT
            query = insert(table_egver).values(
                g_id=g_id,
                user_id=user_id,
                active_version=active_version
            )
        else:  # UPDATE
            query = update(table_egver).values(
                active_version=active_version
            ).where(
                (table_egver.columns.g_id == g_id) &
                (table_egver.columns.user_id == user_id)
            )
        if ARG_VERBOSE:
            print(f'    SQL: {query}')
        dbconn_course.execute(query)
        dbconn_course.commit()

    for user in os.listdir(ARG_SUBMISSIONS):
        if not Path(ARG_SUBMISSIONS, user).is_dir():
            continue
        if user not in user_list:
            print(f'WARNING: found dir for {user} who is not in the course')
            continue
        print(f'SUBMITTING FOR USER {user}')
        user_dirlist = os.listdir(Path(ARG_SUBMISSIONS, user))
        versions = [int(f) for f in user_dirlist
                    if Path(ARG_SUBMISSIONS, user, f).is_dir()]
        # determine active version
        if Path(ARG_SUBMISSIONS, user, UASF_NAME).is_file():
            with open(Path(ARG_SUBMISSIONS, user, UASF_NAME)) as uasfile:
                uasdata = json.load(uasfile)
            active_version = uasdata['active_version']
        else:  # use highest
            active_version = max(versions)
        for version in sorted(versions):
            # skip inactive versions with active only flag
            if ARG_ACTIVE_ONLY and version != active_version:
                continue
            print(f'  SUBMITTING VERSION {version}')
            submit(ARG_SEMESTER, ARG_COURSE, ARG_GRADEABLE, user,
                   Path(ARG_SUBMISSIONS, user, str(version)),
                   insert_egdata, update_egver)
    dbconn_course.close()
    # initiate batch regrade
    print('ADDING SUBMISSIONS TO GRADING QUEUE')
    regrade_py = Path(ARG_INSTALL_DIR, 'bin', 'regrade.py')
    gradeable_path = Path(ARG_DATA_DIR, 'courses', ARG_SEMESTER,
                          ARG_COURSE, 'submissions', ARG_GRADEABLE)
    os.system(f'{regrade_py} {gradeable_path}')


if __name__ == '__main__':
    if os.getuid() == 0:
        main()
    else:
        print('Please run this as root, exiting...')
