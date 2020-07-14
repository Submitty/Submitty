"""Config class for the autograder package."""
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
        :param log_path: str Path to log output to.
        :param stack_trace_path: Path to log stack traces to
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
        database = self._get_data(config_path, 'database')
        if 'database_driver' not in self.database:
            self.database['database_driver'] = DEFAULT_DATABASE_DRIVER

        submitty = self._get_data('submitty')
        submitty_users = self._get_data('submitty_users')

        log_path = submitty['autograding_log_path']
        stack_trace_path = os.path.join(submitty['site_log_path'], 'autograding_stack_traces')

        return cls(submitty, database, submitty_users, log_path, error_path)

    def _get_data(self, config_path, filename):
        with Path(config_path, filename + '.json').open('r') as open_file:
            return json.load(open_file, object_pairs_hook=OrderedDict)