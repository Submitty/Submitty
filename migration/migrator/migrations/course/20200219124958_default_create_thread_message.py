import json
from pathlib import Path


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)

    # add new field in course config details
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)

        if 'forum_create_thread_message' not in j['course_details']:
            j['course_details']['forum_create_thread_message'] = ''
        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)


def down(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)

    # remove additional field in course config details
    config_file = Path(course_dir, 'config', 'config.json')
    if config_file.is_file():
        with open(config_file, 'r') as in_file:
            j = json.load(in_file)

        if 'forum_create_thread_message' in j['course_details']:
            del j['course_details']['forum_create_thread_message']
        with open(config_file, 'w') as out_file:
            json.dump(j, out_file, indent=4)
