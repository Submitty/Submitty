#!/usr/bin/env python3
'''
DOCUMENTATION
currently uses hard coded values
'''

'''
DATABASE

COURSE.electronic_gradeable - gradeable parameters - DO NOT NEED TO MODIFY
- columns: g_id,eg_config_path,eg_is_repository,eg_subdirectory,
    eg_vcs_host_type,eg_team_assignment,eg_max_team_size,eg_team_lock_date,
    eg_use_ta_grading,eg_student_download,eg_student_view,
    eg_student_view_after_grades,eg_student_submit,eg_submission_open_date,
    eg_submission_due_date,eg_has_due_date,eg_late_days,
    eg_allow_late_submission,eg_precision,eg_regrade_allowed,
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

import getpass
import json
import os
import shutil
import sqlalchemy as sql

from submitty_utils import dateutils as submitty_dateutils

# load configuration files
with open('/usr/local/submitty/config/submitty.json','r') as conf:
    SUBMITTY_CONFIG_JSON = json.load(conf)
with open('/usr/local/submitty/config/database.json','r') as conf:
    SUBMITTY_DBCONFIG_JSON = json.load(conf)

ARG_INSTALL_DIR = SUBMITTY_CONFIG_JSON['submitty_install_dir']
ARG_DATA_DIR = SUBMITTY_CONFIG_JSON['submitty_data_dir']
#ARG_DB_HOST = 'localhost'
ARG_DB_HOST = SUBMITTY_DBCONFIG_JSON['database_host']
ARG_DB_PORT = SUBMITTY_DBCONFIG_JSON['database_port']
ARG_DB_USER = SUBMITTY_DBCONFIG_JSON['database_user']
ARG_DB_PASS = SUBMITTY_DBCONFIG_JSON['database_password']

# variables specifying gradeable to submit to
ARG_SEMESTER = 's23'
ARG_COURSE = 'blank'
ARG_GRADEABLE = 'test_resubmit1'

# place with files to submit
ARG_SUBMISSIONS = '/test'

UASF_NAME = 'user_assignment_settings.json'
UAAF_NAME = '.user_assignment_access.json'
UST_NAME = '.submit.timestamp'

def submit(semester:str, course:str, gradeable:str, user:str, data: str,
        func_add_submission, func_update_active):
    '''
    Create a new submission as a student
    semester,course,gradeable = specify gradeable to make submission to
    user = user to submit as
    data = path to directory containing the files for submission
    '''
    gradeable_path = os.path.join(ARG_DATA_DIR,'courses',semester,course,
        'submissions',gradeable)
    # make sure we are submitting to a real gradeable
    #assert os.path.exists(gradeable_path),gradeable_path
    user_path = os.path.join(gradeable_path,user)
    if not os.path.exists(user_path):
        os.makedirs(user_path)
    # load data from the user_assignment_settings.json file if it exists
    if os.path.exists(os.path.join(user_path,UASF_NAME)):
        with open(os.path.join(user_path,UASF_NAME),'r') as uasf:
            UAS = json.load(uasf)
    else: # initialize empty
        UAS = {
            'active_version': 0,
            'history': []
        }
    highest_version = 0 if len(UAS['history']) == 0 else max(obj['version']
        for obj in UAS['history'])
    submission_path = os.path.join(user_path,str(highest_version+1))
    # copy submission files, the submission_path should be created here
    shutil.copytree(data,submission_path)
    current_time = submitty_dateutils.get_current_time()
    current_time_str = f'{str(current_time)} {str(current_time.tzinfo)}'
    with open(os.path.join(submission_path,UST_NAME),'w') as stf:
        stf.write(current_time_str+'\n')
    with open(os.path.join(submission_path,UAAF_NAME),'w') as uaaf:
        uaaf.write('[]')
    UAS['history'].append({
        'version': highest_version+1,
        'time': current_time_str,
        'who': user,
        'type': 'upload'
    })
    # store updated user_assignment_settings.json file
    with open(os.path.join(user_path,UASF_NAME),'w') as uasf:
        json.dump(UAS,uasf,indent=4)
    # update database
    func_add_submission(gradeable,user,highest_version+1,str(current_time))
    func_update_active(gradeable,user,highest_version+1)
    # write a queue file for the autograding daemon
    queue_data = {
        'semester': semester,
        'course': course,
        'gradeable': gradeable,
        'required_capabilities': ['default'], # TODO FIXME
        'is_team': False, # TODO is this bad
        'max_possible_grading_time': 60, # TODO
        'queue_time': str(submitty_dateutils.get_current_time()),
        'regrade': False,
        'user': user,
        'vcs_checkout': False,
        'version': highest_version+1,
        'who': user
    }
    qfn = '__'.join([semester,course,gradeable,user,str(highest_version+1)])
    with open(os.path.join(ARG_DATA_DIR,'to_be_graded_queue',qfn),'w') as qfo:
        json.dump(queue_data,qfo,indent=4)

def main():
    dbengine_course = sql.create_engine(
        f'postgresql:///submitty_{ARG_SEMESTER}_{ARG_COURSE}?host={ARG_DB_HOST}'
        f'&port={ARG_DB_PORT}&user={ARG_DB_USER}&password={ARG_DB_PASS}')
    dbconn_course = dbengine_course.connect()
    metadata = sql.MetaData(dbengine_course)
    table_users = sql.Table('users',metadata,autoload=True)
    table_egdata = sql.Table('electronic_gradeable_data',metadata,autoload=True)
    table_egver = sql.Table('electronic_gradeable_version',metadata,autoload=True)
    ret = dbconn_course.execute(sql.select(table_users))
    user_list = set(row['user_id'] for row in ret.all())
    ret.close()
    def insert_egdata(g_id: str, user_id: str, g_version: int,
            submission_time: str, autograding_complete = False):
        print(f'    insert_egdata()')
        print(f'    g_id                 = {g_id}')
        print(f'    user_id              = {user_id}')
        print(f'    g_version            = {g_version}')
        print(f'    submission_time      = {submission_time}')
        print(f'    autograding_complete = {autograding_complete}')
        query = table_egdata.insert().values(
            g_id=g_id,user_id=user_id,g_version=g_version,
            submission_time=submission_time,
            autograding_complete=autograding_complete
        )
        print(f'    SQL: {query}')
        dbconn_course.execute(query)
    def update_egver(g_id: str, user_id: str, active_version: int):
        print(f'    update_egver()')
        print(f'    g_id           = {g_id}')
        print(f'    user_id        = {user_id}')
        print(f'    active_version = {active_version}')
        query = table_egver.select().where(
            (table_egver.c.g_id == g_id) &
            (table_egver.c.user_id == user_id)
        )
        ret = dbconn_course.execute(query)
        print(f'    SQL: {query}')
        rows = ret.all()
        print(f'    egver_data = {rows}')
        if len(rows) == 0: # INSERT
            query = table_egver.insert().values(
                g_id = g_id,
                user_id = user_id,
                active_version = active_version
            )
        else: # UPDATE
            query = table_egver.update().values(
                active_version = active_version
            ).where(
                (table_egver.columns.g_id == g_id) &
                (table_egver.columns.user_id == user_id)
            )
        print(f'    SQL: {query}')
        dbconn_course.execute(query)
    for user in os.listdir(ARG_SUBMISSIONS):
        if not os.path.isdir(os.path.join(ARG_SUBMISSIONS,user)):
            continue
        assert user in user_list
        print(f'SUBMITTING FOR USER {user}')
        user_dirlist = os.listdir(os.path.join(ARG_SUBMISSIONS,user))
        versions = [int(f) for f in user_dirlist if os.path.isdir(
            os.path.join(ARG_SUBMISSIONS,user,f))]
        for version in sorted(versions):
            print(f'  SUBMITTING VERSION {version}')
            submit(ARG_SEMESTER,ARG_COURSE,ARG_GRADEABLE,user,
                os.path.join(ARG_SUBMISSIONS,user,str(version)),
                insert_egdata,update_egver)
    dbconn_course.close()

if __name__ == '__main__':
    if getpass.getuser() == 'root':
        print('Run this as submitty_php')
        quit()
    main()
