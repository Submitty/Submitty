from shutil import copy2
from pathlib import Path
import os
import grp
import pwd

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
    course_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, 'rainbow_grades')
    customization_file = course_dir / 'customization.json'
    backup_file = course_dir / 'backup_customization.json'
    gui_custom_file = course_dir / 'gui_customization.json'

    if customization_file.exists():
        # copy the file to backup_customization.json
        copy2(str(customization_file), str(backup_file))

        # Copy backup_customization.json to gui_customization.json
        copy2(str(customization_file), str(gui_custom_file))

    daemon_user = config.submitty_users['daemon_user']
    daemon_uid = pwd.getpwnam(daemon_user).pw_uid

    # Get course group
    stat_info = os.stat(str(course_dir))
    course_group_id = stat_info.st_gid

    # Set ownership and permissions for all customization JSON files
    for file in course_dir.glob('*customization*.json'):
        os.chown(file, daemon_uid, course_group_id)
        os.chmod(file, 0o660)  # -rw-rw----

def down(config, database, semester, course):
    """
    Run down migration (rollback).

    :param config: Object holding configuration details about Submitty
    :type config: migrator.config.Config
    :param database: Object for interacting with given database for environment
    :type database: migrator.db.Database
    :param semester: Semester of the course being migrated
    :type semester: str
    :param course: Code of course being migrated
    :type course: str
    """
    pass
