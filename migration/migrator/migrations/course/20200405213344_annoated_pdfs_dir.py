"""Migration for a given Submitty course database."""
import os
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
    pdfs_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, "annotated_pdfs")
    # add boolean to course config
    if not os.path.exists(pdfs_dir):
        os.makedirs(pdfs_dir)


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

    pdfs_dir = Path(config.submitty['submitty_data_dir'], 'courses', semester, course, "annotated_pdfs")
    # add boolean to course config
    if os.path.exists(pdfs_dir):
        for root, dirs, files in os.walk(pdfs_dir, topdown=False):
            for name in files:
                os.remove(os.path.join(root, name))
            for name in dirs:
                os.rmdir(os.path.join(root, name))
