import os
from pathlib import Path
import configparser


def up(config, database, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    config_file = Path(course_dir, 'config', 'config.ini')

    if config_file.is_file():
        # should have done this replacement a while ago...
        os.system("sed -i 's/iris/rainbow/' "+str(config_file))

        config = configparser.ConfigParser()
        config.read(str(config_file))

        # set a bunch of (mostly old) defaults
        if not config.has_option('course_details', 'forum_enabled'):
            config.set('course_details', 'forum_enabled', 'false')
        if not config.has_option('course_details', 'regrade_enabled'):
            config.set('course_details', 'regrade_enabled', 'false')
        if not config.has_option('course_details', 'regrade_message'):
            config.set('course_details', 'regrade_message', "Frivolous grade inquiries may result in a grade deduction or loss of late days")
        if not config.has_option('course_details', 'private_repository'):
            config.set('course_details', 'private_repository', '""')
        # the newest default
        if not config.has_option('course_details', 'room_seating_gradeable_id'):
            config.set('course_details', 'room_seating_gradeable_id', '""')

        # write out the file
        with open(str(config_file), 'w') as configfile:
            config.write(configfile)
