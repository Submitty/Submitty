"""
This script will automatically setup and run rainbow grades given a semester, course,
and instructor.
"""

# Imports
import sys
import os
import shutil
import pwd

# Constants
RAINBOW_GRADES_PATH = '/usr/local/submitty/GIT_CHECKOUT/RainbowGrades'
COURSES_PATH = '/var/local/submitty/courses'
PERMISSIONS = 0o640       # Linux style octal file permissions for newly generated files
GROUP = 'sample_tas_www'  # Group to get ownership of newly copied/generated files

# Verify correct number of command line arguments
if len(sys.argv) != 4:
    raise Exception('You must pass 3 command line arguments')

# Collect path information
semester = sys.argv[1]
course = sys.argv[2]
user = sys.argv[3]

# Verify course semester folder exists
semesters = os.listdir(COURSES_PATH)

if semester not in semesters:
    raise Exception('Unable to locate the semester {} folder'.format(semester))

# Verify course name folder exists
courses = os.listdir(COURSES_PATH + '/' + semester)

if course not in courses:
    raise Exception('Unable to locate the course {} folder'.format((course)))

# Verify user exists
users = pwd.getpwall()

user_found = False

for item in users:
    if user in item:
        user_found = True

if user_found is False:
    raise Exception('Unable to locate the specified user {}'.format(user))

# Generate path information
rg_course_path = os.path.join(COURSES_PATH, semester, course, 'rainbow_grades')

# Setup course specific rainbow grades directory if ones does not exist
if not os.path.exists(rg_course_path):

    # Create the rainbow grades directory for the course
    print('Creating new directory: {}'.format(rg_course_path))
    os.mkdir(rg_course_path, PERMISSIONS)

    # Set folder owner
    print('Setting ownership to {}'.format(user))
    shutil.chown(rg_course_path, user)

    # Copy Makefile and customization.json file from master rainbow grades directory
    # to course specific directory
    print('Copying initial files')
    shutil.copyfile(RAINBOW_GRADES_PATH + '/SAMPLE_Makefile',
                    rg_course_path + '/Makefile')
    shutil.copyfile(RAINBOW_GRADES_PATH + '/SAMPLE_customization.json',
                    rg_course_path + '/customization.json')

    # Setup Makefile
    print('Configuring Makefile')
    makefile_path = os.path.join(rg_course_path, 'Makefile')

    # Read in the file
    with open(makefile_path, 'r') as file:
        filedata = file.read()

    # Replace the target strings
    filedata = filedata.replace('username', user)
    filedata = filedata.replace('/<PATH_TO_SUBMITTY_REPO>/RainbowGrades',
                                RAINBOW_GRADES_PATH)
    filedata = filedata.replace('submitty.cs.rpi.edu', 'localhost')
    filedata = filedata.replace('<SEMESTER>/<COURSE>', '{}/{}'.format(semester, course))

    # Write the file out again
    with open(makefile_path, 'w') as file:
        file.write(filedata)


# Change directory to course specific directory
os.chdir(rg_course_path)

# TODO: Tell submitty to generate grade reports

# Run make pull_test (command outputs capture in cmd_output for debugging)
print('Pulling in grade reports')
cmd_output = os.popen('make pull_test').read()

# Run make
print('Compiling rainbow grades')
cmd_output = os.popen('make').read()

# Run make push_test
print('Exporting to summary_html')
cmd_output = os.popen('make push_test').read()

# Change more file permissions
print('Setting ownership of all rainbow grades files to {}:{}'.format(user, GROUP))
os.chdir('..')
cmd_output = os.popen('chown {}:{} rainbow_grades -R'.format(user, GROUP)).read()
cmd_output = os.popen('chown {}:{} reports/summary_html -R'.format(user, GROUP)).read()

print('Done')
