"""Config class for the autograder package."""
import os
import json
from pathlib import Path

DEFAULT_DATABASE_DRIVER = 'psql'


class Config:
    """
    Class to hold the config details for Submitty to use within the autograder.

    It dynamically loads the JSON files in the config directory into a dictionary
    with the matching name accessible at Config.<name> (e.g. Config.database).
    """

    def __init__(self, submitty, database, submitty_users, log_path, error_path):
        """
        dictionary objects rather than by reading json files.
        Useful in a test environment

        :param submitty: dict containing contents of a valid submitty.json
        :param database: dict containing contents of a valid submitty.json
        :param submitty_users: dict containing contents of a valid submitty_users
        :param log_path: str or Path to log output to.
        :param stack_trace_path: str or Path to log stack traces to
        """
        self.submitty = submitty
        self.database = database
        self.submitty_users = submitty_users
        self.log_path = log_path
        self.error_path = error_path

    @classmethod
    def path_constructor(cls, config_path):
        """
        Construct a config using the path to a folder containing a valid submitty.json,
        database.json, and submitty_users.json.

        :param config_path: Path or str to the config directory for Submitty
        """
        database = get_data(config_path, 'database')
        if database is not None and 'database_driver' not in database:
            database['database_driver'] = DEFAULT_DATABASE_DRIVER

        submitty = get_data(config_path, 'submitty')
        submitty_users = get_data(config_path, 'submitty_users')

        log_path = submitty['autograding_log_path']
        error_path = os.path.join(submitty['site_log_path'], 'autograding_stack_traces')

        return cls(submitty, database, submitty_users, log_path, error_path)

    def load_workers_json(self, config_path):
        """
        Given a path that contains a 'autograding_worker.json', load it into memory.
        """
        self.workers_json = get_data(config_path, 'autograding_worker.json')


def get_data(config_path, filename):
    myfile = Path(config_path, f'{filename}.json')
    if os.path.isfile(myfile):
        with myfile.open('r') as open_file:
            return json.load(open_file)
    else:
        # NOTE: database.json does not exist on the worker machine!
        return None
