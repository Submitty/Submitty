import os
from pathlib import Path
import configparser


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.ini')

    if config_file.is_file():
        config = configparser.ConfigParser()
        config.read(str(config_file))

        if not config.has_option('course_details', 'seating_only_for_instructor'):
            config.set('course_details', 'seating_only_for_instructor', 'false')

        with config_file.open('w') as configfile:
            config.write(configfile)
