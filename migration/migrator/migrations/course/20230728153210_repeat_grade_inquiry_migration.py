"""Repeats the course configuration edit from
migration 20230601123230_change_regrade_to_grade_inquiry.py, 
which failed to run successfully for a number of courses."""

import os
import json

def course_config_location(semester, course):
    """
    Returns path to course config file for a given semester and course.
    
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    return f"/var/local/submitty/courses/{semester}/{course}/config/config.json"



def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    
    # Update Course Config
    path = course_config_location(semester, course)
    if not os.path.isfile(path) or os.path.getsize(path) == 0: # Empty file
        # NOTE: unfortunately cannot throw an exception, breaks the database github action
        print(f"ERROR: course config path: '{path}' does not exist")
        return

    with open(path, "r") as config_file:
        # Get regrade_message
        course_config = json.load(config_file)
        config_file.close()
        
    if "grade_inquiry_message" in course_config["course_details"].keys():
        # Previous migration was successful, no update required
        pass
        
    elif "regrade_message" in course_config["course_details"].keys():
        # previous migration was unsuccessful, update required

        grade_inquiry_message = course_config["course_details"].pop("regrade_message")
        course_config["course_details"]["grade_inquiry_message"] = grade_inquiry_message
        
        with open(path, "w") as config_file2:
           json.dump(course_config, config_file2, indent=4)
           config_file2.close()
           
        with open(path, "r") as config_file3:
            # Get regrade_message
            course_config3 = json.load(config_file3)
            config_file3.close()
            
        if not "grade_inquiry_message" in course_config3["course_details"].keys():
            raise Exception(f"ERROR: did not successfully rename regrade_message to grade_inquiry_message")
            
        print ("SUCCESS: regrade_message was successfully renamed to grade_inquiry_message")
           
    else:
        # neither message exists, this shouldn't happen
        raise Exception(f"ERROR: course config is missing the regrade/regrade_inquiry message")


def down(config, database, semester, course):
    pass
