import json
from pathlib import Path

def up(config, database, semester, course):

    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.json')

    if config_file.is_file():

        with open(config_file) as f:
            j = json.load(f)

            # add a default for the parameter 'git_autograding_branch',
            # which indicates which branch should be used when grading
            # student submissions by vcs/git

            # we'll default existing courses to 'master', which was the standard
            # until mid 2020 -- new courses will default to 'main'
            if not 'git_autograding_branch' in j['course_details']:
                j['course_details']['git_autograding_branch'] = 'master'

        # write out the file
        with open(config_file, 'w') as f:
            json.dump(j, f, indent=4)


def down(config, database, semester, course):
    pass
