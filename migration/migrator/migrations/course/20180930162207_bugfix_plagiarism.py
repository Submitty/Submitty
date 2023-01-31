import os
from pathlib import Path


def up(config, database, term, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', term, course)
    lichen_dir = Path(course_dir, 'lichen')

    # set the owner/group/permissions
    # group was missing write bit in create_course script
    os.system("chmod -R g+rwxs "+str(lichen_dir))
