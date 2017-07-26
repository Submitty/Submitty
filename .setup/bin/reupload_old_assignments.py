#!/usr/bin/env python3 
import sys
import os
import json
from sqlalchemy import create_engine, Table, MetaData, bindparam, and_
import shutil

SUBMITTY_DATA_DIR = "/var/local/submitty"

sys.path.append("/usr/local/submitty/bin")
import submitty_utils

DB_HOST = "localhost"
DB_USER = "hsdbu"
DB_PASS = "Graphics"



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
    grade = False
    #In order to successfully re-upload the assignment, we need the directory of the assignment to be inflated,
    #and the semester, course name, and assignment name that it should be uploaded to.
    semester = course_name = assignment_name = ARCHIVED_directory =""
    if len(sys.argv) < 5:
        print("ERROR: Please provide:\n\t1)Directory with your files\n\t2)semester \n\t3)course code \n\t4)assignment name.\n\t5)--grade (optional)")
        sys.exit(1)
    else:
        ARCHIVED_directory = sys.argv[1]
        if not os.path.isdir(ARCHIVED_directory):
            print("The provided directory (" + ARCHIVED_directory + + ") does not exist.")
            sys.exit(1)
        semester = sys.argv[2]
        course_name = sys.argv[3]
        assignment_name = sys.argv[4]
        #The optional argument --grade causes the uploaded assignment to be added to the grading queue.
        if len(sys.argv) == 6:
            if sys.argv[5] == "--grade":
                grade = True
    print("You are about to attempt to copy\n\tSOURCE: " + ARCHIVED_directory + "\n\tDESTINATION: " + SUBMITTY_DATA_DIR + "/" + semester+"/courses/"+course_name+"/submissions/"+assignment_name)

    #Check the existence of the submission path (the user should have already created the assignment for us to populate)
    CURRENT_course_path = os.path.join(SUBMITTY_DATA_DIR, "courses", semester, course_name)
    print("Current course path:" + CURRENT_course_path)
    CURRENT_assignment_path = os.path.join(CURRENT_course_path, "submissions", assignment_name)
    print("Current assignment path" + CURRENT_assignment_path)
    #we check the bin directory to see if the assignment has ever been built. (Not a guarantee, but high probability of success)
    CURRENT_bin_directory = os.path.join(CURRENT_course_path, "bin", assignment_name)
    if not os.path.isdir(CURRENT_bin_directory):
        print("ERROR: The directory " + CURRENT_bin_directory + " does not exist. Please make sure that you\n\t1) Configured the assignment on the course website\n\t2) Did not mistype the program arguments.")
        sys.exit(1)
    else:
        print("SUCCESS: The directory " + CURRENT_assignment_path + " does exist.")    

    #Make a connection to the database and grab the necessary tables.
    os.environ['PGPASSWORD'] = DB_PASS
    database = "submitty_" + semester + "_" + course_name
    print("Connecting to database: ", end="")
    engine = create_engine("postgresql://{}:{}@{}/{}".format(DB_USER, DB_PASS, DB_HOST,
                                                           database))
    conn = engine.connect()
    metadata = MetaData(bind=engine)
    print("(connection made, metadata bound)...")
    electronic_gradeable_data = Table("electronic_gradeable_data", metadata, autoload=True)
    electronic_gradeable_version = Table("electronic_gradeable_version", metadata, autoload=True)

    #For every user folder in our directory, for every submission folder inside of it, copy over that user/submission, add it to 
    #the database, and create a queue file. 
    for user_folder in os.listdir(ARCHIVED_directory):
        #If the item we are currently looking at is a directory, we will assume it is a student submission directory.
        ARCHIVED_user_dir = os.path.join(ARCHIVED_directory, user_folder)
        print("Starting work on archived dir: " + ARCHIVED_user_dir)
        if not os.path.isdir(ARCHIVED_user_dir):
            print("Skipping the following as it is not a directory: " + ARCHIVED_user_dir)
            continue
        user_name = user_folder
        print("processing user: " + user_name)
        #For every folder inside of the user submission folder.
        for submission in os.listdir(ARCHIVED_user_dir):
            ARCHIVED_submission_path = os.path.join(ARCHIVED_user_dir, submission)
            print("Evaluating submission: " + ARCHIVED_submission_path)
            #If this entry in the directory to be copied is not a directory, it is a user_assignments settings.
            #Right now, we ignore these.
            #TODO copy over the user_assignment_settings files and use them instead of creating new ones.
            if not os.path.isdir(ARCHIVED_submission_path):
                continue
            CURRENT_user_path = os.path.join(CURRENT_assignment_path, user_name)

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
            os.system("chown -R hwphp:{}_tas_www {}".format(course_name, CURRENT_user_path))

            #TODO: Sort the submissions so that they are guaranteed to be given in chronological (1,2,3,etc) order.
            #copy in the submission directory.
            print("Copied from\n\tSOURCE: " + ARCHIVED_submission_path +"\n\t" + "DESTINATION: " + CURRENT_submission_path)
            shutil.copytree(ARCHIVED_submission_path, CURRENT_submission_path)
            #give the appropriate permissions
            os.system("chown -R hwphp:{}_tas_www {}".format(course_name, CURRENT_submission_path))
            #add each submission to the database.
            current_time_string = submitty_utils.write_submitty_date()

            conn.execute(electronic_gradeable_data.insert(), g_id=assignment_name, user_id=user_name,
                         g_version=submission, submission_time=current_time_string)
            #If this is the first submission, create a new entry in the table, otherwise, update.
            #TODO use a more reliable method of determining if this is the first submission.
            if int(submission) == 1:
                print("Entered new user " + user_name + " because submission was " + submission)
                conn.execute(electronic_gradeable_version.insert(), g_id=assignment_name, user_id=user_name,
                         active_version=submission)
            else:
                print("UPDATED: where g_id is " + assignment_name + " and user id is " + user_name + " to value " + str(submission))
                stmt = electronic_gradeable_version.update().\
                        where(and_(electronic_gradeable_version.c.g_id==assignment_name, electronic_gradeable_version.c.user_id==user_name)).\
                        values(active_version=submission)
                conn.execute(stmt)
            with open(os.path.join(CURRENT_user_path, "user_assignment_settings.json"), "w") as open_file:
                json.dump({"active_version": submission, "history": [{"version": submission, "time": current_time_string}]},
                          open_file)
            with open(os.path.join(CURRENT_submission_path, ".submit.timestamp"), "w") as open_file:
                open_file.write(current_time_string + "\n")

                if grade:
                    #Create a queue file for each submission            
                    queue_file = "__".join([semester, course_name, assignment_name, user_name, submission])
                    print("Creating queue file:", queue_file)
                    queue_file = os.path.join(SUBMITTY_DATA_DIR, "to_be_graded_batch", queue_file)
                    with open(queue_file, "w") as open_file:
                        # FIXME: This will need to be adjusted for team assignments!
                        json.dump({"semester": semester,
                                   "course": course_name,
                                   "gradeable": assignment_name,
                                   "user": user_name,
                                   "version": submission,
                                   "who": user_name,
                                   "is_team": False,
                                   "team": ""}, open_file)
    conn.close()
    os.environ['PGPASSWORD'] = ""


if __name__ == "__main__":
    main()