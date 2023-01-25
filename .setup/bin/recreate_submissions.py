#!/usr/bin/env python3
'''
DOCUMENTATION
'''

'''
DATABASE
'''

import argparse
import datetime
import os
import sqlalchemy
from typing import Union

ARG_INSTALL_DIR = '/usr/local/submitty'
ARG_DATA_DIR = '/var/local/submitty'
ARG_DB_HOST = 'localhost'
ARG_DB_PORT = 5432
ARG_DB_USER = 'submitty_dbuser'
ARG_DB_PASS = 'submitty_dbuser'

ARG_SEMESTER = ''
ARG_COURSE = ''
ARG_GRADEABLE = ''

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
    submitty_engine = sqlalchemy.create_engine(
        f'postgresql:///submitty''?host={ARG_DB_HOST}'
        '&port={ARG_DB_PORT}&user={ARG_DB_USER}&password={ARG_DB_PASS}')
    submitty_conn = submitty_engine.connect();

if __name__ == '__main__':
    main()
