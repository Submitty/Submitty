"""Migration for a given Submitty course database."""
import os
import grp
from pathlib import Path

def up(config, database, semester, course):
    """
    Run up migration.

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course)
    annotations_dir = Path(course_dir, 'annotated_pdfs')
    # create the directories
    os.makedirs(str(annotations_dir), exist_ok=True)

    php_user = config.submitty_users['php_user']

    # get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid
    course_group = grp.getgrgid(course_group_id)[0]

    # set the owner/group/permissions
    os.system("chown -R "+php_user+":"+course_group+" "+str(annotations_dir))
    os.system("chmod -R u+rwx "+str(annotations_dir))
    os.system("chmod -R g+rxs "+str(annotations_dir))
    os.system("chmod -R o-rwx "+str(annotations_dir))

def down(config, database, semester, course):
    pass
