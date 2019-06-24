# Imports
import sys
import os
import shutil

# Constants
RAINBOW_GRADES_PATH = '/usr/local/submitty/GIT_CHECKOUT/RainbowGrades'
COURSES_PATH = '/var/local/submitty/courses'

# Collect path information
semester = sys.argv[1]
course = sys.argv[2]
user = sys.argv[3]

# TODO: Validate passed in parameters

# Generate path information
rg_course_path = os.path.join(COURSES_PATH, semester, course, 'rainbow_grades')

# Setup course specific rainbow grades directory if ones does not exist
if not os.path.exists(rg_course_path):

    # Create the rainbow grades directory for the course
    print('Creating new directory: {}'.format(rg_course_path))
    os.mkdir(rg_course_path, 0o770)

    # Set folder owner
    print('Setting ownership to {}'.format(user))
    shutil.chown(rg_course_path, user)

    # Copy Makefile and customization.json file from master rainbow grades directory to course specific directory
    print('Copying initial files')
    shutil.copyfile(RAINBOW_GRADES_PATH + '/SAMPLE_Makefile', rg_course_path + '/Makefile')
    shutil.copyfile(RAINBOW_GRADES_PATH + '/SAMPLE_customization.json', rg_course_path + '/customization.json')

    # Setup Makefile
    print('Configuring Makefile')
    makefile_path = os.path.join(rg_course_path, 'Makefile')

    # Read in the file
    with open(makefile_path, 'r') as file:
        filedata = file.read()

    # Replace the target string
    filedata = filedata.replace('username', user)
    filedata = filedata.replace('/<PATH_TO_SUBMITTY_REPO>/RainbowGrades', RAINBOW_GRADES_PATH)
    filedata = filedata.replace('submitty.cs.rpi.edu', 'localhost')
    filedata = filedata.replace('<SEMESTER>/<COURSE>', '{}/{}'.format(semester, course))

    # Write the file out again
    with open(makefile_path, 'w') as file:
        file.write(filedata)

# Change directory to course specific directory
os.chdir(rg_course_path)

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
print('Setting ownership of all rainbow grades files to {}'.format(user))
os.chdir('..')
cmd_output = os.popen('chown {}:sample_tas_www rainbow_grades/ -R'.format(user)).read()

