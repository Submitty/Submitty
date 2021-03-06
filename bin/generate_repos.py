#!/usr/bin/env python3
"""
This script will generate the repositories for a specified course and semester
for each student that currently does not have a repository. You can either make
the repositories at a per course level (for a repo that would carry through
all gradeables for example) or on a per gradeable level.

usage:
sudo /usr/local/submitty/bin/generate_repos.py <semester> <course_code> <project_name/gradeable_id>

"""

import argparse
import json
import os
import sys
import shutil
import tempfile
import subprocess
import re
from sqlalchemy import create_engine, MetaData, Table, bindparam

from submitty_utils import db_utils
CONFIG_PATH = os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', 'config')

with open(os.path.join(CONFIG_PATH, 'submitty_users.json')) as open_file:
    JSON = json.load(open_file)
DAEMONCGI_GROUP = JSON['daemoncgi_group']

with open(os.path.join(CONFIG_PATH, 'database.json')) as open_file:
    JSON = json.load(open_file)
DATABASE_HOST = JSON['database_host']
DATABASE_PORT = JSON['database_port']
DATABASE_USER = JSON['database_user']
DATABASE_PASS = JSON['database_password']

with open(os.path.join(CONFIG_PATH, 'submitty.json')) as open_file:
    JSON = json.load(open_file)
VCS_FOLDER = os.path.join(JSON['submitty_data_dir'], 'vcs', 'git')

# =======================================================================
def add_empty_commit(folder,which_branch):

    assert (os.path.isdir(folder))
    os.chdir(folder)

    # check to see if there are any branches in the repo with commits
    result = subprocess.run(['git', 'branch', '-v'], stdout=subprocess.PIPE)
    s = result.stdout.decode('utf-8')
    if s != "":
        # do nothing if there is at least one branch with a commit
        print('NOTE: this repo is non-empty (has a commit on at least one branch)')
        return

    # otherwise clone to a non-bare repo and add an empty commit
    # to the specified branch
    with tempfile.TemporaryDirectory() as tmpdirname:
        os.system(f'git clone {folder} {tmpdirname}')
        os.chdir(tmpdirname)
        os.system(f'git checkout -b {which_branch}')
        os.system("git " +
                  "-c user.name=submitty -c user.email=submitty@example.com commit " +
                  "--allow-empty -m 'initial empty commit' " +
                  "--author='submitty <submitty@example.com>'")
        os.system(f'git push origin {which_branch}')

    print(f'Made new empty commit on branch {which_branch} in repo {folder}')


# =======================================================================
def create_new_repo(folder, which_branch):

    # create the folder & initialize an empty bare repo
    os.makedirs(folder, mode=0o770)
    os.chdir(folder)
    # note: --initial-branch option requires git 2.28.0 or greater
    os.system(f'git init --bare --shared --initial-branch={which_branch}')

    # unfortuantely, when an empty repo with no branches is cloned,
    # the active branch and HEAD does NOT default to the specified branch

    # so let's manually specify the initial branch
    os.system(f'git symbolic-ref HEAD refs/heads/{which_branch}')

    # and explicitly add an empty commit to the specified branch
    # so that the repository is not empty
    add_empty_commit(folder,which_branch)

    print(f'Created new repo {folder}')


# =======================================================================
def create_or_update_repo(folder, which_branch):
    print ('--------------------------------------------')
    print (f'Create or update repo {folder}')

    if not os.path.isdir(folder):
        # if the repo doesn't already exist, create it
        create_new_repo(folder,which_branch)

    else:
        os.chdir(folder)

        # whether or not this repo was newly created, set the default HEAD
        # on the origin repo
        os.system(f'git symbolic-ref HEAD refs/heads/{which_branch}')

        # if this repo has no branches with valid commits, add an
        # empty commit to the specified branch so that the repository
        # is not empty
        add_empty_commit(folder,which_branch)
        
        
    # set/correct the permissions of all files
    os.chdir(folder)
    for root, dirs, files in os.walk(folder):
        for entry in files + dirs:
            shutil.chown(os.path.join(root, entry), group=DAEMONCGI_GROUP)


# =======================================================================

parser = argparse.ArgumentParser(description="Generate git repositories for a specific course and homework")
parser.add_argument("--non-interactive", action='store_true', default=False)
parser.add_argument("semester", help="semester")
parser.add_argument("course", help="course code")
parser.add_argument("repo_name", help="repository name")
args = parser.parse_args()

conn_string = db_utils.generate_connect_string(
    DATABASE_HOST,
    DATABASE_PORT,
    "submitty",
    DATABASE_USER,
    DATABASE_PASS,
)

engine = create_engine(conn_string)
connection = engine.connect()
metadata = MetaData(bind=engine)

courses_table = Table('courses', metadata, autoload=True)
select = courses_table.select().where(courses_table.c.semester == bindparam('semester')).where(courses_table.c.course == bindparam('course'))
course = connection.execute(select, semester=args.semester, course=args.course).fetchone()

if course is None:
    raise SystemExit("Semester '{}' and Course '{}' not found".format(args.semester, args.course))

vcs_semester = os.path.join(VCS_FOLDER, args.semester)
if not os.path.isdir(vcs_semester):
    os.makedirs(vcs_semester, mode=0o770, exist_ok=True)
    shutil.chown(vcs_semester, group=DAEMONCGI_GROUP)

vcs_course = os.path.join(vcs_semester, args.course)
if not os.path.isdir(vcs_course):
    os.makedirs(vcs_course, mode=0o770, exist_ok=True)
    shutil.chown(vcs_course, group=DAEMONCGI_GROUP)

is_team = False

# We will always pass in the name of the desired repository.
#
# If the repository name matches the name of an existing gradeable in
# the course, we will check if it's a team gradeable and create
# individual or team repos as appropriate.
#
# If it's not an existing gradeable, we will ask the user for
# confirmation, and make individual repos if requested.

course_conn_string = db_utils.generate_connect_string(
    DATABASE_HOST,
    DATABASE_PORT,
    f"submitty_{args.semester}_{args.course}",
    DATABASE_USER,
    DATABASE_PASS,
)

course_engine = create_engine(course_conn_string)
course_connection = course_engine.connect()
course_metadata = MetaData(bind=course_engine)

eg_table = Table('electronic_gradeable', course_metadata, autoload=True)
select = eg_table.select().where(eg_table.c.g_id == bindparam('gradeable_id'))
eg = course_connection.execute(select, gradeable_id=args.repo_name).fetchone()

is_team = False
if eg is not None:
    is_team = eg.eg_team_assignment
elif not args.non_interactive:
    print ("Warning: Semester '{}' and Course '{}' does not contain gradeable_id '{}'.".format(args.semester, args.course, args.repo_name))
    response = input ("Should we continue and make individual repositories named '"+args.repo_name+"' for each student? (y/n) ")
    if not response.lower() == 'y':
        print ("exiting");
        sys.exit()


# Load the git branch for autgrading from the course config file
course_config_file = os.path.join('/var/local/submitty/courses/',
                                  args.semester, args.course,
                                  'config', 'config.json')
with open(course_config_file) as open_file:
    COURSE_JSON = json.load(open_file)
course_git_autograding_branch = COURSE_JSON['course_details']['git_autograding_branch']
# verify that the branch only contains alphabetic characters a-z
if not re.match('^[a-z]+$',course_git_autograding_branch):
    print (f"Invalid course git autograding branch '{course_git_autograding_branch}'")
    course_git_autograding_branch = 'main'
print ("The git autograding branch for this course is: " + course_git_autograding_branch)


if not os.path.isdir(os.path.join(vcs_course, args.repo_name)):
    os.makedirs(os.path.join(vcs_course, args.repo_name), mode=0o770)
    shutil.chown(os.path.join(vcs_course, args.repo_name), group=DAEMONCGI_GROUP)

if is_team:
    teams_table = Table('gradeable_teams', course_metadata, autoload=True)
    select = teams_table.select().where(teams_table.c.g_id == bindparam('gradeable_id')).order_by(teams_table.c.team_id)
    teams = course_connection.execute(select, gradeable_id=args.repo_name)

    for team in teams:
        create_or_update_repo(os.path.join(vcs_course, args.repo_name, team.team_id), course_git_autograding_branch)

else:
    users_table = Table('courses_users', metadata, autoload=True)
    select = users_table.select().where(users_table.c.semester == bindparam('semester')).where(users_table.c.course == bindparam('course')).order_by(users_table.c.user_id)
    users = connection.execute(select, semester=args.semester, course=args.course)

    for user in users:
        create_or_update_repo(os.path.join(vcs_course, args.repo_name, user.user_id), course_git_autograding_branch)
