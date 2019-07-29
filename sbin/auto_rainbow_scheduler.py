#!/usr/bin/env python3

"""
Script to be called by cron job, easily schedules auto rainbow grade jobs.

This script scans over all courses and reads the course config.json file to determine
if the auto_rainbow_grades bool is set to true.  If true the script will add a job to
the jobs daemon to automatically build or update rainbow grades for that course.
"""

import os
import json

# Get path to current file directory
dir = os.path.dirname(__file__)

# Collect path information from configuration file
config_file = dir + '/../config/submitty.json'

if not os.path.exists(config_file):
    raise Exception('Unable to locate submitty.json configuration file')

with open(config_file, 'r') as file:
    data = json.load(file)
    data_dir = data['submitty_data_dir']

# Get path to jobs daemon directory for later
daemon_directory = os.path.join(data_dir, 'daemon_job_queue')

courses_path = os.path.join(data_dir, 'courses')

# For each semester in the courses directory
for semester in os.listdir(courses_path):

    course_path = os.path.join(courses_path, semester)

    # For each course in the semester directory
    for course in os.listdir(course_path):

        course_config_path = os.path.join(course_path, course, 'config', 'config.json')

        # Retrieve the auto_rainbow_grades bool from the course config.json
        with open(course_config_path, 'r') as file:
            data = json.load(file)
            auto_rainbow_grades = data['course_details']['auto_rainbow_grades']

        # If true then schedule a RunAutoRainbowGrades job
        if auto_rainbow_grades:

            # Setup jobs daemon json
            jobs_json = {
                'job': 'RunAutoRainbowGrades',
                'semester': semester,
                'course': course
            }

            # Prepare filename
            job_filename = 'auto_scheduled_rainbow_' + semester + '_' + course + '.json'

            # Drop job into jobs queue
            with open(os.path.join(daemon_directory, job_filename), 'w') as file:
                json.dump(jobs_json, file, indent=4)
