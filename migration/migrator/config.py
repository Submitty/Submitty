"""Config class for the migrator."""

from collections import OrderedDict
import json
from pathlib import Path


DEFAULT_DATABASE_DRIVER = 'psql'


class Config:
    """
    Class to hold the config details for Submitty to use within the migrator.

    It dynamically loads the JSON files in the config directory into a dictionary
    with the maching name accessible at Config.<name> (e.g. Config.database).
    """

    def __init__(self, config_path):
        """
        Initialize the Config class, loading files from the passed in config_path.

        :param config_path: Path or str to the config directory for Submitty
        """
        self.config_path = Path(config_path)

        self.database = self._get_data('database')
        if 'database_driver' not in self.database:
            self.database['database_driver'] = DEFAULT_DATABASE_DRIVER

        self.submitty = self._get_data('submitty')
        self.submitty_users = self._get_data('submitty_users')

    def _get_data(self, filename):
        with Path(self.config_path, filename + '.json').open('r') as open_file:
            return json.load(open_file, object_pairs_hook=OrderedDict)
