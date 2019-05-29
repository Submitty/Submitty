# Imports
import sys
import os
import shutil

# Constants
RAINBOW_GRADES_DIR = "/usr/local/submitty/GIT_CHECKOUT/RainbowGrades"

# Collect path information
courses_path = "/var/local/submitty/courses"
semester = sys.argv[1]
course = sys.argv[2]

# TODO: Validate passed in parameters

# Get contents of grades reports directory
reports_path = os.path.join(courses_path, semester, course, "reports", "all_grades")
reports = os.listdir(reports_path)

# Ensure the grades report directory isn't empty
# If it is empty then grade reports haven't been generated and rainbow grades cannot run
if not reports:
    print("The grade reports directory was empty")
    print("Ensure grade reports have been generated before attempting to run this script")
    exit("Exiting...")

# Auto rainbow grades files should reside inside
# /var/local/submitty/courses/<semester>/<course>/uploads/auto_rainbow_grades
auto_path = os.path.join(courses_path, semester, course, "uploads", "auto_rainbow_grades")

# If this directory exists then remove it
if os.path.exists(auto_path):

    # Directory found remove it
    print("Found old auto_rainbow_grades directory")
    print("Deleting {}".format(auto_path))
    shutil.rmtree(auto_path)

# Create the auto_rainbow_grades directory for the course
print("Creating new directory: {}".format(auto_path))
os.mkdir(auto_path, 0o770)

raw_data_path = os.path.join(auto_path, "raw_data")

# Copy grade reports directory to auto_rainbow_grades (rename as raw_data)
# shutil.copytree(reports_path, )

