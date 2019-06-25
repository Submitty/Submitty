import os
from pathlib import Path
import json


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.json')

    if config_file.is_file():
        j = json.load(open(config_file,'r'))

        if not 'seating_only_for_instructor' in j['course_details']:
            j['course_details']['seating_only_for_instructor'] = False

        json.dump(j,open(config_file,'w'))

def down(config, database, semester, course):
    pass
