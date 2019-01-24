import os
import grp
from pathlib import Path


def up(config, conn, semester, course):
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    uploads_seating_dir = Path(course_dir, 'uploads', 'seating')

    # add group write
    os.system("chmod g+w "+str(uploads_seating_dir))

    pass


def down(config, conn, semester, course):
    pass
