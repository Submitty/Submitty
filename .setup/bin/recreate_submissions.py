#!/usr/bin/env python3
'''
DOCUMENTATION
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
- TODO INSERT INTO (for new version, make sure they are numbered 1,2,...)
- UPDATE electronic_gradeable_data
    SET submission_time=? autograding_complete=false
    WHERE g_id=? AND user_id=? AND g_version=?

COURSE.electronic_gradeable_version
- columns: g_id,user_id,team_id,active_version,anonymous_leaderboard
- TODO set active version based on what submissions student has
- UPDATE electronic_gradeable_version SET active_version=?
    WHERE g_id=? AND user_id=?
'''

import argparse
import datetime
import os
import sqlalchemy as sql
from typing import Union

import submitty_utils

ARG_INSTALL_DIR = '/usr/local/submitty'
ARG_DATA_DIR = '/var/local/submitty'
ARG_DB_HOST = 'localhost'
ARG_DB_PORT = 5432
ARG_DB_USER = 'submitty_dbuser'
ARG_DB_PASS = 'submitty_dbuser'

ARG_SEMESTER = 's23'
ARG_COURSE = 'blank'
ARG_GRADEABLE = 'test_resubmit1'

def isVcs(semester:str, course:str, gradeable:str) -> bool:
    assert 0

def submit(semester:str, course:str, gradeable:str, user:str,
        data: Union[dict[str,bytes],None], repo_url = '') -> bool:
    gradeable_path = os.path.join('TODO_SUBMITTY_DATA',semester,course,gradeable)
    user_path = os.path.join(gradeable_path,user)
    version_num = 0
    for item in os.listdir(user_path):
        if os.path.isdir(item):
            version_num = max(version_num,int(item))
    version_num += 1
    version_path = os.path.join(user_path,str(version_num))
    timestamp = datetime.datetime.now()
    timestamp_str = str(timestamp).split()[0]
    timezone = datetime.datetime.now().astimezone().tzname()
    if isVcs(semester,course,gradeable):
        assert 0
    else:
        files = data
        assert files is not None
        # write files to directory for this submission version
        for file in files:
            path = os.path.join(version_path,file)
            with open(path,'wb') as outf:
                outf.write(files[file])
    # TODO queue data
    queue_data = {
        'semester': semester,
        'course': course,
        'gradeable': gradeable,
        'required_capabilities': ['default'], # TODO FIXME
    }

def main():
    global ARG_INSTALL_DIR, ARG_DATA_DIR
    global ARG_DB_HOST, ARG_DB_PORT, ARG_DB_USER, ARG_DB_PASS
    #dbengine_master = sql.create_engine(
    #    f'postgresql:///submitty''?host={ARG_DB_HOST}'
    #    f'&port={ARG_DB_PORT}&user={ARG_DB_USER}&password={ARG_DB_PASS}')
    dbengine_course = sql.create_engine(
        f'postgresql:///submitty_{ARG_SEMESTER}_{ARG_COURSE}?host={ARG_DB_HOST}'
        f'&port={ARG_DB_PORT}&user={ARG_DB_USER}&password={ARG_DB_PASS}')
    #dbconn_master = dbengine_master.connect()
    dbconn_course = dbengine_course.connect()

if __name__ == '__main__':
    main()
