#!/usr/bin/env python3 
import os
import json
import shutil
import argparse
from submitty_utils import dateutils
from sqlalchemy import create_engine, Table, MetaData, and_
import grp

# This file gets installed to SUBMITTY_INSTALL_DIR/.setup/bin/
SUBMITTY_INSTALL_DIR = os.path.realpath(
    os.path.join(os.path.dirname(os.path.realpath(__file__)), '..', '..')
)

# Verify that this has been installed by just checking that this file is located in
# a directory next to the config directory which has submitty.json in it
if not os.path.exists(os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'submitty.json')):
    raise SystemExit('You must install the test suite before being able to run it.')

with open(os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'submitty.json')) as open_file:
    SUBMITTY_JSON = json.load(open_file)

SUBMITTY_DATA_DIR = SUBMITTY_JSON['submitty_data_dir']

with open(os.path.join(SUBMITTY_INSTALL_DIR, 'config', 'database.json')) as open_file:
    DB_JSON = json.load(open_file)
DB_HOST = DB_JSON['database_host']
DB_USER = DB_JSON['database_user']
DB_PASS = DB_JSON['database_password']


# This script was created to help professors re-upload an old semester's assignments for autograding.
# To run it, please create a new assignment (hereafter called the CURRENT assignment) via the submitty interface 
# to which you wish to upload your old assignment. Then, run this script and provide the following arguments:
# ARGUMENTS
# 1) The path to the top level of the old assignment's directory tree. This folder's subdirectories should mirror 
#    a /var/local/submitty/courses/<semester>/<course>/submissions/<assignment_name> folder.
# 2) The semester of the course to which the old assignments will be uploaded.
# 3) The name of the course to which the old assignments will be uploaded.
# 4) The name of the current assignment to which the old assignments will be uploaded
# 5) The optional argument --grade, which causes the uploaded assignments to be added to the grading queue.

#NOTE: variables beginning with ARCHIVED_ are populated using the homework assignment being copied.
#Varialbes beginning with CURRENT_ are populated using the destination/current semester's directories.
def main():
    #In order to successfully re-upload the assignment, we need the directory of the assignment to be inflated,
    #and the semester, course name, and assignment name that it should be uploaded to.
    parser = argparse.ArgumentParser(description='This script was created to help professors re-upload an old semester\'s\
        assignments for autograding and reevaluation. To run it, please create a new assignment via the submitty interface to\
        which you wish to upload a past assignment\'s submissions. Then, run this script.')
    parser.add_argument('-g', '--grade', action='store_true', help='adds assignments to the grading queue.')
    parser.add_argument('ARCHIVED_directory', help='The path to the top level of the old assignment\'s directory tree. This folder\'s\
        subdirectories should mirror a /var/local/submitty/courses/<semester>/<course>/submissions/<assignment_name> folder.')
    parser.add_argument('semester', help='The semester of the course you wish to upload to.')
    parser.add_argument('course_name', help='The name of the course you wish to upload to.')
    parser.add_argument('assignment_name', help='The assignment name you wish to upload to.')
    args = parser.parse_args()

    print("You are about to attempt to copy\n\tSOURCE: " + args.ARCHIVED_directory + "\n\tDESTINATION: "
     + SUBMITTY_DATA_DIR + "/" + args.semester+"/courses/"+args.course_name+"/submissions/"+args.assignment_name)

    #Check the existence of the submission path (the user should have already created the assignment for us to populate)
    CURRENT_course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", args.semester, args.course_name)
    print("Current course path:" + CURRENT_course_path)
    CURRENT_assignment_path = os.path.join(CURRENT_course_path, "submissions", args.assignment_name)
    print("Current assignment path" + CURRENT_assignment_path)
    #we check the bin directory to see if the assignment has ever been built. (Not a guarantee, but high probability of success)
    CURRENT_bin_directory = os.path.join(CURRENT_course_path, "bin", args.assignment_name)
    if not os.path.isdir(CURRENT_bin_directory):
        raise SystemExit("ERROR: The directory " + CURRENT_bin_directory + " does not exist. Please make sure that you\n\t\
            1) Configured the assignment on the course website\n\t2) Did not mistype the program arguments.") 

    else:
        print("SUCCESS: The directory " + CURRENT_assignment_path + " does exist.")    

    #Make a connection to the database and grab the necessary tables.
    database = "submitty_" + args.semester + "_" + args.course_name
    print("Connecting to database: ", end="")
    engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST,
                                                           database))
    conn = engine.connect()
    metadata = MetaData(bind=engine)
    print("(connection made, metadata bound)...")
    electronic_gradeable_data = Table("electronic_gradeable_data", metadata, autoload=True)
    electronic_gradeable_version = Table("electronic_gradeable_version", metadata, autoload=True)

    course_group = grp.getgrgid(os.stat(os.path.join(SUBMITTY_DATA_DIR,"courses",args.semester,args.course_name)).st_gid)[0]

    #For every user folder in our directory, for every submission folder inside of it, copy over that user/submission, add it to 
    #the database, and create a queue file. 
    for user_folder in sorted(os.listdir(args.ARCHIVED_directory)):
        print("evaluating " + user_folder)
        #If the item we are currently looking at is a directory, we will assume it is a student submission directory.
        ARCHIVED_user_dir = os.path.join(args.ARCHIVED_directory, user_folder)
        print("Starting work on archived dir: " + ARCHIVED_user_dir)
        if not os.path.isdir(ARCHIVED_user_dir):
            print("Skipping the following as it is not a directory: " + ARCHIVED_user_dir)
            continue
        user_name = user_folder
        print("processing user: " + user_name)
        CURRENT_user_path = os.path.join(CURRENT_assignment_path, user_name)
        with open(os.path.join(ARCHIVED_user_dir, "user_assignment_settings.json"), "r") as open_file:
            user_assignment_settings = json.load(open_file)

        #For every folder inside of the user submission folder.
        for submission in os.listdir(ARCHIVED_user_dir):
            ARCHIVED_submission_path = os.path.join(ARCHIVED_user_dir, submission)
            print("Evaluating submission: " + ARCHIVED_submission_path)
            #If this entry in the directory to be copied is not a directory, it is a user_assignments settings.
            #Right now, we ignore these.
            #TODO copy over the user_assignment_settings files and use them instead of creating new ones.
            if not os.path.isdir(ARCHIVED_submission_path):
                if user_folder == ".submit.timestamp":
                    with open(ARCHIVED_submission_path, 'r') as old_timestamp, open(CURRENT_user_path, 'w') as new_timestamp:
                        for line in old_timestamp:
                            new_timestamp.write(line)
                        os.system("chown -R submitty_php:{} {}".format(course_group, new_timestamp))
                continue
            #if the student's submission dir does not exist, make it.
            if not os.path.isdir(CURRENT_user_path):
                os.makedirs(CURRENT_user_path)
            #The current directory for the new submission
            CURRENT_submission_path = os.path.join(CURRENT_user_path, submission)
            #TODO If the submission already exists, give up?
            if os.path.isdir(CURRENT_submission_path):
                print("Skipped " + CURRENT_submission_path + " as it already exists.")
                continue
            #This permission also sets the underlying submission paths recursively.g
            print("Set permissions on the submission")
            os.system("chown -R submitty_php:{} {}".format(course_group, CURRENT_user_path))

            #TODO: Sort the submissions so that they are guaranteed to be given in chronological (1,2,3,etc) order.
            #copy in the submission directory.
            print("Copied from\n\tSOURCE: " + ARCHIVED_submission_path +"\n\t" + "DESTINATION: " + CURRENT_submission_path)
            shutil.copytree(ARCHIVED_submission_path, CURRENT_submission_path)
            #give the appropriate permissions
            os.system("chown -R submitty_php:{} {}".format(course_group, CURRENT_submission_path))
            #add each submission to the database.
            current_time_string = dateutils.write_submitty_date()

            conn.execute(electronic_gradeable_data.insert(), g_id=args.assignment_name, user_id=user_name,
                         g_version=submission, submission_time=current_time_string)
            #If this is the first submission, create a new entry in the table, otherwise, update.
            #TODO use a more reliable method of determining if this is the first submission.
            if int(submission) == 1:
                print("Entered new user " + user_name + " because submission was " + submission)
                conn.execute(electronic_gradeable_version.insert(), g_id=args.assignment_name, user_id=user_name,
                         active_version=user_assignment_settings['active_version'])
            else:
                print("UPDATED: where g_id is " + args.assignment_name + " and user id is " + user_name + " to value " + str(user_assignment_settings['active_version']))
                stmt = electronic_gradeable_version.update().\
                        where(and_(electronic_gradeable_version.c.g_id==args.assignment_name, electronic_gradeable_version.c.user_id==user_name)).\
                        values(active_version=user_assignment_settings['active_version'])
                conn.execute(stmt)
            with open(os.path.join(CURRENT_user_path, "user_assignment_settings.json"), "w") as open_file:
                json.dump(user_assignment_settings, open_file, indent = 4)

            if args.grade:
                # Create a queue file for each submission
                queue_file = "__".join([args.semester, args.course_name, args.assignment_name, user_name, submission])
                print("Creating queue file:", queue_file)
                queue_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_queue", queue_file)
                with open(queue_file, "w") as open_file:
                    # FIXME: This will need to be adjusted for team assignments
                    # and assignments with special required capabilities!
                    queue_time = dateutils.write_submitty_date()
                    json.dump({"semester": args.semester,
                               "course": args.course_name,
                               "gradeable": args.assignment_name,
                               "user": user_name,
                               "team": "",
                               "who": user_name,
                               "is_team": False,
                               "version": submission,
                               "required_capabilities" : "default",
                               "queue_time": queue_time,
                               "regrade": True,
                               "max_possible_grading_time": -1}, open_file)

    conn.close()

if __name__ == "__main__":
    main()
